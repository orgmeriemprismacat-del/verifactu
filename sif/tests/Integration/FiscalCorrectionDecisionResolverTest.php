<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalCorrectionDecisionRepository;
use Prisma\Sif\Repository\SifAuditEventRepository;
use Prisma\Sif\Service\FiscalCorrectionDecisionGuard;
use Prisma\Sif\Service\FiscalCorrectionDecisionResolver;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class FiscalCorrectionDecisionResolverTest
{
    public function testResolvesPersistedApprovedUc74DecisionForSameInvoice(): void
    {
        $db = TestDatabase::fresh();
        $eventUuid = $this->appendDecision($db, 'invoice-uc005');

        $resolved = $this->resolver()->resolve(
            $db,
            $eventUuid,
            'invoice-uc005',
            ['mode' => 'DIFERENCIES']
        );

        Assert::same('RECTIFICATION', $resolved['decision']);
        Assert::same('UC-74', $resolved['source_uc']);
        Assert::same('AMOUNT_DECREASE', $resolved['reason_code']);
        Assert::same('DIFERENCIES', $resolved['rectification_mode']);
        Assert::same(strtolower($eventUuid), $resolved['decision_event_uuid']);
    }

    public function testRejectsDecisionEventForDifferentInvoice(): void
    {
        $db = TestDatabase::fresh();
        $eventUuid = $this->appendDecision($db, 'invoice-a');

        Assert::throws(SifException::class, function () use ($db, $eventUuid): void {
            $this->resolver()->resolve(
                $db,
                $eventUuid,
                'invoice-b',
                ['mode' => 'DIFERENCIES']
            );
        }, 409);
    }

    public function testRejectsAuditEventThatIsNotApprovedUc74Classification(): void
    {
        $db = TestDatabase::fresh();
        $eventUuid = (new SifAuditEventRepository(new UuidGenerator()))->append($db, [
            'request_id' => 'req-uc005-invalid',
            'correlation_id' => 'corr-uc005-invalid',
            'action' => 'RECTIFICATION_PREVIEW',
            'result' => 'SUCCEEDED',
            'resource_type' => 'FACTURA',
            'resource_id' => 'invoice-uc005',
            'source_environment' => 'TEST',
            'source_channel' => 'INTERNAL_API',
            'actor_type' => 'INTERNAL_USER',
            'actor_id' => 'tester',
            'actor_role' => 'FACTURACIO',
            'reason_code' => 'AMOUNT_DECREASE',
            'changeset' => [
                'classification' => $this->classification(),
            ],
        ]);

        Assert::throws(SifException::class, function () use ($db, $eventUuid): void {
            $this->resolver()->resolve(
                $db,
                $eventUuid,
                'invoice-uc005',
                ['mode' => 'DIFERENCIES']
            );
        }, 409);
    }

    private function appendDecision(\PDO $db, string $invoiceUuid): string
    {
        return (new SifAuditEventRepository(new UuidGenerator()))->append($db, [
            'request_id' => 'req-uc074-approved',
            'correlation_id' => 'corr-uc074-approved',
            'action' => 'FISCAL_CORRECTION_CLASSIFIED',
            'result' => 'SUCCEEDED',
            'resource_type' => 'FACTURA',
            'resource_id' => $invoiceUuid,
            'source_environment' => 'TEST',
            'source_channel' => 'INTERNAL_API',
            'actor_type' => 'INTERNAL_USER',
            'actor_id' => 'responsable-fiscal',
            'actor_role' => 'FACTURACIO',
            'reason_code' => 'AMOUNT_DECREASE',
            'changeset' => [
                'classification' => $this->classification(),
            ],
        ]);
    }

    private function classification(): array
    {
        return [
            'decision' => 'RECTIFICATION',
            'source_uc' => 'UC-74',
            'reason_code' => 'AMOUNT_DECREASE',
            'policy_version' => '2026-10',
            'rectification_mode' => 'DIFERENCIES',
        ];
    }

    private function resolver(): FiscalCorrectionDecisionResolver
    {
        return new FiscalCorrectionDecisionResolver(
            new FiscalCorrectionDecisionRepository(),
            new FiscalCorrectionDecisionGuard()
        );
    }
}
