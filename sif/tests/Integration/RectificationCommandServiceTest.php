<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Service\FiscalCorrectionDecisionGuard;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Service\PayloadIdempotencyValidator;
use Prisma\Sif\Service\RectificationCommandService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RectificationCommandServiceTest
{
    public function testPreviewAndConfirmPersistAuditAndOperationalTrace(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'RECTIFICATION|COMMAND|ORIGINAL',
        ]));
        $commands = $this->commands($db);
        $input = $this->input();
        $classification = $this->classification();

        $preview = $commands->preview(
            $this->actor('11111111-1111-4111-8111-111111111111'),
            $original['uuid_factura'],
            $input,
            $classification,
            ['correlation_id' => 'uc005-command-1']
        );

        Assert::same(true, $preview['ok']);
        Assert::same('preview', $preview['action']);
        Assert::matchesRegularExpression('/^[a-f0-9]{64}$/', $preview['fingerprint']);
        Assert::same(1, (int) $db->query(
            'SELECT COUNT(*) FROM sif_audit_event WHERE ACTION = "RECTIFICATION_PREVIEW" AND RESULT = "SUCCEEDED"'
        )->fetchColumn());

        $confirmed = $commands->confirm(
            $this->actor('22222222-2222-4222-8222-222222222222'),
            $original['uuid_factura'],
            $input,
            $classification,
            $preview['fingerprint'],
            ['correlation_id' => 'uc005-command-1']
        );

        Assert::same(true, $confirmed['ok']);
        Assert::same('confirm', $confirmed['action']);
        Assert::same(true, $confirmed['fingerprint_verified']);
        Assert::same('RECTIFICATION', $confirmed['classification']['decision']);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn());
        Assert::same(1, (int) $db->query(
            'SELECT COUNT(*) FROM operational_event WHERE OPERATION_TYPE = "RECTIFICATION"'
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            'SELECT COUNT(*) FROM sif_audit_event WHERE ACTION = "RECTIFICATION_CONFIRM" AND RESULT = "REQUESTED"'
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            'SELECT COUNT(*) FROM sif_audit_event WHERE ACTION = "RECTIFICATION_CONFIRM" AND RESULT = "SUCCEEDED"'
        )->fetchColumn());
        Assert::same('COMMITTED', (string) $db->query(
            'SELECT STATUS FROM operational_event LIMIT 1'
        )->fetchColumn());
    }

    public function testEquivalentRetryReusesSameRectificationAfterOriginalBecomesRectified(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'RECTIFICATION|COMMAND|RETRY',
        ]));
        $commands = $this->commands($db);
        $input = $this->input();
        $classification = $this->classification();

        $firstPreview = $commands->preview(
            $this->actor('55555555-5555-4555-8555-555555555555'),
            $original['uuid_factura'],
            $input,
            $classification
        );
        $first = $commands->confirm(
            $this->actor('66666666-6666-4666-8666-666666666666'),
            $original['uuid_factura'],
            $input,
            $classification,
            $firstPreview['fingerprint']
        );

        $retryPreview = $commands->preview(
            $this->actor('77777777-7777-4777-8777-777777777777'),
            $original['uuid_factura'],
            $input,
            $classification
        );
        Assert::same($firstPreview['fingerprint'], $retryPreview['fingerprint']);

        $retry = $commands->confirm(
            $this->actor('88888888-8888-4888-8888-888888888888'),
            $original['uuid_factura'],
            $input,
            $classification,
            $retryPreview['fingerprint']
        );

        Assert::same(true, $retry['idempotency_reused']);
        Assert::same($first['uuid_factura'], $retry['uuid_factura']);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn());
        Assert::same(1, (int) $db->query(
            'SELECT COUNT(*) FROM operational_event WHERE STATUS = "REUSED"'
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            'SELECT COUNT(*) FROM sif_audit_event WHERE ACTION = "RECTIFICATION_CONFIRM" AND RESULT = "REUSED"'
        )->fetchColumn());
    }

    public function testConfirmRejectsChangedPayloadBeforeIssuing(): void
    {
        $db = TestDatabase::fresh();
        $original = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'RECTIFICATION|COMMAND|CONFLICT',
        ]));
        $commands = $this->commands($db);

        $preview = $commands->preview(
            $this->actor('33333333-3333-4333-8333-333333333333'),
            $original['uuid_factura'],
            $this->input(),
            $this->classification()
        );

        $changed = $this->input();
        $changed['amount'] = '-30.00';

        Assert::throws(\Prisma\Sif\Exception\SifException::class, function () use (
            $commands,
            $original,
            $preview,
            $changed
        ): void {
            $commands->confirm(
                $this->actor('44444444-4444-4444-8444-444444444444'),
                $original['uuid_factura'],
                $changed,
                $this->classification(),
                $preview['fingerprint']
            );
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn());
    }

    private function commands(\PDO $db): RectificationCommandService
    {
        $invoices = new ManualPaymentInvoiceRepository();
        $builder = new ManualRectificationPayloadBuilder();

        return new RectificationCommandService(
            $db,
            $invoices,
            $builder,
            new ManualRectificationService(
                $invoices,
                new RectificationRepository(),
                $builder,
                IssueInvoiceTest::serviceFor($db)
            ),
            new FiscalCorrectionDecisionGuard(),
            new PayloadIdempotencyValidator(),
            new SifAuditEventRepository(new UuidGenerator()),
            new OperationalEventRepository(new UuidGenerator()),
            'test'
        );
    }

    private function actor(string $requestId): array
    {
        return [
            'actor_id' => 'operator-1',
            'roles' => ['FACTURACIO'],
            'request_id' => $requestId,
            'source_channel' => 'INTERNAL_API',
            'rectification_role' => 'FACTURACIO',
            'rectification_scope' => [
                'preview' => true,
                'issue' => true,
            ],
        ];
    }

    private function input(): array
    {
        return [
            'amount' => '-40.00',
            'reason' => 'DEVOLUCIO_PARCIAL',
            'mode' => 'DIFERENCIES',
            'concept' => 'Rectificacio parcial curs',
            'detail' => 'Retorn parcial per baixa',
        ];
    }

    private function classification(): array
    {
        return [
            'decision' => 'RECTIFICATION',
            'source_uc' => 'UC-74',
            'reason_code' => 'AMOUNT_DECREASE',
            'policy_version' => '2026-10',
            'invoice_type' => 'R1',
            'rectification_mode' => 'DIFERENCIES',
        ];
    }
}
