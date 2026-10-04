<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\GroupParticipantAcademicGatewayInterface;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\GroupParticipantChangeExecutionRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Service\EnrollmentFundDispositionService;
use Prisma\Sif\Service\GroupEnrollmentFundAllocationService;
use Prisma\Sif\Service\GroupParticipantChangeFingerprint;
use Prisma\Sif\Service\GroupParticipantRemovalCoordinator;
use Prisma\Sif\Service\GroupParticipantRemovalDecisionService;
use Prisma\Sif\Service\GroupParticipantRemovalPreviewService;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Service\ManualRefundPayloadBuilder;
use Prisma\Sif\Service\ManualRefundService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GroupParticipantRemovalCoordinatorTest
{
    public function testConfirmIsResumableAndDoesNotDuplicateFiscalOrEconomicEffects(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            $this->groupInvoicePayload()
        );

        $fundRepository = new EnrollmentFundMovementRepository(new UuidGenerator());
        (new GroupEnrollmentFundAllocationService($fundRepository))->allocate(
            $db,
            'ORDER-GROUP-REMOVE-1',
            $this->snapshot(),
            $invoice
        );

        $academic = new GroupParticipantAcademicSpy();
        $coordinator = $this->coordinator($db, $fundRepository, $academic);
        $preview = $coordinator->preview($db, $invoice['uuid_factura'], 751);

        Assert::same('120.00', $preview['participant']['funds_attributed']);

        $decision = [
            'repricing_policy' => 'KEEP_EXISTING_MEMBER_PRICES',
            'fiscal_action' => 'RECTIFY_PARTICIPANT_ONLY',
            'rectification_amount' => '-120.00',
            'refund_amount' => '120.00',
            'refund_reference' => 'RET-UC016B-751',
            'refund_movement_date' => '2030-10-02 12:00:00',
            'credit_amount' => '0.00',
            'non_refundable_amount' => '0.00',
            'operation_reference' => 'UC016B-751-REMOVE',
        ];
        $context = [
            'actor_id' => 'meriem-test',
            'correlation_id' => 'UC016B-CORR-751',
            'idempotency_key' => 'UC016B|REMOVE|FACT:GROUP1|INSC:751',
        ];

        $first = $coordinator->confirm(
            $db,
            new GroupParticipantLegacyDummyPdo(),
            $invoice['uuid_factura'],
            751,
            $preview['fingerprint'],
            $decision,
            $context
        );

        Assert::same(true, $first['ok']);
        Assert::same('COMPLETED', $first['status']);
        Assert::same(1, $academic->removeCalls);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query("SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='REFUND'")->fetchColumn());
        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
        Assert::same('0.00', $fundRepository->attributedBalanceForEnrollment(
            $db,
            751,
            $invoice['uuid_factura']
        ));

        $second = $coordinator->confirm(
            $db,
            new GroupParticipantLegacyDummyPdo(),
            $invoice['uuid_factura'],
            751,
            $preview['fingerprint'],
            $decision,
            $context
        );

        Assert::same(true, $second['ok']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, $academic->removeCalls);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(3, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM group_participant_change_execution')->fetchColumn());
        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM group_participant_change_step')->fetchColumn());
    }

    private function coordinator(
        \PDO $db,
        EnrollmentFundMovementRepository $funds,
        GroupParticipantAcademicSpy $academic
    ): GroupParticipantRemovalCoordinator {
        return new GroupParticipantRemovalCoordinator(
            new GroupParticipantRemovalPreviewService($funds),
            new GroupParticipantRemovalDecisionService(),
            new GroupParticipantChangeFingerprint(),
            new GroupParticipantChangeExecutionRepository(new UuidGenerator()),
            $academic,
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
            new EnrollmentFundDispositionService($funds)
        );
    }

    private function groupInvoicePayload(): array
    {
        return Fixtures::invoicePayload([
            'idempotency_key' => 'UC016B|ORIGINAL|GROUP1',
            'billing' => [
                'name' => 'Responsable Grup',
                'nif' => '44444444G',
                'email' => 'responsable@example.test',
            ],
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
                $this->line(751, 'Anna Participant', '120.00'),
                $this->line(752, 'Biel Participant', '80.00'),
            ],
            'relations' => [
                [
                    'source_type' => 'GRUP',
                    'source_id' => 950,
                    'idpag' => 950,
                    'ds_order' => 'ORDER-GROUP-REMOVE-1',
                    'visible_alumne' => 0,
                ],
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 751,
                    'idpag' => 950,
                    'ds_order' => 'ORDER-GROUP-REMOVE-1',
                    'visible_alumne' => 0,
                ],
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 752,
                    'idpag' => 950,
                    'ds_order' => 'ORDER-GROUP-REMOVE-1',
                    'visible_alumne' => 0,
                ],
            ],
            'payment' => [
                'idempotency_key' => 'PAYMENT|UC016B|GROUP1',
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'REDSYS',
                'amount' => '200.00',
                'movement_date' => '2030-10-01 10:00:00',
                'ds_order' => 'ORDER-GROUP-REMOVE-1',
                'idpag' => 950,
            ],
        ]);
    }

    private function line(int $id, string $name, string $amount): array
    {
        return [
            'concept' => 'Comunicacio assertiva - ' . $name,
            'quantity' => '1.00',
            'unit_price' => $amount,
            'base' => $amount,
            'import_base' => $amount,
            'discount_amount' => '0.00',
            'taxable_base' => $amount,
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $amount,
            'source_type' => 'INSCRIPCIO',
            'source_id' => $id,
        ];
    }

    private function snapshot(): array
    {
        return [
            'payment' => [
                'idpag' => 950,
                'amount' => '200.00',
            ],
            'items' => [
                ['inscription' => ['ID' => 751, 'TOTAL' => '120.00']],
                ['inscription' => ['ID' => 752, 'TOTAL' => '80.00']],
            ],
        ];
    }
}

final class GroupParticipantAcademicSpy implements GroupParticipantAcademicGatewayInterface
{
    public int $removeCalls = 0;

    public function addParticipant(\PDO $legacyDb, int $idInsc, array $context): array
    {
        return ['ok' => true, 'id_insc' => $idInsc, 'action' => 'ADD'];
    }

    public function removeParticipant(\PDO $legacyDb, int $idInsc, array $context): array
    {
        $this->removeCalls++;

        return [
            'ok' => true,
            'id_insc' => $idInsc,
            'action' => 'REMOVE',
            'uuid_execution' => $context['uuid_execution'] ?? null,
        ];
    }
}

final class GroupParticipantLegacyDummyPdo extends \PDO
{
    public function __construct()
    {
    }
}
