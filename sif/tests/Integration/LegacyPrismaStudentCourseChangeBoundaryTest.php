<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyPrismaStudentCourseChangeBoundaryTest
{
    public function testCourseChangeDefinesServerSideSourceAndMoneyHelpersBeforeExecution(): void
    {
        $source = $this->readEndpoint();

        Assert::stringContainsString('function normalizeLegacyCourseChangeMoney(', $source);
        Assert::stringContainsString('function loadLegacyCourseChangeSource(', $source);
        Assert::stringContainsString('function resolveLegacyPrismaStudentCourseChangePrice(', $source);
    }

    public function testCourseChangeDiscountTypeAndStatusComeFromDatabaseNotHiddenInputs(): void
    {
        $source = $this->readEndpoint();

        Assert::same(false, str_contains($source, "\$_POST['tipusDesc']"));
        Assert::same(false, str_contains($source, "\$_POST['validDesc']"));
        Assert::stringContainsString(
            "SELECT CURS, A_PAGAR, PAGAMENT, TIPUS_DESC, VALID_DESC",
            $source
        );
        Assert::stringContainsString("\$tipusDesc = (string) \$source['discount_type'];", $source);
        Assert::stringContainsString("\$validDesc = (string) \$source['discount_status'];", $source);
    }

    public function testPrismaStudentTargetPriceUsesCourseHoursMonthValidityAndUniqueTariff(): void
    {
        $source = $this->readEndpoint();

        Assert::stringContainsString('WHERE ID_PREU = ? AND TIPUS = 1', $source);
        Assert::stringContainsString('DATAI <= CURRENT_TIMESTAMP', $source);
        Assert::stringContainsString('(DATAF IS NULL OR CURRENT_TIMESTAMP <= DATAF)', $source);
        Assert::stringContainsString("(CURS = 'TOTS' OR CURS = ? OR CURS = ?)", $source);
        Assert::stringContainsString("(MES = 'TOTS' OR MES = ?)", $source);
        Assert::stringContainsString('if ($stmt->num_rows() !== 1)', $source);
        Assert::stringContainsString('tarifa Alumne PrisMa inexistent o ambigua', $source);
    }

    public function testPrismaStudentCourseChangeOverwritesClientAmountsBeforePreviewAndMutation(): void
    {
        $source = $this->readEndpoint();

        $guard = strpos($source, 'if ((int) $tipusDesc === 1) {');
        $serverPrice = strpos(
            $source,
            '$apagarC = resolveLegacyPrismaStudentCourseChangePrice(',
            $guard ?: 0
        );
        $serverPaid = strpos($source, '$pagatC = $source[\'paid\'];', $guard ?: 0);
        $preview = strpos($source, "if (getenv('SIF_COURSE_CHANGE_PREVIEW_ENFORCED') === '1')", $guard ?: 0);
        $mutation = strpos(
            $source,
            'realitzarCanviCurs_modalCanviCurs(',
            $preview ?: 0
        );

        Assert::same(true, $guard !== false);
        Assert::same(true, $serverPrice !== false);
        Assert::same(true, $serverPaid !== false);
        Assert::same(true, $preview !== false);
        Assert::same(true, $mutation !== false);
        Assert::same(true, $guard < $serverPrice && $serverPrice < $preview);
        Assert::same(true, $guard < $serverPaid && $serverPaid < $preview);
        Assert::same(true, $serverPrice < $mutation);
    }

    public function testPrismaStudentPreviewReceivesAuthoritativeTargetAmount(): void
    {
        $source = $this->readEndpoint();

        Assert::stringContainsString(
            '$standardTargetRaw = (int) $tipusDesc === 1',
            $source
        );
        Assert::stringContainsString("? $apagarC", $source);
        Assert::stringContainsString("'standard_target_amount' => $standardTargetAmount", $source);
        Assert::stringContainsString("'proposed_target_amount' => $apagarC", $source);
    }

    private function readEndpoint(): string
    {
        $root = dirname(__DIR__, 3);
        $path = $root . '/codi-drive/intranet-actual/ajax/alumnes/realitzarCanviCurs_CanviCurs.php';
        $content = file_get_contents($path);
        if ($content === false) {
            Assert::fail('Could not read legacy course-change endpoint.');
        }

        return $content;
    }
}
