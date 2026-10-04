<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ExistingInvoiceLegacyProjectionService;
use Prisma\Sif\Service\ExistingInvoicePaymentPreviewService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ExistingInvoicePaymentPreviewServiceTest
{
    public function testPreviewReturnsPendingAmountFromSifLedger(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC002|PREVIEW|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $service = $this->service();

        $initial = $service->preview(
            $db,
            ['num_visible' => $invoice['num_visible']]
        );

        Assert::same('120.00', $initial['invoice']['total']);
        Assert::same('0.00', $initial['invoice']['net_paid']);
        Assert::same('120.00', $initial['invoice']['pending_amount']);
        Assert::same(true, $initial['invoice']['can_register_payment']);

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'UC002|PREVIEW|PAYMENT',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '50.00',
            'movement_date' => '2026-10-04 03:30:00',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '50.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $partial = $service->preview(
            $db,
            ['uuid_factura' => $invoice['uuid_factura']]
        );

        Assert::same('50.00', $partial['invoice']['net_paid']);
        Assert::same('70.00', $partial['invoice']['pending_amount']);
        Assert::same('PARTIAL', $partial['invoice']['estat_cobrament']);
    }

    public function testPreviewByLegacyRelatedInvoice(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC002|LEGACY-REL|PREVIEW',
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 10,
                    'factura_relacionada' => 888,
                    'idpag' => 123,
                    'ds_order' => null,
                    'visible_alumne' => 1,
                ]],
                'emesa_abans_cobrament' => 1,
            ])
        );

        $preview = $this->service()->preview(
            $db,
            ['legacy_factura_relacionada' => 888]
        );

        Assert::same($invoice['uuid_factura'], $preview['invoice']['uuid_factura']);
        Assert::same('120.00', $preview['invoice']['pending_amount']);
    }

    public function testPreviewRejectsAmbiguousSelector(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(
            SifException::class,
            fn (): array => $this->service()->preview($db, [
                'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                'num_visible' => 'A2026/000001',
            ]),
            422
        );
    }

    private function service(): ExistingInvoicePaymentPreviewService
    {
        return new ExistingInvoicePaymentPreviewService(
            new ManualPaymentInvoiceRepository(),
            new ExistingInvoiceLegacyProjectionService()
        );
    }
}
