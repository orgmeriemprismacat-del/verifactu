<?php

namespace Prisma\Sif\Service;

final class InvoiceBeforePaymentService
{
    public function __construct(
        private InvoiceBeforePaymentPayloadBuilder $payloadBuilder,
        private InvoiceService $invoices,
        private ?InvoiceBeforePaymentDocumentQueueService $documents = null
    ) {
    }

    public function issueBeforePayment(array $input): array
    {
        $payload = $this->payloadBuilder->build($input);
        $result = $this->invoices->issueInvoice($payload);

        if ($this->documents === null) {
            $result['document_status'] = 'NOT_ENQUEUED';
            return $result;
        }

        try {
            $job = $this->documents->ensurePdf(
                (string) $result['uuid_factura'],
                (string) $payload['idempotency_key']
            );
        } catch (\Throwable) {
            // The fiscal invoice is already committed at this point. A
            // document-queue failure must never turn into a second invoice on
            // retry. Surface the document failure independently and let the
            // caller retry the same idempotent UC-004 command.
            $result['document_status'] = 'ERROR';
            $result['document_error_code'] = 'DOCUMENT_QUEUE_FAILED';

            return $result;
        }

        $result['document_status'] = (string) $job['status'];
        $result['document_job'] = [
            'id' => (int) $job['document_job_id'],
            'uuid_job' => (string) $job['uuid_job'],
            'type' => (string) $job['document_type'],
            'generator_version' => (string) $job['generator_version'],
            'status' => (string) $job['status'],
            'reused' => (bool) $job['reused'],
        ];

        return $result;
    }
}
