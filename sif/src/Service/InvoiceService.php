<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\PayloadIdempotencyValidatorInterface;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository;
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
        private ?PaymentRepository $payments = null,
        private ?PayloadIdempotencyValidatorInterface $idempotency = null,
        private ?InvoiceBeforePaymentCoverageRepository $beforePaymentCoverage = null
    ) {
        $this->idempotency ??= new PayloadIdempotencyValidator();
    }

    public function issueInvoice(array $payload): array
    {
        $payload = $this->validator->validate($payload);

        if ($this->requiresBeforePaymentCoverage($payload) && $this->beforePaymentCoverage === null) {
            throw new \RuntimeException(
                'Invoice-before-payment payload requires the UC-004 coverage repository.'
            );
        }

        try {
            return $this->createOrReuseInvoice($payload);
        } catch (\PDOException $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            if ($this->isBeforePaymentCoverageConflict($exception)) {
                throw SifException::conflict(
                    'One or more INSCRIPCIO origins are already claimed by another invoice-before-payment operation'
                );
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

            if ($this->requiresBeforePaymentCoverage($payload)) {
                $this->beforePaymentCoverage->claim(
                    $db,
                    $payload['relations'] ?? [],
                    $created['uuid_factura'],
                    $payload['idempotency_key']
                );
            }

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

            return $this->withStatusProjection($db, $result);
        });
    }

    private function createInitialPaymentIfPresent(\PDO $db, array $payload, string $uuidFactura): ?array
    {
        if (!array_key_exists('payment', $payload) || $payload['payment'] === null) {
            return null;
        }

        if (!is_array($payload['payment'])) {
            throw SifException::validation('Invalid invoice payment block');
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
                throw SifException::conflict(
                    'Invoice uniqueness conflict could not be resolved as an idempotent retry'
                );
            }

            return $this->existingResultWithPaymentIfPresent($db, $payload, $existing);
        });
    }

    private function existingResultWithPaymentIfPresent(\PDO $db, array $payload, array $existing): array
    {
        // Fail closed for pre-migration invoices: the complete original request
        // cannot be recovered from the fiscal payload (e.g. the payment block).
        $this->idempotency->assertMatches($payload, (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? ''));
        $result = $this->existingResult($existing);

        if (!array_key_exists('payment', $payload) || $payload['payment'] === null) {
            return $this->withStatusProjection($db, $result);
        }

        if (!is_array($payload['payment'])) {
            throw SifException::validation('Invalid invoice payment block');
        }

        if ($this->paymentValidator === null || $this->payments === null) {
            throw new \RuntimeException('Invoice payment block requires payment dependencies.');
        }

        $paymentPayload = $this->paymentValidator->validate(
            $this->buildInitialPaymentPayload($payload, $existing['UUID_FACTURA'])
        );
        $payment = $this->payments->findByIdempotencyKey($db, $paymentPayload['idempotency_key'], true);

        if ($payment === null) {
            throw SifException::conflict(
                'Invoice retry expected the original initial payment, but the payment record is missing'
            );
        }

        $this->assertInitialPaymentStillMatches(
            $db,
            $paymentPayload,
            $payment,
            (string) $existing['UUID_FACTURA']
        );

        $result['uuid_payment'] = $payment['UUID_PAYMENT'];

        return $this->withStatusProjection($db, $result);
    }

    private function withStatusProjection(\PDO $db, array $result): array
    {
        $projection = $this->invoices->statusProjection($db, (string) $result['uuid_factura']);
        $result['status'] = [
            'invoice' => $projection['invoice_status'],
            'payment' => $projection['payment_status'],
            'aeat' => $projection['aeat_status'],
            'fiscal_queue' => $projection['fiscal_queue_status'],
            'document' => $projection['document_status'],
            'document_type' => $projection['document_type'],
        ];
        $result['fiscal_order'] = $projection['fiscal_order'];

        return $result;
    }


    private function assertInitialPaymentStillMatches(
        \PDO $db,
        array $paymentPayload,
        array $payment,
        string $uuidFactura
    ): void {
        $storedHash = (string) ($payment['PAYLOAD_HASH'] ?? '');
        $hashVersion = (int) ($payment['PAYLOAD_HASH_VERSION'] ?? 1);

        if ($hashVersion === 1) {
            try {
                $legacyPayload = json_encode(
                    $paymentPayload,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $exception) {
                throw SifException::validation('Invalid payment payload encoding');
            }
            $this->idempotency->assertMatches($legacyPayload, $storedHash);
        } elseif ($hashVersion === 2) {
            $this->idempotency->assertMatches($paymentPayload, $storedHash);
        } else {
            throw SifException::conflict('Unknown payment idempotency hash version');
        }

        $sameTransaction = (string) ($payment['TIPUS_MOVIMENT'] ?? '') === (string) $paymentPayload['movement_type']
            && (string) ($payment['METODE'] ?? '') === (string) $paymentPayload['method']
            && (string) ($payment['SOURCE_CHANNEL'] ?? '') === (string) $paymentPayload['source_channel']
            && $this->moneyEquals($payment['IMPORT'] ?? null, $paymentPayload['amount'])
            && (string) ($payment['DATA_MOVIMENT'] ?? '') === (string) $paymentPayload['movement_date']
            && $this->nullableString($payment['PROVIDER_REF'] ?? null)
                === $this->nullableString($paymentPayload['provider_ref'] ?? null)
            && $this->nullableString($payment['DS_ORDER'] ?? null)
                === $this->nullableString($paymentPayload['ds_order'] ?? null)
            && $this->nullableInt($payment['IDPAG'] ?? null)
                === $this->nullableInt($paymentPayload['idpag'] ?? null)
            && $this->nullableString($payment['REFERENCIA_BANCARIA'] ?? null)
                === $this->nullableString($paymentPayload['reference'] ?? null)
            && $this->nullableString($payment['NOTES'] ?? null)
                === $this->nullableString($paymentPayload['notes'] ?? null)
            && (string) ($payment['ESTAT'] ?? '') === 'CONFIRMED';

        if (!$sameTransaction) {
            throw SifException::conflict(
                'Invoice retry initial payment no longer matches the recorded payment transaction'
            );
        }

        $allocations = $this->payments->findAllocationsForInvoice(
            $db,
            (string) $payment['UUID_PAYMENT'],
            $uuidFactura,
            true
        );
        $expectedAllocation = $paymentPayload['allocations'][0] ?? null;

        if (count($allocations) !== 1 || !is_array($expectedAllocation)) {
            throw SifException::conflict(
                'Invoice retry initial payment allocation is missing or ambiguous'
            );
        }

        $allocation = $allocations[0];
        if (!$this->moneyEquals($allocation['IMPORT_ASSIGNAT'] ?? null, $expectedAllocation['amount'] ?? null)
            || (string) ($allocation['TIPUS_ASSIGNACIO'] ?? '')
                !== (string) ($expectedAllocation['allocation_type'] ?? '')) {
            throw SifException::conflict(
                'Invoice retry initial payment allocation no longer matches the original invoice'
            );
        }
    }

    private function moneyEquals(mixed $left, mixed $right): bool
    {
        if (!is_numeric($left) || !is_numeric($right)) {
            return false;
        }

        return number_format((float) $left, 2, '.', '') === number_format((float) $right, 2, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
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

    private function requiresBeforePaymentCoverage(array $payload): bool
    {
        return (int) ($payload['uc004_invoice_before_payment'] ?? 0) === 1;
    }

    private function isDuplicateKeyException(\PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }

    private function isBeforePaymentCoverageConflict(\PDOException $exception): bool
    {
        $detail = (string) ($exception->errorInfo[2] ?? $exception->getMessage());

        return str_contains($detail, 'uq_invoice_before_payment_source');
    }
}
