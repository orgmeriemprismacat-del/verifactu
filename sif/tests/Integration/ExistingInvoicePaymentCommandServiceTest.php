<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ExistingInvoicePaymentCommandService;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ExistingInvoicePaymentCommandServiceTest
{
    public function testRegistersExistingInvoiceByVisibleNumberWithExplicitIdempotency(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $service = $this->service($db);
        $command = [
            'selector' => ['num_visible' => $invoice['num_visible']],
            'payment' => [
                'idempotency_key' => 'INTRANET|UC002|REQ:11111111-1111-4111-8111-111111111111',
                'amount' => '120.00',
                'movement_date' => '2026-10-04 03:30:00',
                'bank' => 'CAIXA',
                'notes' => 'Pagament manual intranet',
            ],
        ];

        $first = $service->register($db, $command);
        $second = $service->register($db, $command);

        Assert::same(true, $first['ok']);
        Assert::same(true, $first['payment_committed']);
        Assert::same('register_existing_invoice', $first['action']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same($invoice['uuid_factura'], $first['uuid_factura']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRegistersExistingInvoiceByLegacyRelatedInvoice(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC002|LEGACY-REL|INVOICE',
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 10,
                    'factura_relacionada' => 777,
                    'idpag' => 123,
                    'ds_order' => null,
                    'visible_alumne' => 1,
                ]],
                'emesa_abans_cobrament' => 1,
            ])
        );

        $result = $this->service($db)->register($db, [
            'selector' => ['legacy_factura_relacionada' => 777],
            'payment' => [
                'idempotency_key' => 'INTRANET|UC002|REQ:77777777-7777-4777-8777-777777777777',
                'amount' => '120.00',
                'movement_date' => '2026-10-04 04:20:00',
                'bank' => 'CAIXA',
            ],
        ]);

        Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
        Assert::same(false, $result['idempotency_reused']);
    }

    public function testRejectsMissingExplicitIdempotencyKey(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        Assert::throws(
            SifException::class,
            fn (): array => $this->service($db)->register($db, [
                'selector' => ['uuid_factura' => $invoice['uuid_factura']],
                'payment' => [
                    'amount' => '120.00',
                    'movement_date' => '2026-10-04 03:30:00',
                ],
            ]),
            422
        );
    }

    public function testRejectsAmbiguousInvoiceSelector(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(
            SifException::class,
            fn (): array => $this->service($db)->register($db, [
                'selector' => [
                    'uuid_factura' => '11111111-1111-4111-8111-111111111111',
                    'num_visible' => 'A2026/000001',
                ],
                'payment' => [
                    'idempotency_key' => 'INTRANET|UC002|REQ:22222222-2222-4222-8222-222222222222',
                    'amount' => '120.00',
                    'movement_date' => '2026-10-04 03:30:00',
                ],
            ]),
            422
        );
    }

    private function service(\PDO $db): ExistingInvoicePaymentCommandService
    {
        return new ExistingInvoicePaymentCommandService(
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            )
        );
    }
}
