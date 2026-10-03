<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialOperationPartyRepository;
use Prisma\Sif\Repository\CommercialOperationRepository;
use Prisma\Sif\Repository\DiscountValidationRepository;
use Prisma\Sif\Repository\LegacyPrismaStudentHistoryRepository;

/**
 * Trusted server-side UC-020 bridge.
 *
 * It stages the authoritative Alumne PrisMa commercial decision and creates
 * the CURS Redsys intent from the same frozen snapshot. Browser supplied
 * prices/discount types are not accepted as authoritative input.
 */
final class PrismaStudentCourseCheckoutService
{
    public function __construct(
        private LegacyPrismaStudentHistoryRepository $history,
        private PrismaStudentDiscountPolicy $policy,
        private RedsysPaymentIntentService $intents,
        private UuidGenerator $uuids,
        private ?CommercialOperationRepository $operations = null,
        private ?DiscountValidationRepository $discounts = null,
        private ?CommercialOperationPartyRepository $parties = null
    ) {
        $this->operations ??= new CommercialOperationRepository();
        $this->discounts ??= new DiscountValidationRepository();
        $this->parties ??= new CommercialOperationPartyRepository();
    }

    public function stageAndCreateIntent(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $enrollmentId,
        string $canonicalPartyKey,
        array $trustedPriceSnapshot,
        array $intentRequest,
        string $sourceChannel = 'WEB'
    ): array {
        if ($sifDb->inTransaction()) {
            throw new \LogicException('Prisma student checkout must start its own SIF transaction.');
        }
        if ($enrollmentId < 1 || trim($canonicalPartyKey) === ''
            || strlen($canonicalPartyKey) > 100
            || !in_array($sourceChannel, ['WEB', 'INTRANET'], true)
        ) {
            throw SifException::validation('Trusted course checkout identity or channel is invalid.');
        }

        $enrollment = $this->enrollment($legacyDb, $enrollmentId);
        $history = $this->history->findByDocument($legacyDb, (string) $enrollment['DNI']);
        $decision = $this->policy->evaluate($history);
        if (($decision['eligible'] ?? false) !== true) {
            throw SifException::conflict('Enrollment is not eligible for Alumne PrisMa under the selected rule version.');
        }

        $price = $this->price($trustedPriceSnapshot, (string) $enrollment['A_PAGAR']);
        $operationKey = 'UC020|ALUMNE_PRISMA|INSCRIPCIO:' . $enrollmentId;
        $validationKey = $operationKey . '|VALIDATION|' . (string) $decision['rule_version'];

        $sifDb->beginTransaction();
        try {
            $existing = $this->operations->findByIdempotencyKey($sifDb, $operationKey, true);

            if ($existing !== null) {
                if ($this->moneyToCents((string) $existing['NET_AMOUNT']) !== $price['net_cents']
                    || $this->canonicalJson((string) $existing['PRICE_SNAPSHOT_JSON'])
                        !== $this->canonicalJson($price['price_json'])
                ) {
                    throw SifException::conflict('A conflicting Alumne PrisMa commercial operation already exists.');
                }
                $uuidOperation = (string) $existing['UUID_OPERATION'];

                $linkedIntent = trim((string) ($existing['UUID_INTENT'] ?? ''));
                if ($linkedIntent !== '') {
                    $linkedOrder = $this->one(
                        $sifDb,
                        'SELECT DS_ORDER FROM redsys_payment_intent WHERE UUID_INTENT = ? FOR UPDATE',
                        [$linkedIntent]
                    );
                    if ($linkedOrder === null
                        || trim((string) ($intentRequest['ds_order'] ?? '')) !== (string) $linkedOrder['DS_ORDER']
                    ) {
                        throw SifException::conflict(
                            'Commercial operation is already linked to another Redsys intent.'
                        );
                    }
                }
            } else {
                $uuidOperation = $this->uuids->generate();
                $this->operations->insert($sifDb, [
                    'uuid_operation' => $uuidOperation,
                    'idempotency_key' => $operationKey,
                    'operation_type' => 'ENROLLMENT',
                    'source_channel' => $sourceChannel,
                    'source_type' => 'CURS',
                    'source_id' => (string) $enrollmentId,
                    'product_type' => 'CURS',
                    'product_code' => (string) $enrollment['CURS'],
                    'product_edition' => (string) $enrollment['ANY'] . '/' . (string) $enrollment['MES'],
                    'classification' => 'READY_FOR_PAYMENT',
                    'classification_reason' => 'ALUMNE_PRISMA_VALIDATED',
                    'status' => 'READY_FOR_PAYMENT',
                    'currency' => 'EUR',
                    'gross_amount' => $price['gross'],
                    'discount_amount' => $price['discount'],
                    'net_amount' => $price['net'],
                    'price_snapshot_json' => $price['price_json'],
                    'capacity_snapshot_json' => null,
                    'tax_snapshot_json' => $price['tax_json'],
                    'expires_at' => null,
                    'created_by' => trim((string) ($intentRequest['created_by'] ?? 'uc-020-checkout')),
                ]);

                $name = trim((string) $enrollment['NOM'] . ' ' . (string) ($enrollment['COGNOMS'] ?? ''));
                $this->parties->insert($sifDb, [
                    'uuid_operation' => $uuidOperation,
                    'party_key' => $canonicalPartyKey,
                    'party_role' => 'PARTICIPANT',
                    'legacy_person_id' => null,
                    'nif_cif' => (string) $enrollment['DNI'],
                    'nom_rao' => $name,
                    'email' => null,
                    'product_code' => (string) $enrollment['CURS'],
                    'product_edition' => (string) $enrollment['ANY'] . '/' . (string) $enrollment['MES'],
                    'line_amount' => $price['net'],
                    'snapshot_json' => json_encode([
                        'source' => 'legacy_inscription',
                        'source_id' => $enrollmentId,
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ]);
            }

            $validation = $this->discounts->findByIdempotencyKey($sifDb, $validationKey, true);
            if ($validation === null) {
                $uuidValidation = $this->uuids->generate();
                $ruleSnapshot = json_encode([
                    'decision' => 'ELIGIBLE',
                    'reason' => $decision['reason'] ?? null,
                    'evidence' => $decision['evidence'] ?? null,
                    'rule_version' => $decision['rule_version'],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

                $now = date('Y-m-d H:i:s');
                $this->discounts->insert($sifDb, [
                    'uuid_validation' => $uuidValidation,
                    'uuid_operation' => $uuidOperation,
                    'discount_type' => 'ALUMNE_PRISMA',
                    'subject_party_key' => $canonicalPartyKey,
                    'status' => 'VALIDATED',
                    'rule_version' => (string) $decision['rule_version'],
                    'rule_snapshot_json' => $ruleSnapshot,
                    'evidence_storage_ref' => null,
                    'evidence_hash' => null,
                    'requested_at' => $now,
                    'validated_at' => $now,
                    'validated_by' => 'PrismaStudentDiscountPolicy',
                    'rejection_reason' => null,
                    'result_discount_amount' => $price['discount'],
                    'future_entitlement_ref' => null,
                    'idempotency_key' => $validationKey,
                ]);
            } else {
                $uuidValidation = (string) $validation['UUID_VALIDATION'];
                if ((string) $validation['RULE_VERSION'] !== (string) $decision['rule_version']
                    || (string) $validation['STATUS'] !== 'VALIDATED'
                    || $this->moneyToCents((string) $validation['RESULT_DISCOUNT_AMOUNT']) !== $price['discount_cents']
                ) {
                    throw SifException::conflict('A conflicting Alumne PrisMa validation already exists.');
                }
            }

            $snapshot = [
                'operation' => ['uuid' => $uuidOperation],
                'inscription' => [
                    'ID' => (int) $enrollment['ID'],
                    'IDPAG' => (int) $enrollment['IDPAG'],
                    'ANY' => (int) $enrollment['ANY'],
                    'MES' => (string) $enrollment['MES'],
                    'CURS' => (string) $enrollment['CURS'],
                    'NOM' => (string) $enrollment['NOM'],
                    'COGNOMS' => (string) ($enrollment['COGNOMS'] ?? ''),
                    'DNI' => (string) $enrollment['DNI'],
                    'A_PAGAR' => $price['net'],
                ],
                'course' => [
                    'NOM_CURS' => (string) $trustedPriceSnapshot['course_title'],
                ],
                'payment' => [
                    'amount' => $price['net'],
                ],
                'discount' => [
                    'origin' => 'ALUMNE_PRISMA',
                    'mode' => 'FIXED_PRICE',
                    'base' => $price['gross'],
                    'amount' => $price['discount'],
                    'rule_version' => (string) $decision['rule_version'],
                    'validation_uuid' => $uuidValidation,
                    'text' => 'Descompte Alumne PrisMa',
                    'internal_reason' => 'discount_validation:' . $uuidValidation,
                ],
            ];

            $intentInput = $intentRequest;
            $intentInput['idpag'] = (int) $enrollment['IDPAG'];
            $intentInput['source_type'] = 'CURS';
            $intentInput['source_id'] = (string) $enrollmentId;
            $intentInput['expected_amount'] = $price['net'];
            $intentInput['currency'] = 'EUR';
            $intentInput['snapshot'] = $snapshot;

            $intent = $this->intents->create($sifDb, $intentInput);
            $this->operations->linkIntent(
                $sifDb,
                $uuidOperation,
                (string) $intent['uuid_intent']
            );
            $this->operations->updateStatus(
                $sifDb,
                $uuidOperation,
                'INTENT_CREATED'
            );

            $sifDb->commit();

            return [
                'uuid_operation' => $uuidOperation,
                'uuid_validation' => $uuidValidation,
                'uuid_intent' => (string) $intent['uuid_intent'],
                'ds_order' => (string) $intent['ds_order'],
                'status' => 'INTENT_CREATED',
                'idempotency_reused' => (bool) $intent['idempotency_reused'],
                'rule_version' => (string) $decision['rule_version'],
                'amount' => $price['net'],
            ];
        } catch (\Throwable $exception) {
            if ($sifDb->inTransaction()) {
                $sifDb->rollBack();
            }
            throw $exception;
        }
    }

    private function enrollment(\PDO $legacyDb, int $enrollmentId): array
    {
        $row = $this->one(
            $legacyDb,
            'SELECT ID, IDPAG, ANY, MES, CURS, NOM, COGNOMS, DNI, A_PAGAR
             FROM inscripcions WHERE ID = ?',
            [$enrollmentId]
        );
        if ($row === null || !is_numeric($row['IDPAG'] ?? null) || (int) $row['IDPAG'] <= 0) {
            throw SifException::conflict('A payable legacy enrollment with IDPAG is required.');
        }

        return $row;
    }

    private function price(array $snapshot, string $legacyNet): array
    {
        foreach (['gross_amount', 'discount_amount', 'net_amount', 'course_title', 'tax_snapshot'] as $required) {
            if (!array_key_exists($required, $snapshot)) {
                throw SifException::validation('Trusted Alumne PrisMa price snapshot is incomplete.');
            }
        }
        if (!is_array($snapshot['tax_snapshot']) || $snapshot['tax_snapshot'] === []
            || trim((string) $snapshot['course_title']) === ''
        ) {
            throw SifException::validation('Trusted Alumne PrisMa price/tax snapshot is incomplete.');
        }

        $gross = $this->moneyToCents((string) $snapshot['gross_amount']);
        $discount = $this->moneyToCents((string) $snapshot['discount_amount']);
        $net = $this->moneyToCents((string) $snapshot['net_amount']);
        $legacy = $this->moneyToCents($legacyNet);
        if ($gross <= 0 || $discount <= 0 || $net <= 0 || $gross - $discount !== $net || $net !== $legacy) {
            throw SifException::conflict('Trusted Alumne PrisMa price does not match the real enrollment.');
        }

        $priceJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $taxJson = json_encode($snapshot['tax_snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return [
            'gross_cents' => $gross,
            'discount_cents' => $discount,
            'net_cents' => $net,
            'gross' => $this->centsToMoney($gross),
            'discount' => $this->centsToMoney($discount),
            'net' => $this->centsToMoney($net),
            'price_json' => $priceJson,
            'tax_json' => $taxJson,
        ];
    }

    private function moneyToCents(string $value): int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($value), $m)) {
            throw SifException::validation('Invalid trusted monetary amount.');
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function centsToMoney(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function canonicalJson(string $json): string
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return json_encode($this->canonicalize($data), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    private function one(\PDO $db, string $sql, array $parameters): ?array
    {
        $statement = $db->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

}
