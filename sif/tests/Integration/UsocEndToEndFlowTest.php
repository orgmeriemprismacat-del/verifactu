<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Repository\UsocStudentInvoiceLinkRepository;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysUsocInvoiceService;
use Prisma\Sif\Service\UsocCaseReconciler;
use Prisma\Sif\Service\UsocEntityInvoiceService;
use Prisma\Sif\Service\UsocEntityPaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocEndToEndFlowTest
{
    public function testStudentRetryEntityRetryPartialAndFinalPaymentReachSingleReconciledCase(): void
    {
        $db = TestDatabase::fresh();
        $legacy = new UsocEndToEndLegacySpyPdo([
            $this->inscriptionRow(), $this->courseRow(),
            $this->inscriptionRow(), $this->courseRow(),
            $this->inscriptionRow(), $this->courseRow(),
            $this->inscriptionRow(), $this->courseRow(),
        ]);

        $notifications = new RedsysNotificationRepository();
        $notifications->recordReceived(
            $db,
            'ORDERUSOC-E2E-980',
            980,
            '75.00',
            '0000',
            true,
            ['source' => 'uc-013-e2e'],
            'VALIDATED'
        );

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $studentService = new RedsysUsocInvoiceService(
            $notifications,
            new LegacyUsocSnapshotRepository(),
            new LegacyUsocInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($db),
            $cases
        );

        $studentFirst = $studentService->issueStudentFromValidatedNotification(
            $db,
            $legacy,
            'ORDERUSOC-E2E-980',
            '25.00',
            880
        );
        $studentRetry = $studentService->issueStudentFromValidatedNotification(
            $db,
            $legacy,
            'ORDERUSOC-E2E-980',
            '25.00',
            880
        );

        Assert::same(false, $studentFirst['idempotency_reused']);
        Assert::same(true, $studentRetry['idempotency_reused']);
        Assert::same($studentFirst['uuid_factura'], $studentRetry['uuid_factura']);
        Assert::same($studentFirst['uuid_payment'], $studentRetry['uuid_payment']);

        $caseAfterStudent = $cases->findByInscriptionAndIdpag($db, 880, 980);
        Assert::same('PENDING_ENTITY_INVOICE', $caseAfterStudent['STATUS']);
        Assert::same($studentFirst['uuid_factura'], $caseAfterStudent['UUID_STUDENT_INVOICE']);
        Assert::same(null, $caseAfterStudent['UUID_ENTITY_INVOICE']);

        $entityService = new UsocEntityInvoiceService(
            new LegacyUsocSnapshotRepository(),
            new LegacyUsocInvoicePayloadBuilder(),
            IssueInvoiceTest::serviceFor($db),
            new UsocStudentInvoiceLinkRepository(),
            $cases
        );
        $entityInput = [
            'idpag' => 980,
            'id_insc' => 880,
            'student_amount' => '75.00',
            'amount' => '25.00',
            'student_invoice_uuid' => $studentFirst['uuid_factura'],
            'created_by' => 'uc-013-e2e',
            'correlation_id' => 'UC013-E2E-980',
            'billing' => [
                'name' => 'USOC E2E',
                'nif' => 'G00000000',
                'address' => 'Carrer Entitat 1',
                'cp' => '08001',
                'city' => 'Barcelona',
                'country' => 'ES',
                'email' => 'facturacio-usoc@example.test',
            ],
        ];

        $entityFirst = $entityService->issueEntityFromExplicitInput($db, $legacy, $entityInput);
        $entityRetry = $entityService->issueEntityFromExplicitInput($db, $legacy, $entityInput);

        Assert::same(false, $entityFirst['idempotency_reused']);
        Assert::same(true, $entityRetry['idempotency_reused']);
        Assert::same($entityFirst['uuid_factura'], $entityRetry['uuid_factura']);

        $caseAfterEntity = $cases->findByInscriptionAndIdpag($db, 880, 980);
        Assert::same('ENTITY_INVOICED', $caseAfterEntity['STATUS']);
        Assert::same($entityFirst['uuid_factura'], $caseAfterEntity['UUID_ENTITY_INVOICE']);

        $entityPaymentService = new UsocEntityPaymentService(
            $cases,
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            new UsocCaseReconciler($cases)
        );

        $partial = $entityPaymentService->registerByEntityInvoiceUuid(
            $db,
            $entityFirst['uuid_factura'],
            [
                'amount' => '10.00',
                'movement_date' => '2026-09-30 09:00:00',
                'reference' => 'UC013-E2E-PARTIAL',
                'method' => 'TRANSFERENCIA',
            ]
        );
        Assert::same(false, $partial['reconciliation_pending']);
        Assert::same('ENTITY_PARTIAL', $partial['usoc_case']['STATUS']);
        Assert::same('PARTIAL', $partial['usoc_case']['ENTITY_PAYMENT_STATUS']);

        $completed = $entityPaymentService->registerByEntityInvoiceUuid(
            $db,
            $entityFirst['uuid_factura'],
            [
                'amount' => '15.00',
                'movement_date' => '2026-09-30 10:00:00',
                'reference' => 'UC013-E2E-FINAL',
                'method' => 'TRANSFERENCIA',
            ]
        );

        Assert::same(false, $completed['reconciliation_pending']);
        Assert::same('FINANCING_RECONCILED', $completed['usoc_case']['STATUS']);
        Assert::same('PAID', $completed['usoc_case']['STUDENT_PAYMENT_STATUS']);
        Assert::same('PAID', $completed['usoc_case']['ENTITY_PAYMENT_STATUS']);

        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM usoc_financing_case')->fetchColumn());

        $finalCase = $cases->findByInscriptionAndIdpag($db, 880, 980);
        Assert::same('FINANCING_RECONCILED', $finalCase['STATUS']);
        Assert::same($studentFirst['uuid_factura'], $finalCase['UUID_STUDENT_INVOICE']);
        Assert::same($entityFirst['uuid_factura'], $finalCase['UUID_ENTITY_INVOICE']);
    }

    private function inscriptionRow(): array
    {
        return [
            'ID' => 880,
            'ANY' => 2026,
            'MES' => '06',
            'CURS' => 'COM',
            'NOM' => 'Alumna',
            'COGNOMS' => 'USOC',
            'DNI' => '12345678Z',
            'CORREU' => 'alumna@example.test',
            'ADRECA' => 'Carrer Alumna 10',
            'Codi_Postal' => '08002',
            'Poblacio' => 'Barcelona',
            'FACTURA_RELACIONADA' => 1880,
            'A_PAGAR' => '75.00',
            'PAGAMENT' => 1,
            'IDPAG' => 980,
            'TIPUS_DESC' => 4,
            'VALID_DESC' => 1,
            'FRACCIO' => 0,
            'FRACCIONAT' => 0,
        ];
    }

    private function courseRow(): array
    {
        return [
            'NOM_CURS' => 'Comunicacio assertiva',
            'DATAI' => '2026-06-10',
            'DATAF' => '2026-06-20',
            'HORES' => 12,
        ];
    }
}

final class UsocEndToEndLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;
        return new UsocEndToEndLegacySpyStatement($this);
    }

    public function nextRow(): mixed
    {
        return $this->rows === [] ? false : array_shift($this->rows);
    }

    public function recordParams(?array $params): void
    {
        $this->executedParams[] = $params ?? [];
    }
}

final class UsocEndToEndLegacySpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private UsocEndToEndLegacySpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->db->recordParams($params);
        $this->row = $this->db->nextRow();
        return true;
    }

    public function fetch(
        int $mode = \PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        $row = $this->row;
        $this->row = false;
        return $row;
    }
}
