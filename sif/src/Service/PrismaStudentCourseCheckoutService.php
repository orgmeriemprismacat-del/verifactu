<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentPaymentFlowLockRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository;
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
        private ?EnrollmentPaymentFlowLockRepository $paymentFlowLocks = null,
        private ?InvoiceBeforePaymentCoverageRepository $beforePaymentCoverage = null
    ) {
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
            if ($this->paymentFlowLocks !== null) {
                $this->paymentFlowLocks->lockInscription($sifDb, $enrollmentId);
            }
            if ($this->beforePaymentCoverage !== null) {
                $claims = $this->beforePaymentCoverage->findClaims($sifDb, [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => $enrollmentId,
                    'relation_type' => 'ORIGIN',
                ]]);
                if ($claims !== []) {
                    throw SifException::conflict(
                        "Course inscription {$enrollmentId} is already covered by an invoice-before-payment operation."
                    );
                }
            }

            $existing = $this->one(
                $sifDb,
                'SELECT UUID_OPERATION, STATUS, NET_AMOUNT, PRICE_SNAPSHOT_JSON, UUID_INTENT
                 FROM commercial_operation WHERE IDEMPOTENCY_KEY = ? FOR UPDATE',
                [$operationKey]
            );

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
                $this->execute(
                    $sifDb,
                    'INSERT INTO commercial_operation
                     (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
                      SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION,
                      CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
                      GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON,
                      TAX_SNAPSHOT_JSON, CREATED_BY)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $uuidOperation,
                        $operationKey,
                        'ENROLLMENT',
                        $sourceChannel,
                        'CURS',
                        (string) $enrollmentId,
                        'CURS',
                        (string) $enrollment['CURS'],
                        (string) $enrollment['ANY'] . '/' . (string) $enrollment['MES'],
                        'READY_FOR_PAYMENT',
                        'ALUMNE_PRISMA_VALIDATED',
                        'READY_FOR_PAYMENT',
                        'EUR',
                        $price['gross'],
                        $price['discount'],
                        $price['net'],
                        $price['price_json'],
                        $price['tax_json'],
                        trim((string) ($intentRequest['created_by'] ?? 'uc-020-checkout')),
                    ]
                );

                $name = trim((string) $enrollment['NOM'] . ' ' . (string) ($enrollment['COGNOMS'] ?? ''));
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
                        (string) $enrollment['DNI'],
                        $name,
                        (string) $enrollment['CURS'],
                        (string) $enrollment['ANY'] . '/' . (string) $enrollment['MES'],
                        $price['net'],
                        json_encode([
                            'source' => 'legacy_inscription',
                            'source_id' => $enrollmentId,
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    ]
                );
            }


            $uuidOperationLine = $this->ensureOperationLine(
                $sifDb,
                $uuidOperation,
                $canonicalPartyKey,
                $enrollment,
                $price
            );

            $validation = $this->one(
                $sifDb,
                'SELECT UUID_VALIDATION, RULE_VERSION, STATUS, RESULT_DISCOUNT_AMOUNT, RULE_SNAPSHOT_JSON
                 FROM discount_validation WHERE IDEMPOTENCY_KEY = ? FOR UPDATE',
                [$validationKey]
            );
            if ($validation === null) {
                $uuidValidation = $this->uuids->generate();
                $ruleSnapshot = json_encode([
                    'decision' => 'ELIGIBLE',
                    'reason' => $decision['reason'] ?? null,
                    'evidence' => $decision['evidence'] ?? null,
                    'rule_version' => $decision['rule_version'],
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

                $this->execute(
                    $sifDb,
                    'INSERT INTO discount_validation
                     (UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
                      STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, REQUESTED_AT,
                      VALIDATED_AT, VALIDATED_BY, RESULT_DISCOUNT_AMOUNT, IDEMPOTENCY_KEY)
                     VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?, ?, ?)',
                    [
                        $uuidValidation,
                        $uuidOperation,
                        'ALUMNE_PRISMA',
                        $canonicalPartyKey,
                        'VALIDATED',
                        (string) $decision['rule_version'],
                        $ruleSnapshot,
                        'PrismaStudentDiscountPolicy',
                        $price['discount'],
                        $validationKey,
                    ]
                );
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
                'operation' => [
                    'uuid' => $uuidOperation,
                    'line_uuid' => $uuidOperationLine,
                ],
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
            $this->execute(
                $sifDb,
                'UPDATE commercial_operation
                 SET UUID_INTENT = ?, STATUS = ?, UPDATED_AT = CURRENT_TIMESTAMP
                 WHERE UUID_OPERATION = ?',
                [(string) $intent['uuid_intent'], 'INTENT_CREATED', $uuidOperation]
            );

            $sifDb->commit();

            return [
                'uuid_operation' => $uuidOperation,
                'uuid_operation_line' => $uuidOperationLine,
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


    private function ensureOperationLine(
        \PDO $db,
        string $uuidOperation,
        string $participantPartyKey,
        array $enrollment,
        array $price
    ): string {
        $existing = $this->one(
            $db,
            'SELECT UUID_LINE, PRODUCT_CODE, PARTICIPANT_PARTY_KEY, NET_AMOUNT, PRICE_RULE_VERSION
             FROM commercial_operation_line
             WHERE UUID_OPERATION = ? AND ORDRE = 1
             FOR UPDATE',
            [$uuidOperation]
        );
        if ($existing !== null) {
            if ((string) $existing['PRODUCT_CODE'] !== (string) $enrollment['CURS']
                || (string) $existing['PARTICIPANT_PARTY_KEY'] !== $participantPartyKey
                || $this->moneyToCents((string) $existing['NET_AMOUNT']) !== $price['net_cents']
                || (string) $existing['PRICE_RULE_VERSION'] !== $price['price_rule_version']
            ) {
                throw SifException::conflict(
                    'Commercial operation line conflicts with the trusted checkout snapshot.'
                );
            }

            return (string) $existing['UUID_LINE'];
        }

        $tax = $price['tax_snapshot'];
        $taxRegime = strtoupper(trim((string) ($tax['regime'] ?? '')));
        if ($taxRegime === '') {
            throw SifException::validation('Trusted checkout tax regime is required.');
        }
        $taxRate = $this->centsToMoney(
            $this->moneyToCents((string) ($tax['iva_pct'] ?? $tax['tax_rate'] ?? '0.00'))
        );
        $taxAmount = $this->centsToMoney(
            $this->moneyToCents((string) ($tax['iva_import'] ?? $tax['tax'] ?? '0.00'))
        );
        $exemptionReason = trim((string) ($tax['exemption_reason'] ?? ''));
        $uuidLine = $this->uuids->generate();

        $this->execute(
            $db,
            'INSERT INTO commercial_operation_line (
                UUID_LINE, UUID_OPERATION, PARENT_UUID_LINE, LINE_TYPE, ORDRE,
                PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION, PARTICIPANT_PARTY_KEY,
                DESCRIPTION, QUANTITY, UNIT_PRICE, GROSS_AMOUNT, DISCOUNT_AMOUNT,
                NET_AMOUNT, TAX_REGIME, TAX_RATE, TAX_AMOUNT,
                EXEMPTION_OR_NON_SUBJECT_REASON, PRICE_RULE_VERSION, SNAPSHOT_JSON, STATUS
            ) VALUES (?, ?, NULL, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $uuidLine,
                $uuidOperation,
                'PRODUCT',
                'CURS',
                (string) $enrollment['CURS'],
                (string) $enrollment['ANY'] . '/' . (string) $enrollment['MES'],
                $participantPartyKey,
                $price['course_title'],
                '1.00',
                $price['gross'],
                $price['gross'],
                $price['discount'],
                $price['net'],
                $taxRegime,
                $taxRate,
                $taxAmount,
                $exemptionReason === '' ? null : $exemptionReason,
                $price['price_rule_version'],
                $price['price_json'],
                'READY_FOR_PAYMENT',
            ]
        );

        return $uuidLine;
    }

    private function price(array $snapshot, string $legacyNet): array
    {
        foreach (['gross_amount', 'discount_amount', 'net_amount', 'course_title', 'price_rule_version', 'tax_snapshot'] as $required) {
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
            'course_title' => trim((string) $snapshot['course_title']),
            'price_rule_version' => trim((string) $snapshot['price_rule_version']),
            'tax_snapshot' => $snapshot['tax_snapshot'],
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

    private function execute(\PDO $db, string $sql, array $parameters): void
    {
        $statement = $db->prepare($sql);
        $statement->execute($parameters);
    }
}
