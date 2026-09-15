<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;

final class RedsysPaymentIntentService
{
    private const SOURCE_TYPES = ['CURS', 'PACK', 'GRUP', 'REGAL', 'USOC_ALUMNE'];

    public function __construct(
        private RedsysPaymentIntentRepository $intents,
        private UuidGenerator $uuidGenerator
    ) {
    }

    public function create(\PDO $db, array $input): array
    {
        $snapshot = $input['snapshot'] ?? [];
        if (!is_array($snapshot) || $snapshot === []) {
            throw SifException::validation('Redsys payment intent snapshot is required');
        }

        $snapshotJson = json_encode(
            $snapshot,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($snapshotJson === false) {
            throw SifException::validation('Redsys payment intent snapshot is not serializable');
        }

        $intent = [
            'uuid_intent' => $this->uuidGenerator->generate(),
            'ds_order' => $this->dsOrder($input['ds_order'] ?? null),
            'idpag' => $this->optionalPositiveInt($input['idpag'] ?? null),
            'source_type' => $this->sourceType($input['source_type'] ?? null),
            'source_id' => $this->sourceId($input['source_id'] ?? null),
            'expected_amount' => $this->amount($input['expected_amount'] ?? null),
            'currency' => $this->currency($input['currency'] ?? 'EUR'),
            'terminal' => $this->terminal($input['terminal'] ?? null),
            'snapshot_json' => $snapshotJson,
            'status' => 'PENDING',
            'created_by' => isset($input['created_by']) ? trim((string) $input['created_by']) : null,
            'expires_at' => isset($input['expires_at']) ? trim((string) $input['expires_at']) : null,
        ];
        if ($intent['source_type'] === 'REGAL' && !ctype_digit($intent['source_id'])) {
            throw SifException::validation('Redsys gift payment intent source ID must be numeric');
        }

        $existing = $this->intents->findByDsOrder($db, $intent['ds_order']);
        if ($existing !== null) {
            if (!$this->sameIntent($existing, $intent)) {
                throw SifException::conflict('Redsys payment intent DS_ORDER already exists with different data');
            }

            return [
                'uuid_intent' => $existing['UUID_INTENT'],
                'ds_order' => $existing['DS_ORDER'],
                'status' => $existing['STATUS'],
                'idempotency_reused' => true,
            ];
        }

        return $this->intents->insert($db, $intent);
    }

    private function sameIntent(array $existing, array $intent): bool
    {
        $existingSnapshot = json_decode((string) $existing['SNAPSHOT_JSON'], true);
        $candidateSnapshot = json_decode($intent['snapshot_json'], true);
        if (!is_array($existingSnapshot) || !is_array($candidateSnapshot)) {
            return false;
        }

        return $this->nullableInt($existing['IDPAG']) === $intent['idpag']
            && (string) $existing['SOURCE_TYPE'] === $intent['source_type']
            && $this->nullableString($existing['SOURCE_ID']) === $intent['source_id']
            && number_format((float) $existing['EXPECTED_AMOUNT'], 2, '.', '') === $intent['expected_amount']
            && (string) $existing['CURRENCY'] === $intent['currency']
            && $this->nullableString($existing['TERMINAL']) === $intent['terminal']
            && $this->canonicalJson($existingSnapshot) === $this->canonicalJson($candidateSnapshot)
            && $this->nullableString($existing['CREATED_BY']) === $intent['created_by']
            && $this->nullableString($existing['EXPIRES_AT']) === $intent['expires_at'];
    }

    private function canonicalJson(array $value): string
    {
        $json = json_encode(
            $this->canonicalize($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );

        return $json === false ? '' : $json;
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

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function sourceType(mixed $value): string
    {
        $sourceType = strtoupper(trim((string) $value));
        if (!in_array($sourceType, self::SOURCE_TYPES, true)) {
            throw SifException::validation('Unsupported Redsys payment intent source type');
        }

        return $sourceType;
    }

    private function amount(mixed $value): string
    {
        if (!is_numeric($value) || (float) $value <= 0) {
            throw SifException::validation('Redsys payment intent amount must be positive');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function dsOrder(mixed $value): string
    {
        $dsOrder = trim((string) $value);
        if ($dsOrder === '') {
            throw SifException::validation('Redsys payment intent DS_ORDER is required');
        }
        if (strlen($dsOrder) > 40) {
            throw SifException::validation('Redsys payment intent DS_ORDER is too long');
        }

        return $dsOrder;
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = trim((string) $value);
        if (!ctype_digit($normalized)) {
            throw SifException::validation('Redsys payment intent IDPAG must be a positive integer');
        }

        $integer = (int) $normalized;
        if ($integer <= 0) {
            throw SifException::validation('Redsys payment intent IDPAG must be positive');
        }

        return $integer;
    }

    private function currency(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));
        if (strlen($currency) !== 3) {
            throw SifException::validation('Redsys payment intent currency must have three characters');
        }

        return $currency;
    }

    private function sourceId(mixed $value): string
    {
        $sourceId = trim((string) $value);
        if ($sourceId === '') {
            throw SifException::validation('Redsys payment intent source ID is required');
        }
        if (strlen($sourceId) > 64) {
            throw SifException::validation('Redsys payment intent source ID is too long');
        }

        return $sourceId;
    }

    private function terminal(mixed $value): string
    {
        $terminal = trim((string) $value);
        if ($terminal === '') {
            throw SifException::validation('Redsys payment intent terminal is required');
        }
        if (strlen($terminal) > 20) {
            throw SifException::validation('Redsys payment intent terminal is too long');
        }

        return $terminal;
    }
}
