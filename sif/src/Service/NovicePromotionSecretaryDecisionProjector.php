<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * Internal UC-111 bridge: copy a SECRETARIAT DECISION to a previously staged
 * SIF JASOM operation. Invoke synchronously from an authenticated secretary
 * action AFTER the legacy update has committed, never from a public callback.
 *
 * Requirements before calling:
 * - The commercial_operation was created for the REAL enrollment while it was
 *   still pending validation. Its canonical participant PARTY_KEY and tax ID
 *   were resolved by the trusted enrollment/identity subsystem.
 * - $secretaryActor is the authenticated backend actor, not a browser value.
 * - The legacy database remains the authority for recent_titulat.VALIDAT.
 *
 * No cross-database atomicity is implied. Reversals must be propagated by a
 * separately audited action; neither a cron nor this class auto-denies.
 */
final class NovicePromotionSecretaryDecisionProjector
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function projectDecision(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $uuidOperation,
        string $secretaryActor,
        ?\DateTimeImmutable $decidedAt = null
    ): array {
        if ($sifDb->inTransaction()) {
            throw new \LogicException('Decision projection must start its own SIF transaction.');
        }

        $secretaryActor = trim($secretaryActor);
        if ($uuidOperation === '' || $secretaryActor === '' || strlen($secretaryActor) > 100) {
            throw SifException::validation('Origin operation and authenticated actor are required.');
        }

        $sifDb->beginTransaction();
        try {
            $operation = $this->one(
                $sifDb,
                'SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
                        STATUS, CREATED_AT
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$uuidOperation]
            );

            if ($operation === null
                || $operation['SOURCE_TYPE'] !== 'CURS'
                || $operation['PRODUCT_TYPE'] !== 'CURS'
                || $operation['PRODUCT_CODE'] !== 'JASOM'
                || !in_array($operation['STATUS'], ['PENDING_VALIDATION', 'READY_FOR_PAYMENT'], true)
            ) {
                throw SifException::conflict('A pending JASOM origin operation is required.');
            }

            $enrollmentId = trim((string) $operation['SOURCE_ID']);
            if ($enrollmentId === '' || !ctype_digit($enrollmentId) || (int) $enrollmentId <= 0) {
                throw SifException::conflict('JASOM enrollment reference is missing.');
            }

            $legacy = $this->many(
                $legacyDb,
                'SELECT i.ID, i.CURS, i.DNI, r.VALIDAT
                 FROM inscripcions i
                 JOIN recent_titulat r ON r.ID_INSC = i.ID
                 WHERE i.ID = ?',
                [(int) $enrollmentId]
            );

            if (count($legacy) !== 1
                || (string) $legacy[0]['CURS'] !== 'JASOM'
                || !in_array((int) $legacy[0]['VALIDAT'], [1, 2], true)
            ) {
                throw SifException::conflict('Legacy secretary decision must be recorded before projection.');
            }

            $participant = $this->many(
                $sifDb,
                "SELECT PARTY_KEY, NIF_CIF FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                [$uuidOperation]
            );
            if (count($participant) !== 1
                || trim((string) $participant[0]['PARTY_KEY']) === ''
                || $this->normalizedIdentity((string) $participant[0]['NIF_CIF']) === ''
                || $this->normalizedIdentity((string) $participant[0]['NIF_CIF'])
                    !== $this->normalizedIdentity((string) $legacy[0]['DNI'])
            ) {
                throw SifException::conflict('Legacy enrollment and canonical SIF participant disagree.');
            }

            $approved = (int) $legacy[0]['VALIDAT'] === 1;
            $status = $approved ? 'VALIDATED' : 'REJECTED';

            $existing = $this->many(
                $sifDb,
                'SELECT UUID_VALIDATION, STATUS, SUBJECT_PARTY_KEY
                 FROM discount_validation
                 WHERE UUID_OPERATION = ? AND DISCOUNT_TYPE = ? FOR UPDATE',
                [$uuidOperation, NovicePromotionGrantService::VALIDATION_TYPE]
            );

            if ($existing !== []) {
                if (count($existing) !== 1
                    || (string) $existing[0]['STATUS'] !== $status
                    || (string) $existing[0]['SUBJECT_PARTY_KEY'] !== (string) $participant[0]['PARTY_KEY']
                ) {
                    throw SifException::conflict('A conflicting novice decision was already projected.');
                }

                if ($operation['STATUS'] !== 'READY_FOR_PAYMENT') {
                    throw SifException::conflict('Projected decision is inconsistent with payment gate.');
                }

                $sifDb->commit();
                return [
                    'uuid_validation' => (string) $existing[0]['UUID_VALIDATION'],
                    'decision' => $status,
                    'idempotency_reused' => true,
                ];
            }

            if ($operation['STATUS'] !== 'PENDING_VALIDATION') {
                throw SifException::conflict('Cannot project a new decision after opening payment.');
            }

            $uuidValidation = $this->uuids->generate();
            $decidedAt ??= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
            $timestamp = $decidedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $ruleSnapshot = json_encode([
                'rule' => NovicePromotionGrantService::RULE_VERSION,
                'legacy_decision_status' => (int) $legacy[0]['VALIDAT'],
                'secretary_manual_review' => true,
                'origin_program' => 'JASOM',
                'not_an_origin_course_discount' => true,
                'source' => 'secretary_action_legacy_recent_titulat',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

            $this->execute(
                $sifDb,
                'INSERT INTO discount_validation
                 (UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
                  STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, REQUESTED_AT,
                  VALIDATED_AT, VALIDATED_BY, REJECTION_REASON, IDEMPOTENCY_KEY)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $uuidValidation,
                    $uuidOperation,
                    NovicePromotionGrantService::VALIDATION_TYPE,
                    (string) $participant[0]['PARTY_KEY'],
                    $status,
                    NovicePromotionGrantService::RULE_VERSION,
                    $ruleSnapshot,
                    (string) $operation['CREATED_AT'],
                    $approved ? $timestamp : null,
                    $secretaryActor,
                    $approved ? null : 'SECRETARY_DENIED',
                    'NOVICE|DECISION|' . $uuidOperation,
                ]
            );

            $updated = $sifDb->prepare(
                "UPDATE commercial_operation
                 SET STATUS = 'READY_FOR_PAYMENT',
                     CLASSIFICATION = 'BILLABLE',
                     CLASSIFICATION_REASON = 'NOVICE_DECIDED'
                 WHERE UUID_OPERATION = ? AND STATUS = 'PENDING_VALIDATION'"
            );
            $updated->execute([$uuidOperation]);
            if ($updated->rowCount() !== 1) {
                throw SifException::conflict('JASOM state changed while recording secretary decision.');
            }

            $sifDb->commit();
            return [
                'uuid_validation' => $uuidValidation,
                'decision' => $status,
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($sifDb->inTransaction()) {
                $sifDb->rollBack();
            }
            throw $exception;
        }
    }

    private function normalizedIdentity(string $identity): string
    {
        return strtoupper((string) preg_replace('/[\s.\-]+/u', '', trim($identity)));
    }

    private function one(\PDO $db, string $sql, array $parameters): ?array
    {
        return $this->many($db, $sql, $parameters)[0] ?? null;
    }

    private function many(\PDO $db, string $sql, array $parameters): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function execute(\PDO $db, string $sql, array $parameters): void
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);
    }
}
