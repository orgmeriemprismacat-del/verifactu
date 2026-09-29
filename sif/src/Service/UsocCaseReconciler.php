<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;

final class UsocCaseReconciler
{
    public function __construct(private UsocFinancingCaseRepository $cases)
    {
    }

    public function reconcile(\PDO $db, int $inscriptionId, int $idpag): array
    {
        if ($inscriptionId <= 0) {
            throw SifException::validation('Invalid USOC inscription ID');
        }
        if ($idpag <= 0) {
            throw SifException::validation('Invalid USOC IDPAG');
        }

        $case = $this->cases->findByInscriptionAndIdpag($db, $inscriptionId, $idpag, true);
        if ($case === null) {
            throw SifException::conflict('USOC financing case not found');
        }

        $studentInvoice = $this->invoiceState($db, (string) $case['UUID_STUDENT_INVOICE']);
        $studentStatus = $studentInvoice['status'];
        $entityUuid = trim((string) ($case['UUID_ENTITY_INVOICE'] ?? ''));

        if ($entityUuid === '') {
            return $this->cases->updateReconciliation(
                $db,
                $inscriptionId,
                $idpag,
                $studentStatus,
                'PENDING',
                $studentStatus === 'PAID' ? 'PENDING_ENTITY_INVOICE' : 'REVIEW_REQUIRED'
            );
        }

        $entityInvoice = $this->invoiceState($db, $entityUuid);
        $entityStatus = $entityInvoice['status'];

        if (
            !$this->sameMoney($studentInvoice['total'], (string) $case['STUDENT_AMOUNT'])
            || !$this->sameMoney($entityInvoice['total'], (string) $case['ENTITY_AMOUNT'])
        ) {
            return $this->cases->updateReconciliation(
                $db,
                $inscriptionId,
                $idpag,
                $studentStatus,
                $entityStatus,
                'REVIEW_REQUIRED'
            );
        }

        $status = $this->caseStatus($studentStatus, $entityStatus);

        return $this->cases->updateReconciliation(
            $db,
            $inscriptionId,
            $idpag,
            $studentStatus,
            $entityStatus,
            $status
        );
    }

    private function invoiceState(\PDO $db, string $uuidInvoice): array
    {
        if ($uuidInvoice === '') {
            throw SifException::conflict('Missing invoice in USOC financing case');
        }

        $stmt = $db->prepare('SELECT ESTAT_COBRAMENT, TOTAL FROM factura WHERE UUID_FACTURA = ?');
        $stmt->execute([$uuidInvoice]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row) || trim((string) ($row['ESTAT_COBRAMENT'] ?? '')) === '') {
            throw SifException::conflict('Invoice not found while reconciling USOC financing case');
        }

        return [
            'status' => strtoupper(trim((string) $row['ESTAT_COBRAMENT'])),
            'total' => number_format((float) $row['TOTAL'], 2, '.', ''),
        ];
    }

    private function sameMoney(string $left, string $right): bool
    {
        return number_format((float) $left, 2, '.', '') === number_format((float) $right, 2, '.', '');
    }

    private function caseStatus(string $studentStatus, string $entityStatus): string
    {
        if ($studentStatus !== 'PAID') {
            return 'REVIEW_REQUIRED';
        }

        return match ($entityStatus) {
            'PENDING' => 'ENTITY_INVOICED',
            'PARTIAL' => 'ENTITY_PARTIAL',
            'PAID' => 'FINANCING_RECONCILED',
            default => 'REVIEW_REQUIRED',
        };
    }
}
