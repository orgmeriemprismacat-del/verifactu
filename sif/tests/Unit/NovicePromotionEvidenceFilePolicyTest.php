<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionEvidenceFilePolicy;
use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionEvidenceFilePolicyTest
{
    public function testAcceptsAllowedMimeSizeAndSha256(): void
    {
        $policy = new NovicePromotionEvidenceFilePolicy(
            1024,
            ['application/pdf', 'image/jpeg']
        );

        $policy->assertAcceptable(
            'APPLICATION/PDF',
            512,
            str_repeat('a', 64)
        );

        Assert::same(true, true);
    }

    public function testRejectsDisallowedMimeOversizeAndInvalidHash(): void
    {
        $policy = new NovicePromotionEvidenceFilePolicy(
            1024,
            ['application/pdf']
        );

        Assert::throws(
            \InvalidArgumentException::class,
            static fn () => $policy->assertAcceptable(
                'text/plain',
                100,
                str_repeat('a', 64)
            )
        );

        Assert::throws(
            \InvalidArgumentException::class,
            static fn () => $policy->assertAcceptable(
                'application/pdf',
                2048,
                str_repeat('a', 64)
            )
        );

        Assert::throws(
            \InvalidArgumentException::class,
            static fn () => $policy->assertAcceptable(
                'application/pdf',
                100,
                'not-a-sha256'
            )
        );
    }

    public function testRejectsInvalidConfiguration(): void
    {
        Assert::throws(
            \InvalidArgumentException::class,
            static fn () => new NovicePromotionEvidenceFilePolicy(0, ['application/pdf'])
        );

        Assert::throws(
            \InvalidArgumentException::class,
            static fn () => new NovicePromotionEvidenceFilePolicy(1024, [])
        );
    }
}
