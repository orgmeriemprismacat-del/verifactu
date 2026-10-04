<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\JointInvoiceEnrollmentFundAllocationService;
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

    public function testJointInvoicePaymentsPersistExplicitParticipantLedger(): void
    {
        $db = TestDatabase::fresh();

        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC021|MANUAL|JOINT|1',
                'source_channel' => 'INTRANET',
                'emesa_abans_cobrament' => 1,
                'uc004_invoice_before_payment' => 1,
                'totals' => [
                    'import_base' => '200.00',
                    'discount' => '0.00',
                    'taxable_base' => '200.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '200.00',
                ],
                'lines' => [
                    [
                        'concept' => 'Participant 11',
                        'detail' => 'Curs conjunt',
                        'quantity' => '1.00',
                        'unit_price' => '80.00',
                        'base' => '80.00',
                        'import_base' => '80.00',
                        'discount_amount' => '0.00',
                        'taxable_base' => '80.00',
                        'iva_regim' => 'EXEMPT',
                        'iva_pct' => '0.00',
                        'iva_import' => '0.00',
                        'total' => '80.00',
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 11,
                    ],
                    [
                        'concept' => 'Participant 12',
                        'detail' => 'Curs conjunt',
                        'quantity' => '1.00',
                        'unit_price' => '120.00',
                        'base' => '120.00',
                        'import_base' => '120.00',
                        'discount_amount' => '0.00',
                        'taxable_base' => '120.00',
                        'iva_regim' => 'EXEMPT',
                        'iva_pct' => '0.00',
                        'iva_import' => '0.00',
                        'total' => '120.00',
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 12,
                    ],
                ],
                'relations' => [
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 11,
                        'relation_type' => 'ORIGIN',
                        'visible_alumne' => 0,
                    ],
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 12,
                        'relation_type' => 'ORIGIN',
                        'visible_alumne' => 0,
                    ],
                ],
            ])
        );

        $service = $this->service($db, true);

        $first = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '80.00',
            'movement_date' => '2026-10-04 01:00:00',
            'reference' => 'UC021-JOINT-P1',
            'participant_allocations' => [
                11 => '80.00',
            ],
        ]);

        Assert::same('80.00', $first['participant_allocations']['amount']);
        Assert::same(1, $first['participant_allocations']['count']);
        Assert::same('PARTIAL', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura'
        )->fetchColumn());

        $second = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '120.00',
            'movement_date' => '2026-10-04 02:00:00',
            'reference' => 'UC021-JOINT-P2',
            'participant_allocations' => [
                12 => '120.00',
            ],
        ]);

        Assert::same('120.00', $second['participant_allocations']['amount']);
        Assert::same('PAID', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura'
        )->fetchColumn());

        Assert::same(2, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'EXTERNAL_ALLOCATION'"
        )->fetchColumn());
        Assert::same(
            '200.00',
            number_format(
                (float) $db->query(
                    "SELECT SUM(IMPORT) FROM enrollment_fund_movement
                     WHERE UUID_FACTURA = '" . $invoice['uuid_factura'] . "'"
                )->fetchColumn(),
                2,
                '.',
                ''
            )
        );

        $byParticipant = $db->query(
            "SELECT ID_INSC_DESTI, IMPORT
             FROM enrollment_fund_movement
             WHERE UUID_FACTURA = '" . $invoice['uuid_factura'] . "'
             ORDER BY ID_INSC_DESTI"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(11, (int) $byParticipant[0]['ID_INSC_DESTI']);
        Assert::same('80.00', $byParticipant[0]['IMPORT']);
        Assert::same(12, (int) $byParticipant[1]['ID_INSC_DESTI']);
        Assert::same('120.00', $byParticipant[1]['IMPORT']);

        $retry = $service->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '120.00',
            'movement_date' => '2026-10-04 02:00:00',
            'reference' => 'UC021-JOINT-P2',
            'participant_allocations' => [
                12 => '120.00',
            ],
        ]);

        Assert::same(true, $retry['idempotency_reused']);
        Assert::same(true, $retry['participant_allocations']['movements'][0]['idempotency_reused']);
        Assert::same(2, (int) $db->query(
            'SELECT COUNT(*) FROM enrollment_fund_movement'
        )->fetchColumn());
    }

    public function testJointInvoiceParticipantLedgerRejectsAmountMismatch(): void
    {
        $db = TestDatabase::fresh();

        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC021|MANUAL|JOINT|MISMATCH',
                'source_channel' => 'INTRANET',
                'emesa_abans_cobrament' => 1,
                'uc004_invoice_before_payment' => 1,
                'totals' => [
                    'import_base' => '120.00',
                    'taxable_base' => '120.00',
                    'total' => '120.00',
                ],
                'lines' => [[
                    'concept' => 'Participant 21',
                    'detail' => 'Curs conjunt',
                    'quantity' => '1.00',
                    'unit_price' => '120.00',
                    'base' => '120.00',
                    'import_base' => '120.00',
                    'discount_amount' => '0.00',
                    'taxable_base' => '120.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '120.00',
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 21,
                ]],
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 21,
                    'relation_type' => 'ORIGIN',
                    'visible_alumne' => 0,
                ]],
            ])
        );

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->service($db, true)->registerByUuid($db, $invoice['uuid_factura'], [
                'amount' => '120.00',
                'movement_date' => '2026-10-04 03:00:00',
                'reference' => 'UC021-JOINT-MISMATCH',
                'participant_allocations' => [
                    21 => '100.00',
                ],
            ]);
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
    }

    public function testJointInvoiceParticipantLedgerRejectsMalformedAllocationBeforePaymentMutation(): void
    {
        $db = TestDatabase::fresh();

        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC021|MANUAL|JOINT|MALFORMED',
                'source_channel' => 'INTRANET',
                'emesa_abans_cobrament' => 1,
                'uc004_invoice_before_payment' => 1,
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 31,
                    'relation_type' => 'ORIGIN',
                    'visible_alumne' => 0,
                ]],
                'lines' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 31,
                ]],
            ])
        );

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->service($db, true)->registerByUuid($db, $invoice['uuid_factura'], [
                'amount' => '120.00',
                'movement_date' => '2026-10-04 03:15:00',
                'reference' => 'UC021-JOINT-MALFORMED',
                'participant_allocations' => [
                    'not-an-id' => '120.00',
                ],
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
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

    private function service(\PDO $db, bool $withParticipantLedger = false): ManualPaymentService
    {
        return new ManualPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ManualPaymentPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db),
            $withParticipantLedger
                ? new JointInvoiceEnrollmentFundAllocationService(
                    new EnrollmentFundMovementRepository(new UuidGenerator())
                )
                : null
        );
    }
}
