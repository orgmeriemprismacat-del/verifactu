<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Tests\Support\Assert;

final class ConnectionFactoryTest
{
    public function testConfigDefinesLegacyDatabaseFromEnvironmentOnly(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/config/sif.php');

        if ($source === false) {
            Assert::fail('Could not read SIF config');
        }

        Assert::stringContainsString('legacy_db', $source);
        Assert::stringContainsString('SIF_LEGACY_DB_DSN', $source);
        Assert::stringContainsString('SIF_LEGACY_DB_USER', $source);
        Assert::stringContainsString('SIF_LEGACY_DB_PASSWORD', $source);

        if (str_contains($source, 'parametres' . '-connexio')) {
            Assert::fail('SIF config must not include legacy parameter files.');
        }
    }

    public function testConnectionFactoryCanCreateLegacyConnection(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Database/ConnectionFactory.php');

        if ($source === false) {
            Assert::fail('Could not read ConnectionFactory');
        }

        Assert::stringContainsString('makeLegacy(array $config)', $source);
        Assert::stringContainsString('$config[\'legacy_db\']', $source);
        Assert::stringContainsString('Legacy DB DSN not configured', $source);
        Assert::stringContainsString('SET NAMES utf8mb4', $source);
    }
}
