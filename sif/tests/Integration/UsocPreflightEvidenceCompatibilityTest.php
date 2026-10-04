<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocPreflightEvidenceCompatibilityTest
{
    public function testComparatorRequiresBothPreflightsAndCrossHostIdentityCompatibility(): void
    {
        $root = dirname(__DIR__, 3);
        $script = file_get_contents(
            $root . '/sif/scripts/compare-usoc-preflight-evidence.php'
        );

        if ($script === false) {
            Assert::fail('Could not read USOC preflight evidence comparator');
        }

        foreach ([
            'sif_course_change',
            'intranet_runtime',
            'key_id_matches',
            'signed_path_matches',
            'menu_roles_are_manage_roles',
            'intranet_ui_enabled',
            'roles_not_managed_by_sif',
            'hash_equals',
            'JSON_THROW_ON_ERROR',
        ] as $needle) {
            Assert::stringContainsString($needle, $script);
        }

        Assert::same(false, str_contains($script, 'SIF_INTERNAL_API_SECRET'));
        Assert::same(false, str_contains($script, "'secret' =>"));
    }
}
