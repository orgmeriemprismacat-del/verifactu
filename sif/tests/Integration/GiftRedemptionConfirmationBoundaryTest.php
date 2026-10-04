<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

require_once dirname(__DIR__, 3)
    . '/codi-drive/web-actual/inc/GiftRedemptionConfirmationToken.php';

final class GiftRedemptionConfirmationBoundaryTest
{
    public function testConfirmationTokenRoundTripsAndIsUrlFragmentSafe(): void
    {
        $token = \GiftRedemptionConfirmationToken::issue(
            501,
            'uc018-confirmation-test-key'
        );

        Assert::same(true, str_starts_with($token, 'v2.'));
        Assert::same(false, str_contains($token, '+'));
        Assert::same(false, str_contains($token, '/'));
        Assert::same(false, str_contains($token, '='));
        Assert::same(
            501,
            \GiftRedemptionConfirmationToken::parse(
                $token,
                'uc018-confirmation-test-key'
            )
        );

        $source = file_get_contents(
            dirname(__DIR__, 3)
            . '/codi-drive/web-actual/inc/GiftRedemptionConfirmationToken.php'
        );
        if (!is_string($source)) {
            Assert::fail('Could not read gift confirmation token implementation');
        }
        Assert::stringContainsString(
            "hash_hmac('sha256', self::AAD, \$keyMaterial, true)",
            $source
        );
    }

    public function testConfirmationTokenRejectsNonceTagOrCiphertextTampering(): void
    {
        $token = \GiftRedemptionConfirmationToken::issue(
            501,
            'uc018-confirmation-test-key'
        );
        $payload = substr($token, 3);

        foreach ([0, 12, max(28, strlen($payload) - 1)] as $position) {
            $tampered = $payload;
            $position = min($position, strlen($tampered) - 1);
            $tampered[$position] = $tampered[$position] === 'A' ? 'B' : 'A';

            Assert::throws(
                \RuntimeException::class,
                static function () use ($tampered): void {
                    \GiftRedemptionConfirmationToken::parse(
                        'v2.' . $tampered,
                        'uc018-confirmation-test-key'
                    );
                }
            );
        }
    }

    public function testConfirmationSurfaceKeepsBearerTokenOutOfRequestAndReferer(): void
    {
        $root = dirname(__DIR__, 3);
        $checkoutJs = file_get_contents(
            $root . '/codi-drive/web-actual/js1619773569/mostrarBescanvia.min.js'
        );
        $confirmationJs = file_get_contents(
            $root . '/codi-drive/web-actual/js1619773569/mostrarConfirmacioBescanvia.min.js'
        );
        $endpoint = file_get_contents(
            $root . '/codi-drive/web-actual/ajax/mostrar_confirmacio_bescanvia.php'
        );
        $page = file_get_contents(
            $root . '/codi-drive/web-actual/pagina_confirmacio_bescanvia.php'
        );
        $routes = file_get_contents(
            $root . '/codi-drive/web-actual/.htaccess'
        );

        foreach ([
            'checkout JS' => $checkoutJs,
            'confirmation JS' => $confirmationJs,
            'confirmation endpoint' => $endpoint,
            'confirmation page' => $page,
            'routes' => $routes,
        ] as $label => $source) {
            if (!is_string($source)) {
                Assert::fail('Could not read UC-018 ' . $label);
            }
        }

        Assert::stringContainsString(
            '/bescanvia/confirmacio/v2#',
            $checkoutJs
        );
        Assert::stringContainsString('encodeURIComponent', $checkoutJs);
        Assert::stringContainsString('window.location.hash', $confirmationJs);
        Assert::stringContainsString('window.history.replaceState', $confirmationJs);
        Assert::stringContainsString('type: "POST"', $confirmationJs);
        Assert::stringContainsString('token: confirmationToken', $confirmationJs);
        Assert::same(false, str_contains($confirmationJs, '?keyEncr='));

        Assert::stringContainsString(
            "\$_SERVER['REQUEST_METHOD']",
            $endpoint
        );
        Assert::stringContainsString("!== 'POST'", $endpoint);
        Assert::stringContainsString(
            'GiftRedemptionConfirmationToken::parse',
            $endpoint
        );
        Assert::same(false, str_contains($endpoint, 'REQUEST_URI'));
        Assert::same(false, str_contains($endpoint, 'base64_decode'));
        Assert::same(false, str_contains($endpoint, 'SELECT CODI FROM regal'));
        Assert::stringContainsString('no-store', $endpoint);
        Assert::stringContainsString('no-referrer', $endpoint);

        Assert::stringContainsString(
            '<meta name="referrer" content="no-referrer">',
            $page
        );
        Assert::stringContainsString(
            'mostrarConfirmacioBescanvia.min.js?ver=7.0',
            $page
        );

        Assert::stringContainsString(
            'RewriteRule ^bescanvia-regal/?$ /pagina_bescanvia.php [L]',
            $routes
        );
        Assert::same(
            false,
            str_contains(
                $routes,
                'RewriteRule ^bescanvia-regal /pagina_bescanvia_prova.php'
            )
        );
    }
}
