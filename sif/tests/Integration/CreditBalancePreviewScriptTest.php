<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class CreditBalancePreviewScriptTest
{
    public function testPreviewBuildsCreditBalancePayloadWithoutCreatingCredit(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/preview-credit-balance.php');

        if ($source === false) {
            Assert::fail('Could not read credit balance preview script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('new CreditBalancePayloadBuilder()', $source);
        Assert::stringContainsString('--holder-type=', $source);
        Assert::stringContainsString('--holder-name=', $source);
        Assert::stringContainsString('--source-type=', $source);
        Assert::stringContainsString('forCreditBalance($input)', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'ConnectionFactory::make($config)')) {
            Assert::fail('Credit balance preview must not open a database connection.');
        }

        if (str_contains($source, 'createCredit(')) {
            Assert::fail('Credit balance preview must not create credit.');
        }
    }
}
