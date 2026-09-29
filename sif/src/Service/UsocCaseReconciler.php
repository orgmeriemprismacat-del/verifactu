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

        $studentStatus = $this->invoicePaymentStatus($db, (string) $case['UUID_STUDENT_INVOICE']);
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

        $entityStatus = $this->invoicePaymentStatus($db, $entityUuid);
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

    private function invoicePaymentStatus(\PDO $db, string $uuidInvoice): string
    {
        if ($uuidInvoice === '') {
            throw SifException::conflict('Missing invoice in USOC financing case');
        }

        $stmt = $db->prepare('SELECT ESTAT_COBRAMENT FROM factura WHERE UUID_FACTURA = ?');
        $stmt->execute([$uuidInvoice]);
        $status = $stmt->fetchColumn();

        if ($status === false || trim((string) $status) === '') {
            throw SifException::conflict('Invoice not found while reconciling USOC financing case');
        }

        return strtoupper(trim((string) $status));
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
