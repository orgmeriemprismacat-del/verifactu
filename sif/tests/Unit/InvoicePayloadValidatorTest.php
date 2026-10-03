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


    public function testNormalizesIdempotencyKeyAndSourceChannel(): void
    {
        $payload = $this->validPayload();
        $payload['idempotency_key'] = '  INTRANET|UC001|NORMALIZE  ';
        $payload['source_channel'] = ' intranet ';

        $validated = (new InvoicePayloadValidator())->validate($payload);

        Assert::same('INTRANET|UC001|NORMALIZE', $validated['idempotency_key']);
        Assert::same('INTRANET', $validated['source_channel']);
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


    public function testRejectsWhitespaceBillingIdentity(): void
    {
        $payload = $this->validPayload();
        $payload['billing']['nif'] = '   ';

        $exception = Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );

        Assert::same('Missing billing field nif', $exception->getMessage());
    }

    public function testNormalizesBillingIdentityAndLineConcept(): void
    {
        $payload = $this->validPayload();
        $payload['billing']['name'] = '  Client Exemple  ';
        $payload['billing']['nif'] = '  12345678Z  ';
        $payload['lines'][0]['concept'] = '  Curs individual  ';

        $validated = (new InvoicePayloadValidator())->validate($payload);

        Assert::same('Client Exemple', $validated['billing']['name']);
        Assert::same('12345678Z', $validated['billing']['nif']);
        Assert::same('Curs individual', $validated['lines'][0]['concept']);
    }

    public function testRejectsBlankLineConcept(): void
    {
        $payload = $this->validPayload();
        $payload['lines'][0]['concept'] = '   ';

        $exception = Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );

        Assert::same('Invalid invoice line concept 0', $exception->getMessage());
    }

    public function testRejectsInvalidCommercialOperationLineUuid(): void
    {
        $payload = $this->validPayload();
        $payload['lines'][0]['uuid_operation_line'] = 'not-a-uuid';

        Assert::throws(
            SifException::class,
            static fn () => (new InvoicePayloadValidator())->validate($payload),
            422
        );
    }

    public function testRejectsOversizedOrNonCanonicalTraceMetadata(): void
    {
        foreach ([
            ['request_id', str_repeat('R', 121)],
            ['correlation_id', ' CORR '],
            ['actor_role', str_repeat('A', 81)],
            ['actor_type', 'human'],
        ] as [$field, $value]) {
            $payload = $this->validPayload();
            $payload[$field] = $value;

            Assert::throws(
                SifException::class,
                static fn () => (new InvoicePayloadValidator())->validate($payload),
                422
            );
        }
    }

    public function testAcceptsCanonicalTraceMetadata(): void
    {
        $payload = $this->validPayload();
        $payload['request_id'] = '11111111-1111-4111-8111-111111111111';
        $payload['correlation_id'] = 'UC001-TRACE-OK';
        $payload['actor_role'] = 'FACTURACIO';
        $payload['actor_type'] = 'SYSTEM';

        Assert::same($payload, (new InvoicePayloadValidator())->validate($payload));
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
