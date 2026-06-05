<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;

final class InvoiceService
{
    public function __construct(
        private TransactionRunner $transactions,
        private InvoicePayloadValidator $validator,
        private FiscalSequenceRepository $sequences,
        private InvoiceRepository $invoices,
        private ?PaymentPayloadValidator $paymentValidator = null,
        private ?PaymentRepository $payments = null
    ) {
    }

    public function issueInvoice(array $payload): array
    {
        $payload = $this->validator->validate($payload);

        try {
            return $this->createOrReuseInvoice($payload);
        } catch (\PDOException $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            return $this->reuseInvoiceAfterDuplicateKey($payload);
        }
    }

    private function createOrReuseInvoice(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->invoices->findByIdempotencyKey($db, $payload['idempotency_key'], true);
            if ($existing !== null) {
                return $this->existingResultWithPaymentIfPresent($db, $payload, $existing);
            }

            $year = (int) ($payload['year'] ?? date('Y'));
            $seq = $this->sequences->next($db, $payload['series'], $year);
            $chainState = $this->invoices->lockChainState($db);
            $created = $this->invoices->createInvoiceGraph($db, $payload, $seq, $chainState);
            $payment = $this->createInitialPaymentIfPresent($db, $payload, $created['uuid_factura']);

            $result = [
                'ok' => true,
                'idempotency_reused' => false,
                'uuid_factura' => $created['uuid_factura'],
                'num_visible' => $created['num_visible'],
            ];

            if ($payment !== null) {
                $result['uuid_payment'] = $payment['uuid_payment'];
            }

            return $result;
        });
    }

    private function createInitialPaymentIfPresent(\PDO $db, array $payload, string $uuidFactura): ?array
    {
        if (!array_key_exists('payment', $payload) || $payload['payment'] === null) {
            return null;
        }

        if (!is_array($payload['payment'])) {
            throw \Prisma\Sif\Exception\SifException::validation('Invalid invoice payment block');
        }

        if ($this->paymentValidator === null || $this->payments === null) {
            throw new \RuntimeException('Invoice payment block requires payment dependencies.');
        }

        $paymentPayload = $this->paymentValidator->validate(
            $this->buildInitialPaymentPayload($payload, $uuidFactura)
        );

        return $this->payments->createPayment($db, $paymentPayload);
    }

    private function buildInitialPaymentPayload(array $payload, string $uuidFactura): array
    {
        $payment = $payload['payment'];
        $firstRelation = $payload['relations'][0] ?? [];

        return [
            'idempotency_key' => $payment['idempotency_key'] ?? 'PAYMENT|' . $payload['idempotency_key'],
            'movement_type' => $payment['movement_type'] ?? 'CHARGE',
            'method' => $payment['method'] ?? $payload['source_channel'],
            'source_channel' => $payment['source_channel'] ?? $payload['source_channel'],
            'amount' => $payment['amount'] ?? $payload['totals']['total'],
            'movement_date' => $payment['movement_date'] ?? date('Y-m-d H:i:s'),
            'provider_ref' => $payment['provider_ref'] ?? null,
            'ds_order' => $payment['ds_order'] ?? ($firstRelation['ds_order'] ?? null),
            'idpag' => $payment['idpag'] ?? ($firstRelation['idpag'] ?? null),
            'reference' => $payment['reference'] ?? null,
            'notes' => $payment['notes'] ?? null,
            'allocations' => [[
                'uuid_factura' => $uuidFactura,
                'amount' => $payment['amount'] ?? $payload['totals']['total'],
                'allocation_type' => $payment['allocation_type'] ?? 'INVOICE_PAYMENT',
            ]],
        ];
    }

    private function reuseInvoiceAfterDuplicateKey(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->invoices->findByIdempotencyKey($db, $payload['idempotency_key'], true);

            if ($existing === null) {
                throw new \RuntimeException('Duplicate key detected, but existing invoice could not be loaded.');
            }

            return $this->existingResultWithPaymentIfPresent($db, $payload, $existing);
        });
    }

    private function existingResultWithPaymentIfPresent(\PDO $db, array $payload, array $existing): array
    {
        $result = $this->existingResult($existing);

        if (!array_key_exists('payment', $payload) || $payload['payment'] === null) {
            return $result;
        }

        if (!is_array($payload['payment'])) {
            throw \Prisma\Sif\Exception\SifException::validation('Invalid invoice payment block');
        }

        if ($this->paymentValidator === null || $this->payments === null) {
            throw new \RuntimeException('Invoice payment block requires payment dependencies.');
        }

        $paymentPayload = $this->paymentValidator->validate(
            $this->buildInitialPaymentPayload($payload, $existing['UUID_FACTURA'])
        );
        $payment = $this->payments->findByIdempotencyKey($db, $paymentPayload['idempotency_key'], true);

        if ($payment !== null) {
            $result['uuid_payment'] = $payment['UUID_PAYMENT'];
        }

        return $result;
    }

    private function existingResult(array $existing): array
    {
        return [
            'ok' => true,
            'idempotency_reused' => true,
            'uuid_factura' => $existing['UUID_FACTURA'],
            'num_visible' => $existing['NUM_VISIBLE'],
        ];
    }

    private function isDuplicateKeyException(\PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }
}
