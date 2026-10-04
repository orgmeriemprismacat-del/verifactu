<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\PayloadIdempotencyValidatorInterface;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CreditBalanceRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
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
        private PaymentRepository $payments,
        private ?PayloadIdempotencyValidatorInterface $idempotency = null,
        private ?EnrollmentFundMovementRepository $funds = null
    ) {
        $this->idempotency ??= new PayloadIdempotencyValidator();
        $this->funds ??= new EnrollmentFundMovementRepository(new UuidGenerator());
    }

    public function createCredit(array $input): array
    {
        $payload = $this->builder->forCreditBalance($input);
        $key = trim((string) ($payload['idempotency_key'] ?? ''));
        $sourceEnrollmentId = (int) ($payload['source_enrollment_id'] ?? 0);

        if ($sourceEnrollmentId > 0 && $key === '') {
            throw SifException::validation(
                'Credit balance from enrollment funds requires idempotency_key'
            );
        }

        if ($key === '') {
            return $this->createCreditWithoutIdempotency($payload);
        }

        $payload['idempotency_key'] = $key;

        try {
            return $this->createOrReuseCredit($payload);
        } catch (\PDOException $exception) {
            if (!$this->isDuplicateKeyException($exception)) {
                throw $exception;
            }

            return $this->reuseCreditAfterDuplicateKey($payload);
        }
    }

    private function createCreditWithoutIdempotency(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $created = $this->credits->createCredit($db, $payload);
            $this->ensureCreditFundExit(
                $db,
                $payload,
                (string) $created['uuid_credit']
            );

            return [
                'ok' => true,
                'idempotency_reused' => false,
                'uuid_credit' => $created['uuid_credit'],
                'import_disponible' => $created['import_disponible'],
                'estat' => $created['estat'],
            ];
        });
    }

    private function createOrReuseCredit(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->credits->findByIdempotencyKey(
                $db,
                (string) $payload['idempotency_key'],
                true
            );
            if ($existing !== null) {
                $this->assertSameCreditPayload($payload, $existing);
                $this->ensureCreditFundExit(
                    $db,
                    $payload,
                    (string) $existing['UUID_CREDIT']
                );

                return $this->existingCreditResult($existing, true);
            }

            $payload['idempotency_payload_hash'] = $this->idempotency->calculateHash(
                $this->creditIdempotencyPayload($payload)
            );
            $created = $this->credits->createCredit($db, $payload);

            return [
                'ok' => true,
                'idempotency_reused' => false,
                'uuid_credit' => $created['uuid_credit'],
                'import_disponible' => $created['import_disponible'],
                'estat' => $created['estat'],
            ];
        });
    }

    private function reuseCreditAfterDuplicateKey(array $payload): array
    {
        return $this->transactions->run(function (\PDO $db) use ($payload): array {
            $existing = $this->credits->findByIdempotencyKey(
                $db,
                (string) $payload['idempotency_key'],
                true
            );
            if ($existing === null) {
                throw new \RuntimeException(
                    'Duplicate key detected, but existing credit balance could not be loaded.'
                );
            }

            $this->assertSameCreditPayload($payload, $existing);
            $this->ensureCreditFundExit(
                $db,
                $payload,
                (string) $existing['UUID_CREDIT']
            );

            return $this->existingCreditResult($existing, true);
        });
    }

    private function assertSameCreditPayload(array $payload, array $existing): void
    {
        $this->idempotency->assertMatches(
            $this->creditIdempotencyPayload($payload),
            (string) ($existing['IDEMPOTENCY_PAYLOAD_HASH'] ?? '')
        );
    }

    private function creditIdempotencyPayload(array $payload): array
    {
        unset($payload['idempotency_payload_hash']);

        return $payload;
    }

    private function ensureCreditFundExit(
        \PDO $db,
        array $payload,
        string $uuidCredit
    ): void {
        $idInsc = (int) ($payload['source_enrollment_id'] ?? 0);
        if ($idInsc <= 0) {
            return;
        }

        $key = trim((string) ($payload['idempotency_key'] ?? ''));
        if ($key === '') {
            throw SifException::validation(
                'Credit enrollment fund exit requires idempotency_key'
            );
        }

        $correlationId = trim((string) ($payload['correlation_id'] ?? ''));
        if ($correlationId === '') {
            $correlationId = 'UC006|CREDIT|'
                . substr(hash('sha256', $key), 0, 32);
        }

        $this->funds->insertOrReuseCreditCreate(
            $db,
            [
                'idempotency_key' => 'FUND|CREDIT_CREATE|'
                    . hash('sha256', $key)
                    . '|INSC:' . $idInsc,
                'order' => 1,
                'id_insc_origin' => $idInsc,
                'uuid_credit' => $uuidCredit,
                'uuid_factura' => $payload['uuid_factura_origen'] ?? null,
                'amount' => $payload['amount'],
                'currency' => 'EUR',
                'uuid_operation' => $payload['uuid_operation'] ?? null,
                'correlation_id' => $correlationId,
                'notes' => 'UC-006 enrollment funds converted to credit balance',
            ]
        );
    }

    private function existingCreditResult(array $existing, bool $reused): array
    {
        return [
            'ok' => true,
            'idempotency_reused' => $reused,
            'uuid_credit' => (string) $existing['UUID_CREDIT'],
            'import_disponible' => number_format((float) $existing['IMPORT_DISPONIBLE'], 2, '.', ''),
            'estat' => (string) $existing['ESTAT'],
        ];
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
                $this->assertSamePaymentPayload($payload, $existing);

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

            $this->assertSamePaymentPayload($payload, $existing);

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

    private function assertSamePaymentPayload(array $payload, array $existing): void
    {
        $version = (int) ($existing['PAYLOAD_HASH_VERSION'] ?? 1);
        $hash = (string) ($existing['PAYLOAD_HASH'] ?? '');

        if ($version === 1) {
            try {
                $legacy = json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_PRESERVE_ZERO_FRACTION
                    | JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $exception) {
                throw SifException::validation('Invalid payment payload encoding');
            }

            $this->idempotency->assertMatches($legacy, $hash);

            return;
        }

        if ($version !== 2) {
            throw SifException::conflict('Unknown payment idempotency hash version');
        }

        $this->idempotency->assertMatches($payload, $hash);
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
