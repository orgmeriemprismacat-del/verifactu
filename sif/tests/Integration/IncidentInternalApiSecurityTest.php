<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\IncidentActionRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Service\IncidentLifecycleService;
use Prisma\Sif\Service\InternalApiAuthenticator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IncidentInternalApiSecurityTest
{
    public function testInvalidSignatureExpiredTimestampAndReplayFailClosed(): void
    {
        $db = TestDatabase::fresh();
        $keyId = 'incident-api-key';
        $secret = str_repeat('s', 40);
        $path = '/api/incidents/manage.php';
        $body = '{"action":"summary"}';

        $invalid = $this->server($keyId, $secret, $path, $body, time());
        $invalid['HTTP_X_SIF_SIGNATURE'] = str_repeat('0', 64);
        Assert::throws(
            SifException::class,
            fn () => $this->authenticator($db, $keyId, $secret)->authenticate($invalid, $body, 'POST', $path),
            401
        );

        $expired = $this->server($keyId, $secret, $path, $body, time() - 1200);
        Assert::throws(
            SifException::class,
            fn () => $this->authenticator($db, $keyId, $secret)->authenticate($expired, $body, 'POST', $path),
            401
        );

        $valid = $this->server($keyId, $secret, $path, $body, time());
        $actor = $this->authenticator($db, $keyId, $secret)->authenticate($valid, $body, 'POST', $path);
        Assert::same('incident-api-test', $actor['actor_id']);
        Assert::same(['AUDITOR_FISCAL'], $actor['roles']);
        Assert::same(false, array_key_exists('secret', $actor));
        Assert::same(false, array_key_exists('signature', $actor));
        Assert::same(false, array_key_exists('key_id', $actor));

        Assert::throws(
            SifException::class,
            fn () => $this->authenticator($db, $keyId, $secret)->authenticate($valid, $body, 'POST', $path),
            409
        );
    }

    public function testEmptyRoleConfigurationFailsClosed(): void
    {
        $db = TestDatabase::fresh();
        $service = new IncidentLifecycleService(
            $db,
            new TransactionRunner($db),
            new IncidentRepository(),
            new IncidentActionRepository(),
            [],
            []
        );

        Assert::throws(
            SifException::class,
            fn () => $service->list([
                'actor_id' => 'operator-test',
                'roles' => ['SIF_ADMIN'],
                'request_id' => 'request-test',
            ]),
            403
        );
    }

    public function testUnknownViewReturns404AndJournalStoresActorAndRole(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);

        Assert::throws(
            SifException::class,
            fn () => $service->view($this->reader(), 999999),
            404
        );

        $opened = $service->open($this->manager(), [
            'type' => 'API_SECURITY_TEST',
            'message' => 'Journal actor test',
            'reason_code' => 'TEST_OPEN',
            'idempotency_key' => 'TEST|UC08|API|OPEN',
        ]);

        $service->assign($this->manager(), $opened['incident_id'], [
            'assignee_id' => 'operator-2',
            'reason_code' => 'TRIAGE',
            'idempotency_key' => 'TEST|UC08|API|ASSIGN',
        ]);

        $service->resolve($this->manager(), $opened['incident_id'], [
            'reason_code' => 'VERIFIED',
            'idempotency_key' => 'TEST|UC08|API|RESOLVE',
            'closure_criteria' => 'API journal verified.',
            'resolution_notes' => 'Resolved in API security test.',
            'evidence' => ['reference' => 'UC08-API-SECURITY'],
        ]);

        $rows = $db->query(
            "SELECT ACTION_TYPE, ACTOR_ID, ACTOR_ROLE
             FROM sif_incident_action
             WHERE ACTION_TYPE IN ('ASSIGN', 'RESOLVE')
             ORDER BY ID ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(2, count($rows));
        Assert::same('incident-manager', $rows[0]['ACTOR_ID']);
        Assert::same('SIF_ADMIN', $rows[0]['ACTOR_ROLE']);
        Assert::same('incident-manager', $rows[1]['ACTOR_ID']);
        Assert::same('SIF_ADMIN', $rows[1]['ACTOR_ROLE']);
    }

    public function testEndpointContractRejectsGetUnknownActionAndCapsList(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/public/api/incidents/manage.php');
        if ($source === false) {
            Assert::fail('Could not read incident management endpoint');
        }

        Assert::stringContainsString("REQUEST_METHOD", $source);
        Assert::stringContainsString("'POST'", $source);
        Assert::stringContainsString("'Method not allowed'], 405", $source);
        Assert::stringContainsString("Unknown incident action", $source);
        Assert::stringContainsString("max(1, min(100", $source);
        Assert::stringContainsString('max(1, min($configuredMax, $requested))', $source);
    }

    private function authenticator(\PDO $db, string $keyId, string $secret): InternalApiAuthenticator
    {
        return new InternalApiAuthenticator(
            $db,
            new InternalApiRequestRepository(),
            $keyId,
            $secret,
            300
        );
    }

    private function server(
        string $keyId,
        string $secret,
        string $path,
        string $body,
        int $timestamp
    ): array {
        $requestId = (new UuidGenerator())->generate();
        $actorId = 'incident-api-test';
        $roles = 'AUDITOR_FISCAL';
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", [
            'POST',
            $path,
            (string) $timestamp,
            strtolower($requestId),
            $actorId,
            $roles,
            $bodyHash,
        ]);

        return [
            'HTTP_X_SIF_KEY_ID' => $keyId,
            'HTTP_X_SIF_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SIF_REQUEST_ID' => $requestId,
            'HTTP_X_SIF_ACTOR_ID' => $actorId,
            'HTTP_X_SIF_ACTOR_ROLES' => $roles,
            'HTTP_X_SIF_SIGNATURE' => hash_hmac('sha256', $canonical, $secret),
        ];
    }

    private function service(\PDO $db): IncidentLifecycleService
    {
        return new IncidentLifecycleService(
            $db,
            new TransactionRunner($db),
            new IncidentRepository(),
            new IncidentActionRepository(),
            ['AUDITOR_FISCAL', 'SIF_ADMIN'],
            ['SIF_ADMIN']
        );
    }

    private function reader(): array
    {
        return [
            'actor_id' => 'incident-reader',
            'roles' => ['AUDITOR_FISCAL'],
            'request_id' => 'reader-request',
        ];
    }

    private function manager(): array
    {
        return [
            'actor_id' => 'incident-manager',
            'roles' => ['SIF_ADMIN'],
            'request_id' => 'manager-request',
        ];
    }
}
