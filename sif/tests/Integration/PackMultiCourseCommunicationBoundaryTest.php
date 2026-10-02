<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PackMultiCourseCommunicationBoundaryTest
{
    public function testPackEnrollmentEmailUsesDynamicCourseList(): void
    {
        $root = dirname(__DIR__, 3);
        $endpointPath = $root . '/codi-drive/web-actual/ajax/enviarInscripcioPack.php';
        $templatePath = $root . '/codi-drive/web-actual/templates/inscripcions/enviamentPack.php';

        $endpoint = file_get_contents($endpointPath);
        $template = file_get_contents($templatePath);
        if (!is_string($endpoint) || !is_string($template)) {
            Assert::fail('Could not load PACK communication sources');
        }

        Assert::stringContainsString('[CURSOS_PACK]', $template);
        Assert::stringContainsString('$datesRealitzacioCursos', $endpoint);
        Assert::stringContainsString('"[CURSOS_PACK]"', $endpoint);

        Assert::same(false, str_contains($template, '[TITOL1]'));
        Assert::same(false, str_contains($template, '[TITOL2]'));
        Assert::same(false, str_contains($template, '[DATAI_DATAF1]'));
        Assert::same(false, str_contains($template, '[DATAI_DATAF2]'));

        Assert::same(false, str_contains($endpoint, '$titols[0]'));
        Assert::same(false, str_contains($endpoint, '$titols[1]'));
        Assert::same(false, str_contains($endpoint, '$edicions[1]'));
    }
}
