<?php

namespace Prisma\Sif\Contract;

interface InvoiceBeforePaymentDocumentQueueInterface
{
    public function ensurePdf(string $uuidFactura, string $invoiceIdempotencyKey): array;
}
