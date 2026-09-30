<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;

final class UsocLifecycleGuardService
{
    public function __construct(private UsocFinancingCaseRepository $cases)
    {
    }

    public function check(
        \PDO $db,
        int $idInsc,
        int $idpag,
        string $operation
    ): array {
        if ($idInsc <= 0 || $idpag <= 0) {
            throw SifException::validation('Invalid USOC lifecycle identity');
        }

        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['course_change', 'cancellation'], true)) {
            throw SifException::validation('Invalid USOC lifecycle operation');
        }

        $case = $this->cases->findByInscriptionAndIdpag($db, $idInsc, $idpag);
        $orphanFiscalEvidence = $case === null
            ? $this->findUsocFiscalEvidence($db, $idInsc, $idpag)
            : null;

        $allowed = $case === null && $orphanFiscalEvidence === null;
        $reason = 'NO_SIF_USOC_CASE';
        if ($case !== null) {
            $reason = 'USOC_FINANCING_CASE_REQUIRES_ORCHESTRATION';
        } elseif ($orphanFiscalEvidence !== null) {
            $reason = 'USOC_FISCAL_EVIDENCE_WITHOUT_CASE_REQUIRES_REVIEW';
        }

        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'operation' => $operation,
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'case' => $case,
            'payer_snapshot' => $case === null ? null : $this->payerSnapshot($db, $case),
            'orphan_fiscal_evidence' => $orphanFiscalEvidence,
        ];
    }

    private function payerSnapshot(\PDO $db, array $case): array
    {
        $studentUuid = trim((string) ($case['UUID_STUDENT_INVOICE'] ?? ''));
        $entityUuid = trim((string) ($case['UUID_ENTITY_INVOICE'] ?? ''));

        return [
            'student' => $this->invoiceSnapshot($db, $studentUuid, 'student'),
            'entity' => $entityUuid === ''
                ? [
                    'role' => 'entity',
                    'invoice_uuid' => null,
                    'invoice_status' => 'NOT_ISSUED',
                    'payment_status' => 'PENDING',
                    'total' => number_format((float) ($case['ENTITY_AMOUNT'] ?? 0), 2, '.', ''),
                    'charged' => '0.00',
                    'refunded' => '0.00',
                    'net_paid' => '0.00',
                ]
                : $this->invoiceSnapshot($db, $entityUuid, 'entity'),
        ];
    }

    private function invoiceSnapshot(\PDO $db, string $uuidInvoice, string $role): array
    {
        if ($uuidInvoice === '') {
            throw SifException::conflict('Missing USOC ' . $role . ' invoice');
        }

        $stmt = $db->prepare(
            'SELECT UUID_FACTURA, ESTAT_FACTURA, ESTAT_COBRAMENT, TOTAL
             FROM factura
             WHERE UUID_FACTURA = ?'
        );
        $stmt->execute([$uuidInvoice]);
        $invoice = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($invoice)) {
            throw SifException::conflict('USOC ' . $role . ' invoice not found');
        }

        $payments = $db->prepare(
            "SELECT
                COALESCE(SUM(CASE
                    WHEN pt.ESTAT = 'CONFIRMED'
                     AND pt.TIPUS_MOVIMENT IN ('CHARGE', 'COMPENSATION')
                    THEN pa.IMPORT_ASSIGNAT ELSE 0 END), 0) AS CHARGED,
                COALESCE(SUM(CASE
                    WHEN pt.ESTAT = 'CONFIRMED'
                     AND pt.TIPUS_MOVIMENT = 'REFUND'
                    THEN pa.IMPORT_ASSIGNAT ELSE 0 END), 0) AS REFUNDED
             FROM payment_allocation pa
             INNER JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?"
        );
        $payments->execute([$uuidInvoice]);
        $money = $payments->fetch(\PDO::FETCH_ASSOC) ?: [];

        $charged = number_format((float) ($money['CHARGED'] ?? 0), 2, '.', '');
        $refunded = number_format((float) ($money['REFUNDED'] ?? 0), 2, '.', '');
        $netPaid = number_format(max(0, (float) $charged - (float) $refunded), 2, '.', '');

        return [
            'role' => $role,
            'invoice_uuid' => (string) $invoice['UUID_FACTURA'],
            'invoice_status' => strtoupper(trim((string) $invoice['ESTAT_FACTURA'])),
            'payment_status' => strtoupper(trim((string) $invoice['ESTAT_COBRAMENT'])),
            'total' => number_format((float) $invoice['TOTAL'], 2, '.', ''),
            'charged' => $charged,
            'refunded' => $refunded,
            'net_paid' => $netPaid,
        ];
    }

    private function findUsocFiscalEvidence(\PDO $db, int $idInsc, int $idpag): ?array
    {
        $stmt = $db->prepare(
            "SELECT f.UUID_FACTURA, f.IDEMPOTENCY_KEY, f.SOURCE_CHANNEL,
                    r.RELATION_TYPE, r.VISIBLE_ALUMNE
             FROM factura AS f
             INNER JOIN fact_rels AS r ON r.UUID_FACTURA = f.UUID_FACTURA
             WHERE r.SOURCE_TYPE = 'INSCRIPCIO'
               AND r.SOURCE_ID = ?
               AND r.IDPAG = ?
               AND (
                    f.IDEMPOTENCY_KEY LIKE 'REDSYS|USOC_ALUMNE|IDPAG:%'
                    OR f.IDEMPOTENCY_KEY LIKE 'INTRANET|USOC_ENTITAT|ID_INSC:%'
                    OR r.RELATION_TYPE = 'USOC_ENTITY'
               )
             ORDER BY f.CREATED_AT ASC
             LIMIT 1"
        );
        $stmt->execute([$idInsc, $idpag]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
