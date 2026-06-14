<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CreditBalanceRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;

final class CreditBalanceService
{
    public function __construct(
        private TransactionRunner $transactions,
        private CreditBalanceRepository $credits,
        private ManualPaymentInvoiceRepository $invoices,
        private CreditBalancePayloadBuilder $builder,
        private PaymentPayloadValidator $paymentValidator,
        private PaymentRepository $payments
    ) {
    }

    public function createCredit(array $input): array
    {
        $payload = $this->builder->forCreditBalance($input);

        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $created = $this->credits->createCredit($db, $payload);

            return [
                'ok' => true,
                'uuid_credit' => $created['uuid_credit'],
                'import_disponible' => $created['import_disponible'],
                'estat' => $created['estat'],
            ];
        });
    }

    public function applyCreditByUuid(string $uuidCredit, string $uuidFactura, array $input): array
    {
        try {
            return $this->applyCredit($uuidCredit, 'uuid', $uuidFactura, $input);
        } catch (\PDOException $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            return $this->reuseCompensationAfterDuplicateKey($uuidCredit, 'uuid', $uuidFactura, $input);
        }
    }

    public function applyCreditByNumVisible(string $uuidCredit, string $numVisible, array $input): array
    {
        try {
            return $this->applyCredit($uuidCredit, 'num_visible', $numVisible, $input);
        } catch (\PDOException $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            return $this->reuseCompensationAfterDuplicateKey($uuidCredit, 'num_visible', $numVisible, $input);
        }
    }

    private function applyCredit(string $uuidCredit, string $invoiceSelectorType, string $invoiceSelector, array $input): array
    {
        return $this->transactions->run(function (\PDO $db) use ($uuidCredit, $invoiceSelectorType, $invoiceSelector, $input): array {
            [$credit, $invoice, $payload] = $this->lockedCompensationContext($db, $uuidCredit, $invoiceSelectorType, $invoiceSelector, $input);
            $existing = $this->payments->findByIdempotencyKey($db, $payload['idempotency_key'], true);

            if ($existing !== null) {
                return $this->existingPaymentResult($existing, $credit, $invoice);
            }

            $this->assertCreditCanBeApplied($db, $credit, $invoice, $payload['amount']);
            $payment = $this->payments->createPayment($db, $payload);
            $updatedCredit = $this->consumeCredit($db, $credit, $payload['amount']);

            return [
                'ok' => true,
                'idempotency_reused' => false,
                'uuid_payment' => $payment['uuid_payment'],
                'uuid_credit' => $updatedCredit['UUID_CREDIT'],
                'uuid_factura' => $invoice['UUID_FACTURA'],
                'num_visible' => $invoice['NUM_VISIBLE'],
                'import_disponible' => $updatedCredit['IMPORT_DISPONIBLE'],
                'credit_estat' => $updatedCredit['ESTAT'],
            ];
        });
    }

    private function reuseCompensationAfterDuplicateKey(
        string $uuidCredit,
        string $invoiceSelectorType,
        string $invoiceSelector,
        array $input
    ): array {
        return $this->transactions->run(function (\PDO $db) use ($uuidCredit, $invoiceSelectorType, $invoiceSelector, $input): array {
            [$credit, $invoice, $payload] = $this->lockedCompensationContext($db, $uuidCredit, $invoiceSelectorType, $invoiceSelector, $input);
            $existing = $this->payments->findByIdempotencyKey($db, $payload['idempotency_key'], true);

            if ($existing === null) {
                throw new \RuntimeException('Duplicate key detected, but existing compensation could not be loaded.');
            }

            return $this->existingPaymentResult($existing, $credit, $invoice);
        });
    }

    private function lockedCompensationContext(
        \PDO $db,
        string $uuidCredit,
        string $invoiceSelectorType,
        string $invoiceSelector,
        array $input
    ): array {
        $credit = $this->credits->findByUuid($db, trim($uuidCredit), true);
        if ($credit === null) {
            throw SifException::validation('Credit balance not found');
        }

        if ($invoiceSelectorType === 'uuid') {
            $invoice = $this->invoices->findByUuid($db, trim($invoiceSelector), true);
        } else {
            $invoice = $this->invoices->findByNumVisible($db, trim($invoiceSelector), true);
        }

        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for credit compensation');
        }

        $payload = $this->paymentValidator->validate(
            $this->builder->forCompensation($credit['UUID_CREDIT'], $invoice['UUID_FACTURA'], $input, $invoice)
        );

        return [$credit, $invoice, $payload];
    }

    private function assertCreditCanBeApplied(\PDO $db, array $credit, array $invoice, string $amount): void
    {
        if (($credit['ESTAT'] ?? '') !== 'ACTIVE') {
            throw SifException::validation('Credit balance is not active');
        }

        $amountCents = $this->cents($amount);
        $availableCents = $this->cents((string) $credit['IMPORT_DISPONIBLE']);
        if ($amountCents > $availableCents) {
            throw SifException::validation('Compensation amount exceeds available credit');
        }

        $outstanding = $this->credits->invoiceOutstandingAmount($db, (string) $invoice['UUID_FACTURA']);
        if ($amountCents > $this->cents($outstanding)) {
            throw SifException::validation('Compensation amount exceeds invoice outstanding amount');
        }
    }

    private function consumeCredit(\PDO $db, array $credit, string $amount): array
    {
        $availableCents = $this->cents((string) $credit['IMPORT_DISPONIBLE']);
        $newAvailable = $this->amount(max(0, $availableCents - $this->cents($amount)));
        $status = $newAvailable === '0.00' ? 'USED' : 'ACTIVE';

        $this->credits->updateAvailableAmount($db, (string) $credit['UUID_CREDIT'], $newAvailable, $status);
        $credit['IMPORT_DISPONIBLE'] = $newAvailable;
        $credit['ESTAT'] = $status;

        return $credit;
    }

    private function existingPaymentResult(array $existing, array $credit, array $invoice): array
    {
        return [
            'ok' => true,
            'idempotency_reused' => true,
            'uuid_payment' => $existing['UUID_PAYMENT'],
            'uuid_credit' => $credit['UUID_CREDIT'],
            'uuid_factura' => $invoice['UUID_FACTURA'],
            'num_visible' => $invoice['NUM_VISIBLE'],
            'import_disponible' => $credit['IMPORT_DISPONIBLE'],
            'credit_estat' => $credit['ESTAT'],
        ];
    }

    private function isDuplicateKeyException(\PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23000';
    }

    private function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function amount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
