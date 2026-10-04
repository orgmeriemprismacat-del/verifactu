<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocIntranetRuntimePreflightScriptTest
{
    public function testRuntimePreflightValidatesActualIntranetConfigurationWithoutExposingSecrets(): void
    {
        $root = dirname(__DIR__, 3);
        $script = file_get_contents(
            $root . '/codi-drive/intranet-actual/preflight-sif-usoc-runtime.php'
        );

        if ($script === false) {
            Assert::fail('Could not read intranet USOC runtime preflight');
        }

        foreach ([
            'PHP_SAPI',
            'SIF_INTERNAL_USOC_URL',
            'SIF_INTERNAL_USOC_EXPECTED_HOST',
            'SIF_INTERNAL_USOC_SIGNED_PATH',
            'SIF_INTERNAL_API_KEY_ID',
            'SIF_INTERNAL_API_SECRET',
            'SIF_USOC_MENU_ROLES',
            'SIF_USOC_UI_ENABLED',
            'internal_usoc_url_https',
            'internal_usoc_url_matches_expected_host',
            'internal_usoc_url_matches_signed_path',
            'internal_api_key_id',
            'internal_api_secret',
            'usoc_menu_roles',
            'usoc_ui_enabled',
            'SifInternalUsocClient.php',
            'sifUsocCourseChangePreview.php',
            'sifUsocCourseChangePrepare.php',
            'realitzarCanviCurs_CanviCurs.php',
            'alumnes-usoc-lifecycle-preview.js',
        ] as $needle) {
            Assert::stringContainsString($needle, $script);
        }

        Assert::stringContainsString(
            "\$urlPath !== null && \$urlPath === \$signedPath",
            $script
        );
        Assert::stringContainsString(
            "(string) (getenv('SIF_USOC_UI_ENABLED') ?: '') === '1'",
            $script
        );
        Assert::stringContainsString(
            "'secret_present' => \$secret !== ''",
            $script
        );
        Assert::stringContainsString("'key_id' => \$keyId", $script);
        Assert::same(false, str_contains($script, "'secret' => \$secret"));
    }
}
