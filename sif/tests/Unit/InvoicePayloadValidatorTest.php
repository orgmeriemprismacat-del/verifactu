<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class InvoicePayloadValidatorTest
{
    public function testValidInvoicePayloadPasses(): void
    {
        $payload = $this->validPayload();

        Assert::same($payload, (new InvoicePayloadValidator())->validate($payload));
    }

    public function testMissingRequiredInvoiceFieldThrowsValidationError(): void
    {
        $payload = $this->validPayload();
        unset($payload['idempotency_key']);

        $exception = Assert::throws(SifException::class, static fn () => (new InvoicePayloadValidator())->validate($payload), 422);

        Assert::same('Missing invoice field idempotency_key', $exception->getMessage());
    }

    public function testInvalidInvoiceSeriesThrowsValidationError(): void
    {
        $payload = $this->validPayload();
        $payload['series'] = 'B';

        $exception = Assert::throws(SifException::class, static fn () => (new InvoicePayloadValidator())->validate($payload), 422);

        Assert::same('Invalid invoice series', $exception->getMessage());
    }

    public function testRejectsRectificationTypeInOrdinarySeries(): void
    {
        $payload = $this->validPayload();
        $payload['type'] = 'R1';

        Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );
    }

    public function testRejectsOrdinaryTypeInRectificationSeries(): void
    {
        $payload = $this->validPayload();
        $payload['series'] = 'R';

        Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );
    }

    public function testAcceptsRectificationTypeInRectificationSeries(): void
    {
        $payload = $this->validPayload();
        $payload['series'] = 'R';
        $payload['type'] = 'R1';

        Assert::same($payload, (new InvoicePayloadValidator())->validate($payload));
    }

    public function testInvoiceRequiresAtLeastOneLine(): void
    {
        $payload = $this->validPayload();
        $payload['lines'] = [];

        $exception = Assert::throws(SifException::class, static fn () => (new InvoicePayloadValidator())->validate($payload), 422);

        Assert::same('Invoice requires at least one line', $exception->getMessage());
    }

    public function testRejectsBlankIdempotencyKey(): void
    {
        $payload = $this->validPayload();
        $payload['idempotency_key'] = '   ';

        Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );
    }

    public function testRejectsIdempotencyKeyLongerThanDatabaseColumn(): void
    {
        $payload = $this->validPayload();
        $payload['idempotency_key'] = str_repeat('K', 101);

        Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );
    }

    public function testRejectsHeaderTotalThatDoesNotMatchLines(): void
    {
        $payload = $this->validPayload();
        $payload['totals']['total'] = '121.00';

        $exception = Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );

        Assert::same('Invoice total does not match line totals', $exception->getMessage());
    }

    public function testRejectsHeaderDiscountThatDoesNotMatchLines(): void
    {
        $payload = $this->validPayload();
        $payload['totals']['discount'] = '1.00';

        $exception = Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );

        Assert::same('Invoice discount does not match line totals', $exception->getMessage());
    }

    public function testRejectsHeaderTaxableBaseThatDoesNotMatchLines(): void
    {
        $payload = $this->validPayload();
        $payload['totals']['taxable_base'] = '119.00';

        $exception = Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );

        Assert::same('Invoice taxable_base does not match line totals', $exception->getMessage());
    }


    public function testRejectsBlankSourceChannel(): void
    {
        $payload = $this->validPayload();
        $payload['source_channel'] = '   ';

        $exception = Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );

        Assert::same('Invalid invoice source channel', $exception->getMessage());
    }

    public function testRejectsHeaderImportBaseThatDoesNotMatchLines(): void
    {
        $payload = $this->validPayload();
        $payload['totals']['import_base'] = '119.00';

        $exception = Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );

        Assert::same('Invoice import_base does not match line totals', $exception->getMessage());
    }

    public function testRejectsHeaderVatThatDoesNotMatchLines(): void
    {
        $payload = $this->validPayload();
        $payload['totals']['iva_import'] = '1.00';

        $exception = Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );

        Assert::same('Invoice iva_import does not match line totals', $exception->getMessage());
    }

    private function validPayload(): array
    {
        return [
            'idempotency_key' => 'REDSYS|CURS|IDPAG:123|ORDER:999999',
            'series' => 'A',
            'type' => 'F1',
            'source_channel' => 'REDSYS',
            'billing' => [
                'name' => 'Client Exemple',
                'nif' => '12345678Z',
                'country' => 'ES',
            ],
            'totals' => [
                'import_base' => '120.00',
                'discount' => '0.00',
                'taxable_base' => '120.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '120.00',
            ],
            'lines' => [[
                'concept' => 'Curs individual',
                'quantity' => '1.00',
                'unit_price' => '120.00',
                'base' => '120.00',
                'total' => '120.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
            ]],
        ];
    }
}
