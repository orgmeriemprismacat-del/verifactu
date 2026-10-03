<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftCodeGenerationBoundaryTest
{
    public function testGiftCodeUsesCryptographicRandomnessWithUc018CompatibleLength(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/RegalCurs.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read legacy gift purchase service');
        }

        Assert::stringContainsString(
            "$alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';",
            $source
        );
        Assert::stringContainsString('random_int(0, $alphabetLength - 1)', $source);
        Assert::stringContainsString('for ($i = 0; $i < 10; $i++)', $source);
        Assert::same(false, str_contains($source, 'base_convert(uniqid(), 16, 36)'));
    }
}
