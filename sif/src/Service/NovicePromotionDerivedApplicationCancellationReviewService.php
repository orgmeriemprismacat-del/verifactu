<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionDestinationAdjustmentPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111: stage cancellation of a course whose CURRENT promotional exposure
 * is an APPLIED novice_promotion_derived_application.
 *
 * The new proposed right is a CHILD of the balance that funded the source
 * application. This method writes PENDING_FISCAL_REVIEW only; it does not
 * reactivate the parent balance, issue money, emit invoices or make the child
 * spendable. Authentication/authorization belong to the internal caller.
 */
final class NovicePromotionDerivedApplicationCancellationReviewService
{
    public function __construct(
        private UuidGenerator $uuids = new UuidGenerator(),
        private NovicePromotionDestinationAdjustmentPolicy $adjustments
            = new NovicePromotionDestinationAdjustmentPolicy(),
        private NovicePromotionRectificationEvidencePolicy $fiscal
            = new NovicePromotionRectificationEvidencePolicy()
    ) {
    }

    public function stageDerivedApplicationReview(
        \PDO $db,
        string $uuidDerivedApplication,
        string $uuidRectificative,
        string $proposedEligiblePromotionalAmount,
        string $proposedEligibleCashAmount,
        string $authorizedActorId,
        string $policyEvidenceRef,
        string $idempotencyKey,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException(
                'Derived-application cancellation review requires its own transaction.'
            );
        }
        foreach ([
            $uuidDerivedApplication,
            $uuidRectificative,
            $authorizedActorId,
            $policyEvidenceRef,
            $idempotencyKey,
        ] as $value) {
            if (trim($value) === '') {
                throw SifException::validation(
                    'Missing derived-application cancellation review reference.'
                );
            }
        }
        if (strlen($authorizedActorId) > 100
            || strlen($policyEvidenceRef) > 140
            || strlen($idempotencyKey) > 140
        ) {
            throw SifException::validation(
                'Derived-application cancellation review reference is too long.'
            );
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT, UUID_DERIVED_BALANCE
                 FROM novice_promotion_derived_application
                 WHERE UUID_DERIVED_APPLICATION = ?',
                [$uuidDerivedApplication]
            );
            if ($lookup === null) {
                throw SifException::conflict(
                    'Derived promotional application was not found.'
                );
            }

            $root = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.STATUS, e.HOLDER_PARTY_KEY,
                        g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g
                   ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v
                   ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['ROOT_UUID_ENTITLEMENT']]
            );
            if ($root === null
                || $root['STATUS'] !== 'ACTIVE'
                || $root['VALIDATION_STATUS'] !== 'VALIDATED'
            ) {
                throw SifException::conflict(
                    'Novice root is not active for derived-course cancellation.'
                );
            }

            $source = $this->one(
                $db,
                'SELECT a.*, b.HOLDER_PARTY_KEY,
                        b.STATUS AS BALANCE_STATUS,
                        b.UUID_DERIVED_BALANCE AS PARENT_BALANCE_UUID
                 FROM novice_promotion_derived_application a
                 JOIN novice_promotion_derived_balance b
                   ON b.UUID_DERIVED_BALANCE = a.UUID_DERIVED_BALANCE
                 WHERE a.UUID_DERIVED_APPLICATION = ? FOR UPDATE',
                [$uuidDerivedApplication]
            );
            if ($source === null
                || $source['STATUS'] !== 'APPLIED'
                || (string) $source['ROOT_UUID_ENTITLEMENT']
                    !== (string) $root['UUID_ENTITLEMENT']
                || (string) $source['HOLDER_PARTY_KEY']
                    !== (string) $root['HOLDER_PARTY_KEY']
                || !in_array(
                    (string) $source['BALANCE_STATUS'],
                    ['ACTIVE', 'EXPIRED'],
                    true
                )
                || trim((string) ($source['UUID_DESTINATION_FACTURA'] ?? '')) === ''
            ) {
                throw SifException::conflict(
                    'Derived application is not the current applied promotional exposure.'
                );
            }

            if ($this->one(
                $db,
                "SELECT UUID_TRANSFER
                 FROM novice_promotion_application_transfer
                 WHERE UUID_DERIVED_APPLICATION = ?
                   AND STATUS <> 'CANCELLED'
                 FOR UPDATE",
                [$uuidDerivedApplication]
            ) !== null) {
                throw SifException::conflict(
                    'Derived application already has an active transfer path.'
                );
            }

            $previous = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_balance
                 WHERE SOURCE_UUID_DERIVED_APPLICATION = ? FOR UPDATE',
                [$uuidDerivedApplication]
            );

            $destination = $this->one(
                $db,
                'SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE,
                        CURRENCY, STATUS, UUID_FACTURA, NET_AMOUNT
                 FROM commercial_operation
                 WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $source['UUID_DESTINATION_OPERATION']]
            );
            if ($destination === null
                || $destination['SOURCE_TYPE'] !== 'CURS'
                || $destination['PRODUCT_TYPE'] !== 'CURS'
                || $destination['CURRENCY'] !== 'EUR'
                || (string) $destination['UUID_FACTURA']
                    !== (string) $source['UUID_DESTINATION_FACTURA']
                || !in_array(
                    (string) $destination['STATUS'],
                    ['PAID', 'COMPLETED', 'CANCELLED'],
                    true
                )
            ) {
                throw SifException::conflict(
                    'Derived destination no longer matches its applied invoice.'
                );
            }

            $people = $this->many(
                $db,
                "SELECT PARTY_KEY
                 FROM commercial_operation_party
                 WHERE UUID_OPERATION = ?
                   AND PARTY_ROLE = 'PARTICIPANT'
                 FOR UPDATE",
                [(string) $destination['UUID_OPERATION']]
            );
            if (count($people) !== 1
                || (string) $people[0]['PARTY_KEY']
                    !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict(
                    'Derived cancellation cannot change the promotion holder.'
                );
            }

            $sourceInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA,
                        ESTAT_COBRAMENT, TOTAL, DATA_EMISSIO
                 FROM factura
                 WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $source['UUID_DESTINATION_FACTURA']]
            );
            $rectificative = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA,
                        ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura
                 WHERE UUID_FACTURA = ? FOR UPDATE',
                [$uuidRectificative]
            );
            $link = $this->one(
                $db,
                'SELECT UUID_FACTURA_RECTIFICADA,
                        UUID_FACTURA_RECTIFICATIVA,
                        MOTIU, MODE_RECTIFICACIO
                 FROM factura_rectificacio
                 WHERE UUID_FACTURA_RECTIFICATIVA = ?
                   AND UUID_FACTURA_RECTIFICADA = ?
                 FOR UPDATE',
                [
                    $uuidRectificative,
                    (string) $source['UUID_DESTINATION_FACTURA'],
                ]
            );
            try {
                $this->fiscal->assertCancellationReference(
                    $sourceInvoice ?? [],
                    $rectificative ?? [],
                    $link ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict(
                    'Derived destination rectificative does not match its issued invoice.'
                );
            }

            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId < 1
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels
                     WHERE UUID_FACTURA = ?
                       AND SOURCE_TYPE = 'INSCRIPCIO'
                       AND SOURCE_ID = ?
                     LIMIT 1",
                    [
                        (string) $source['UUID_DESTINATION_FACTURA'],
                        (int) $sourceId,
                    ]
                ) === null
            ) {
                throw SifException::conflict(
                    'Derived destination invoice is not linked to its enrollment.'
                );
            }

            $promotion = $this->cents((string) $source['AMOUNT']);
            $ordinary = $this->cents(
                (string) $source['DESTINATION_ORDINARY_NET_AMOUNT']
            );
            $finalNet = $this->cents((string) $destination['NET_AMOUNT']);
            $invoiceTotal = $this->cents(
                (string) ($sourceInvoice['TOTAL'] ?? '')
            );
            $settlement = $this->one(
                $db,
                "SELECT COALESCE(SUM(CASE
                    WHEN pt.TIPUS_MOVIMENT = 'CHARGE'
                        THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND'
                        THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0 END), 0) AS NET_CASH
                 FROM payment_allocation pa
                 JOIN payment_transaction pt
                   ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                 WHERE pa.UUID_FACTURA = ?
                   AND pt.ESTAT = 'CONFIRMED'",
                [(string) $source['UUID_DESTINATION_FACTURA']]
            );
            $cash = $this->cents((string) ($settlement['NET_CASH'] ?? '0.00'));

            if ($promotion <= 0
                || $finalNet < 0
                || $ordinary !== $promotion + $finalNet
                || $invoiceTotal !== $finalNet
                || $cash !== $finalNet
            ) {
                throw SifException::conflict(
                    'Derived promotional use and external cash no longer reconcile.'
                );
            }

            try {
                $plan = $this->adjustments->planCancellation(
                    (string) $source['AMOUNT'],
                    $this->money($cash),
                    $proposedEligiblePromotionalAmount,
                    $proposedEligibleCashAmount,
                    $now
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict(
                    'Derived cancellation proposal exceeds documented provenance.'
                );
            }
            if (!$plan['creates_promotional_derived_right']) {
                throw SifException::conflict(
                    'Cash-only cancellation belongs to the cash refund/credit circuit.'
                );
            }

            $snapshot = [
                'source_kind' => 'DERIVED_APPLICATION',
                'source_derived_application' => $uuidDerivedApplication,
                'parent_derived_balance'
                    => (string) $source['PARENT_BALANCE_UUID'],
                'source_operation'
                    => (string) $source['UUID_DESTINATION_OPERATION'],
                'source_invoice'
                    => (string) $source['UUID_DESTINATION_FACTURA'],
                'rectificative_invoice' => $uuidRectificative,
                'proposed_promotional_amount'
                    => (string) $plan['promotional_derived_amount'],
                'proposed_cash_amount'
                    => (string) $plan['cash_refund_or_credit_eligible'],
                'original_cash_reconciled' => $this->money($cash),
                'policy_evidence_ref' => $policyEvidenceRef,
                'recorded_by' => $authorizedActorId,
                'recorded_at' => $timestamp,
                'state' => 'PENDING_FISCAL_REVIEW',
            ];

            if ($previous !== null) {
                $saved = json_decode(
                    (string) $previous['POLICY_SNAPSHOT_JSON'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                if ((string) $previous['IDEMPOTENCY_KEY'] !== $idempotencyKey
                    || (string) $previous['PARENT_UUID_DERIVED_BALANCE']
                        !== (string) $source['PARENT_BALANCE_UUID']
                    || (string) $previous['UUID_RECTIFICATIVE_FACTURA']
                        !== $uuidRectificative
                    || (string) $previous['PROMOTIONAL_ORIGIN_AMOUNT']
                        !== (string) $plan['promotional_derived_amount']
                    || !is_array($saved)
                    || ($saved['proposed_cash_amount'] ?? null)
                        !== (string) $plan['cash_refund_or_credit_eligible']
                    || ($saved['policy_evidence_ref'] ?? null)
                        !== $policyEvidenceRef
                    || ($saved['recorded_by'] ?? null)
                        !== $authorizedActorId
                ) {
                    throw SifException::conflict(
                        'Derived cancellation review already exists with different evidence.'
                    );
                }
                $db->commit();
                return [
                    'uuid_derived_balance'
                        => (string) $previous['UUID_DERIVED_BALANCE'],
                    'parent_uuid_derived_balance'
                        => (string) $previous['PARENT_UUID_DERIVED_BALANCE'],
                    'status' => (string) $previous['STATUS'],
                    'proposed_promotional_amount'
                        => (string) $previous['PROMOTIONAL_ORIGIN_AMOUNT'],
                    'idempotency_reused' => true,
                ];
            }

            $uuidDerived = $this->uuids->generate();
            $stmt = $db->prepare(
                'INSERT INTO novice_promotion_derived_balance
                 (UUID_DERIVED_BALANCE, ROOT_UUID_ENTITLEMENT,
                  PARENT_UUID_DERIVED_BALANCE, SOURCE_UUID_APPLICATION,
                  SOURCE_UUID_DERIVED_APPLICATION, SOURCE_UUID_TRANSFER,
                  UUID_DESTINATION_OPERATION, UUID_RECTIFICATIVE_FACTURA,
                  HOLDER_PARTY_KEY, PROMOTIONAL_ORIGIN_AMOUNT,
                  AVAILABLE_PROMOTIONAL_AMOUNT, STATUS,
                  ISSUED_AT, EXPIRES_AT,
                  POLICY_SNAPSHOT_JSON, IDEMPOTENCY_KEY)
                 VALUES (?, ?, ?, NULL, ?, NULL, ?, ?, ?, ?, 0.00,
                         ?, NULL, NULL, ?, ?)'
            );
            $stmt->execute([
                $uuidDerived,
                (string) $root['UUID_ENTITLEMENT'],
                (string) $source['PARENT_BALANCE_UUID'],
                $uuidDerivedApplication,
                (string) $source['UUID_DESTINATION_OPERATION'],
                $uuidRectificative,
                (string) $root['HOLDER_PARTY_KEY'],
                (string) $plan['promotional_derived_amount'],
                'PENDING_FISCAL_REVIEW',
                json_encode(
                    $snapshot,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
                ),
                $idempotencyKey,
            ]);

            $db->commit();
            return [
                'uuid_derived_balance' => $uuidDerived,
                'parent_uuid_derived_balance'
                    => (string) $source['PARENT_BALANCE_UUID'],
                'source_uuid_derived_application' => $uuidDerivedApplication,
                'status' => 'PENDING_FISCAL_REVIEW',
                'proposed_promotional_amount'
                    => (string) $plan['promotional_derived_amount'],
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function cents(string $amount): int
    {
        if (!preg_match(
            '/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D',
            trim($amount),
            $match
        )) {
            throw SifException::validation(
                'Invalid derived cancellation amount.'
            );
        }
        $cents = (int) $match[2] * 100
            + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation(
                'Negative derived cancellation cash is invalid.'
            );
        }
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function many(\PDO $db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
