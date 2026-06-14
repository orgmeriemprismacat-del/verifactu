<?php

namespace Prisma\Sif\Service;

final class ManualInvoiceService
{
    public function __construct(
        private ManualInvoicePayloadBuilder $payloadBuilder,
        private InvoiceService $invoices
    ) {
    }

    public function issueManualInvoice(array $input): array
    {
        $payload = $this->payloadBuilder->build($input);

        return $this->invoices->issueInvoice($payload);
    }
}
