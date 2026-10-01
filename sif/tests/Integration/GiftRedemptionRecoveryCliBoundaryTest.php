<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftRedemptionRecoveryCliBoundaryTest
{
    public function testRecoveryCliUsesOnlyCommittedEnrollmentIdAndReusableOrchestrator(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/scripts/retry-gift-redemption.php');
        if (!is_string($source)) {
            Assert::fail('Could not read UC-018 recovery script');
        }

        Assert::stringContainsString('GiftRedemptionOrchestrator', $source);
        Assert::stringContainsString("'--enrollment-id='", $source);
        Assert::stringContainsString('SELECT pag_observacions FROM inscripcions WHERE ID = ?', $source);
        Assert::same(false, str_contains($source, '--gift-code='));
        Assert::same(false, str_contains($source, 'holder_party_key'));
        Assert::same(false, str_contains($source, 'trusted_price_snapshot'));
        Assert::same(false, str_contains($source, 'INSERT INTO inscripcions'));
        Assert::same(false, str_contains($source, 'MailSMTPComvive'));
    }
}
