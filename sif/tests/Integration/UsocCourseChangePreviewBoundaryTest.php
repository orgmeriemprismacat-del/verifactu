<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocCourseChangePreviewBoundaryTest
{
    public function testIntranetPreviewIsPostCsrfAuthorizedAndServerPriced(): void
    {
        $root = dirname(__DIR__, 3);
        $endpoint = file_get_contents(
            $root . '/codi-drive/intranet-actual/ajax/alumnes/sifUsocCourseChangePreview.php'
        );
        $pricing = file_get_contents(
            $root . '/codi-drive/intranet-actual/LegacyUsocCourseChangePricingResolver.php'
        );
        $mysql = file_get_contents(
            $root . '/codi-drive/intranet-actual/LegacyUsocCourseChangePricingMysqlSource.php'
        );
        $client = file_get_contents(
            $root . '/codi-drive/intranet-actual/SifInternalUsocClient.php'
        );

        if ($endpoint === false || $pricing === false || $mysql === false || $client === false) {
            Assert::fail('Could not read USOC course change preview boundary files');
        }

        Assert::stringContainsString("REQUEST_METHOD", $endpoint);
        Assert::stringContainsString("!== 'POST'", $endpoint);
        Assert::stringContainsString('assertSameOrigin', $endpoint);
        Assert::stringContainsString('assertCanEdit', $endpoint);
        Assert::stringContainsString('csrf_alumnes_lifecycle', $endpoint);
        Assert::stringContainsString('HTTP_X_CSRF_TOKEN', $endpoint);

        Assert::stringContainsString(
            'LegacyUsocCourseChangePricingResolver',
            $endpoint
        );
        Assert::stringContainsString(
            'LegacyUsocCourseChangePricingMysqlSource',
            $endpoint
        );
        Assert::stringContainsString(
            '->courseChangePreview(',
            $endpoint
        );

        Assert::stringContainsString("'pricing_source' => 'LEGACY_SERVER'", $pricing);
        Assert::stringContainsString(
            "'management_fee_uses_source_hours' => true",
            $pricing
        );
        Assert::stringContainsString(
            'USOC target pricing must preserve positive student and entity parts',
            $pricing
        );

        Assert::stringContainsString("TIPUS = 4", $mysql);
        Assert::stringContainsString("FROM preu", $mysql);
        Assert::stringContainsString("FROM descomptes", $mysql);
        Assert::stringContainsString("PARAM = 'despeses-gestio'", $mysql);
        Assert::stringContainsString("LIMIT 2", $mysql);

        Assert::stringContainsString(
            "public function courseChangePreview",
            $client
        );
        Assert::stringContainsString(
            "'action' => 'course_change_preview'",
            $client
        );
    }
}
