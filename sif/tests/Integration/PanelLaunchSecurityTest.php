<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Service\PanelLaunchAuthenticator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

require_once dirname(__DIR__, 3) . '/codi-drive/intranet-actual/SifPanelLaunchToken.php';

final class PanelLaunchSecurityTest
{
    public function testSignedVersionPanelLaunchAuthenticatesAndRejectsReplay(): void
    {
        $db = TestDatabase::fresh();
        $keyId = 'panel-test-key';
        $secret = str_repeat('s', 48);
        $path = '/sif/versions/';

        $token = new \SifPanelLaunchToken(
            'https://pay-test.prisma.cat/sif/versions/',
            $path,
            $keyId,
            $secret
        );
        $launch = $token->create('meriem', ['SIF_ADMIN', 'SIF_AUDITOR']);

        $authenticator = new PanelLaunchAuthenticator(
            $db,
            new InternalApiRequestRepository(),
            $keyId,
            $secret,
            120
        );

        $actor = $authenticator->authenticate($launch['fields'], $path);
        Assert::same('meriem', $actor['actor_id']);
        Assert::same(['SIF_ADMIN', 'SIF_AUDITOR'], $actor['roles']);

        Assert::throws(
            SifException::class,
            fn () => $authenticator->authenticate($launch['fields'], $path),
            409
        );
    }

    public function testSignedLaunchRejectsTamperingAndWrongPath(): void
    {
        $db = TestDatabase::fresh();
        $keyId = 'panel-test-key';
        $secret = str_repeat('t', 48);
        $path = '/sif/versions/';

        $authenticator = new PanelLaunchAuthenticator(
            $db,
            new InternalApiRequestRepository(),
            $keyId,
            $secret,
            120
        );

        $token = new \SifPanelLaunchToken(
            'https://pay-dev.prisma.cat/sif/versions/',
            $path,
            $keyId,
            $secret
        );

        $tampered = $token->create('meriem', ['SIF_ADMIN']);
        $tampered['fields']['roles'] = 'SIF_AUDITOR';
        Assert::throws(
            SifException::class,
            fn () => $authenticator->authenticate($tampered['fields'], $path),
            401
        );

        $wrongPath = $token->create('meriem', ['SIF_ADMIN']);
        Assert::throws(
            SifException::class,
            fn () => $authenticator->authenticate($wrongPath['fields'], '/sif/incidencies/'),
            401
        );
    }

    public function testLaunchTokenRejectsExternalHostAndMismatchedUrlPath(): void
    {
        Assert::throws(
            \RuntimeException::class,
            fn () => new \SifPanelLaunchToken(
                'https://example.invalid/sif/versions/',
                '/sif/versions/',
                'key',
                'secret'
            )
        );

        Assert::throws(
            \RuntimeException::class,
            fn () => new \SifPanelLaunchToken(
                'https://pay.prisma.cat/sif/incidencies/',
                '/sif/versions/',
                'key',
                'secret'
            )
        );

        Assert::throws(
            \RuntimeException::class,
            fn () => new \SifPanelLaunchToken(
                'https://pay.prisma.cat/sif/versions/?leak=1',
                '/sif/versions/',
                'key',
                'secret'
            )
        );
    }
}
