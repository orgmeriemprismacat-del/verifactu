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
        private UuidGenerator $uuidGenerator,
        private ?CourseIntentSnapshotValidator $courseSnapshots = null
    ) {
        $this->courseSnapshots ??= new CourseIntentSnapshotValidator();
    }

    public function create(\PDO $db, array $input): array
    {
        $snapshot = $input['snapshot'] ?? [];
        if (!is_array($snapshot) || $snapshot === []) {
            throw SifException::validation('Redsys payment intent snapshot is required');
        }

        $idpag = $this->optionalPositiveInt($input['idpag'] ?? null);
        $sourceType = $this->sourceType($input['source_type'] ?? null);
        $sourceId = $this->sourceId($input['source_id'] ?? null);
        $expectedAmount = $this->amount($input['expected_amount'] ?? null);

        if ($sourceType === 'CURS') {
            $this->courseSnapshots->validate($snapshot, $idpag, $sourceId, $expectedAmount);
        } elseif ($sourceType === 'PACK') {
            $this->validatePackSnapshot($snapshot, $idpag, $sourceId, $expectedAmount);
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
            'idpag' => $idpag,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'expected_amount' => $expectedAmount,
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

    private function validatePackSnapshot(
        array $snapshot,
        ?int $idpag,
        string $sourceId,
        string $expectedAmount
    ): void {
        if ($idpag === null) {
            throw SifException::validation('Redsys pack payment intent requires IDPAG');
        }

        $pack = $snapshot['pack'] ?? null;
        $items = $snapshot['items'] ?? null;
        if (!is_array($pack) || !is_array($items) || count($items) < 2) {
            throw SifException::validation('Redsys pack snapshot requires pack and at least two items');
        }

        $packId = $pack['ID_PACK'] ?? $pack['id_pack'] ?? $pack['id'] ?? null;
        if (!is_numeric($packId) || (int) $packId <= 0 || (string) (int) $packId !== ltrim($sourceId, '0')) {
            throw SifException::validation('Redsys pack snapshot source does not match pack ID');
        }

        $ordinals = [];
        $sum = 0.0;
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw SifException::validation('Invalid Redsys pack snapshot item ' . $index);
            }

            $ordinal = $item['ordinal'] ?? null;
            if (!is_int($ordinal) && !(is_string($ordinal) && ctype_digit($ordinal))) {
                throw SifException::validation('Redsys pack snapshot item ordinal is required');
            }
            $ordinal = (int) $ordinal;
            if ($ordinal <= 0 || isset($ordinals[$ordinal])) {
                throw SifException::validation('Redsys pack snapshot item ordinal must be unique and positive');
            }
            $ordinals[$ordinal] = true;

            $inscription = $item['inscription'] ?? null;
            $course = $item['course'] ?? null;
            if (!is_array($inscription) || !is_array($course)) {
                throw SifException::validation('Redsys pack snapshot item requires inscription and course');
            }

            foreach (['ID', 'ANY', 'MES', 'NOM', 'DNI'] as $field) {
                if (!array_key_exists($field, $inscription) || $inscription[$field] === '' || $inscription[$field] === null) {
                    throw SifException::validation('Missing Redsys pack inscription field ' . $field);
                }
            }

            $itemIdpag = $inscription['IDPAG'] ?? $inscription['idpag'] ?? $idpag;
            if (!is_numeric($itemIdpag) || (int) $itemIdpag !== $idpag) {
                throw SifException::validation('Redsys pack snapshot item IDPAG mismatch');
            }

            $courseTitle = $course['NOM_CURS'] ?? $course['TITOL'] ?? $course['title'] ?? null;
            if (!is_string($courseTitle) || trim($courseTitle) === '') {
                throw SifException::validation('Redsys pack snapshot course title is required');
            }

            $lineTotal = $inscription['TOTAL'] ?? $inscription['total'] ?? $inscription['A_PAGAR'] ?? $inscription['a_pagar'] ?? null;
            if (!is_numeric($lineTotal) || (float) $lineTotal < 0) {
                throw SifException::validation('Invalid Redsys pack snapshot line total');
            }
            $sum += (float) $lineTotal;
        }

        ksort($ordinals);
        $expectedOrdinal = 1;
        foreach (array_keys($ordinals) as $ordinal) {
            if ($ordinal !== $expectedOrdinal) {
                throw SifException::validation('Redsys pack snapshot ordinals must be contiguous from 1');
            }
            $expectedOrdinal++;
        }

        if (number_format($sum, 2, '.', '') !== $expectedAmount) {
            throw SifException::conflict('Redsys pack snapshot total does not match expected amount');
        }
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
