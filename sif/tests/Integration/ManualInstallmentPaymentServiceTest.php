<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ManualInstallmentPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualInstallmentPaymentService;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
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
            'id_insc' => 10,
            'user' => 'adam',
        ]);
        $repeat = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 10,
            'user' => 'adam',
        ]);
        $second = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '80.00',
            'movement_date' => '2026-06-20',
            'id_insc' => 10,
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
        Assert::same('MANUAL|FRACCIO|ID_INSC:10|DATA:2026-06-12|IMPORT:40.00|USUARI:adam', $keys[0]);
        Assert::same('MANUAL|FRACCIO|ID_INSC:10|DATA:2026-06-20|IMPORT:80.00|USUARI:adam', $keys[1]);

        $allocationType = (string) $db->query('SELECT DISTINCT TIPUS_ASSIGNACIO FROM payment_allocation')->fetchColumn();
        Assert::same('INSTALLMENT_PAYMENT', $allocationType);
    }

    public function testRegistersTwoEqualInstallmentsWhenTheyHaveDifferentEventIdentifiers(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);

        $first = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 10,
            'user' => 'adam',
            'operation_id' => 'BANK-20260612-A',
        ]);
        $second = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 10,
            'user' => 'adam',
            'operation_id' => 'BANK-20260612-B',
        ]);
        $repeat = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 10,
            'user' => 'adam',
            'operation_id' => 'BANK-20260612-A',
        ]);

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(false, $second['idempotency_reused']);
        Assert::same(true, $repeat['idempotency_reused']);
        Assert::same($first['uuid_payment'], $repeat['uuid_payment']);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PARTIAL', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $keys = $db->query('SELECT IDEMPOTENCY_KEY FROM payment_transaction ORDER BY IDEMPOTENCY_KEY')
            ->fetchAll(\PDO::FETCH_COLUMN);
        Assert::same('MANUAL|FRACCIO|EVENT:BANK-20260612-A', $keys[0]);
        Assert::same('MANUAL|FRACCIO|EVENT:BANK-20260612-B', $keys[1]);
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
            'id_insc' => 10,
            'user' => 'pablo',
        ]);

        Assert::same(true, $result['ok']);
        Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
        Assert::same($invoice['num_visible'], $result['num_visible']);
        Assert::same('PARTIAL', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public function testRejectsInstallmentForInscriptionOutsideInvoiceCoverage(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->service($db)->registerByUuid($db, $invoice['uuid_factura'], [
                'amount' => '40.00',
                'movement_date' => '2026-06-12',
                'id_insc' => 999,
                'user' => 'adam',
                'operation_id' => 'BANK-WRONG-INSCRIPTION',
            ]);
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public function testRejectsInstallmentAbovePendingInvoiceBalance(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);

        $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '100.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 10,
            'user' => 'adam',
            'operation_id' => 'BANK-PARTIAL-100',
        ]);

        Assert::throws(SifException::class, function () use ($service, $db, $invoice): void {
            $service->registerByUuid($db, $invoice['uuid_factura'], [
                'amount' => '30.00',
                'movement_date' => '2026-06-13',
                'id_insc' => 10,
                'user' => 'adam',
                'operation_id' => 'BANK-OVERPAY-30',
            ]);
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PARTIAL', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public function testReconcilesExistingTransferInsteadOfCreatingSecondManualCharge(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $transfer = new ManualPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ManualPaymentPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );

        $existing = $transfer->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'reference' => 'TRF-UC023-001',
            'bank' => 'CAIXA',
        ]);

        $result = $this->service($db)->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 10,
            'user' => 'adam',
            'reference' => 'TRF-UC023-001',
            'operation_id' => 'MANUAL-UC023-001',
        ]);

        Assert::same(true, $result['idempotency_reused']);
        Assert::same(true, $result['reconciled_existing']);
        Assert::same($existing['uuid_payment'], $result['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_external_receipt_claim')->fetchColumn());
    }

    public function testRejectsCrossChannelReferenceWhenAmountDiffers(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $transfer = new ManualPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ManualPaymentPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );

        $transfer->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'reference' => 'TRF-UC023-CONFLICT',
            'bank' => 'CAIXA',
        ]);

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->service($db)->registerByUuid($db, $invoice['uuid_factura'], [
                'amount' => '30.00',
                'movement_date' => '2026-06-12',
                'id_insc' => 10,
                'user' => 'adam',
                'reference' => 'TRF-UC023-CONFLICT',
                'operation_id' => 'MANUAL-UC023-CONFLICT',
            ]);
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_external_receipt_claim')->fetchColumn());
    }

    public function testReconcilesExistingRedsysLikeDsOrderInsteadOfCreatingSecondCharge(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $paymentService = RegisterPaymentTest::paymentServiceFor($db);
        $existing = $paymentService->registerPayment([
            'idempotency_key' => 'REDSYS|ORDER:123456789012',
            'movement_type' => 'CHARGE',
            'method' => 'REDSYS',
            'source_channel' => 'REDSYS',
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'ds_order' => '123456789012',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '40.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $result = $this->service($db)->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '40.00',
            'movement_date' => '2026-06-12',
            'id_insc' => 10,
            'user' => 'adam',
            'ds_order' => '123456789012',
            'operation_id' => 'MANUAL-UC023-REDSYS',
        ]);

        Assert::same(true, $result['idempotency_reused']);
        Assert::same(true, $result['reconciled_existing']);
        Assert::same($existing['uuid_payment'], $result['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_external_receipt_claim')->fetchColumn());
    }

    public function testRejectsUnknownInvoiceBeforeRegisteringInstallment(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->registerByUuid($db, 'missing-invoice', [
                'amount' => '40.00',
                'movement_date' => '2026-06-12',
                'id_insc' => 10,
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
