<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftRedemptionLegacyMailBoundaryTest
{
    public function testLegacyWriterCannotSendSmtpBeforeRedeemAndClaim(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioBescanvia.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read legacy gift enrollment writer');
        }

        $redeem = strpos($source, 'redeemCommittedEnrollment');
        $closure = strpos($source, '$sendGovernedGiftMail = static function');
        $claim = $closure === false ? false : strpos($source, 'claimNotificationBundle', $closure);
        $factory = $closure === false ? false : strpos($source, '$mailer = $factory();', $closure);
        $complete = $closure === false ? false : strpos($source, 'completeNotificationBundle', $closure);
        $firstMail = strpos($source, 'new MailSMTPComvive');

        if ($redeem === false || $closure === false || $claim === false
            || $factory === false || $complete === false || $firstMail === false
        ) {
            Assert::fail('UC-018 mail governance calls are incomplete');
        }

        Assert::same(true, $redeem < $closure);
        Assert::same(true, $claim < $factory);
        Assert::same(true, $factory < $complete);
        Assert::same(true, $redeem < $firstMail);
        Assert::same(6, substr_count($source, 'new MailSMTPComvive'));
        Assert::same(true, str_contains($source, '$giftMailIssues'));
        Assert::same(true, str_contains($source, ':AMBIGUOUS_SENDING'));
    }

    public function testNotificationApiIsPostHmacAndHasNoAutomaticRetryAction(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents(
            $root . '/public/api/gifts/redemption/notifications.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read gift notification API');
        }

        Assert::same(true, str_contains($source, "!== 'POST'"));
        Assert::same(true, str_contains($source, 'InternalApiAuthenticator'));
        Assert::same(true, str_contains($source, "action === 'claim'"));
        Assert::same(true, str_contains($source, "action === 'complete'"));
        Assert::same(true, str_contains($source, 'assertGiftNotificationScope'));
        Assert::same(true, str_contains($source, "'GIFT_REDEEM_'"));
        Assert::same(false, str_contains($source, "action === 'retry'"));
        Assert::same(false, str_contains($source, '$_GET'));
    }

    public function testWebClientSignsRedeemClaimAndCompletionThroughSameCanonicalPath(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/inc/SifGiftRedemptionClient.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read gift redemption client');
        }

        Assert::same(
            true,
            str_contains(
                $source,
                'SIF_INTERNAL_GIFT_REDEMPTION_NOTIFICATION_SIGNED_PATH'
            )
        );
        Assert::same(true, str_contains($source, 'claimNotificationBundle'));
        Assert::same(true, str_contains($source, 'completeNotificationBundle'));
        Assert::same(3, substr_count($source, '$this->postSigned('));
        Assert::same(
            true,
            str_contains($source, "hash('sha256', " . '$body' . ")")
        );
        Assert::same(false, str_contains($source, '?gift_code='));
    }
}
