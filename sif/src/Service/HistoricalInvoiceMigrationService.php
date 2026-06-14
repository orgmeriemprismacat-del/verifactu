<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\HistoricalInvoiceMigrationRepository;

final class HistoricalInvoiceMigrationService
{
    public function __construct(
        private TransactionRunner $transactions,
        private HistoricalInvoicePayloadBuilder $payloadBuilder,
        private HistoricalInvoiceMigrationRepository $historicalInvoices
    ) {
    }

    public function importHistoricalInvoice(array $input): array
    {
        $payload = $this->payloadBuilder->build($input);

        return $this->transactions->run(
            fn (\PDO $db): array => $this->historicalInvoices->importHistoricalInvoice($db, $payload)
        );
    }
}
