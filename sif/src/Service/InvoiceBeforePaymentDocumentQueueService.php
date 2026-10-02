<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\DocumentJobRepository;

final class InvoiceBeforePaymentDocumentQueueService
{
    public function __construct(
        private TransactionRunner $transactions,
        private DocumentJobRepository $jobs,
        private string $generatorVersion
    ) {
        $this->generatorVersion = trim($this->generatorVersion);
        if ($this->generatorVersion === '' || strlen($this->generatorVersion) > 80) {
            throw new \RuntimeException('UC-004 document generator version is not configured');
        }
    }

    public function ensurePdf(string $uuidFactura, string $invoiceIdempotencyKey): array
    {
        $correlationId = 'UC004-DOC:' . hash('sha256', trim($invoiceIdempotencyKey));

        return $this->transactions->run(
            fn (\PDO $db): array => $this->jobs->ensurePending(
                $db,
                $uuidFactura,
                'PDF',
                $this->generatorVersion,
                $correlationId
            )
        );
    }
}
