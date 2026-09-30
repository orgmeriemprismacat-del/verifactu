<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Service\PanelLaunchAuthenticator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IncidentPanelLaunchAuthenticatorTest
{
    public function testValidSignedLaunchAuthenticatesAndReplayIsRejected(): void
    {
        $db = TestDatabase::fresh();
        $keyId = 'panel-test-key';
        $secret = 'panel-test-secret';
        $path = '/sif/incidencies/';
        $timestamp = (string) time();
        $requestId = (new UuidGenerator())->generate();
        $actorId = 'operator-test';
        $roles = 'AUDITOR_FISCAL,SIF_ADMIN';

        $canonical = implode("\n", [
            'SIF_PANEL_LAUNCH',
            'POST',
            $path,
            $timestamp,
            strtolower($requestId),
            $actorId,
            $roles,
        ]);

        $payload = [
            'key_id' => $keyId,
            'timestamp' => $timestamp,
            'request_id' => $requestId,
            'actor_id' => $actorId,
            'roles' => $roles,
            'signature' => hash_hmac('sha256', $canonical, $secret),
        ];

        $authenticator = new PanelLaunchAuthenticator(
            $db,
            new InternalApiRequestRepository(),
            $keyId,
            $secret,
            120
        );

        $actor = $authenticator->authenticate($payload, $path);
        Assert::same($actorId, $actor['actor_id']);
        Assert::same(['AUDITOR_FISCAL', 'SIF_ADMIN'], $actor['roles']);
        Assert::same(
            1,
            (int) $db->query("SELECT COUNT(*) FROM internal_api_request WHERE REQUEST_PATH = '/sif/incidencies/'")
                ->fetchColumn()
        );

        Assert::throws(
            SifException::class,
            fn () => $authenticator->authenticate($payload, $path),
            409
        );
    }

    public function testInvalidPanelLaunchSignatureFailsClosed(): void
    {
        $db = TestDatabase::fresh();

        $authenticator = new PanelLaunchAuthenticator(
            $db,
            new InternalApiRequestRepository(),
            'panel-test-key',
            'panel-test-secret',
            120
        );

        Assert::throws(
            SifException::class,
            fn () => $authenticator->authenticate([
                'key_id' => 'panel-test-key',
                'timestamp' => (string) time(),
                'request_id' => (new UuidGenerator())->generate(),
                'actor_id' => 'operator-test',
                'roles' => 'SIF_ADMIN',
                'signature' => str_repeat('0', 64),
            ], '/sif/incidencies/'),
            401
        );
    }
}
