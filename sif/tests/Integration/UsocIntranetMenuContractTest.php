<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class UsocIntranetMenuContractTest
{
    public function testUsocMenuLinkIsFailClosedAndServerSideRoleControlled(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/codi-drive/intranet-actual/ajax/mostrarSideBarMenu.php'
        );

        if ($source === false) {
            Assert::fail('Could not read intranet sidebar menu');
        }

        Assert::stringContainsString('SIF_USOC_MENU_ROLES', $source);
        Assert::stringContainsString('Finançament USOC', $source);
        Assert::stringContainsString('/alumnes-usoc-financament.php', $source);
        Assert::stringContainsString('$usocMenuRoles !== []', $source);
        Assert::stringContainsString('in_array(trim((string) $rolUsuari), $usocMenuRoles, true)', $source);
    }
}
