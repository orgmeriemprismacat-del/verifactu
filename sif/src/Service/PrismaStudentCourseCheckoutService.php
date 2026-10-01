<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialOperationRepository;
use Prisma\Sif\Repository\DiscountValidationRepository;
use Prisma\Sif\Repository\LegacyPrismaStudentHistoryRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;

/**
 * Trusted server-side UC-020 bridge.
 *
 * It stages the authoritative Alumne PrisMa commercial decision and creates
 * the CURS Redsys intent from the same frozen snapshot. Browser supplied
 * prices/discount types are never accepted as authoritative input.
 *
 * Commercial operation, participant and discount validation are delegated to
 * the shared commercial runtime so UC-020 does not maintain a parallel SQL
 * implementation.
 */
final class PrismaStudentCourseCheckoutService
{
    public function __construct(
        private LegacyPrismaStudentHistoryRepository $history,
        private PrismaStudentDiscountPolicy $policy,
        private CommercialOfferService $offers,
        private CommercialOperationRepository $operations,
        private DiscountValidationRepository $discounts,
        private RedsysPaymentIntentRepository $intentRepository,
        private RedsysPaymentIntentService $intents,
        private TransactionRunner $transactions
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
            throw new \LogicException('Prisma student checkout must start outside an active SIF transaction.');
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
            throw SifException::conflict(
                'Enrollment is not eligible for Alumne PrisMa under the selected rule version.'
            );
        }

        $price = $this->price($trustedPriceSnapshot, (string) $enrollment['A_PAGAR']);
        $operationKey = 'UC020|ALUMNE_PRISMA|INSCRIPCIO:' . $enrollmentId;
        $validationKey = $operationKey . '|VALIDATION|' . (string) $decision['rule_version'];
        $edition = (string) $enrollment['ANY'] . '/' . (string) $enrollment['MES'];
        $createdBy = trim((string) ($intentRequest['created_by'] ?? 'uc-020-checkout'));
        $evaluationAt = $this->evaluationAt($trustedPriceSnapshot, $enrollment);
        $requestedAt = $evaluationAt;
        $validatedAt = $evaluationAt;
        $existingValidation = $this->discounts->findByIdempotencyKey($sifDb, $validationKey);
        if ($existingValidation !== null) {
            $existingRequestedAt = trim((string) ($existingValidation['REQUESTED_AT'] ?? ''));
            $existingValidatedAt = trim((string) ($existingValidation['VALIDATED_AT'] ?? ''));
            if ($existingRequestedAt !== '') {
                $requestedAt = $existingRequestedAt;
            }
            if ($existingValidatedAt !== '') {
                $validatedAt = $existingValidatedAt;
            }
        }

        $offer = $this->offers->createOrReuse([
            'idempotency_key' => $operationKey,
            'operation_type' => 'ENROLLMENT',
            'source_channel' => $sourceChannel,
            'source_type' => 'CURS',
            'source_id' => (string) $enrollmentId,
            'product_type' => 'CURS',
            'product_code' => (string) $enrollment['CURS'],
            'product_edition' => $edition,
            'classification' => 'READY_FOR_PAYMENT',
            'classification_reason' => 'ALUMNE_PRISMA_VALIDATED',
            'status' => 'READY_FOR_PAYMENT',
            'acceptable_existing_statuses' => ['READY_FOR_PAYMENT', 'INTENT_CREATED'],
            'currency' => 'EUR',
            'gross_amount' => $price['gross'],
            'discount_amount' => $price['discount'],
            'net_amount' => $price['net'],
            'price_snapshot' => $trustedPriceSnapshot,
            'tax_snapshot' => $trustedPriceSnapshot['tax_snapshot'],
            'created_by' => $createdBy,
            'correlation_id' => $operationKey,
            'actor_type' => 'SYSTEM',
            'actor_id' => $createdBy,
            'actor_role' => 'PAYMENT_CHANNEL',
            'occurred_at' => $evaluationAt,
            'parties' => [[
                'party_key' => $canonicalPartyKey,
                'party_role' => 'PARTICIPANT',
                'nif_cif' => (string) $enrollment['DNI'],
                'nom_rao' => trim(
                    (string) $enrollment['NOM'] . ' ' . (string) ($enrollment['COGNOMS'] ?? '')
                ),
                'email' => $enrollment['CORREU'] ?? null,
                'product_code' => (string) $enrollment['CURS'],
                'product_edition' => $edition,
                'line_amount' => $price['net'],
                'snapshot' => [
                    'source' => 'legacy_inscription',
                    'source_id' => $enrollmentId,
                ],
            ]],
            'discount' => [
                'idempotency_key' => $validationKey,
                'discount_type' => 'ALUMNE_PRISMA',
                'subject_party_key' => $canonicalPartyKey,
                'status' => 'VALIDATED',
                'rule_version' => (string) $decision['rule_version'],
                'rule_snapshot' => [
                    'decision' => 'ELIGIBLE',
                    'reason' => $decision['reason'] ?? null,
                    'evidence' => $decision['evidence'] ?? null,
                    'rule_version' => $decision['rule_version'],
                ],
                'requested_at' => $requestedAt,
                'validated_at' => $validatedAt,
                'validated_by' => 'PrismaStudentDiscountPolicy',
                'result_discount_amount' => $price['discount'],
            ],
        ]);

        $uuidOperation = (string) $offer['uuid_operation'];
        $uuidValidation = trim((string) ($offer['uuid_validation'] ?? ''));
        if ($uuidValidation === '') {
            throw new \RuntimeException('Alumne PrisMa commercial offer has no discount validation.');
        }

        $operation = $this->operations->findByUuid($sifDb, $uuidOperation);
        if ($operation === null) {
            throw new \RuntimeException('Alumne PrisMa commercial operation disappeared after staging.');
        }

        $requestedOrder = trim((string) ($intentRequest['ds_order'] ?? ''));
        $linkedIntentUuid = trim((string) ($operation['UUID_INTENT'] ?? ''));
        if ($linkedIntentUuid !== '') {
            $linkedIntent = $this->intentRepository->findByUuid($sifDb, $linkedIntentUuid);
            if ($linkedIntent === null
                || $requestedOrder === ''
                || $requestedOrder !== (string) $linkedIntent['DS_ORDER']
            ) {
                throw SifException::conflict(
                    'Commercial operation is already linked to another Redsys intent.'
                );
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

        $this->transactions->run(function (\PDO $db) use ($uuidOperation, $intent): void {
            $this->operations->linkIntent(
                $db,
                $uuidOperation,
                (string) $intent['uuid_intent']
            );
            $this->operations->transitionStatus(
                $db,
                $uuidOperation,
                'READY_FOR_PAYMENT',
                'INTENT_CREATED'
            );
        });

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
    }

    private function enrollment(\PDO $legacyDb, int $enrollmentId): array
    {
        $row = $this->one(
            $legacyDb,
            'SELECT ID, IDPAG, ANY, MES, CURS, DATA_INSC, NOM, COGNOMS, DNI, CORREU, A_PAGAR
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

        return [
            'gross' => $this->centsToMoney($gross),
            'discount' => $this->centsToMoney($discount),
            'net' => $this->centsToMoney($net),
        ];
    }

    private function evaluationAt(array $trustedPriceSnapshot, array $enrollment): string
    {
        $priceSource = $trustedPriceSnapshot['price_source'] ?? null;
        if (is_array($priceSource)) {
            $evaluatedAt = trim((string) ($priceSource['evaluated_at'] ?? ''));
            if ($evaluatedAt !== '' && strtotime($evaluatedAt) !== false) {
                return $evaluatedAt;
            }
        }

        $dataInsc = trim((string) ($enrollment['DATA_INSC'] ?? ''));
        if ($dataInsc === '' || strtotime($dataInsc) === false) {
            throw SifException::validation('Enrollment decision timestamp is required.');
        }

        return $dataInsc;
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

    private function one(\PDO $db, string $sql, array $parameters): ?array
    {
        $statement = $db->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
