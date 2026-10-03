<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

/**
 * Reconciles secretary decisions that were committed in the legacy database
 * but could not be projected to SIF during the original intranet request.
 *
 * The legacy recent_titulat.VALIDAT row remains authoritative for the manual
 * secretary decision. This reconciler never invents an approval/rejection and
 * never opens payment while the legacy decision is still pending.
 */
final class NovicePromotionDecisionReconciler
{
    public function __construct(private NovicePromotionSecretaryDecisionProjector $projector)
    {
    }

    public function run(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $actorId,
        int $limit = 100
    ): array {
        if ($sifDb->inTransaction()) {
            throw new \LogicException('Decision reconciler requires a connection outside an existing transaction.');
        }

        $actorId = trim($actorId);
        if ($actorId === '' || strlen($actorId) > 100) {
            throw new \InvalidArgumentException('A reconciliation actor id is required.');
        }

        if ($limit < 1 || $limit > 500) {
            throw new \InvalidArgumentException('Reconciliation limit must be between 1 and 500.');
        }

        $stmt = $sifDb->prepare(
            "SELECT DISTINCT op.UUID_OPERATION, op.SOURCE_ID
             FROM commercial_operation op
             LEFT JOIN discount_validation validation
               ON validation.UUID_OPERATION = op.UUID_OPERATION
              AND validation.DISCOUNT_TYPE = 'NOVICE_TEACHER'
             WHERE op.SOURCE_TYPE = 'CURS'
               AND op.PRODUCT_TYPE = 'CURS'
               AND op.PRODUCT_CODE = 'JASOM'
               AND op.STATUS = 'PENDING_VALIDATION'
               AND (
                    validation.STATUS = 'PENDING'
                    OR (
                        validation.UUID_VALIDATION IS NULL
                        AND op.CLASSIFICATION_REASON = 'NOVICE_REVIEW'
                    )
               )
             ORDER BY op.CREATED_AT, op.UUID_OPERATION
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        $operations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $outcome = [
            'candidates' => count($operations),
            'projected' => 0,
            'reused' => 0,
            'pending_legacy_decision' => 0,
            'conflicts' => 0,
            'errors' => 0,
            'failed_operation_refs' => [],
        ];

        foreach ($operations as $operation) {
            $uuidOperation = trim((string) ($operation['UUID_OPERATION'] ?? ''));
            $sourceId = trim((string) ($operation['SOURCE_ID'] ?? ''));

            if ($uuidOperation === '' || $sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1) {
                $outcome['conflicts']++;
                if ($uuidOperation !== '') {
                    $outcome['failed_operation_refs'][] = $uuidOperation;
                }
                continue;
            }

            try {
                $legacyStatus = $this->legacyStatus($legacyDb, (int) $sourceId);
                if ($legacyStatus === 0) {
                    $outcome['pending_legacy_decision']++;
                    continue;
                }

                $result = $this->projector->projectDecision(
                    $sifDb,
                    $legacyDb,
                    $uuidOperation,
                    $actorId
                );

                $outcome[$result['idempotency_reused'] ? 'reused' : 'projected']++;
            } catch (SifException $exception) {
                $outcome['conflicts']++;
                $outcome['failed_operation_refs'][] = $uuidOperation;
            } catch (\Throwable $exception) {
                $outcome['errors']++;
                $outcome['failed_operation_refs'][] = $uuidOperation;
            }
        }

        $outcome['failed_operation_refs'] = array_values(array_unique($outcome['failed_operation_refs']));

        return $outcome;
    }

    private function legacyStatus(\PDO $legacyDb, int $enrollmentId): int
    {
        $stmt = $legacyDb->prepare(
            "SELECT r.VALIDAT
             FROM recent_titulat r
             JOIN inscripcions i ON i.ID = r.ID_INSC
             WHERE r.ID_INSC = ? AND i.CURS = 'JASOM'"
        );
        $stmt->execute([$enrollmentId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (count($rows) !== 1) {
            throw SifException::conflict('A single legacy JASOM novice decision row is required.');
        }

        $status = (int) $rows[0];
        if (!in_array($status, [0, 1, 2], true)) {
            throw SifException::conflict('Legacy novice decision status is invalid.');
        }

        return $status;
    }
}
