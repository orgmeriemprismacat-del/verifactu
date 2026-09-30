<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InternalApiAuthenticatorTest
{
    public function testAuthenticatesSignedRequestAndRejectsReplay(): void
    {
        $db = TestDatabase::fresh();
        $secret = 'synthetic-internal-api-secret-for-tests';
        $keyId = 'intranet-test';
        $body = '{"action":"preview","entity_id":7}';
        $timestamp = (string) time();
        $requestId = '123e4567-e89b-42d3-a456-426614174000';
        $actorId = 'gestio-test';
        $roles = 'GESTIO,FACTURACIO';
        $path = '/api/factures/before-payment.php';

        $server = $this->signedServer(
            $keyId,
            $secret,
            $timestamp,
            $requestId,
            $actorId,
            $roles,
            'POST',
            $path,
            $body
        );

        $authenticator = new InternalApiAuthenticator(
            $db,
            new InternalApiRequestRepository(),
            $keyId,
            $secret,
            300
        );

        $actor = $authenticator->authenticate($server, $body, 'POST', $path);

        Assert::same($actorId, $actor['actor_id']);
        Assert::same(['GESTIO', 'FACTURACIO'], $actor['roles']);
        Assert::same(strtolower($requestId), $actor['request_id']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM internal_api_request')->fetchColumn());

        Assert::throws(SifException::class, function () use (
            $authenticator,
            $server,
            $body,
            $path
        ): void {
            $authenticator->authenticate($server, $body, 'POST', $path);
        }, 409);
    }

    public function testRejectsInvalidSignatureWithoutClaimingRequest(): void
    {
        $db = TestDatabase::fresh();
        $server = [
            'HTTP_X_SIF_KEY_ID' => 'intranet-test',
            'HTTP_X_SIF_TIMESTAMP' => (string) time(),
            'HTTP_X_SIF_REQUEST_ID' => '123e4567-e89b-42d3-a456-426614174001',
            'HTTP_X_SIF_ACTOR_ID' => 'gestio-test',
            'HTTP_X_SIF_ACTOR_ROLES' => 'GESTIO',
            'HTTP_X_SIF_SIGNATURE' => str_repeat('0', 64),
        ];

        $authenticator = new InternalApiAuthenticator(
            $db,
            new InternalApiRequestRepository(),
            'intranet-test',
            'synthetic-internal-api-secret-for-tests',
            300
        );

        Assert::throws(SifException::class, function () use ($authenticator, $server): void {
            $authenticator->authenticate(
                $server,
                '{"action":"preview"}',
                'POST',
                '/api/factures/before-payment.php'
            );
        }, 401);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM internal_api_request')->fetchColumn());
    }

    private function signedServer(
        string $keyId,
        string $secret,
        string $timestamp,
        string $requestId,
        string $actorId,
        string $roles,
        string $method,
        string $path,
        string $body
    ): array {
        $normalizedRoles = [];
        foreach (explode(',', $roles) as $role) {
            $value = strtoupper(trim($role));
            if ($value !== '') {
                $normalizedRoles[$value] = true;
            }
        }
        $canonicalRoles = implode(',', array_keys($normalizedRoles));

        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            strtolower($requestId),
            $actorId,
            $canonicalRoles,
            hash('sha256', $body),
        ]);

        return [
            'HTTP_X_SIF_KEY_ID' => $keyId,
            'HTTP_X_SIF_TIMESTAMP' => $timestamp,
            'HTTP_X_SIF_REQUEST_ID' => strtolower($requestId),
            'HTTP_X_SIF_ACTOR_ID' => $actorId,
            'HTTP_X_SIF_ACTOR_ROLES' => $canonicalRoles,
            'HTTP_X_SIF_SIGNATURE' => hash_hmac('sha256', $canonical, $secret),
        ];
    }
}
