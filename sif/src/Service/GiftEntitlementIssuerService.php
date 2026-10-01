<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;

/**
 * Materialises the redeemable GIFT right only after UC-017 has a confirmed
 * invoice + payment. The beneficiary is intentionally unknown at purchase
 * time: the right is created with a non-person placeholder and can be claimed
 * exactly once by UC-018 from a committed enrollment.
 */
final class GiftEntitlementIssuerService
{
    public const RULE_VERSION = 'GIFT_V1';

    public function __construct(
        private UuidGenerator $uuids,
        private CommercialEntitlementRepository $entitlements
    ) {
    }

    public function issue(
        \PDO $db,
        array $gift,
        array $invoiceResult,
        string $dsOrder,
        string $sourceChannel = 'REDSYS',
        string $actorId = 'redsys-gift-worker'
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Gift entitlement issuance owns its transaction.');
        }

        $giftId = $this->positiveInt($gift['ID'] ?? $gift['id'] ?? null, 'gift.ID');
        $code = $this->required($gift['CODI'] ?? $gift['code'] ?? null, 'gift.CODI', 200);
        $course = strtoupper($this->required(
            $gift['CCURS'] ?? $gift['course_code'] ?? 'GIFT',
            'gift.CCURS',
            80
        ));
        $amount = $this->money($gift['IMPORT'] ?? $gift['amount'] ?? null, 'gift.IMPORT');
        $uuidInvoice = $this->uuid($invoiceResult['uuid_factura'] ?? null, 'uuid_factura');
        $uuidPayment = $this->uuid($invoiceResult['uuid_payment'] ?? null, 'uuid_payment');
        $dsOrder = $this->required($dsOrder, 'ds_order', 100);
        $sourceChannel = strtoupper($this->required($sourceChannel, 'source_channel', 30));
        $actorId = $this->required($actorId, 'actor_id', 100);

        $codeHash = hash('sha256', $code);
        $operationKey = 'GIFT|PURCHASE|REGAL:' . $giftId;
        $entitlementKey = 'GIFT|ENTITLEMENT|REGAL:' . $giftId;
        $unclaimedHolder = CommercialEntitlementRepository::unclaimedGiftHolderKey($codeHash);
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s');

        $db->beginTransaction();

        try {
            $operation = $this->one(
                $db,
                'SELECT * FROM commercial_operation WHERE IDEMPOTENCY_KEY = ? FOR UPDATE',
                [$operationKey]
            );

            $operationReused = $operation !== null;
            if ($operation === null) {
                $uuidOperation = $this->uuids->generate();
                $priceSnapshot = json_encode([
                    'source' => 'validated_gift_purchase',
                    'legacy_gift_id' => $giftId,
                    'legacy_course_code' => $course,
                    'face_value' => $amount,
                    'currency' => 'EUR',
                    'ds_order_hash' => hash('sha256', $dsOrder),
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                $taxSnapshot = json_encode([
                    'source' => 'gift_purchase_invoice',
                    'regime' => 'EXEMPT',
                    'taxable_base' => $amount,
                    'tax' => '0.00',
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

                $this->execute(
                    $db,
                    'INSERT INTO commercial_operation
                     (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
                      SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
                      CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
                      GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON,
                      TAX_SNAPSHOT_JSON, UUID_FACTURA, UUID_PAYMENT, CREATED_BY)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $uuidOperation,
                        $operationKey,
                        'GIFT_PURCHASE',
                        $sourceChannel,
                        'REGAL',
                        (string) $giftId,
                        'REGAL',
                        'GIFT',
                        'BILLABLE',
                        'GIFT_PURCHASE',
                        'PAID',
                        'EUR',
                        $amount,
                        '0.00',
                        $amount,
                        $priceSnapshot,
                        $taxSnapshot,
                        $uuidInvoice,
                        $uuidPayment,
                        $actorId,
                    ]
                );

                $operation = [
                    'UUID_OPERATION' => $uuidOperation,
                    'SOURCE_TYPE' => 'REGAL',
                    'SOURCE_ID' => (string) $giftId,
                    'STATUS' => 'PAID',
                    'CURRENCY' => 'EUR',
                    'NET_AMOUNT' => $amount,
                    'UUID_FACTURA' => $uuidInvoice,
                    'UUID_PAYMENT' => $uuidPayment,
                ];
            } else {
                $this->assertExistingOperation(
                    $operation,
                    $giftId,
                    $amount,
                    $uuidInvoice,
                    $uuidPayment
                );
            }

            $entitlement = $this->entitlements->findByCodeHash($db, $codeHash, true);
            $entitlementReused = $entitlement !== null;

            if ($entitlement === null) {
                $uuidEntitlement = $this->uuids->generate();
                $ruleSnapshot = json_encode([
                    'source' => 'uc017_gift_purchase',
                    'legacy_gift_id' => $giftId,
                    'legacy_course_code' => $course,
                    'claim_mode' => 'CODE_POSSESSION_PLUS_COMMITTED_ENROLLMENT',
                    'holder_state' => 'UNCLAIMED',
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

                $this->execute(
                    $db,
                    'INSERT INTO commercial_entitlement
                     (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
                      ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON, FACE_VALUE,
                      CURRENCY, STATUS, ISSUED_AT, IDEMPOTENCY_KEY)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $uuidEntitlement,
                        'GIFT',
                        $codeHash,
                        $unclaimedHolder,
                        (string) $operation['UUID_OPERATION'],
                        self::RULE_VERSION,
                        $ruleSnapshot,
                        $amount,
                        'EUR',
                        'ACTIVE',
                        $now,
                        $entitlementKey,
                    ]
                );

                $this->entitlements->appendEvent($db, [
                    'uuid_entitlement' => $uuidEntitlement,
                    'action' => 'ISSUE',
                    'result' => 'SUCCESS',
                    'from_status' => null,
                    'to_status' => 'ACTIVE',
                    'uuid_operation' => (string) $operation['UUID_OPERATION'],
                    'actor_type' => 'SYSTEM',
                    'actor_id' => $actorId,
                    'correlation_id' => 'UC017-' . substr(hash('sha256', $dsOrder), 0, 32),
                    'causation_id' => $entitlementKey,
                    'reason_code' => 'UC017_GIFT_PURCHASE',
                    'changeset' => [
                        'holder_state' => 'UNCLAIMED',
                        'code_hash' => $codeHash,
                    ],
                    'occurred_at' => $now,
                ]);

                $entitlement = [
                    'UUID_ENTITLEMENT' => $uuidEntitlement,
                    'HOLDER_PARTY_KEY' => $unclaimedHolder,
                    'STATUS' => 'ACTIVE',
                ];
            } else {
                $this->assertExistingEntitlement(
                    $entitlement,
                    (string) $operation['UUID_OPERATION'],
                    $amount,
                    $entitlementKey
                );
            }

            $db->commit();

            return [
                'uuid_operation' => (string) $operation['UUID_OPERATION'],
                'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
                'holder_state' => ((string) $entitlement['HOLDER_PARTY_KEY'] === $unclaimedHolder)
                    ? 'UNCLAIMED'
                    : 'CLAIMED',
                'status' => (string) $entitlement['STATUS'],
                'idempotency_reused' => $operationReused || $entitlementReused,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertExistingOperation(
        array $operation,
        int $giftId,
        string $amount,
        string $uuidInvoice,
        string $uuidPayment
    ): void {
        if (strtoupper((string) ($operation['SOURCE_TYPE'] ?? '')) !== 'REGAL'
            || (string) ($operation['SOURCE_ID'] ?? '') !== (string) $giftId
            || !in_array(
                strtoupper((string) ($operation['STATUS'] ?? '')),
                ['PAID', 'INVOICED', 'COMPLETED'],
                true
            )
            || strtoupper((string) ($operation['CURRENCY'] ?? '')) !== 'EUR'
            || $this->money($operation['NET_AMOUNT'] ?? null, 'operation.NET_AMOUNT') !== $amount
            || (string) ($operation['UUID_FACTURA'] ?? '') !== $uuidInvoice
            || (string) ($operation['UUID_PAYMENT'] ?? '') !== $uuidPayment
        ) {
            throw SifException::conflict(
                'Gift purchase operation exists with different confirmed data.'
            );
        }
    }

    private function assertExistingEntitlement(
        array $entitlement,
        string $originOperation,
        string $amount,
        string $idempotencyKey
    ): void {
        $holder = trim((string) ($entitlement['HOLDER_PARTY_KEY'] ?? ''));
        if (strtoupper((string) ($entitlement['ENTITLEMENT_TYPE'] ?? '')) !== 'GIFT'
            || (string) ($entitlement['ORIGIN_UUID_OPERATION'] ?? '') !== $originOperation
            || (string) ($entitlement['IDEMPOTENCY_KEY'] ?? '') !== $idempotencyKey
            || $this->money($entitlement['FACE_VALUE'] ?? null, 'entitlement.FACE_VALUE') !== $amount
            || strtoupper((string) ($entitlement['CURRENCY'] ?? '')) !== 'EUR'
            || $holder === ''
        ) {
            throw SifException::conflict(
                'Gift code already maps to a different entitlement.'
            );
        }
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function execute(\PDO $db, string $sql, array $params): void
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if (!is_numeric($value) || (int) $value <= 0) {
            throw SifException::validation('Invalid ' . $field);
        }

        return (int) $value;
    }

    private function required(mixed $value, string $field, int $max): string
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > $max) {
            throw SifException::validation('Invalid ' . $field);
        }

        return $value;
    }

    private function uuid(mixed $value, string $field): string
    {
        $value = $this->required($value, $field, 36);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $value) !== 1) {
            throw SifException::validation('Invalid ' . $field);
        }

        return strtolower($value);
    }

    private function money(mixed $value, string $field): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid ' . $field);
        }

        $amount = number_format((float) $value, 2, '.', '');
        if ((float) $amount <= 0.0) {
            throw SifException::validation('Invalid ' . $field);
        }

        return $amount;
    }
}
