<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CreditBalanceRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\CreditBalancePayloadBuilder;
use Prisma\Sif\Service\CreditBalanceService;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class CreditBalanceServiceTest
{
    public function testCreatesCreditBalanceWithoutFiscalOrPaymentSideEffects(): void
    {
        $db = TestDatabase::fresh();
        $result = $this->service($db)->createCredit([
            'holder_type' => 'student',
            'holder_id' => 10,
            'holder_nif_cif' => '12345678Z',
            'holder_name' => 'Client Exemple',
            'amount' => '80.00',
            'source_type' => 'BAIXA',
            'source_id' => 44,
            'review_after' => '2031-06-12',
        ]);

        Assert::same(true, $result['ok']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $result['uuid_credit']);
        Assert::same('80.00', $result['import_disponible']);
        Assert::same('ACTIVE', $result['estat']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM credit_balance')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());

        $credit = $db->query(
            'SELECT HOLDER_TYPE, HOLDER_ID, HOLDER_NIF_CIF, HOLDER_NOM_RAO, IMPORT_ORIGINAL,
                    IMPORT_DISPONIBLE, SOURCE_TYPE, SOURCE_ID, REVIEW_AFTER, ESTAT
             FROM credit_balance'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('STUDENT', $credit['HOLDER_TYPE']);
        Assert::same(10, (int) $credit['HOLDER_ID']);
        Assert::same('12345678Z', $credit['HOLDER_NIF_CIF']);
        Assert::same('Client Exemple', $credit['HOLDER_NOM_RAO']);
        Assert::same('80.00', $credit['IMPORT_ORIGINAL']);
        Assert::same('80.00', $credit['IMPORT_DISPONIBLE']);
        Assert::same('BAIXA', $credit['SOURCE_TYPE']);
        Assert::same(44, (int) $credit['SOURCE_ID']);
        Assert::same('2031-06-12', $credit['REVIEW_AFTER']);
        Assert::same('ACTIVE', $credit['ESTAT']);
    }

    public function testAppliesCreditAsCompensationAndConsumesAvailableBalanceOnce(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $credit = $service->createCredit([
            'holder_type' => 'STUDENT',
            'holder_id' => 10,
            'holder_name' => 'Client Exemple',
            'amount' => '80.00',
            'source_type' => 'CANVI_CURS',
            'source_id' => 77,
            'uuid_factura_origen' => $invoice['uuid_factura'],
        ]);
        $input = [
            'amount' => '60.00',
            'movement_date' => '2026-06-12',
            'notes' => 'Aplicacio parcial de saldo',
        ];

        $first = $service->applyCreditByUuid($credit['uuid_credit'], $invoice['uuid_factura'], $input);
        $second = $service->applyCreditByUuid($credit['uuid_credit'], $invoice['uuid_factura'], $input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same($credit['uuid_credit'], $first['uuid_credit']);
        Assert::same($invoice['uuid_factura'], $first['uuid_factura']);
        Assert::same($invoice['num_visible'], $first['num_visible']);
        Assert::same('20.00', $first['import_disponible']);
        Assert::same('ACTIVE', $first['credit_estat']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same('PARTIAL', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
        Assert::same('20.00', (string) $db->query('SELECT IMPORT_DISPONIBLE FROM credit_balance')->fetchColumn());

        $payment = $db->query(
            'SELECT IDEMPOTENCY_KEY, TIPUS_MOVIMENT, METODE, SOURCE_CHANNEL, IMPORT, PROVIDER_REF
             FROM payment_transaction'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same(
            'COMPENSACIO|UUID_CREDIT:' . $credit['uuid_credit'] . '|FACT:' . $invoice['num_visible'] . '|IMPORT:60.00',
            $payment['IDEMPOTENCY_KEY']
        );
        Assert::same('COMPENSATION', $payment['TIPUS_MOVIMENT']);
        Assert::same('COMPENSACIO', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('60.00', $payment['IMPORT']);
        Assert::same($credit['uuid_credit'], $payment['PROVIDER_REF']);

        $allocationType = (string) $db->query('SELECT TIPUS_ASSIGNACIO FROM payment_allocation')->fetchColumn();
        Assert::same('CREDIT_COMPENSATION', $allocationType);
    }

    public function testAppliesFullCreditByVisibleInvoiceNumberAndMarksCreditUsed(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'CREDIT|FULL|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );
        $service = $this->service($db);
        $credit = $service->createCredit([
            'holder_type' => 'STUDENT',
            'holder_name' => 'Client Exemple',
            'amount' => '120.00',
            'source_type' => 'BAIXA',
        ]);

        $input = [
            'amount' => '120.00',
            'movement_date' => '2026-06-12',
        ];

        $result = $service->applyCreditByNumVisible($credit['uuid_credit'], $invoice['num_visible'], $input);
        $repeat = $service->applyCreditByNumVisible($credit['uuid_credit'], $invoice['num_visible'], $input);

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::same(true, $repeat['idempotency_reused']);
        Assert::same($result['uuid_payment'], $repeat['uuid_payment']);
        Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
        Assert::same($invoice['num_visible'], $result['num_visible']);
        Assert::same('0.00', $result['import_disponible']);
        Assert::same('USED', $result['credit_estat']);
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
        Assert::same('USED', (string) $db->query('SELECT ESTAT FROM credit_balance')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRejectsApplyingMoreThanAvailableCredit(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $credit = $service->createCredit([
            'holder_type' => 'STUDENT',
            'holder_name' => 'Client Exemple',
            'amount' => '50.00',
            'source_type' => 'BAIXA',
        ]);

        Assert::throws(SifException::class, static function () use ($service, $credit, $invoice): void {
            $service->applyCreditByUuid($credit['uuid_credit'], $invoice['uuid_factura'], [
                'amount' => '60.00',
                'movement_date' => '2026-06-12',
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same('50.00', (string) $db->query('SELECT IMPORT_DISPONIBLE FROM credit_balance')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public function testRejectsApplyingMoreThanInvoiceOutstandingAmount(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $service = $this->service($db);
        $credit = $service->createCredit([
            'holder_type' => 'STUDENT',
            'holder_name' => 'Client Exemple',
            'amount' => '200.00',
            'source_type' => 'BAIXA',
        ]);

        Assert::throws(SifException::class, static function () use ($service, $credit, $invoice): void {
            $service->applyCreditByUuid($credit['uuid_credit'], $invoice['uuid_factura'], [
                'amount' => '130.00',
                'movement_date' => '2026-06-12',
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same('200.00', (string) $db->query('SELECT IMPORT_DISPONIBLE FROM credit_balance')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    private function service(\PDO $db): CreditBalanceService
    {
        return new CreditBalanceService(
            new TransactionRunner($db),
            new CreditBalanceRepository(new UuidGenerator()),
            new ManualPaymentInvoiceRepository(),
            new CreditBalancePayloadBuilder(),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );
    }
}
