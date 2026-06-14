<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\ManualRefundPayloadBuilder;
use Prisma\Sif\Service\ManualRefundService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualRefundServiceTest
{
    public function testRegistersManualRefundAgainstExistingInvoiceByUuid(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $this->chargeService($db)->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '120.00',
            'movement_date' => '2026-06-10 11:30:00',
            'reference' => 'TRF-REFUND-BASE',
            'bank' => 'CAIXA',
        ]);

        $service = $this->refundService($db);
        $input = [
            'amount' => '40.00',
            'movement_date' => '2026-06-12 12:00:00',
            'reference' => 'RET-001',
            'bank' => 'CAIXA',
            'notes' => 'Devolucio manual parcial',
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
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PARTIALLY_REFUNDED', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $refund = $db->query(
            'SELECT IDEMPOTENCY_KEY, TIPUS_MOVIMENT, METODE, SOURCE_CHANNEL, IMPORT, REFERENCIA_BANCARIA
             FROM payment_transaction
             WHERE TIPUS_MOVIMENT = \'REFUND\''
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REFUND|REF:RET-001', $refund['IDEMPOTENCY_KEY']);
        Assert::same('REFUND', $refund['TIPUS_MOVIMENT']);
        Assert::same('TRANSFERENCIA', $refund['METODE']);
        Assert::same('INTRANET', $refund['SOURCE_CHANNEL']);
        Assert::same('40.00', $refund['IMPORT']);
        Assert::same('RET-001', $refund['REFERENCIA_BANCARIA']);
    }

    public function testRegistersFullRefundByVisibleInvoiceNumber(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'MANUAL|REFUND|FULL',
                'emesa_abans_cobrament' => 1,
            ])
        );
        $this->chargeService($db)->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '120.00',
            'movement_date' => '2026-06-10',
            'reference' => 'TRF-FULL-BASE',
        ]);

        $result = $this->refundService($db)->registerByNumVisible($db, $invoice['num_visible'], [
            'amount' => '120.00',
            'movement_date' => '2026-06-12',
            'reference' => 'RET-FULL-001',
        ]);

        Assert::same(true, $result['ok']);
        Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
        Assert::same($invoice['num_visible'], $result['num_visible']);
        Assert::same('REFUNDED', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public function testRejectsUnknownInvoiceBeforeRegisteringRefund(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->refundService($db)->registerByUuid($db, 'missing-invoice', [
                'amount' => '40.00',
                'movement_date' => '2026-06-12',
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
    }

    private function chargeService(\PDO $db): ManualPaymentService
    {
        return new ManualPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ManualPaymentPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );
    }

    private function refundService(\PDO $db): ManualRefundService
    {
        return new ManualRefundService(
            new ManualPaymentInvoiceRepository(),
            new ManualRefundPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );
    }
}
