<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftRedemptionWebClientBoundaryTest
{
    public function testWebClientUsesPostHmacHttpsAndNoGiftCodeInUrl(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/inc/SifGiftRedemptionClient.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read gift redemption web client');
        }

        Assert::stringContainsString("CURLOPT_POST => true", $source);
        Assert::stringContainsString("X-SIF-Signature", $source);
        Assert::stringContainsString("hash('sha256', \$body)", $source);
        Assert::stringContainsString("requires HTTPS", $source);
        Assert::stringContainsString(
            "Gift redemption authority must be resolved inside SIF",
            $source
        );
        Assert::stringContainsString(
            "['holder_party_key', 'trusted_price_snapshot']",
            $source
        );
        Assert::same(false, str_contains($source, '?gift_code='));
        Assert::same(false, str_contains($source, 'http_build_query'));
    }

    public function testLegacyWriterUsesRecoverableGetOrCreateBeforeCallingSif(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioBescanvia.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read legacy gift enrollment writer');
        }

        Assert::stringContainsString('SifGiftRedemptionClient', $source);
        Assert::stringContainsString('begin_transaction()', $source);
        Assert::stringContainsString('SELECT FACT_REL, USAT FROM regal', $source);
        Assert::stringContainsString('FOR UPDATE', $source);
        Assert::stringContainsString('WHERE pag_observacions=?', $source);
        Assert::stringContainsString("'enrollment_id' => (int) \$idInserit", $source);
        Assert::stringContainsString("'gift_code' => \$codiRegalBD", $source);
        Assert::same(false, str_contains($source, "'holder_party_key' =>"));
        Assert::same(false, str_contains($source, "'trusted_price_snapshot' =>"));
        Assert::same(false, str_contains($source, 'UPDATE regal SET USAT'));

        $clientCall = strpos($source, 'redeemCommittedEnrollment');
        $response = strpos($source, 'echo $hashIdInserit');
        Assert::same(true, is_int($clientCall) && is_int($response) && $clientCall < $response);
    }

    public function testCompletedGiftReplayReentersSifAndReusesEnrollmentBeforeMailSideEffects(): void
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioBescanvia.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read legacy gift enrollment writer');
        }

        Assert::stringContainsString('SELECT FACT_REL, USAT FROM regal', $source);
        Assert::stringContainsString('FROM inscripcions WHERE ID=? FOR UPDATE', $source);
        Assert::stringContainsString('$idInserit = (int) $candidateId;', $source);

        $redeem = strpos($source, 'redeemCommittedEnrollment');
        $firstMail = strpos($source, 'new MailSMTPComvive');
        Assert::same(
            true,
            is_int($redeem) && is_int($firstMail) && $redeem < $firstMail
        );

        Assert::same(false, str_contains(
            $source,
            'echo $encryptEnrollmentId((int) $usatReplay);'
        ));
        Assert::stringContainsString(
            "reconstruir o reutilitzar l'outbox idempotent",
            $source
        );
    }

}
