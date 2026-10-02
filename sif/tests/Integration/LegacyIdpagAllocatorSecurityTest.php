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
    public function testCourseChangeUsesLockedIntranetAllocator(): void
    {
        $root = dirname(__DIR__, 3);
        $connection = file_get_contents(
            $root . '/codi-drive/intranet-actual/ConnexioWeb.php'
        );
        $intranet = file_get_contents(
            $root . '/codi-drive/intranet-actual/Intranet.php'
        );

        if ($connection === false || $intranet === false) {
            Assert::fail('Could not read intranet IDPAG allocator sources');
        }

        Assert::stringContainsString('function reserveIdPag', $connection);
        Assert::stringContainsString('function releaseIdPag', $connection);
        Assert::stringContainsString('SELECT GET_LOCK(?, ?)', $connection);
        Assert::stringContainsString('SELECT RELEASE_LOCK(?)', $connection);
        Assert::stringContainsString('prisma_inscripcions_idpag_allocator', $connection);

        $methodStart = strpos($intranet, 'public function realitzarCanviCurs_modalCanviCurs');
        $methodEnd = strpos($intranet, 'public function ', $methodStart + 20);
        $method = $methodStart === false
            ? ''
            : substr(
                $intranet,
                $methodStart,
                $methodEnd === false ? null : $methodEnd - $methodStart
            );

        Assert::stringContainsString('$conWeb->reserveIdPag()', $method);
        Assert::stringContainsString('$conWeb->releaseIdPag()', $method);

        if (str_contains($method, '$lastIdPag+1')) {
            Assert::fail('Unsafe MAX+1 IDPAG allocation remains in course change');
        }

        $reserve = strpos($method, '$conWeb->reserveIdPag()');
        $insert = strpos($method, 'insertRegInscCanvi');
        $release = strpos($method, '$conWeb->releaseIdPag()');

        if (
            $reserve === false
            || $insert === false
            || $release === false
            || !($reserve < $insert && $insert < $release)
        ) {
            Assert::fail('Course-change IDPAG lock must cover target enrollment INSERT');
        }
    }

}
