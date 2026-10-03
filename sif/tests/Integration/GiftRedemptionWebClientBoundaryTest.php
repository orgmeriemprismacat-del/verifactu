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


    public function testPublicGiftRedemptionAjaxUsesPostAndDoesNotExposeGiftCodeOrPiiInUrls(): void
    {
        $root = dirname(__DIR__, 3);
        $javascript = file_get_contents(
            $root . '/codi-drive/web-actual/js1619773569/mostrarBescanvia.min.js'
        );
        if (!is_string($javascript)) {
            Assert::fail('Could not read public gift redemption JavaScript');
        }
        $page = file_get_contents(
            $root . '/codi-drive/web-actual/pagina_bescanvia.php'
        );
        if (!is_string($page)) {
            Assert::fail('Could not read public gift redemption page');
        }
        Assert::stringContainsString(
            'js1619773569/mostrarBescanvia.min.js?ver=6.0',
            $page
        );
        Assert::same(false, str_contains($page, 'mostrarBescanvia_prova.min.js'));


        foreach ([
            'codiRegalValid.php',
            'buscarCursRegalat.php',
            'inscripcioDuplicada.php',
            'enviarInscripcioBescanvia.php',
        ] as $endpoint) {
            $needle = 'url: "https://www.prisma.cat/ajax/' . $endpoint . '"';
            $position = strpos($javascript, $needle);
            if ($position === false) {
                Assert::fail('Missing UC-018 AJAX endpoint: ' . $endpoint);
            }

            $snippet = substr($javascript, $position, 700);
            Assert::stringContainsString('type: "POST"', $snippet);
            Assert::stringContainsString('data: {', $snippet);
        }

        Assert::same(false, str_contains($javascript, '?codiRegal='));
        Assert::same(false, str_contains($javascript, '&codiRegal='));
        Assert::same(false, str_contains($javascript, '?dni='));
        Assert::same(false, str_contains($javascript, '&dni='));
        Assert::same(
            false,
            str_contains($javascript, 'enviarInscripcioBescanvia.php?')
        );

        foreach ([
            'codiRegalValid.php',
            'buscarCursRegalat.php',
            'enviarInscripcioBescanvia.php',
        ] as $endpoint) {
            $source = file_get_contents(
                $root . '/codi-drive/web-actual/ajax/' . $endpoint
            );
            if (!is_string($source)) {
                Assert::fail('Could not read public UC-018 endpoint: ' . $endpoint);
            }

            Assert::stringContainsString(
                "\$_SERVER['REQUEST_METHOD']",
                $source
            );
            Assert::stringContainsString("!== 'POST'", $source);
            Assert::stringContainsString('$_POST', $source);
            Assert::same(false, str_contains($source, '$_GET'));
        }

        $duplicate = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/inscripcioDuplicada.php'
        );
        if (!is_string($duplicate)) {
            Assert::fail('Could not read shared duplicate-enrollment endpoint');
        }
        Assert::stringContainsString(
            "\$requestMethod === 'POST' ? \$_POST : \$_GET",
            $duplicate
        );

        $lookup = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/buscarCursRegalat.php'
        );
        if (!is_string($lookup)) {
            Assert::fail('Could not read gift course lookup endpoint');
        }
        Assert::stringContainsString('codiRegalValid($codiRegal)', $lookup);

        $writer = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/enviarInscripcioBescanvia.php'
        );
        if (!is_string($writer)) {
            Assert::fail('Could not read gift redemption writer');
        }
        Assert::stringContainsString('if ((int) $factRel <= 0)', $writer);

        $legacyGift = file_get_contents(
            $root . '/codi-drive/web-actual/BescanviaRegal.php'
        );
        if (!is_string($legacyGift)) {
            Assert::fail('Could not read legacy gift validation service');
        }

        Assert::stringContainsString(
            'El codi de regal no es pot utilitzar en aquest moment.',
            $legacyGift
        );
        Assert::same(2, substr_count($legacyGift, 'WHERE CODI = ?'));
        Assert::same(false, str_contains($legacyGift, 'WHERE CODI LIKE ?'));

        Assert::same(false, str_contains(
            $legacyGift,
            'El codi <strong>".strtoupper($codiRegal)."</strong>'
        ));
    }

}
