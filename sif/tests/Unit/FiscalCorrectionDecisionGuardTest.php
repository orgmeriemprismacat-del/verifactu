<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\FiscalCorrectionDecisionGuard;
use Prisma\Sif\Tests\Support\Assert;

final class FiscalCorrectionDecisionGuardTest
{
    public function testAcceptsUc74RectificationWithMatchingMode(): void
    {
        $result = (new FiscalCorrectionDecisionGuard())->assertRectification([
            'decision' => 'RECTIFICATION',
            'source_uc' => 'UC-74',
            'reason_code' => 'AMOUNT_DECREASE',
            'policy_version' => '2026-10',
            'invoice_type' => 'R1',
            'rectification_mode' => 'DIFERENCIES',
        ], [
            'mode' => 'DIFERENCIES',
        ]);

        Assert::same('RECTIFICATION', $result['decision']);
        Assert::same('UC-74', $result['source_uc']);
        Assert::same('R1', $result['invoice_type']);
        Assert::same('DIFERENCIES', $result['rectification_mode']);
    }

    public function testRejectsDecisionThatIsNotRectification(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new FiscalCorrectionDecisionGuard())->assertRectification([
                'decision' => 'SUBSANATION',
                'source_uc' => 'UC-74',
                'reason_code' => 'AEAT_DATA_ONLY',
                'policy_version' => '2026-10',
                'rectification_mode' => 'DIFERENCIES',
            ], [
                'mode' => 'DIFERENCIES',
            ]);
        }, 409);
    }

    public function testRejectsUntrustedClassificationSource(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new FiscalCorrectionDecisionGuard())->assertRectification([
                'decision' => 'RECTIFICATION',
                'source_uc' => 'UI',
                'reason_code' => 'AMOUNT_DECREASE',
                'policy_version' => '2026-10',
                'rectification_mode' => 'DIFERENCIES',
            ], [
                'mode' => 'DIFERENCIES',
            ]);
        }, 422);
    }

    public function testRejectsMissingRectificationInvoiceType(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new FiscalCorrectionDecisionGuard())->assertRectification([
                'decision' => 'RECTIFICATION',
                'source_uc' => 'UC-74',
                'reason_code' => 'AMOUNT_DECREASE',
                'policy_version' => '2026-10',
                'rectification_mode' => 'DIFERENCIES',
            ], [
                'mode' => 'DIFERENCIES',
            ]);
        }, 422);
    }

    public function testRejectsModeDifferentFromUc74Decision(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new FiscalCorrectionDecisionGuard())->assertRectification([
                'decision' => 'RECTIFICATION',
                'source_uc' => 'UC-74',
                'reason_code' => 'SERVICE_CHANGED',
                'policy_version' => '2026-10',
                'invoice_type' => 'R1',
                'rectification_mode' => 'SUBSTITUCIO',
            ], [
                'mode' => 'DIFERENCIES',
            ]);
        }, 409);
    }
}
