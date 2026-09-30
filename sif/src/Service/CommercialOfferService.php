<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialOperationRepository;
use Prisma\Sif\Repository\DiscountValidationRepository;
use Prisma\Sif\Repository\OperationalEventRepository;

final class CommercialOfferService
{
    public function __construct(
        private TransactionRunner $transactions,
        private CommercialOperationRepository $operations,
        private DiscountValidationRepository $discounts,
        private OperationalEventRepository $events,
        private UuidGenerator $uuidGenerator
    ) {
    }

    public function createOrReuse(array $input): array
    {
        $payload = $this->validate($input);

        try {
            return $this->createOrReuseValidated($payload);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            return $this->reuseAfterDuplicateKey($payload);
        }
    }

    private function createOrReuseValidated(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->operations->findByIdempotencyKey(
                $db,
                $payload['idempotency_key'],
                true
            );

            if ($existing !== null) {
                return $this->existingResult($db, $payload, $existing);
            }

            $operation = $payload;
            $operation['uuid_operation'] = $this->uuidGenerator->generate();
            $created = $this->operations->insert($db, $operation);

            $validation = null;
            if ($payload['discount'] !== null) {
                $validation = $this->createDiscountValidation(
                    $db,
                    $operation['uuid_operation'],
                    $payload['discount']
                );
            }

            $eventUuid = $this->events->append($db, [
                'operation_type' => 'COMMERCIAL_OFFER',
                'source_type' => $operation['source_type'],
                'source_id' => $operation['source_id'],
                'fiscal_impact' => 'NONE',
                'economic_impact' => 'COMMERCIAL_PRICE_ONLY',
                'status' => $operation['status'],
                'reason_code' => 'COMMERCIAL_OFFER_CREATED',
                'before_snapshot' => null,
                'after_snapshot' => $this->eventSnapshot($operation, $validation),
                'actor_type' => $payload['actor_type'],
                'actor_id' => $payload['actor_id'],
                'actor_role' => $payload['actor_role'],
                'source_channel' => $operation['source_channel'],
                'correlation_id' => $payload['correlation_id'],
                'occurred_at' => $payload['occurred_at'],
            ]);

            return $created + [
                'uuid_validation' => $validation['uuid_validation'] ?? null,
                'uuid_operational_event' => $eventUuid,
            ];
        });
    }

    private function reuseAfterDuplicateKey(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->operations->findByIdempotencyKey(
                $db,
                $payload['idempotency_key'],
                true
            );

            if ($existing === null) {
                throw new \RuntimeException(
                    'Duplicate commercial operation key detected, but the existing operation could not be loaded.'
                );
            }

            return $this->existingResult($db, $payload, $existing);
        });
    }

    private function existingResult(\PDO $db, array $payload, array $existing): array
    {
        $this->assertOperationMatches($payload, $existing);

        $validation = null;
        if ($payload['discount'] !== null) {
            $validation = $this->discounts->findByIdempotencyKey(
                $db,
                $payload['discount']['idempotency_key'],
                true
            );
            if ($validation === null) {
                throw SifException::conflict(
                    'Commercial operation exists but its discount validation is missing'
                );
            }
            $this->assertDiscountMatches(
                $payload['discount'],
                (string) $existing['UUID_OPERATION'],
                $validation
            );
        }

        return [
            'uuid_operation' => (string) $existing['UUID_OPERATION'],
            'idempotency_key' => (string) $existing['IDEMPOTENCY_KEY'],
            'status' => (string) $existing['STATUS'],
            'uuid_validation' => $validation['UUID_VALIDATION'] ?? null,
            'uuid_operational_event' => null,
            'idempotency_reused' => true,
        ];
    }

    private function createDiscountValidation(
        \PDO $db,
        string $uuidOperation,
        array $discount
    ): array {
        $existing = $this->discounts->findByIdempotencyKey(
            $db,
            $discount['idempotency_key'],
            true
        );
        if ($existing !== null) {
            $this->assertDiscountMatches($discount, $uuidOperation, $existing);

            return [
                'uuid_validation' => (string) $existing['UUID_VALIDATION'],
                'uuid_operation' => $uuidOperation,
                'status' => (string) $existing['STATUS'],
                'idempotency_reused' => true,
            ];
        }

        $discount['uuid_validation'] = $this->uuidGenerator->generate();
        $discount['uuid_operation'] = $uuidOperation;

        return $this->discounts->insert($db, $discount);
    }

    private function validate(array $input): array
    {
        foreach ([
            'idempotency_key',
            'operation_type',
            'source_channel',
            'source_type',
            'product_type',
            'classification',
            'classification_reason',
            'status',
            'currency',
            'correlation_id',
            'actor_type',
        ] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field]) || trim($input[$field]) === '') {
                throw SifException::validation('Missing commercial offer field: ' . $field);
            }
        }

        $gross = $this->amount($input['gross_amount'] ?? null, 'gross_amount');
        $discountAmount = $this->amount($input['discount_amount'] ?? null, 'discount_amount');
        $net = $this->amount($input['net_amount'] ?? null, 'net_amount');

        if ($this->cents($gross) - $this->cents($discountAmount) !== $this->cents($net)) {
            throw SifException::validation(
                'Commercial offer amounts must satisfy gross_amount - discount_amount = net_amount'
            );
        }

        $priceSnapshot = $this->snapshot(
            $input['price_snapshot'] ?? null,
            'price_snapshot',
            false
        );
        $taxSnapshot = $this->snapshot(
            $input['tax_snapshot'] ?? null,
            'tax_snapshot',
            false
        );
        $capacitySnapshot = $this->snapshot(
            $input['capacity_snapshot'] ?? null,
            'capacity_snapshot',
            true
        );

        $currency = strtoupper(trim((string) $input['currency']));
        if (strlen($currency) !== 3) {
            throw SifException::validation('Commercial offer currency must have three characters');
        }

        $idempotencyKey = trim((string) $input['idempotency_key']);
        if (strlen($idempotencyKey) > 140) {
            throw SifException::validation('Commercial offer idempotency key is too long');
        }

        $discount = null;
        if (array_key_exists('discount', $input) && $input['discount'] !== null) {
            if (!is_array($input['discount'])) {
                throw SifException::validation('Commercial offer discount must be an object');
            }
            $discount = $this->validateDiscount($input['discount']);
            if (
                $discount['result_discount_amount'] !== null
                && $this->cents($discount['result_discount_amount']) !== $this->cents($discountAmount)
            ) {
                throw SifException::validation(
                    'Discount validation result amount must match commercial offer discount amount'
                );
            }
        }

        return [
            'idempotency_key' => $idempotencyKey,
            'operation_type' => strtoupper(trim((string) $input['operation_type'])),
            'source_channel' => strtoupper(trim((string) $input['source_channel'])),
            'source_type' => strtoupper(trim((string) $input['source_type'])),
            'source_id' => $this->nullableString($input['source_id'] ?? null),
            'product_type' => strtoupper(trim((string) $input['product_type'])),
            'product_code' => $this->nullableString($input['product_code'] ?? null),
            'product_edition' => $this->nullableString($input['product_edition'] ?? null),
            'classification' => strtoupper(trim((string) $input['classification'])),
            'classification_reason' => strtoupper(trim((string) $input['classification_reason'])),
            'status' => strtoupper(trim((string) $input['status'])),
            'currency' => $currency,
            'gross_amount' => $gross,
            'discount_amount' => $discountAmount,
            'net_amount' => $net,
            'price_snapshot_json' => $priceSnapshot,
            'capacity_snapshot_json' => $capacitySnapshot,
            'tax_snapshot_json' => $taxSnapshot,
            'expires_at' => $this->nullableString($input['expires_at'] ?? null),
            'created_by' => $this->nullableString($input['created_by'] ?? null),
            'discount' => $discount,
            'correlation_id' => trim((string) $input['correlation_id']),
            'actor_type' => strtoupper(trim((string) $input['actor_type'])),
            'actor_id' => $this->nullableString($input['actor_id'] ?? null),
            'actor_role' => $this->nullableString($input['actor_role'] ?? null),
            'occurred_at' => $this->nullableString($input['occurred_at'] ?? null)
                ?? date('Y-m-d H:i:s'),
        ];
    }

    private function validateDiscount(array $input): array
    {
        foreach ([
            'idempotency_key',
            'discount_type',
            'subject_party_key',
            'status',
            'rule_version',
            'requested_at',
        ] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field]) || trim($input[$field]) === '') {
                throw SifException::validation('Missing discount validation field: ' . $field);
            }
        }

        $idempotencyKey = trim((string) $input['idempotency_key']);
        if (strlen($idempotencyKey) > 140) {
            throw SifException::validation('Discount validation idempotency key is too long');
        }

        $evidenceHash = $this->nullableString($input['evidence_hash'] ?? null);
        if ($evidenceHash !== null && preg_match('/^[a-f0-9]{64}$/iD', $evidenceHash) !== 1) {
            throw SifException::validation('Discount evidence hash must be SHA-256 hex');
        }

        return [
            'idempotency_key' => $idempotencyKey,
            'discount_type' => strtoupper(trim((string) $input['discount_type'])),
            'subject_party_key' => trim((string) $input['subject_party_key']),
            'status' => strtoupper(trim((string) $input['status'])),
            'rule_version' => trim((string) $input['rule_version']),
            'rule_snapshot_json' => $this->snapshot(
                $input['rule_snapshot'] ?? null,
                'discount.rule_snapshot',
                false
            ),
            'evidence_storage_ref' => $this->nullableString($input['evidence_storage_ref'] ?? null),
            'evidence_hash' => $evidenceHash,
            'requested_at' => trim((string) $input['requested_at']),
            'validated_at' => $this->nullableString($input['validated_at'] ?? null),
            'validated_by' => $this->nullableString($input['validated_by'] ?? null),
            'rejection_reason' => $this->nullableString($input['rejection_reason'] ?? null),
            'result_discount_amount' => isset($input['result_discount_amount'])
                ? $this->amount($input['result_discount_amount'], 'discount.result_discount_amount')
                : null,
            'future_entitlement_ref' => $this->nullableString($input['future_entitlement_ref'] ?? null),
        ];
    }

    private function assertOperationMatches(array $payload, array $existing): void
    {
        $pairs = [
            'OPERATION_TYPE' => $payload['operation_type'],
            'SOURCE_CHANNEL' => $payload['source_channel'],
            'SOURCE_TYPE' => $payload['source_type'],
            'SOURCE_ID' => $payload['source_id'],
            'PRODUCT_TYPE' => $payload['product_type'],
            'PRODUCT_CODE' => $payload['product_code'],
            'PRODUCT_EDITION' => $payload['product_edition'],
            'CLASSIFICATION' => $payload['classification'],
            'CLASSIFICATION_REASON' => $payload['classification_reason'],
            'STATUS' => $payload['status'],
            'CURRENCY' => $payload['currency'],
            'EXPIRES_AT' => $payload['expires_at'],
            'CREATED_BY' => $payload['created_by'],
        ];

        foreach ($pairs as $column => $expected) {
            if ($this->nullableString($existing[$column] ?? null) !== $expected) {
                throw SifException::conflict(
                    'Commercial offer idempotency key exists with different field: ' . $column
                );
            }
        }

        foreach ([
            'GROSS_AMOUNT' => 'gross_amount',
            'DISCOUNT_AMOUNT' => 'discount_amount',
            'NET_AMOUNT' => 'net_amount',
        ] as $column => $field) {
            if (number_format((float) ($existing[$column] ?? 0), 2, '.', '') !== $payload[$field]) {
                throw SifException::conflict(
                    'Commercial offer idempotency key exists with different amount: ' . $column
                );
            }
        }

        foreach ([
            'PRICE_SNAPSHOT_JSON' => 'price_snapshot_json',
            'CAPACITY_SNAPSHOT_JSON' => 'capacity_snapshot_json',
            'TAX_SNAPSHOT_JSON' => 'tax_snapshot_json',
        ] as $column => $field) {
            if (!$this->sameJson($existing[$column] ?? null, $payload[$field])) {
                throw SifException::conflict(
                    'Commercial offer idempotency key exists with different snapshot: ' . $column
                );
            }
        }
    }

    private function assertDiscountMatches(
        array $payload,
        string $uuidOperation,
        array $existing
    ): void {
        $pairs = [
            'UUID_OPERATION' => $uuidOperation,
            'DISCOUNT_TYPE' => $payload['discount_type'],
            'SUBJECT_PARTY_KEY' => $payload['subject_party_key'],
            'STATUS' => $payload['status'],
            'RULE_VERSION' => $payload['rule_version'],
            'EVIDENCE_STORAGE_REF' => $payload['evidence_storage_ref'],
            'EVIDENCE_HASH' => $payload['evidence_hash'],
            'REQUESTED_AT' => $payload['requested_at'],
            'VALIDATED_AT' => $payload['validated_at'],
            'VALIDATED_BY' => $payload['validated_by'],
            'REJECTION_REASON' => $payload['rejection_reason'],
            'FUTURE_ENTITLEMENT_REF' => $payload['future_entitlement_ref'],
        ];

        foreach ($pairs as $column => $expected) {
            if ($this->nullableString($existing[$column] ?? null) !== $expected) {
                throw SifException::conflict(
                    'Discount validation idempotency key exists with different field: ' . $column
                );
            }
        }

        $existingAmount = $existing['RESULT_DISCOUNT_AMOUNT'] ?? null;
        $expectedAmount = $payload['result_discount_amount'];
        if (
            ($existingAmount === null) !== ($expectedAmount === null)
            || ($existingAmount !== null
                && number_format((float) $existingAmount, 2, '.', '') !== $expectedAmount)
        ) {
            throw SifException::conflict(
                'Discount validation idempotency key exists with different result amount'
            );
        }

        if (!$this->sameJson($existing['RULE_SNAPSHOT_JSON'] ?? null, $payload['rule_snapshot_json'])) {
            throw SifException::conflict(
                'Discount validation idempotency key exists with different rule snapshot'
            );
        }
    }

    private function eventSnapshot(array $operation, ?array $validation): array
    {
        return [
            'uuid_operation' => $operation['uuid_operation'],
            'operation_type' => $operation['operation_type'],
            'product_type' => $operation['product_type'],
            'product_code' => $operation['product_code'],
            'product_edition' => $operation['product_edition'],
            'status' => $operation['status'],
            'gross_amount' => $operation['gross_amount'],
            'discount_amount' => $operation['discount_amount'],
            'net_amount' => $operation['net_amount'],
            'currency' => $operation['currency'],
            'discount_validation' => $validation === null ? null : [
                'uuid_validation' => $validation['uuid_validation'],
                'status' => $validation['status'],
            ],
        ];
    }

    private function amount(mixed $value, string $field): string
    {
        if (!is_numeric($value) || (float) $value < 0) {
            throw SifException::validation('Invalid commercial offer amount: ' . $field);
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function snapshot(mixed $value, string $field, bool $nullable): ?string
    {
        if ($value === null && $nullable) {
            return null;
        }
        if (!is_array($value) || $value === []) {
            throw SifException::validation('Commercial offer snapshot must be a non-empty object: ' . $field);
        }

        return json_encode(
            $this->canonicalize($value),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR
        );
    }

    private function sameJson(mixed $existingJson, ?string $expectedJson): bool
    {
        if ($existingJson === null || $existingJson === '') {
            return $expectedJson === null;
        }
        if ($expectedJson === null) {
            return false;
        }

        try {
            $existing = json_decode((string) $existingJson, true, 512, JSON_THROW_ON_ERROR);
            $expected = json_decode($expectedJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return $this->canonicalize($existing) === $this->canonicalize($expected);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : trim((string) $value);
    }
}
