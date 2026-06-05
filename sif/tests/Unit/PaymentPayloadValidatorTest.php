<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class PaymentPayloadValidatorTest
{
    public function testValidPaymentPayloadPasses(): void
    {
        $payload = $this->validPayload();

        Assert::same($payload, (new PaymentPayloadValidator())->validate($payload));
    }

    public function testMissingRequiredPaymentFieldThrowsValidationError(): void
    {
        $payload = $this->validPayload();
        unset($payload['idempotency_key']);

        $exception = Assert::throws(SifException::class, static fn () => (new PaymentPayloadValidator())->validate($payload), 422);

        Assert::same('Missing payment field idempotency_key', $exception->getMessage());
    }

    public function testInvalidMovementTypeThrowsValidationError(): void
    {
        $payload = $this->validPayload();
        $payload['movement_type'] = 'UNKNOWN';

        $exception = Assert::throws(SifException::class, static fn () => (new PaymentPayloadValidator())->validate($payload), 422);

        Assert::same('Invalid movement type', $exception->getMessage());
    }

    public function testPaymentRequiresAtLeastOneAllocation(): void
    {
        $payload = $this->validPayload();
        $payload['allocations'] = [];

        $exception = Assert::throws(SifException::class, static fn () => (new PaymentPayloadValidator())->validate($payload), 422);

        Assert::same('Payment requires at least one allocation', $exception->getMessage());
    }

    private function validPayload(): array
    {
        return [
            'idempotency_key' => 'TRANSFERENCIA|REF:ABC123',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-06-02 10:00:00',
            'allocations' => [[
                'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ];
    }
}
