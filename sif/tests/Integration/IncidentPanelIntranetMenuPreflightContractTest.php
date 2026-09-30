<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class IncidentPanelIntranetMenuPreflightContractTest
{
    public function testMenuDiscoveryIsCliOnlyReadOnlyAndUsesRealApartatsContract(): void
    {
        $path = dirname(__DIR__, 3)
            . '/codi-drive/intranet-actual/preflight-sif-verifactu-menu.php';
        $source = file_get_contents($path);
        if ($source === false) {
            Assert::fail('Could not read intranet menu preflight');
        }

        Assert::stringContainsString("PHP_SAPI !== 'cli'", $source);
        Assert::stringContainsString('ConnexioIntranet.php', $source);
        Assert::stringContainsString('FROM apartats', $source);
        Assert::stringContainsString('ROLS_VISUALITZAR', $source);
        Assert::stringContainsString('ID_NIVELL_PARE', $source);
        Assert::stringContainsString("'/sif-verifactu.php'", $source);
        Assert::stringContainsString('DUPLICATE_TARGET_URL', $source);
        Assert::stringContainsString('CONFIRM_PARENT_ROLES_ORDER_BEFORE_INSERT', $source);
        Assert::stringContainsString("'read_only' => true", $source);

        foreach ([
            'INSERT INTO apartats',
            'UPDATE apartats',
            'DELETE FROM apartats',
            'REPLACE INTO apartats',
            'TRUNCATE',
        ] as $write) {
            if (stripos($source, $write) !== false) {
                Assert::fail('Menu preflight must remain read-only: ' . $write);
            }
        }
    }

    public function testMenuDiscoveryDoesNotEmbedConnectionSecrets(): void
    {
        $path = dirname(__DIR__, 3)
            . '/codi-drive/intranet-actual/preflight-sif-verifactu-menu.php';
        $source = file_get_contents($path);
        if ($source === false) {
            Assert::fail('Could not read intranet menu preflight');
        }

        foreach (['DB_PASSWORD', 'password=', 'mysqli_connect('] as $forbidden) {
            if (stripos($source, $forbidden) !== false) {
                Assert::fail('Menu preflight must use ConnexioIntranet, not embedded credentials.');
            }
        }
    }
}
