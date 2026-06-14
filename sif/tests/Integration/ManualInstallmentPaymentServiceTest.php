<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ManualInstallmentPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualInstallmentPaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualInstallmentPaymentServiceTest
{
    public function testRegistersInstallmentsAgainstExistingInvoiceWithoutDuplicatingFiscalRecord(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);

        $first = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 77,
            'user' => 'adam',
        ]);
        $repeat = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 77,
            'user' => 'adam',
        ]);
        $second = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '80.00',
            'movement_date' => '2026-06-20',
            'id_insc' => 77,
            'user' => 'adam',
        ]);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $repeat['idempotency_reused']);
        Assert::same($first['uuid_payment'], $repeat['uuid_payment']);
        Assert::same(true, $second['ok']);
        Assert::same(false, $second['idempotency_reused']);
        Assert::same($invoice['uuid_factura'], $first['uuid_factura']);
        Assert::same($invoice['num_visible'], $first['num_visible']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $keys = $db->query('SELECT IDEMPOTENCY_KEY FROM payment_transaction ORDER BY DATA_MOVIMENT')
            ->fetchAll(\PDO::FETCH_COLUMN);
        Assert::same('MANUAL|FRACCIO|ID_INSC:77|DATA:2026-06-12|IMPORT:40.00|USUARI:adam', $keys[0]);
        Assert::same('MANUAL|FRACCIO|ID_INSC:77|DATA:2026-06-20|IMPORT:80.00|USUARI:adam', $keys[1]);

        $allocationType = (string) $db->query('SELECT DISTINCT TIPUS_ASSIGNACIO FROM payment_allocation')->fetchColumn();
        Assert::same('INSTALLMENT_PAYMENT', $allocationType);
    }

    public function testRegistersInstallmentByVisibleInvoiceNumber(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'MANUAL|FRACCIO|NUM_VISIBLE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $result = $this->service($db)->registerByNumVisible($db, $invoice['num_visible'], [
            'amount' => '60.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 88,
            'user' => 'pablo',
        ]);

        Assert::same(true, $result['ok']);
        Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
        Assert::same($invoice['num_visible'], $result['num_visible']);
        Assert::same('PARTIAL', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public function testRejectsUnknownInvoiceBeforeRegisteringInstallment(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->registerByUuid($db, 'missing-invoice', [
                'amount' => '40.00',
                'movement_date' => '2026-06-12',
                'id_insc' => 77,
                'user' => 'adam',
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
    }

    private function service(\PDO $db): ManualInstallmentPaymentService
    {
        return new ManualInstallmentPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ManualInstallmentPaymentPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );
    }
}
