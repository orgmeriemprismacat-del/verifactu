<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class UsocFinancingCaseRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function recordStudentInvoice(
        \PDO $db,
        int $inscriptionId,
        int $idpag,
        string $studentInvoiceUuid,
        string $studentAmount,
        string $entityAmount,
        string $correlationId
    ): array {
        $existing = $this->findByInscriptionAndIdpag($db, $inscriptionId, $idpag, true);
        if ($existing !== null) {
            $this->assertSameStudentCase($existing, $studentInvoiceUuid, $studentAmount, $entityAmount);
            return $existing;
        }

        if ($inscriptionId <= 0 || $idpag <= 0) {
            throw SifException::validation('Invalid USOC financing case identity');
        }

        $uuidCase = $this->uuidGenerator->generate();
        $stmt = $db->prepare(
            'INSERT INTO usoc_financing_case (
                UUID_CASE, ID_INSC, IDPAG, UUID_STUDENT_INVOICE,
                STUDENT_AMOUNT, ENTITY_AMOUNT, STUDENT_PAYMENT_STATUS,
                ENTITY_PAYMENT_STATUS, STATUS, CORRELATION_ID
            ) VALUES (?, ?, ?, ?, ?, ?, \'PAID\', \'PENDING\', \'PENDING_ENTITY_INVOICE\', ?)'
        );
        try {
            $stmt->execute([
                $uuidCase,
                $inscriptionId,
                $idpag,
                $studentInvoiceUuid,
                $studentAmount,
                $entityAmount,
                $correlationId,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $raced = $this->findByInscriptionAndIdpag($db, $inscriptionId, $idpag, true);
            if ($raced === null) {
                throw $exception;
            }
            $this->assertSameStudentCase($raced, $studentInvoiceUuid, $studentAmount, $entityAmount);
            return $raced;
        }

        return $this->findByInscriptionAndIdpag($db, $inscriptionId, $idpag, true)
            ?? throw new \RuntimeException('USOC financing case could not be reloaded after insert');
    }

    public function recordEntityInvoice(
        \PDO $db,
        int $inscriptionId,
        int $idpag,
        string $studentInvoiceUuid,
        string $entityInvoiceUuid,
        string $studentAmount,
        string $entityAmount,
        string $correlationId
    ): array {
        $existing = $this->findByInscriptionAndIdpag($db, $inscriptionId, $idpag, true);
        if ($existing === null) {
            $existing = $this->recordStudentInvoice(
                $db,
                $inscriptionId,
                $idpag,
                $studentInvoiceUuid,
                $studentAmount,
                $entityAmount,
                $correlationId
            );
        } else {
            $this->assertSameStudentCase($existing, $studentInvoiceUuid, $studentAmount, $entityAmount);
        }

        $currentEntityUuid = trim((string) ($existing['UUID_ENTITY_INVOICE'] ?? ''));
        if ($currentEntityUuid !== '' && $currentEntityUuid !== $entityInvoiceUuid) {
            throw SifException::conflict('USOC financing case already references another entity invoice');
        }

        $stmt = $db->prepare(
            'UPDATE usoc_financing_case
             SET UUID_ENTITY_INVOICE = ?,
                 STATUS = \'ENTITY_INVOICED\',
                 ENTITY_PAYMENT_STATUS = CASE
                     WHEN ENTITY_PAYMENT_STATUS = \'PAID\' THEN \'PAID\'
                     ELSE \'PENDING\'
                 END,
                 CORRELATION_ID = ?
             WHERE ID_INSC = ? AND IDPAG = ?'
        );
        $stmt->execute([$entityInvoiceUuid, $correlationId, $inscriptionId, $idpag]);

        return $this->findByInscriptionAndIdpag($db, $inscriptionId, $idpag, true)
            ?? throw new \RuntimeException('USOC financing case could not be reloaded after entity invoice update');
    }

    public function updateReconciliation(
        \PDO $db,
        int $inscriptionId,
        int $idpag,
        string $studentPaymentStatus,
        string $entityPaymentStatus,
        string $status
    ): array {
        $stmt = $db->prepare(
            'UPDATE usoc_financing_case
             SET STUDENT_PAYMENT_STATUS = ?,
                 ENTITY_PAYMENT_STATUS = ?,
                 STATUS = ?
             WHERE ID_INSC = ? AND IDPAG = ?'
        );
        $stmt->execute([
            $studentPaymentStatus,
            $entityPaymentStatus,
            $status,
            $inscriptionId,
            $idpag,
        ]);

        if ($stmt->rowCount() === 0) {
            throw SifException::conflict('USOC financing case not found for reconciliation');
        }

        return $this->findByInscriptionAndIdpag($db, $inscriptionId, $idpag, true)
            ?? throw new \RuntimeException('USOC financing case could not be reloaded after reconciliation');
    }

    public function findByInscriptionAndIdpag(
        \PDO $db,
        int $inscriptionId,
        int $idpag,
        bool $forUpdate = false
    ): ?array {
        $sql = 'SELECT * FROM usoc_financing_case WHERE ID_INSC = ? AND IDPAG = ?';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$inscriptionId, $idpag]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function assertSameStudentCase(
        array $existing,
        string $studentInvoiceUuid,
        string $studentAmount,
        string $entityAmount
    ): void {
        if ((string) $existing['UUID_STUDENT_INVOICE'] !== $studentInvoiceUuid) {
            throw SifException::conflict('USOC financing case already references another student invoice');
        }

        if (
            number_format((float) $existing['STUDENT_AMOUNT'], 2, '.', '') !== number_format((float) $studentAmount, 2, '.', '')
            || number_format((float) $existing['ENTITY_AMOUNT'], 2, '.', '') !== number_format((float) $entityAmount, 2, '.', '')
        ) {
            throw SifException::conflict('USOC financing case amount mismatch');
        }
    }
}
