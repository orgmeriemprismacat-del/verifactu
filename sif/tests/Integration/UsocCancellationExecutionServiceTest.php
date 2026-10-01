<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentCancellationEventRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Service\ManualRefundPayloadBuilder;
use Prisma\Sif\Service\ManualRefundService;
use Prisma\Sif\Service\UsocCancellationExecutionService;
use Prisma\Sif\Service\UsocLifecycleGuardService;
use Prisma\Sif\Service\UsocLifecyclePlanService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocCancellationExecutionServiceTest
{
    public function testCancellationExecutesPerPayerAndRetryDoesNotDuplicateFiscalOrEconomicEffects(): void
    {
        [$db, $student, $entity] = $this->caseWithStudentPaidAndEntityPartiallyPaid();

        $service = $this->service($db);
        $input = $this->executionInput();

        $first = $service->execute(
            $db,
            880,
            980,
            'uc013-cancel-880-1',
            'secretaria-test',
            ['ADMIN'],
            $input
        );
        $retry = $service->execute(
            $db,
            880,
            980,
            'uc013-cancel-880-1',
            'secretaria-test',
            ['ADMIN'],
            $input
        );

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $retry['idempotency_reused']);
        Assert::same(
            $first['payers']['student']['uuid_rectifying_invoice'],
            $retry['payers']['student']['uuid_rectifying_invoice']
        );
        Assert::same(
            $first['payers']['entity']['uuid_refund_payment'],
            $retry['payers']['entity']['uuid_refund_payment']
        );

        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn());
        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM usoc_lifecycle_execution')->fetchColumn());
        Assert::same(2, (int) $db->query(
            "SELECT COUNT(*) FROM operational_event WHERE OPERATION_TYPE LIKE 'USOC_CANCELLATION_%'"
        )->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM enrollment_cancellation_event')->fetchColumn());

        Assert::same('COMPLETED', (string) $db->query(
            'SELECT STATE FROM usoc_lifecycle_execution'
        )->fetchColumn());
        Assert::same('RECTIFIED', (string) $db->query(
            'SELECT ESTAT_FACTURA FROM factura WHERE UUID_FACTURA = ' . $db->quote($student['uuid_factura'])
        )->fetchColumn());
        Assert::same('RECTIFIED', (string) $db->query(
            'SELECT ESTAT_FACTURA FROM factura WHERE UUID_FACTURA = ' . $db->quote($entity['uuid_factura'])
        )->fetchColumn());

        $refundTotal = (string) $db->query(
            "SELECT COALESCE(SUM(IMPORT), 0) FROM payment_transaction WHERE TIPUS_MOVIMENT = 'REFUND'"
        )->fetchColumn();
        Assert::same('85.00', number_format((float) $refundTotal, 2, '.', ''));

        Assert::same('75.00', $first['payers']['student']['refund_amount']);
        Assert::same('10.00', $first['payers']['entity']['refund_amount']);
    }

    public function testCancellationRejectsRefundBeyondEntityRealFundsBeforeAnyMutation(): void
    {
        [$db] = $this->caseWithStudentPaidAndEntityPartiallyPaid();
        $input = $this->executionInput();
        $input['entity']['refund_amount'] = '25.00';

        Assert::throws(SifException::class, function () use ($db, $input): void {
            $this->service($db)->execute(
                $db,
                880,
                980,
                'uc013-cancel-880-overrefund',
                'secretaria-test',
                ['ADMIN'],
                $input
            );
        }, 422);

        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM usoc_lifecycle_execution')->fetchColumn());
    }

    public function testCancellationRequiresReasonWhenRealFundsAreExplicitlyNotRefunded(): void
    {
        [$db] = $this->caseWithStudentPaidAndEntityPartiallyPaid();
        $input = $this->executionInput();
        $input['entity']['economic_action'] = 'NO_REFUND';
        $input['entity']['refund_amount'] = '0.00';
        $input['entity']['refund_reference'] = null;
        $input['entity']['refund_movement_date'] = null;
        $input['entity']['economic_reason'] = '';

        Assert::throws(SifException::class, function () use ($db, $input): void {
            $this->service($db)->execute(
                $db,
                880,
                980,
                'uc013-cancel-880-no-reason',
                'secretaria-test',
                ['ADMIN'],
                $input
            );
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM usoc_lifecycle_execution')->fetchColumn());
    }

    private function caseWithStudentPaidAndEntityPartiallyPaid(): array
    {
        $db = TestDatabase::fresh();

        $student = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:980|ORDER:CANCEL980',
                'source_channel' => 'REDSYS',
                'totals' => [
                    'import_base' => '75.00',
                    'taxable_base' => '75.00',
                    'total' => '75.00',
                ],
                'lines' => [[
                    'unit_price' => '75.00',
                    'base' => '75.00',
                    'import_base' => '75.00',
                    'taxable_base' => '75.00',
                    'total' => '75.00',
                ]],
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 880,
                    'idpag' => 980,
                    'ds_order' => 'CANCEL980',
                    'visible_alumne' => 1,
                ]],
                'payment' => [
                    'idempotency_key' => 'PAYMENT|REDSYS|ORDER:CANCEL980',
                    'movement_type' => 'CHARGE',
                    'method' => 'REDSYS',
                    'source_channel' => 'REDSYS',
                    'amount' => '75.00',
                    'movement_date' => '2026-10-01 10:00:00',
                    'provider_ref' => 'CANCEL980',
                    'ds_order' => 'CANCEL980',
                    'idpag' => 980,
                ],
            ])
        );

        $entity = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'INTRANET|USOC_ENTITAT|ID_INSC:880|CANCEL',
                'source_channel' => 'INTRANET',
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => '25.00',
                    'taxable_base' => '25.00',
                    'total' => '25.00',
                ],
                'lines' => [[
                    'unit_price' => '25.00',
                    'base' => '25.00',
                    'import_base' => '25.00',
                    'taxable_base' => '25.00',
                    'total' => '25.00',
                ]],
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 880,
                    'relation_type' => 'USOC_ENTITY',
                    'idpag' => 980,
                    'visible_alumne' => 0,
                ]],
            ])
        );

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $cases->recordStudentInvoice(
            $db,
            880,
            980,
            $student['uuid_factura'],
            '75.00',
            '25.00',
            'CANCEL980'
        );
        $cases->recordEntityInvoice(
            $db,
            880,
            980,
            $student['uuid_factura'],
            $entity['uuid_factura'],
            '75.00',
            '25.00',
            'CANCEL980'
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|USOC|CANCEL|10',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '10.00',
            'movement_date' => '2026-10-01 11:00:00',
            'reference' => 'USOC-CANCEL-10',
            'allocations' => [[
                'uuid_factura' => $entity['uuid_factura'],
                'amount' => '10.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        return [$db, $student, $entity];
    }

    private function service(\PDO $db): UsocCancellationExecutionService
    {
        $cases = new UsocFinancingCaseRepository(new UuidGenerator());

        return new UsocCancellationExecutionService(
            new UsocLifecyclePlanService(
                $cases,
                new UsocLifecycleGuardService($cases)
            ),
            new UsocLifecycleExecutionRepository(new UuidGenerator()),
            new ManualRectificationService(
                new ManualPaymentInvoiceRepository(),
                new RectificationRepository(),
                new ManualRectificationPayloadBuilder(),
                IssueInvoiceTest::serviceFor($db)
            ),
            new ManualRefundService(
                new ManualPaymentInvoiceRepository(),
                new ManualRefundPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            new OperationalEventRepository(new UuidGenerator()),
            new EnrollmentCancellationEventRepository(new UuidGenerator())
        );
    }

    private function executionInput(): array
    {
        return [
            'reason_code' => 'BAIXA_SOLLICITADA',
            'effective_at' => '2026-10-02 09:00:00',
            'student' => [
                'fiscal_action' => 'RECTIFY',
                'rectification_amount' => '-75.00',
                'rectification_mode' => 'SUBSTITUCIO',
                'rectification_reason' => 'ANULACIO_TOTAL',
                'fiscal_reason' => 'Baixa USOC de la inscripcio',
                'economic_action' => 'REFUND',
                'refund_amount' => '75.00',
                'refund_movement_date' => '2026-10-02 09:30:00',
                'refund_reference' => 'RET-USOC-STUDENT-880',
                'refund_bank' => 'CAIXA',
                'economic_reason' => 'Retorn de fons reals alumne',
            ],
            'entity' => [
                'fiscal_action' => 'RECTIFY',
                'rectification_amount' => '-25.00',
                'rectification_mode' => 'SUBSTITUCIO',
                'rectification_reason' => 'ANULACIO_TOTAL',
                'fiscal_reason' => 'Baixa USOC de la inscripcio',
                'economic_action' => 'REFUND',
                'refund_amount' => '10.00',
                'refund_movement_date' => '2026-10-02 09:35:00',
                'refund_reference' => 'RET-USOC-ENTITY-880',
                'refund_bank' => 'CAIXA',
                'economic_reason' => 'Retorn limitat al cobrament real entitat',
            ],
        ];
    }
}
