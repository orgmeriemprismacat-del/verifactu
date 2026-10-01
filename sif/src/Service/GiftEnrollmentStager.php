<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;

/**
 * UC-018 bridge executed only after the legacy gift enrollment row is committed.
 *
 * It does not create the legacy inscription and never trusts a browser amount or
 * browser-derived party identity. The caller must provide a canonical party key
 * and an authoritative price/tax snapshot from the trusted enrollment backend.
 */
final class GiftEnrollmentStager
{
    public function __construct(
        private UuidGenerator $uuids,
        private CommercialEntitlementRepository $entitlements
    ) {
    }

    public function stage(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $enrollmentId,
        string $giftCode,
        string $canonicalPartyKey,
        array $trustedPriceSnapshot,
        string $sourceChannel = 'WEB'
    ): array {
        if ($sifDb->inTransaction()) {
            throw new \LogicException('Gift enrollment staging must start its own SIF transaction.');
        }

        $giftCode = trim($giftCode);
        $canonicalPartyKey = trim($canonicalPartyKey);
        $sourceChannel = strtoupper(trim($sourceChannel));

        if ($enrollmentId < 1
            || $giftCode === ''
            || strlen($giftCode) > 200
            || $canonicalPartyKey === ''
            || strlen($canonicalPartyKey) > 100
            || !in_array($sourceChannel, ['WEB', 'INTRANET'], true)
        ) {
            throw SifException::validation('Trusted gift enrollment identity or channel is invalid.');
        }

        $legacyEnrollment = $this->one(
            $legacyDb,
            'SELECT ID, ANY, MES, CURS, DNI, NOM, COGNOMS, A_PAGAR,
                    FACTURA_RELACIONADA, pag_observacions, IDPAG, OBSERVACIONS
             FROM inscripcions WHERE ID = ?',
            [$enrollmentId]
        );
        if ($legacyEnrollment === null) {
            throw SifException::conflict('Legacy gift enrollment was not found.');
        }

        $legacyGift = $this->one(
            $legacyDb,
            'SELECT ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS
             FROM regal WHERE CODI = ?',
            [$giftCode]
        );
        if ($legacyGift === null) {
            throw SifException::conflict('Legacy gift was not found.');
        }

        $this->assertLegacyContract(
            $legacyEnrollment,
            $legacyGift,
            $giftCode,
            $enrollmentId
        );

        $price = $this->trustedPrice($trustedPriceSnapshot);
        $legacyProduct = strtoupper(trim((string) $legacyEnrollment['CURS']));
        $legacyEdition = (string) $legacyEnrollment['ANY'] . '/' . (string) $legacyEnrollment['MES'];

        if ($price['product_code'] !== $legacyProduct
            || $price['product_edition'] !== $legacyEdition
        ) {
            throw SifException::conflict(
                'Trusted product/edition does not match the committed gift enrollment.'
            );
        }

        $legacyGiftProduct = strtoupper(trim((string) ($legacyGift['CCURS'] ?? '')));
        if ($legacyGiftProduct !== '' && $legacyGiftProduct !== $legacyProduct) {
            throw SifException::conflict(
                'Legacy gift product does not match the enrollment product.'
            );
        }

        $legacyGiftAmount = $this->cents((string) $legacyGift['IMPORT']);
        if ($legacyGiftAmount <= 0 || $legacyGiftAmount !== $price['gross_cents']) {
            throw SifException::conflict(
                'Trusted course value does not match the legacy gift value.'
            );
        }

        $identity = $this->normalizedIdentity((string) $legacyEnrollment['DNI']);
        $name = trim(
            (string) $legacyEnrollment['NOM']
            . ' '
            . (string) $legacyEnrollment['COGNOMS']
        );
        if ($identity === '' || $name === '') {
            throw SifException::conflict('Gift enrollment participant identity is incomplete.');
        }

        $codeHash = hash('sha256', $giftCode);
        $sifDb->beginTransaction();

        try {
            $entitlement = $this->entitlements->findByCodeHash($sifDb, $codeHash, true);
            if ($entitlement === null
                || strtoupper((string) ($entitlement['ENTITLEMENT_TYPE'] ?? '')) !== 'GIFT'
            ) {
                throw SifException::conflict('Active SIF gift entitlement was not found.');
            }

            if ((string) ($entitlement['HOLDER_PARTY_KEY'] ?? '') !== $canonicalPartyKey) {
                throw SifException::conflict(
                    'Canonical participant does not match the gift entitlement holder.'
                );
            }

            if ($this->cents((string) ($entitlement['FACE_VALUE'] ?? ''))
                !== $legacyGiftAmount
                || strtoupper((string) ($entitlement['CURRENCY'] ?? ''))
                    !== $price['currency']
            ) {
                throw SifException::conflict(
                    'SIF gift entitlement does not match the committed gift value.'
                );
            }

            $entitlementStatus = strtoupper((string) ($entitlement['STATUS'] ?? ''));
            if (!in_array(
                $entitlementStatus,
                ['ISSUED', 'ACTIVE', 'RESERVED', 'CONSUMED'],
                true
            )) {
                throw SifException::conflict(
                    'Gift entitlement cannot stage an enrollment from its current status.'
                );
            }

            $origin = $this->one(
                $sifDb,
                'SELECT UUID_OPERATION, STATUS, CURRENCY, NET_AMOUNT,
                        UUID_FACTURA, UUID_PAYMENT
                 FROM commercial_operation
                 WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) ($entitlement['ORIGIN_UUID_OPERATION'] ?? '')]
            );
            if ($origin === null
                || !in_array(
                    strtoupper((string) $origin['STATUS']),
                    ['PAID', 'INVOICED', 'COMPLETED'],
                    true
                )
                || strtoupper((string) $origin['CURRENCY']) !== $price['currency']
                || $this->cents((string) $origin['NET_AMOUNT']) !== $legacyGiftAmount
                || trim((string) ($origin['UUID_FACTURA'] ?? '')) === ''
                || trim((string) ($origin['UUID_PAYMENT'] ?? '')) === ''
            ) {
                throw SifException::conflict(
                    'Gift purchase origin is not reconciled with the entitlement.'
                );
            }

            $uuidEntitlement = (string) $entitlement['UUID_ENTITLEMENT'];
            $idempotencyKey = sprintf(
                'GIFT|REDEEM|ENTITLEMENT:%s|INSC:%d',
                $uuidEntitlement,
                $enrollmentId
            );

            $existing = $this->one(
                $sifDb,
                'SELECT UUID_OPERATION, STATUS, SOURCE_TYPE, SOURCE_ID,
                        PRODUCT_CODE, PRODUCT_EDITION, CLASSIFICATION,
                        CLASSIFICATION_REASON, CURRENCY, GROSS_AMOUNT,
                        DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON
                 FROM commercial_operation
                 WHERE IDEMPOTENCY_KEY = ? FOR UPDATE',
                [$idempotencyKey]
            );

            if ($existing !== null) {
                $this->assertExistingOperation(
                    $sifDb,
                    $existing,
                    $canonicalPartyKey,
                    $identity,
                    $legacyProduct,
                    $legacyEdition,
                    $price,
                    $uuidEntitlement,
                    $enrollmentId
                );

                if ($entitlementStatus === 'CONSUMED') {
                    if ((string) ($entitlement['CONSUMED_UUID_OPERATION'] ?? '')
                        !== (string) $existing['UUID_OPERATION']
                    ) {
                        throw SifException::conflict(
                            'Consumed gift points to a different destination operation.'
                        );
                    }
                } else {
                    $this->entitlements->reserve(
                        $sifDb,
                        $entitlement,
                        'UC018-STAGE-' . $enrollmentId,
                        'gift-enrollment-stager',
                        $idempotencyKey
                    );
                }

                $sifDb->commit();

                return [
                    'uuid_operation' => (string) $existing['UUID_OPERATION'],
                    'uuid_entitlement' => $uuidEntitlement,
                    'enrollment_id' => $enrollmentId,
                    'holder_party_key' => $canonicalPartyKey,
                    'redemption_idempotency_key' => $idempotencyKey,
                    'status' => (string) $existing['STATUS'],
                    'idempotency_reused' => true,
                ];
            }

            $other = $this->many(
                $sifDb,
                "SELECT UUID_OPERATION, IDEMPOTENCY_KEY
                 FROM commercial_operation
                 WHERE SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = ?
                 FOR UPDATE",
                [(string) $enrollmentId]
            );
            if ($other !== []) {
                throw SifException::conflict(
                    'Legacy enrollment already has another SIF commercial operation.'
                );
            }

            if ($entitlementStatus === 'CONSUMED') {
                throw SifException::conflict(
                    'Consumed gift has no staged destination operation to reuse.'
                );
            }

            $uuidOperation = $this->uuids->generate();
            $priceJson = json_encode(
                [
                    'source' => 'trusted_price_snapshot',
                    'product_code' => $price['product_code'],
                    'product_edition' => $price['product_edition'],
                    'gross_amount' => $this->money($price['gross_cents']),
                    'discount_amount' => $this->money($price['gross_cents']),
                    'net_amount' => '0.00',
                    'currency' => $price['currency'],
                    'price_rule_version' => $price['price_rule_version'],
                    'gift_entitlement_uuid' => $uuidEntitlement,
                    'legacy_gift_id' => (int) $legacyGift['ID'],
                    'legacy_fact_rel' => (string) $legacyGift['FACT_REL'],
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            );
            $taxJson = json_encode(
                $trustedPriceSnapshot['tax_snapshot'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            );

            $this->execute(
                $sifDb,
                'INSERT INTO commercial_operation
                 (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
                  SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
                  PRODUCT_EDITION, CLASSIFICATION, CLASSIFICATION_REASON,
                  STATUS, CURRENCY, GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT,
                  PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $uuidOperation,
                    $idempotencyKey,
                    'ENROLLMENT',
                    $sourceChannel,
                    'INSCRIPCIO',
                    (string) $enrollmentId,
                    'CURS',
                    $legacyProduct,
                    $legacyEdition,
                    'NON_BILLABLE',
                    'GIFT_REDEMPTION',
                    'RESERVED',
                    $price['currency'],
                    $this->money($price['gross_cents']),
                    $this->money($price['gross_cents']),
                    '0.00',
                    $priceJson,
                    $taxJson,
                ]
            );

            $this->execute(
                $sifDb,
                'INSERT INTO commercial_operation_party
                 (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NIF_CIF, NOM_RAO,
                  PRODUCT_CODE, PRODUCT_EDITION, LINE_AMOUNT, SNAPSHOT_JSON)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $uuidOperation,
                    $canonicalPartyKey,
                    'PARTICIPANT',
                    (string) $legacyEnrollment['DNI'],
                    $name,
                    $legacyProduct,
                    $legacyEdition,
                    $this->money($price['gross_cents']),
                    json_encode(
                        [
                            'source' => 'legacy_gift_enrollment',
                            'source_id' => $enrollmentId,
                            'legacy_idpag' => (int) $legacyEnrollment['IDPAG'],
                            'gift_entitlement_uuid' => $uuidEntitlement,
                            'price_rule_version' => $price['price_rule_version'],
                        ],
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
                    ),
                ]
            );

            $this->entitlements->reserve(
                $sifDb,
                $entitlement,
                'UC018-STAGE-' . $enrollmentId,
                'gift-enrollment-stager',
                $idempotencyKey
            );

            $sifDb->commit();

            return [
                'uuid_operation' => $uuidOperation,
                'uuid_entitlement' => $uuidEntitlement,
                'enrollment_id' => $enrollmentId,
                'holder_party_key' => $canonicalPartyKey,
                'redemption_idempotency_key' => $idempotencyKey,
                'status' => 'RESERVED',
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($sifDb->inTransaction()) {
                $sifDb->rollBack();
            }
            throw $exception;
        }
    }

    private function assertLegacyContract(
        array $enrollment,
        array $gift,
        string $giftCode,
        int $enrollmentId
    ): void {
        if ((int) $enrollment['ID'] !== $enrollmentId
            || trim((string) $gift['CODI']) !== $giftCode
            || $this->cents((string) $enrollment['A_PAGAR']) !== 0
            || trim((string) $enrollment['pag_observacions']) !== $giftCode
            || (string) $enrollment['FACTURA_RELACIONADA']
                !== (string) $gift['FACT_REL']
            || stripos((string) $enrollment['OBSERVACIONS'], 'CURS REGAL') === false
        ) {
            throw SifException::conflict(
                'Committed legacy enrollment does not satisfy the gift-redemption contract.'
            );
        }

        $usedBy = $gift['USAT'] ?? null;
        if ($usedBy !== null
            && $usedBy !== ''
            && (int) $usedBy !== 0
            && (int) $usedBy !== $enrollmentId
        ) {
            throw SifException::conflict(
                'Legacy gift is already linked to another enrollment.'
            );
        }
    }

    private function trustedPrice(array $snapshot): array
    {
        foreach ([
            'product_code',
            'product_edition',
            'gross_amount',
            'currency',
            'price_rule_version',
            'tax_snapshot',
        ] as $field) {
            if (!array_key_exists($field, $snapshot)) {
                throw SifException::validation(
                    'Trusted gift price snapshot is incomplete: ' . $field
                );
            }
        }

        if (!is_array($snapshot['tax_snapshot']) || $snapshot['tax_snapshot'] === []) {
            throw SifException::validation('Trusted gift tax snapshot is empty.');
        }

        $productCode = strtoupper(trim((string) $snapshot['product_code']));
        $productEdition = trim((string) $snapshot['product_edition']);
        $currency = strtoupper(trim((string) $snapshot['currency']));
        $rule = trim((string) $snapshot['price_rule_version']);
        $gross = $this->cents((string) $snapshot['gross_amount']);

        if ($productCode === ''
            || strlen($productCode) > 80
            || $productEdition === ''
            || strlen($productEdition) > 80
            || !preg_match('/^[A-Z]{3}$/D', $currency)
            || $rule === ''
            || strlen($rule) > 80
            || $gross <= 0
        ) {
            throw SifException::validation('Trusted gift price snapshot is invalid.');
        }

        return [
            'product_code' => $productCode,
            'product_edition' => $productEdition,
            'currency' => $currency,
            'price_rule_version' => $rule,
            'gross_cents' => $gross,
        ];
    }

    private function assertExistingOperation(
        \PDO $sifDb,
        array $operation,
        string $canonicalPartyKey,
        string $identity,
        string $productCode,
        string $productEdition,
        array $price,
        string $uuidEntitlement,
        int $enrollmentId
    ): void {
        $participant = $this->one(
            $sifDb,
            "SELECT PARTY_KEY, NIF_CIF
             FROM commercial_operation_party
             WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT'
             FOR UPDATE",
            [(string) $operation['UUID_OPERATION']]
        );

        $snapshot = json_decode(
            (string) $operation['PRICE_SNAPSHOT_JSON'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if ($participant === null
            || (string) $participant['PARTY_KEY'] !== $canonicalPartyKey
            || $this->normalizedIdentity((string) $participant['NIF_CIF']) !== $identity
            || strtoupper((string) $operation['SOURCE_TYPE']) !== 'INSCRIPCIO'
            || (string) $operation['SOURCE_ID'] !== (string) $enrollmentId
            || strtoupper((string) $operation['PRODUCT_CODE']) !== $productCode
            || (string) $operation['PRODUCT_EDITION'] !== $productEdition
            || strtoupper((string) $operation['CLASSIFICATION']) !== 'NON_BILLABLE'
            || strtoupper((string) $operation['CLASSIFICATION_REASON']) !== 'GIFT_REDEMPTION'
            || strtoupper((string) $operation['CURRENCY']) !== $price['currency']
            || $this->cents((string) $operation['GROSS_AMOUNT']) !== $price['gross_cents']
            || $this->cents((string) $operation['DISCOUNT_AMOUNT']) !== $price['gross_cents']
            || $this->cents((string) $operation['NET_AMOUNT']) !== 0
            || (string) ($snapshot['gift_entitlement_uuid'] ?? '') !== $uuidEntitlement
        ) {
            throw SifException::conflict(
                'A conflicting gift redemption enrollment was already staged.'
            );
        }
    }

    private function cents(string $value): int
    {
        $value = trim(str_replace(',', '.', $value));
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', $value, $m)) {
            throw SifException::validation('Invalid gift monetary amount.');
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
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
