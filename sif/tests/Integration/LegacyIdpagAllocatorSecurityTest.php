<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class LegacyIdpagAllocatorSecurityTest
{
    public function testCurrentEnrollmentFlowsUseSharedIdpagAllocator(): void
    {
        $root = dirname(__DIR__, 3);
        $connection = file_get_contents(
            $root . '/codi-drive/web-actual/ConnexioBBDD_PreparedStatment.php'
        );
        if ($connection === false) {
            Assert::fail('Could not read legacy connection class');
        }

        Assert::stringContainsString("GET_LOCK", $connection);
        Assert::stringContainsString("RELEASE_LOCK", $connection);
        Assert::stringContainsString("prisma_inscripcions_idpag_allocator", $connection);
        Assert::stringContainsString("function reserveIdPag", $connection);
        Assert::stringContainsString("function releaseIdPag", $connection);

        $paths = [
            'ajax/enviarInscripcio.php',
            'ajax/enviarInscripcio2.php',
            'ajax/enviarInscripcioAfiliat.php',
            'ajax/enviarInscripcioBescanvia.php',
            'ajax/enviarInscripcioPack.php',
            'ajax/enviarInscripcioTaller.php',
        ];

        foreach ($paths as $relative) {
            $source = file_get_contents($root . '/codi-drive/web-actual/' . $relative);
            if ($source === false) {
                Assert::fail('Could not read current enrollment flow: ' . $relative);
            }

            Assert::stringContainsString('reserveIdPag()', $source);
            Assert::stringContainsString('releaseIdPag()', $source);

            if (str_contains($source, 'SELECT IDPAG FROM inscripcions ORDER BY IDPAG DESC LIMIT 1')) {
                Assert::fail('Unsafe IDPAG allocator remains in current flow: ' . $relative);
            }

            $reservePosition = strpos($source, 'reserveIdPag()');
            $insertPosition = strpos($source, 'INSERT INTO inscripcions');
            $releasePosition = strpos($source, 'releaseIdPag()');

            if (
                $reservePosition === false
                || $insertPosition === false
                || $releasePosition === false
                || !($reservePosition < $insertPosition && $insertPosition < $releasePosition)
            ) {
                Assert::fail('IDPAG lock must cover the insertion in current flow: ' . $relative);
            }
        }
    }
}
