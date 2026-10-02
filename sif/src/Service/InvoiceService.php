<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\PayloadIdempotencyValidatorInterface;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
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
        private ?InvoiceBeforePaymentCoverageRepository $beforePaymentCoverage = null,
        private ?OperationalEventRepository $operationalEvents = null
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

        if ($this->requiresBeforePaymentCoverage($payload) && $this->operationalEvents === null) {
            throw new \RuntimeException(
                'Invoice-before-payment payload requires the UC-004 operational audit repository.'
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
                $this->appendBeforePaymentOperationalEvent($db, $payload, $created);
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

            return $result;
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
            return $result;
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

    private function appendBeforePaymentOperationalEvent(\PDO $db, array $payload, array $created): void
    {
        $relations = array_values(array_map(
            static fn (array $relation): array => [
                'source_type' => (string) ($relation['source_type'] ?? ''),
                'source_id' => $relation['source_id'] ?? null,
                'relation_type' => (string) ($relation['relation_type'] ?? 'ORIGIN'),
            ],
            array_values(array_filter(
                $payload['relations'] ?? [],
                static fn (mixed $relation): bool => is_array($relation)
            ))
        ));

        $this->operationalEvents->append($db, [
            'operation_type' => 'ISSUE_INVOICE_BEFORE_PAYMENT',
            'source_type' => 'UC004_SELECTION',
            'source_id' => (string) $payload['idempotency_key'],
            'uuid_factura' => (string) $created['uuid_factura'],
            'fiscal_impact' => 'INVOICE_ISSUED',
            'economic_impact' => 'PENDING_PAYMENT',
            'status' => 'COMPLETED',
            'reason_code' => 'UC004_CONFIRMED',
            'before_snapshot' => null,
            'after_snapshot' => [
                'uuid_factura' => (string) $created['uuid_factura'],
                'num_visible' => (string) $created['num_visible'],
                'billing_nif' => (string) ($payload['billing']['nif'] ?? ''),
                'total' => (string) ($payload['totals']['total'] ?? ''),
                'relations' => $relations,
            ],
            'actor_type' => 'INTERNAL_USER',
            'actor_id' => $payload['created_by'] ?? null,
            'actor_role' => null,
            'source_channel' => (string) ($payload['source_channel'] ?? 'INTRANET'),
            'correlation_id' => (string) $payload['idempotency_key'],
            'occurred_at' => (new \DateTimeImmutable(
                'now',
                new \DateTimeZone('Europe/Madrid')
            ))->format('Y-m-d H:i:s.u'),
        ]);
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
