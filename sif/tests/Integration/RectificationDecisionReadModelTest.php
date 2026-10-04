<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\InvoiceVisibilityPolicyInterface;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Service\InvoiceQueryService;
use Prisma\Sif\Service\RectificationDecisionFingerprint;
use Prisma\Sif\Service\ResolvedInvoiceVisibilityPolicy;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RectificationDecisionReadModelTest
{
    public function testFullInvoiceViewExposesLatestApprovedUc74DecisionWithoutActorData(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'UC005|QUERY|DECISION',
        ]));

        $correction = [
            'amount' => '-40.00',
            'reason' => 'DEVOLUCIO_PARCIAL',
            'mode' => 'DIFERENCIES',
            'concept' => 'Rectificacio parcial',
        ];
        $fingerprint = (new RectificationDecisionFingerprint())->calculate($correction);
        $eventUuid = (new SifAuditEventRepository(new UuidGenerator()))->append($db, [
            'request_id' => 'uc074-query-decision',
            'correlation_id' => 'uc074-query-decision',
            'action' => 'FISCAL_CORRECTION_CLASSIFIED',
            'result' => 'SUCCEEDED',
            'resource_type' => 'FACTURA',
            'resource_id' => $issued['uuid_factura'],
            'source_environment' => 'TEST',
            'source_channel' => 'INTERNAL_API',
            'actor_type' => 'HUMAN',
            'actor_id' => 'responsable-fiscal-secret',
            'actor_role' => 'FACTURACIO',
            'reason_code' => 'AMOUNT_DECREASE',
            'changeset' => [
                'classification' => [
                    'decision' => 'RECTIFICATION',
                    'source_uc' => 'UC-74',
                    'reason_code' => 'AMOUNT_DECREASE',
                    'policy_version' => '2026-10',
                    'invoice_type' => 'R1',
                    'rectification_mode' => 'DIFERENCIES',
                ],
                'correction_fingerprint' => $fingerprint,
                'correction' => $correction,
            ],
        ]);

        $service = new InvoiceQueryService(
            $db,
            new InvoiceReadRepository(),
            $this->allowAllPolicy()
        );
        $view = $service->view(['actor_id' => 'operator-test'], $issued['uuid_factura']);
        $decision = $view['fiscal_correction_decision'] ?? null;

        Assert::same(true, is_array($decision));
        Assert::same(strtolower($eventUuid), $decision['event_uuid']);
        Assert::same(true, $decision['eligible_for_uc005']);
        Assert::same(true, $decision['ready_for_uc005_ui']);
        Assert::same('R1', $decision['classification']['invoice_type']);
        Assert::same('DIFERENCIES', $decision['classification']['rectification_mode']);
        Assert::same($fingerprint, $decision['correction_fingerprint']);
        Assert::same('-40.00', $decision['correction']['amount']);
        Assert::same('DIFERENCIES', $decision['correction']['mode']);
        Assert::same(false, array_key_exists('actor_id', $decision));
        Assert::same(false, array_key_exists('actor_role', $decision));
    }

    public function testExecutedUc74DecisionIsProjectedReadOnly(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'UC005|QUERY|EXECUTED',
        ]));

        $correction = [
            'amount' => '-40.00',
            'reason' => 'DEVOLUCIO_PARCIAL',
            'mode' => 'DIFERENCIES',
            'concept' => 'Rectificacio executada',
        ];
        $fingerprint = (new RectificationDecisionFingerprint())->calculate($correction);
        $audit = new SifAuditEventRepository(new UuidGenerator());

        $decisionEventUuid = $audit->append($db, [
            'request_id' => 'uc074-query-executed',
            'correlation_id' => 'uc074-query-executed',
            'action' => 'FISCAL_CORRECTION_CLASSIFIED',
            'result' => 'SUCCEEDED',
            'resource_type' => 'FACTURA',
            'resource_id' => $issued['uuid_factura'],
            'source_environment' => 'TEST',
            'source_channel' => 'INTERNAL_API',
            'actor_type' => 'HUMAN',
            'actor_id' => 'responsable-fiscal',
            'actor_role' => 'FACTURACIO',
            'reason_code' => 'AMOUNT_DECREASE',
            'changeset' => [
                'classification' => [
                    'decision' => 'RECTIFICATION',
                    'source_uc' => 'UC-74',
                    'reason_code' => 'AMOUNT_DECREASE',
                    'policy_version' => '2026-10',
                    'invoice_type' => 'R1',
                    'rectification_mode' => 'DIFERENCIES',
                ],
                'correction_fingerprint' => $fingerprint,
                'correction' => $correction,
            ],
        ]);

        $rectificationUuid = (new UuidGenerator())->generate();
        $audit->append($db, [
            'request_id' => 'uc005-query-executed',
            'correlation_id' => 'uc074-query-executed',
            'action' => 'RECTIFICATION_CONFIRM',
            'result' => 'SUCCEEDED',
            'resource_type' => 'FACTURA',
            'resource_id' => $rectificationUuid,
            'source_environment' => 'TEST',
            'source_channel' => 'INTERNAL_API',
            'actor_type' => 'INTERNAL_USER',
            'actor_id' => 'operator-test',
            'actor_role' => 'FACTURACIO',
            'reason_code' => 'AMOUNT_DECREASE',
            'changeset' => [
                'classification' => [
                    'decision' => 'RECTIFICATION',
                    'source_uc' => 'UC-74',
                    'reason_code' => 'AMOUNT_DECREASE',
                    'policy_version' => '2026-10',
                    'invoice_type' => 'R1',
                    'rectification_mode' => 'DIFERENCIES',
                    'decision_event_uuid' => strtolower($decisionEventUuid),
                ],
                'fingerprint' => str_repeat('b', 64),
            ],
        ]);

        $view = (new InvoiceQueryService(
            $db,
            new InvoiceReadRepository(),
            $this->allowAllPolicy()
        ))->view(['actor_id' => 'operator-test'], $issued['uuid_factura']);

        $decision = $view['fiscal_correction_decision'] ?? null;
        Assert::same(true, is_array($decision));
        Assert::same(true, $decision['executed']);
        Assert::same(false, $decision['ready_for_uc005_ui']);
        Assert::same('SUCCEEDED', $decision['execution']['result']);
        Assert::same($rectificationUuid, $decision['execution']['uuid_factura_rectificativa']);
    }

    public function testMinimalProjectionDoesNotExposeFiscalCorrectionDecision(): void
    {
        $db = TestDatabase::fresh();
        $issued = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'UC005|QUERY|MINIMAL',
        ]));

        (new SifAuditEventRepository(new UuidGenerator()))->append($db, [
            'request_id' => 'uc074-query-minimal',
            'correlation_id' => 'uc074-query-minimal',
            'action' => 'FISCAL_CORRECTION_CLASSIFIED',
            'result' => 'SUCCEEDED',
            'resource_type' => 'FACTURA',
            'resource_id' => $issued['uuid_factura'],
            'source_environment' => 'TEST',
            'source_channel' => 'INTERNAL_API',
            'actor_type' => 'HUMAN',
            'actor_id' => 'responsable-fiscal',
            'actor_role' => 'FACTURACIO',
            'reason_code' => 'AMOUNT_DECREASE',
            'changeset' => [
                'classification' => [
                    'decision' => 'RECTIFICATION',
                    'source_uc' => 'UC-74',
                    'reason_code' => 'AMOUNT_DECREASE',
                    'policy_version' => '2026-10',
                    'invoice_type' => 'R1',
                    'rectification_mode' => 'DIFERENCIES',
                ],
                'correction_fingerprint' => str_repeat('a', 64),
            ],
        ]);

        $service = new InvoiceQueryService(
            $db,
            new InvoiceReadRepository(),
            new ResolvedInvoiceVisibilityPolicy()
        );
        $view = $service->view([
            'actor_id' => 'student-test',
            'invoice_scope' => [
                'invoices' => [
                    $issued['uuid_factura'] => 'MINIMAL',
                ],
            ],
        ], $issued['uuid_factura']);

        Assert::same(false, array_key_exists('fiscal_correction_decision', $view));
    }

    private function allowAllPolicy(): InvoiceVisibilityPolicyInterface
    {
        return new class implements InvoiceVisibilityPolicyInterface {
            public function canView(array $actor, array $invoice, array $relations): bool
            {
                return true;
            }

            public function project(array $actor, array $view): array
            {
                return $view;
            }
        };
    }
}
