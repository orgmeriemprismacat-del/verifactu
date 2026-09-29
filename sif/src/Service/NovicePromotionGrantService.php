<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111: idempotent, one-per-person promotional grant for a fully paid JASOM.
 *
 * This INTERNAL service is invoked only after the SIF has reconciled the
 * original invoice and its payments. No amount, approval flag or holder ID is
 * accepted from a browser, a Redsys redirect or the caller.
 *
 * It grants the right; it deliberately does NOT generate a redeemable token,
 * send email, record bank payments or apply a credit compensation. Those are
 * separate idempotent steps and cannot be inferred from a successful grant.
 */
final class NovicePromotionGrantService
{
    public const RULE_VERSION = 'NOVICE_JASOM_V1';
    public const VALIDATION_TYPE = 'NOVICE_TEACHER';

    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function issueForOperation(\PDO $db, string $uuidOperation, ?\DateTimeImmutable $now = null): array
    {
        if ($db->inTransaction()) {
            throw new \LogicException('Novice grant requires an independent transaction after payment reconciliation.');
        }

        if (trim($uuidOperation) === '') {
            throw SifException::validation('Origin operation is required.');
        }

        $holder = null;
        $db->beginTransaction();

        try {
            $operation = $this->one(
                $db,
                'SELECT * FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$uuidOperation]
            );

            if ($operation === null) {
                throw SifException::conflict('Origin operation was not found.');
            }

            // Read-only replay resolution comes FIRST: a delayed callback must
            // not re-grant a right even when the original operation has since
            // been refunded/cancelled or its academic status was archived.
            $alreadyIssued = $this->one(
                $db,
                'SELECT g.UUID_ENTITLEMENT, g.ORIGINAL_CASH_AMOUNT, e.EXPIRES_AT, e.STATUS
                 FROM novice_promotion_grant g
                 JOIN commercial_entitlement e ON e.UUID_ENTITLEMENT = g.UUID_ENTITLEMENT
                 WHERE g.ORIGIN_UUID_OPERATION = ? FOR UPDATE',
                [$uuidOperation]
            );
            if ($alreadyIssued !== null) {
                $db->commit();

                return [
                    'uuid_entitlement' => (string) $alreadyIssued['UUID_ENTITLEMENT'],
                    'original_amount' => (string) $alreadyIssued['ORIGINAL_CASH_AMOUNT'],
                    'expires_at' => (string) $alreadyIssued['EXPIRES_AT'],
                    'status' => (string) $alreadyIssued['STATUS'],
                    'idempotency_reused' => true,
                ];
            }

            if (strtoupper((string) $operation['SOURCE_TYPE']) !== 'CURS'
                || strtoupper((string) $operation['PRODUCT_CODE']) !== 'JASOM'
                || strtoupper((string) $operation['CURRENCY']) !== 'EUR'
                || !in_array((string) $operation['STATUS'], ['PAID', 'INVOICED', 'COMPLETED'], true)
            ) {
                throw SifException::conflict('Origin is not a reconciled JASOM course operation.');
            }

            $validations = $this->many(
                $db,
                'SELECT * FROM discount_validation
                 WHERE UUID_OPERATION = ? AND DISCOUNT_TYPE = ? FOR UPDATE',
                [$uuidOperation, self::VALIDATION_TYPE]
            );

            if (count($validations) !== 1) {
                throw SifException::conflict('A single novice validation is required.');
            }

            $validation = $validations[0];
            if ($validation['STATUS'] !== 'VALIDATED'
                || $validation['VALIDATED_AT'] === null
                || trim((string) ($validation['VALIDATED_BY'] ?? '')) === ''
            ) {
                throw SifException::conflict('Novice qualification has not been approved by the secretariat.');
            }

            $holder = trim((string) $validation['SUBJECT_PARTY_KEY']);
            if ($holder === '') {
                throw SifException::conflict('Verified novice holder is missing.');
            }

            $participants = $this->many(
                $db,
                'SELECT PARTY_KEY FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = ? FOR UPDATE',
                [$uuidOperation, 'PARTICIPANT']
            );

            if (count($participants) !== 1 || (string) $participants[0]['PARTY_KEY'] !== $holder) {
                throw SifException::conflict('Novice holder must be the single enrolled participant.');
            }

            // Uniqueness is also enforced by uq_novice_grant_person in MySQL.
            // A repeat of the SAME operation returns the original right;
            // a second JASOM never grants a second right to the same person.
            $existing = $this->one(
                $db,
                'SELECT g.UUID_ENTITLEMENT, g.ORIGIN_UUID_OPERATION, g.ORIGINAL_CASH_AMOUNT,
                        e.EXPIRES_AT, e.STATUS
                 FROM novice_promotion_grant g
                 JOIN commercial_entitlement e ON e.UUID_ENTITLEMENT = g.UUID_ENTITLEMENT
                 WHERE g.HOLDER_PARTY_KEY = ? FOR UPDATE',
                [$holder]
            );

            if ($existing !== null) {
                if ((string) $existing['ORIGIN_UUID_OPERATION'] !== $uuidOperation) {
                    throw SifException::conflict('Novice promotion already granted for this person.');
                }

                $db->commit();

                return [
                    'uuid_entitlement' => (string) $existing['UUID_ENTITLEMENT'],
                    'original_amount' => (string) $existing['ORIGINAL_CASH_AMOUNT'],
                    'expires_at' => (string) $existing['EXPIRES_AT'],
                    'status' => (string) $existing['STATUS'],
                    'idempotency_reused' => true,
                ];
            }

            if (trim((string) ($validation['FUTURE_ENTITLEMENT_REF'] ?? '')) !== '') {
                throw SifException::conflict('Novice validation refers to a pre-existing or unimported entitlement.');
            }

            $uuidInvoice = trim((string) ($operation['UUID_FACTURA'] ?? ''));
            if ($uuidInvoice === '') {
                throw SifException::conflict('JASOM origin has no linked invoice.');
            }

            $sourceId = trim((string) ($operation['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId) || (int) $sourceId <= 0) {
                throw SifException::conflict('JASOM origin enrollment reference is not available.');
            }

            // InvoiceService may produce one issued JASOM invoice with N
            // payments, OR separate F1/F2 invoices for individual installment
            // orders. Aggregate each distinct origin invoice once by its
            // existing fact_rels enrollment reference, excluding rectifications.
            $invoices = $this->many(
                $db,
                "SELECT f.UUID_FACTURA, f.TOTAL, f.ESTAT_COBRAMENT, f.ESTAT_FACTURA
                 FROM factura f
                 WHERE f.TIPUS_FACTURA IN ('F1', 'F2')
                   AND EXISTS (
                       SELECT 1 FROM fact_rels rel
                       WHERE rel.UUID_FACTURA = f.UUID_FACTURA
                         AND rel.SOURCE_TYPE = 'INSCRIPCIO' AND rel.SOURCE_ID = ?
                   )
                 ORDER BY f.DATA_EMISSIO, f.UUID_FACTURA FOR UPDATE",
                [(int) $sourceId]
            );

            $operationNet = $this->money((string) $operation['NET_AMOUNT']);
            $invoicedTotal = 0;
            $netCash = 0;
            $originInvoiceFound = false;
            $invoiceIds = [];

            foreach ($invoices as $invoice) {
                $uuid = (string) $invoice['UUID_FACTURA'];
                $invoiceIds[] = $uuid;
                $originInvoiceFound = $originInvoiceFound || $uuid === $uuidInvoice;

                if ($invoice['ESTAT_COBRAMENT'] !== 'PAID'
                    || $invoice['ESTAT_FACTURA'] !== 'ISSUED'
                ) {
                    throw SifException::conflict('Every JASOM origin invoice must be fully paid and issued.');
                }

                $invoiceTotal = $this->money((string) $invoice['TOTAL']);
                if ($invoiceTotal <= 0) {
                    throw SifException::conflict('JASOM origin invoice amount must be positive.');
                }

                $cash = $this->one(
                    $db,
                    "SELECT COALESCE(SUM(CASE
                        WHEN pt.TIPUS_MOVIMENT = 'CHARGE' THEN pa.IMPORT_ASSIGNAT
                        WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN -pa.IMPORT_ASSIGNAT
                        ELSE 0 END), 0) AS NET_CASH
                     FROM payment_allocation pa
                     JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                     WHERE pa.UUID_FACTURA = ? AND pt.ESTAT = 'CONFIRMED'",
                    [$uuid]
                );
                $invoiceNetCash = $this->money((string) ($cash['NET_CASH'] ?? '0.00'));
                if ($invoiceNetCash !== $invoiceTotal) {
                    throw SifException::conflict('JASOM invoice has mixed, missing or overpaid cash allocations.');
                }

                $invoicedTotal += $invoiceTotal;
                $netCash += $invoiceNetCash;
            }

            if (!$originInvoiceFound || $netCash <= 0 || $invoicedTotal !== $operationNet
                || $netCash !== $operationNet
            ) {
                throw SifException::conflict('JASOM enrollment is not fully reconciled across all origin invoices.');
            }

            $now ??= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));
            $localNow = $now->setTimezone(new \DateTimeZone('Europe/Madrid'));
            $utc = new \DateTimeZone('UTC');
            $issuedAt = $localNow->setTimezone($utc)->format('Y-m-d H:i:s');
            $expiresAt = $localNow->modify('+1 year')->setTimezone($utc)->format('Y-m-d H:i:s');
            $amount = $this->formatMoney($netCash);
            $uuidEntitlement = $this->uuids->generate();
            $snapshot = json_encode([
                'rule' => self::RULE_VERSION,
                'origin' => 'JASOM',
                'promotional_value_not_prepaid_cash' => true,
                'discounts_applied_before_promotion' => true,
                'original_expiry_preserved_for_remainder' => true,
                'validation_ref' => (string) $validation['UUID_VALIDATION'],
                'origin_invoice_ref' => $uuidInvoice,
                'origin_invoice_refs' => $invoiceIds,
            ], JSON_UNESCAPED_SLASHES);
            if ($snapshot === false) {
                throw new \RuntimeException('Could not encode novice promotion rule snapshot.');
            }

            $this->execute(
                $db,
                'INSERT INTO commercial_entitlement
                 (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
                  ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON, FACE_VALUE,
                  CURRENCY, STATUS, ISSUED_AT, EXPIRES_AT, IDEMPOTENCY_KEY)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $uuidEntitlement,
                    'FUTURE_DISCOUNT',
                    $holder,
                    $uuidOperation,
                    self::RULE_VERSION,
                    $snapshot,
                    $amount,
                    'EUR',
                    'ISSUED',
                    $issuedAt,
                    $expiresAt,
                    'NOVICE|V1|' . hash('sha256', $holder),
                ]
            );

            $this->execute(
                $db,
                'INSERT INTO novice_promotion_grant
                 (UUID_ENTITLEMENT, HOLDER_PARTY_KEY, ORIGIN_UUID_OPERATION,
                  UUID_VALIDATION, UUID_FACTURA, ORIGINAL_CASH_AMOUNT, AVAILABLE_AMOUNT)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $uuidEntitlement,
                    $holder,
                    $uuidOperation,
                    (string) $validation['UUID_VALIDATION'],
                    $uuidInvoice,
                    $amount,
                    $amount,
                ]
            );

            $this->execute(
                $db,
                'INSERT INTO commercial_entitlement_event
                 (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT, FROM_STATUS, TO_STATUS,
                  UUID_OPERATION, ACTOR_TYPE, ACTOR_ID, CORRELATION_ID, CAUSATION_ID,
                  REASON_CODE, CHANGESET_JSON, OCCURRED_AT)
                 VALUES (?, ?, ?, ?, NULL, ?, ?, ?, NULL, ?, ?, ?, ?, ?)',
                [
                    $this->uuids->generate(),
                    $uuidEntitlement,
                    'ISSUE',
                    'SUCCESS',
                    'ISSUED',
                    $uuidOperation,
                    'SYSTEM',
                    $uuidEntitlement,
                    (string) $validation['UUID_VALIDATION'],
                    'NOVICE_JASOM_FULLY_PAID',
                    json_encode(['amount' => $amount, 'currency' => 'EUR'], JSON_THROW_ON_ERROR),
                    $issuedAt,
                ]
            );

            $validationLink = $db->prepare(
                'UPDATE discount_validation SET FUTURE_ENTITLEMENT_REF = ?
                 WHERE UUID_VALIDATION = ? AND FUTURE_ENTITLEMENT_REF IS NULL'
            );
            $validationLink->execute([$uuidEntitlement, (string) $validation['UUID_VALIDATION']]);
            if ($validationLink->rowCount() !== 1) {
                throw SifException::conflict('Novice validation was already linked to another entitlement.');
            }

            $db->commit();

            return [
                'uuid_entitlement' => $uuidEntitlement,
                'original_amount' => $amount,
                'expires_at' => $expiresAt,
                'status' => 'ISSUED',
                'idempotency_reused' => false,
            ];
        } catch (\PDOException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            // Concurrent workers may race after both see no grant. The UNIQUE
            // index is authoritative; recover an equivalent winner after commit.
            if ((string) $exception->getCode() === '23000' && $holder !== null) {
                $winner = $this->one(
                    $db,
                    'SELECT g.UUID_ENTITLEMENT, g.ORIGIN_UUID_OPERATION, g.ORIGINAL_CASH_AMOUNT,
                            e.EXPIRES_AT, e.STATUS
                     FROM novice_promotion_grant g
                     JOIN commercial_entitlement e ON e.UUID_ENTITLEMENT = g.UUID_ENTITLEMENT
                     WHERE g.HOLDER_PARTY_KEY = ?',
                    [$holder]
                );
                if ($winner !== null) {
                    if ((string) $winner['ORIGIN_UUID_OPERATION'] !== $uuidOperation) {
                        throw SifException::conflict('Novice promotion already granted for this person.');
                    }

                    return [
                        'uuid_entitlement' => (string) $winner['UUID_ENTITLEMENT'],
                        'original_amount' => (string) $winner['ORIGINAL_CASH_AMOUNT'],
                        'expires_at' => (string) $winner['EXPIRES_AT'],
                        'status' => (string) $winner['STATUS'],
                        'idempotency_reused' => true,
                    ];
                }
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    private function money(string $value): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($value), $matches)) {
            throw SifException::validation('Invalid monetary amount.');
        }

        $cents = ((int) $matches[2] * 100) + (int) str_pad($matches[3] ?? '', 2, '0');
        return $matches[1] === '-' ? -$cents : $cents;
    }

    private function formatMoney(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation('Negative novice promotion amount.');
        }

        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $rows = $this->many($db, $sql, $params);
        return $rows[0] ?? null;
    }

    private function many(\PDO $db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function execute(\PDO $db, string $sql, array $params): void
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    }
}
