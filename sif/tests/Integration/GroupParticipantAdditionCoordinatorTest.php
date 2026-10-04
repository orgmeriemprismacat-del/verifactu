<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\GroupParticipantAcademicGatewayInterface;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\GroupParticipantChangeExecutionRepository;
use Prisma\Sif\Service\GroupParticipantAdditionCoordinator;
use Prisma\Sif\Service\GroupParticipantAdditionDecisionService;
use Prisma\Sif\Service\GroupParticipantAdditionPreviewService;
use Prisma\Sif\Service\GroupParticipantChangeFingerprint;
use Prisma\Sif\Service\GroupParticipantSupplementalInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GroupParticipantAdditionCoordinatorTest
{
    public function testConfirmStagesAcademicAdditionOnceAndWaitsForFiscalExecutor(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC016A|ORIGINAL|GROUP1',
                'billing' => [
                    'name' => 'Responsable Grup',
                    'nif' => '44444444G',
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
                    $this->line(751, 'Anna', '120.00'),
                    $this->line(752, 'Biel', '80.00'),
                ],
                'relations' => [
                    [
                        'source_type' => 'GRUP',
                        'source_id' => 950,
                        'idpag' => 950,
                        'visible_alumne' => 0,
                    ],
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 751,
                        'idpag' => 950,
                        'visible_alumne' => 0,
                    ],
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 752,
                        'idpag' => 950,
                        'visible_alumne' => 0,
                    ],
                ],
            ])
        );

        $candidate = [
            'id_insc' => 753,
            'idpag' => 950,
            'concept' => 'Comunicacio assertiva - Carla',
            'base' => '100.00',
            'discount' => '20.00',
            'total' => '80.00',
        ];
        $decision = [
            'repricing_policy' => 'KEEP_EXISTING_MEMBER_PRICES',
            'fiscal_action' => 'SUPPLEMENTAL_INVOICE_PARTICIPANT',
            'operation_reference' => 'UC016A-753',
        ];
        $context = [
            'actor_id' => 'meriem-test',
            'correlation_id' => 'UC016A-CORR-753',
            'idempotency_key' => 'UC016A|ADD|FACT:GROUP1|INSC:753',
        ];

        $academic = new GroupParticipantAdditionAcademicSpy();
        $coordinator = new GroupParticipantAdditionCoordinator(
            new GroupParticipantAdditionPreviewService(),
            new GroupParticipantAdditionDecisionService(),
            new GroupParticipantChangeFingerprint(),
            new GroupParticipantChangeExecutionRepository(new UuidGenerator()),
            $academic
        );

        $preview = $coordinator->preview($db, $invoice['uuid_factura'], $candidate);
        $first = $coordinator->confirm(
            $db,
            new GroupParticipantAdditionLegacyDummyPdo(),
            $invoice['uuid_factura'],
            $candidate,
            $preview['fingerprint'],
            $decision,
            $context
        );

        Assert::same(false, $first['ok']);
        Assert::same('WAITING_EXTERNAL', $first['status']);
        Assert::same('SUPPLEMENTAL_INVOICE', $first['waiting_step']);
        Assert::same(1, $academic->addCalls);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM group_participant_change_execution')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM group_participant_change_step')->fetchColumn());

        $second = $coordinator->confirm(
            $db,
            new GroupParticipantAdditionLegacyDummyPdo(),
            $invoice['uuid_factura'],
            $candidate,
            $preview['fingerprint'],
            $decision,
            $context
        );

        Assert::same(false, $second['ok']);
        Assert::same('WAITING_EXTERNAL', $second['status']);
        Assert::same(1, $academic->addCalls);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM group_participant_change_execution')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM group_participant_change_step')->fetchColumn());
    }

    public function testConfirmCompletesWithConfiguredSupplementalInvoiceExecutor(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC016A|ORIGINAL|GROUP2',
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
                    $this->line(751, 'Anna', '120.00'),
                    $this->line(752, 'Biel', '80.00'),
                ],
                'relations' => [
                    [
                        'source_type' => 'GRUP',
                        'source_id' => 950,
                        'idpag' => 950,
                        'visible_alumne' => 0,
                    ],
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 751,
                        'idpag' => 950,
                        'visible_alumne' => 0,
                    ],
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 752,
                        'idpag' => 950,
                        'visible_alumne' => 0,
                    ],
                ],
            ])
        );

        $candidate = [
            'id_insc' => 753,
            'idpag' => 950,
            'concept' => 'Comunicacio assertiva - Carla',
            'base' => '100.00',
            'discount' => '20.00',
            'total' => '80.00',
        ];
        $decision = [
            'repricing_policy' => 'KEEP_EXISTING_MEMBER_PRICES',
            'fiscal_action' => 'SUPPLEMENTAL_INVOICE_PARTICIPANT',
            'operation_reference' => 'UC016A-753-GROUP2',
        ];
        $context = [
            'actor_id' => 'meriem-test',
            'actor_role' => 'GESTIO',
            'correlation_id' => 'UC016A-CORR-753-GROUP2',
            'idempotency_key' => 'UC016A|ADD|FACT:GROUP2|INSC:753',
        ];

        $academic = new GroupParticipantAdditionAcademicSpy();
        $coordinator = new GroupParticipantAdditionCoordinator(
            new GroupParticipantAdditionPreviewService(),
            new GroupParticipantAdditionDecisionService(),
            new GroupParticipantChangeFingerprint(),
            new GroupParticipantChangeExecutionRepository(new UuidGenerator()),
            $academic,
            new GroupParticipantSupplementalInvoiceService(
                IssueInvoiceTest::serviceFor($db)
            )
        );

        $preview = $coordinator->preview($db, $invoice['uuid_factura'], $candidate);
        $first = $coordinator->confirm(
            $db,
            new GroupParticipantAdditionLegacyDummyPdo(),
            $invoice['uuid_factura'],
            $candidate,
            $preview['fingerprint'],
            $decision,
            $context
        );

        Assert::same(true, $first['ok']);
        Assert::same('COMPLETED', $first['status']);
        Assert::same(1, $academic->addCalls);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM group_participant_change_step')->fetchColumn());

        $supplemental = $db->query(
            "SELECT f.TOTAL, f.ESTAT_COBRAMENT, fl.SOURCE_TYPE, fl.SOURCE_ID
             FROM factura f
             JOIN factura_linia fl ON fl.UUID_FACTURA=f.UUID_FACTURA
             WHERE f.UUID_FACTURA <> " . $db->quote($invoice['uuid_factura'])
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('80.00', $supplemental['TOTAL']);
        Assert::same('PENDING', $supplemental['ESTAT_COBRAMENT']);
        Assert::same('INSCRIPCIO', $supplemental['SOURCE_TYPE']);
        Assert::same(753, (int) $supplemental['SOURCE_ID']);

        $second = $coordinator->confirm(
            $db,
            new GroupParticipantAdditionLegacyDummyPdo(),
            $invoice['uuid_factura'],
            $candidate,
            $preview['fingerprint'],
            $decision,
            $context
        );

        Assert::same(true, $second['ok']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, $academic->addCalls);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM group_participant_change_step')->fetchColumn());
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
}

final class GroupParticipantAdditionAcademicSpy implements GroupParticipantAcademicGatewayInterface
{
    public int $addCalls = 0;

    public function addParticipant(\PDO $legacyDb, int $idInsc, array $context): array
    {
        $this->addCalls++;

        return [
            'ok' => true,
            'id_insc' => $idInsc,
            'action' => 'ADD',
            'uuid_execution' => $context['uuid_execution'] ?? null,
        ];
    }

    public function removeParticipant(\PDO $legacyDb, int $idInsc, array $context): array
    {
        return ['ok' => true, 'id_insc' => $idInsc, 'action' => 'REMOVE'];
    }
}

final class GroupParticipantAdditionLegacyDummyPdo extends \PDO
{
    public function __construct()
    {
    }
}
