<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * Internal UC-111 registration bridge. Must be invoked by the trusted
 * enrollment backend after legacy JASOM + recent_titulat rows are committed.
 *
 * The trusted pricing subsystem supplies the full commercial snapshot;
 * the legacy A_PAGAR is checked against its net amount. Neither a browser's
 * amount nor an inferred 0-euro discount is an acceptable pricing source.
 *
 * $canonicalPartyKey must be produced by the authoritative identity resolver
 * (stable across every enrollment of the same PERSON), never from a form.
 * This class does not authenticate the caller or verify an uploaded degree.
 */
final class NovicePromotionEnrollmentStager
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function stage(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $enrollmentId,
        string $canonicalPartyKey,
        array $trustedPriceSnapshot,
        string $sourceChannel = 'WEB'
    ): array {
        if ($sifDb->inTransaction()) {
            throw new \LogicException('Novice enrollment staging must start its own SIF transaction.');
        }
        if ($enrollmentId < 1 || trim($canonicalPartyKey) === ''
            || strlen($canonicalPartyKey) > 100
            || !in_array($sourceChannel, ['WEB', 'INTRANET'], true)
        ) {
            throw SifException::validation('Trusted JASOM enrollment identity or channel is invalid.');
        }

        $legacy = $this->many(
            $legacyDb,
            'SELECT i.ID, i.CURS, i.DNI, i.NOM, i.COGNOMS, i.A_PAGAR,
                    i.ANY, i.MES, r.VALIDAT
             FROM inscripcions i
             JOIN recent_titulat r ON r.ID_INSC = i.ID
             WHERE i.ID = ?',
            [$enrollmentId]
        );
        if (count($legacy) !== 1
            || (string) $legacy[0]['CURS'] !== 'JASOM'
            || (int) $legacy[0]['VALIDAT'] !== 0
        ) {
            throw SifException::conflict('A single pending JASOM novice enrollment is required.');
        }

        $identity = $this->normalizedIdentity((string) $legacy[0]['DNI']);
        $name = trim((string) $legacy[0]['NOM'] . ' ' . (string) $legacy[0]['COGNOMS']);
        if ($identity === '' || $name === '') {
            throw SifException::conflict('Enrollment participant identity is incomplete.');
        }

        foreach (['gross_amount', 'discount_amount', 'net_amount', 'price_rule_version', 'tax_snapshot'] as $required) {
            if (!array_key_exists($required, $trustedPriceSnapshot)) {
                throw SifException::validation('Trusted price snapshot is incomplete.');
            }
        }
        if (!is_array($trustedPriceSnapshot['tax_snapshot']) || $trustedPriceSnapshot['tax_snapshot'] === []
            || trim((string) $trustedPriceSnapshot['price_rule_version']) === ''
        ) {
            throw SifException::validation('Missing authoritative price/tax snapshot.');
        }

        $gross = $this->cents((string) $trustedPriceSnapshot['gross_amount']);
        $discount = $this->cents((string) $trustedPriceSnapshot['discount_amount']);
        $net = $this->cents((string) $trustedPriceSnapshot['net_amount']);
        $legacyNet = $this->cents((string) $legacy[0]['A_PAGAR']);
        if ($gross < 0 || $discount < 0 || $net <= 0 || $gross - $discount !== $net || $net !== $legacyNet) {
            throw SifException::conflict('The approved price does not match the real JASOM enrollment.');
        }

        $priceJson = json_encode($trustedPriceSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $taxJson = json_encode($trustedPriceSnapshot['tax_snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $idempotencyKey = 'NOVICE|JASOM|INSCRIPCIO:' . $enrollmentId;

        $sifDb->beginTransaction();
        try {
            $existing = $this->one(
                $sifDb,
                'SELECT UUID_OPERATION, STATUS, NET_AMOUNT, PRICE_SNAPSHOT_JSON
                 FROM commercial_operation WHERE IDEMPOTENCY_KEY = ? FOR UPDATE',
                [$idempotencyKey]
            );

            if ($existing !== null) {
                $participant = $this->one(
                    $sifDb,
                    "SELECT PARTY_KEY, NIF_CIF FROM commercial_operation_party
                     WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                    [(string) $existing['UUID_OPERATION']]
                );
                if ($participant === null
                    || (string) $participant['PARTY_KEY'] !== $canonicalPartyKey
                    || $this->normalizedIdentity((string) $participant['NIF_CIF']) !== $identity
                    || $this->cents((string) $existing['NET_AMOUNT']) !== $net
                    || (string) $existing['PRICE_SNAPSHOT_JSON'] !== $priceJson
                ) {
                    throw SifException::conflict('A conflicting novice enrollment was already staged.');
                }

                $sifDb->commit();
                return [
                    'uuid_operation' => (string) $existing['UUID_OPERATION'],
                    'status' => (string) $existing['STATUS'],
                    'idempotency_reused' => true,
                ];
            }

            $uuidOperation = $this->uuids->generate();
            $statement = $sifDb->prepare(
                'INSERT INTO commercial_operation
                 (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
                  SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION,
                  CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
                  GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON,
                  TAX_SNAPSHOT_JSON)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([
                $uuidOperation, $idempotencyKey, 'ENROLLMENT', $sourceChannel,
                'CURS', (string) $enrollmentId, 'CURS', 'JASOM',
                (string) $legacy[0]['ANY'] . '/' . (string) $legacy[0]['MES'],
                'PENDING_VALIDATION', 'NOVICE_REVIEW', 'PENDING_VALIDATION', 'EUR',
                $this->money($gross), $this->money($discount), $this->money($net),
                $priceJson, $taxJson,
            ]);

            $this->execute(
                $sifDb,
                'INSERT INTO commercial_operation_party
                 (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NIF_CIF, NOM_RAO,
                  PRODUCT_CODE, PRODUCT_EDITION, LINE_AMOUNT, SNAPSHOT_JSON)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $uuidOperation, $canonicalPartyKey, 'PARTICIPANT',
                    (string) $legacy[0]['DNI'], $name, 'JASOM',
                    (string) $legacy[0]['ANY'] . '/' . (string) $legacy[0]['MES'],
                    $this->money($net),
                    json_encode([
                        'source' => 'legacy_inscription',
                        'source_id' => $enrollmentId,
                        'novice_requested' => true,
                        'price_rule_version' => (string) $trustedPriceSnapshot['price_rule_version'],
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ]
            );

            $sifDb->commit();
            return [
                'uuid_operation' => $uuidOperation,
                'status' => 'PENDING_VALIDATION',
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($sifDb->inTransaction()) {
                $sifDb->rollBack();
            }
            throw $exception;
        }
    }

    private function cents(string $value): int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($value), $m)) {
            throw SifException::validation('Invalid trusted monetary amount.');
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
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
        $statement = $db->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function execute(\PDO $db, string $sql, array $parameters): void
    {
        $statement = $db->prepare($sql);
        $statement->execute($parameters);
    }
}
