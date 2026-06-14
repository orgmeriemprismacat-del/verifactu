<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\ClaimPaymentPayloadBuilder;
use Prisma\Sif\Service\ClaimPaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ClaimPaymentServiceTest
{
    public function testRegistersClaimPaymentAgainstExistingInvoiceWithoutFiscalIssue(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $input = [
            'amount' => '120.00',
            'movement_date' => '2026-06-13 12:00:00',
            'claim_reference' => 'REC-2026-010',
            'bank' => 'CAIXA',
            'created_by' => 'admin-cobraments',
            'notes' => 'Cobrament despres de reclamacio',
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
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $payment = $db->query('SELECT IDEMPOTENCY_KEY, METODE, SOURCE_CHANNEL, IMPORT, REFERENCIA_BANCARIA FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);
        $allocationType = (string) $db->query('SELECT TIPUS_ASSIGNACIO FROM payment_allocation')->fetchColumn();

        Assert::same('CLAIM|REF:REC-2026-010', $payment['IDEMPOTENCY_KEY']);
        Assert::same('TRANSFERENCIA', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('120.00', $payment['IMPORT']);
        Assert::same('REC-2026-010', $payment['REFERENCIA_BANCARIA']);
        Assert::same('CLAIM_PAYMENT', $allocationType);
    }

    public function testRegistersClaimPaymentByVisibleInvoiceNumber(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'MANUAL|FACTURA_ABANS_COBRAMENT|CLAIM',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $result = $this->service($db)->registerByNumVisible($db, $invoice['num_visible'], [
            'amount' => '60.00',
            'movement_date' => '2026-06-13',
            'created_by' => 'admin-cobraments',
        ]);

        Assert::same(true, $result['ok']);
        Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
        Assert::same($invoice['num_visible'], $result['num_visible']);
        Assert::same('PARTIAL', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $paymentKey = (string) $db->query('SELECT IDEMPOTENCY_KEY FROM payment_transaction')->fetchColumn();
        Assert::same(
            'CLAIM|FACT:' . $invoice['num_visible'] . '|DATA:2026-06-13|IMPORT:60.00|USUARI:admin-cobraments',
            $paymentKey
        );
    }

    public function testRejectsUnknownInvoiceBeforeRegisteringClaimPayment(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->registerByUuid($db, 'missing-invoice', [
                'amount' => '120.00',
                'movement_date' => '2026-06-13',
                'claim_reference' => 'REC-MISSING',
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
    }

    private function service(\PDO $db): ClaimPaymentService
    {
        return new ClaimPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ClaimPaymentPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );
    }
}
