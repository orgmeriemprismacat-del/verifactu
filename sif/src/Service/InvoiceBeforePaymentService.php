<?php

namespace Prisma\Sif\Service;

final class InvoiceBeforePaymentService
{
    public function __construct(
        private InvoiceBeforePaymentPayloadBuilder $payloadBuilder,
        private InvoiceService $invoices
    ) {
    }

    public function issueBeforePayment(array $input): array
    {
        $payload = $this->payloadBuilder->build($input);

        return $this->invoices->issueInvoice($payload);
    }
}
