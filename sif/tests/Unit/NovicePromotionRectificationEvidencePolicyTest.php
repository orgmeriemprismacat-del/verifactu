<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Tests\Support\Assert;

/** No database, invoice emission or MySQL required. */
final class NovicePromotionRectificationEvidencePolicyTest
{
    public function testAcceptsMatchingIssuedOriginalAndItsActualRectificativeLink(): void
    {
        [$original, $rectification, $link] = $this->fixture();
        (new NovicePromotionRectificationEvidencePolicy())
            ->assertCancellationReference($original, $rectification, $link);

        Assert::same('original-test-invoice', $link['UUID_FACTURA_RECTIFICADA']);
    }

    public function testRejectsRectificativeForSomeOtherEnrollment(): void
    {
        [$original, $rectification, $link] = $this->fixture();
        $link['UUID_FACTURA_RECTIFICADA'] = 'unrelated-invoice';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $original, $rectification, $link
        ): void {
            (new NovicePromotionRectificationEvidencePolicy())
                ->assertCancellationReference($original, $rectification, $link);
        });
    }

    public function testRejectsOrdinaryInvoicePretendingToBeRectificative(): void
    {
        [$original, $rectification, $link] = $this->fixture();
        $rectification['TIPUS_FACTURA'] = 'F1';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $original, $rectification, $link
        ): void {
            (new NovicePromotionRectificationEvidencePolicy())
                ->assertCancellationReference($original, $rectification, $link);
        });
    }

    public function testRejectsUnissuedOrPredatingCorrection(): void
    {
        [$original, $rectification, $link] = $this->fixture();
        $rectification['ESTAT_FACTURA'] = 'PENDING';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $original, $rectification, $link
        ): void {
            (new NovicePromotionRectificationEvidencePolicy())
                ->assertCancellationReference($original, $rectification, $link);
        });

        $rectification['ESTAT_FACTURA'] = 'ISSUED';
        $rectification['DATA_EMISSIO'] = '2026-01-01 00:00:00';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $original, $rectification, $link
        ): void {
            (new NovicePromotionRectificationEvidencePolicy())
                ->assertCancellationReference($original, $rectification, $link);
        });
    }

    public function testRejectsLinkWithoutRectificationReasonOrMode(): void
    {
        [$original, $rectification, $link] = $this->fixture();
        $link['MOTIU'] = '';
        Assert::throws(\InvalidArgumentException::class, static function () use (
            $original, $rectification, $link
        ): void {
            (new NovicePromotionRectificationEvidencePolicy())
                ->assertCancellationReference($original, $rectification, $link);
        });
    }

    private function fixture(): array
    {
        $original = [
            'UUID_FACTURA' => 'original-test-invoice',
            'TIPUS_FACTURA' => 'F1',
            'ESTAT_FACTURA' => 'ISSUED',
            'DATA_EMISSIO' => '2026-09-25 10:00:00',
        ];
        $rectification = [
            'UUID_FACTURA' => 'rectificative-test-invoice',
            'TIPUS_FACTURA' => 'R1',
            'ESTAT_FACTURA' => 'ISSUED',
            'DATA_EMISSIO' => '2026-09-25 11:00:00',
        ];
        $link = [
            'UUID_FACTURA_RECTIFICADA' => $original['UUID_FACTURA'],
            'UUID_FACTURA_RECTIFICATIVA' => $rectification['UUID_FACTURA'],
            'MOTIU' => 'BAIXA_CURS',
            'MODE_RECTIFICACIO' => 'SUBSTITUCIO',
        ];
        return [$original, $rectification, $link];
    }
}
