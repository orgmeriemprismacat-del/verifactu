<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualPaymentServiceTest
{
    public function testRegistersManualPaymentAgainstExistingInvoiceByUuid(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $input = [
            'amount' => '120.00',
            'movement_date' => '2026-06-10 11:30:00',
            'reference' => 'TRF-EXIST-1',
            'bank' => 'CAIXA',
            'notes' => 'Transferencia validada a Passar pagaments',
        ];

        $first = $service->registerByUuid($db, $invoice['uuid_factura'], $input);
        $second = $service->registerByUuid($db, $invoice['uuid_factura'], $input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same($invoice['uuid_factura'], $first['uuid_factura']);
        Assert::same($invoice['num_visible'], $first['num_visible']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $payment = $db->query('SELECT IDEMPOTENCY_KEY, METODE, SOURCE_CHANNEL, IMPORT, PROVIDER_REF, REFERENCIA_BANCARIA FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('TRANSFERENCIA|REF:TRF-EXIST-1', $payment['IDEMPOTENCY_KEY']);
        Assert::same('TRANSFERENCIA', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('120.00', $payment['IMPORT']);
        Assert::same(null, $payment['PROVIDER_REF']);
        Assert::same('TRF-EXIST-1', $payment['REFERENCIA_BANCARIA']);
    }

    public function testRegistersManualPaymentByVisibleInvoiceNumber(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'MANUAL|FACTURA_ABANS_COBRAMENT|2',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $result = $this->service($db)->registerByNumVisible($db, $invoice['num_visible'], [
            'amount' => '60.00',
            'movement_date' => '2026-06-10',
            'bank' => 'BANC TEST',
        ]);

        Assert::same(true, $result['ok']);
        Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
        Assert::same($invoice['num_visible'], $result['num_visible']);
        Assert::same('PARTIAL', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $paymentKey = (string) $db->query('SELECT IDEMPOTENCY_KEY FROM payment_transaction')->fetchColumn();
        Assert::same(
            'TRANSFERENCIA|FACT:' . $invoice['num_visible'] . '|DATA:2026-06-10|IMPORT:60.00|BANC:BANC_TEST',
            $paymentKey
        );
    }

    public function testRejectsUnknownInvoiceBeforeRegisteringPayment(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->registerByUuid($db, 'missing-invoice', [
                'amount' => '120.00',
                'movement_date' => '2026-06-10',
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
    }

    private function service(\PDO $db): ManualPaymentService
    {
        return new ManualPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ManualPaymentPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );
    }
}
