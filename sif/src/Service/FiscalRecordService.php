<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalRecordRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;

final class FiscalRecordService
{
    public function __construct(
        private TransactionRunner $transactions,
        private ManualPaymentInvoiceRepository $invoices,
        private FiscalRecordRepository $records,
        private FiscalRecordPayloadBuilder $payloads
    ) {
    }

    public function createCancellationByUuid(string $uuidFactura, array $input): array
    {
        return $this->create('ANULACIO', 'uuid', $uuidFactura, $input);
    }

    public function createCancellationByNumVisible(string $numVisible, array $input): array
    {
        return $this->create('ANULACIO', 'num_visible', $numVisible, $input);
    }

    public function createSubsanationByUuid(string $uuidFactura, array $input): array
    {
        return $this->create('SUBSANACIO', 'uuid', $uuidFactura, $input);
    }

    public function createSubsanationByNumVisible(string $numVisible, array $input): array
    {
        return $this->create('SUBSANACIO', 'num_visible', $numVisible, $input);
    }

    private function create(string $recordType, string $selectorType, string $selector, array $input): array
    {
        try {
            return $this->createOrReuse($recordType, $selectorType, $selector, $input);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            return $this->reuseAfterDuplicate($recordType, $selectorType, $selector, $input);
        }
    }

    private function createOrReuse(string $recordType, string $selectorType, string $selector, array $input): array
    {
        return $this->transactions->run(function (\PDO $db) use ($recordType, $selectorType, $selector, $input): array {
            $invoice = $this->loadInvoice($db, $selectorType, $selector, true);
            $this->assertVerifactuInvoice($invoice);
            $previous = $this->records->latestForInvoice($db, $invoice['UUID_FACTURA'], true);
            if ($previous === null) {
                throw SifException::validation('Invoice has no fiscal registration record');
            }

            $payload = $recordType === 'ANULACIO'
                ? $this->payloads->cancellation($invoice, $previous, $input)
                : $this->payloads->subsanation($invoice, $previous, $input);
            $key = $this->payloads->idempotencyKey($recordType, $invoice, $payload, $input);
            $existing = $this->records->findQueuedResult($db, $key, true);
            if ($existing !== null) {
                return $existing;
            }

            if ($recordType === 'ANULACIO' && $previous['TIPUS_REGISTRE'] === 'ANULACIO') {
                throw SifException::conflict('Invoice already has a cancellation record');
            }
            if ($recordType === 'SUBSANACIO' && $invoice['ESTAT_FACTURA'] === 'CANCELLED') {
                throw SifException::conflict('Cancelled invoices do not accept subsanation records');
            }

            return $this->records->create(
                $db,
                $invoice,
                $recordType,
                $key,
                $payload,
                $recordType === 'ANULACIO'
            );
        });
    }

    private function reuseAfterDuplicate(
        string $recordType,
        string $selectorType,
        string $selector,
        array $input
    ): array {
        return $this->transactions->run(function (\PDO $db) use ($recordType, $selectorType, $selector, $input): array {
            $invoice = $this->loadInvoice($db, $selectorType, $selector, true);
            $previous = $this->records->latestForInvoice($db, $invoice['UUID_FACTURA'], true);
            if ($previous === null) {
                throw new \RuntimeException('Duplicate key detected, but fiscal record could not be loaded.');
            }

            $payload = $recordType === 'ANULACIO'
                ? $this->payloads->cancellation($invoice, $previous, $input)
                : $this->payloads->subsanation($invoice, $previous, $input);
            $key = $this->payloads->idempotencyKey($recordType, $invoice, $payload, $input);
            $existing = $this->records->findQueuedResult($db, $key, true);
            if ($existing === null) {
                throw new \RuntimeException('Duplicate key detected, but queued fiscal record could not be loaded.');
            }

            return $existing;
        });
    }

    private function loadInvoice(\PDO $db, string $selectorType, string $selector, bool $forUpdate): array
    {
        $invoice = $selectorType === 'uuid'
            ? $this->invoices->findByUuid($db, $selector, $forUpdate)
            : $this->invoices->findByNumVisible($db, $selector, $forUpdate);

        if ($invoice === null) {
            throw SifException::validation('Unknown invoice');
        }

        return $invoice;
    }

    private function assertVerifactuInvoice(array $invoice): void
    {
        if (($invoice['ESTAT_AEAT'] ?? '') === 'NO_VERIFACTU' || ($invoice['ESTAT_FACTURA'] ?? '') === 'HISTORICAL') {
            throw SifException::validation('Historical NO_VERIFACTU invoices do not accept fiscal records');
        }
    }
}
