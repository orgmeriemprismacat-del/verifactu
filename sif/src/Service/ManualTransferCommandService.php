<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ManualTransferCommandService
{
    public function __construct(
        private ManualPaymentService $manualPayments,
        private array $writeRoles,
        private ?PaymentActionGateway $auditGateway = null,
        private string $sourceEnvironment = 'DEVELOPMENT'
    ) {
        $this->writeRoles = array_values(array_unique(array_filter(array_map(
            static fn (mixed $role): string => strtoupper(trim((string) $role)),
            $this->writeRoles
        ))));
        $this->sourceEnvironment = $this->normalizeEnvironment($this->sourceEnvironment);
    }

    public function register(\PDO $db, array $actor, array $payload): array
    {
        $this->assertAuthorized($actor);

        $externalEventId = trim((string) ($payload['external_bank_event_id'] ?? ''));
        if ($externalEventId === '') {
            throw SifException::validation('Missing external bank event id');
        }
        if (mb_strlen($externalEventId, 'UTF-8') > 190) {
            throw SifException::validation('External bank event id is too long');
        }

        $uuidFactura = trim((string) ($payload['uuid_factura'] ?? ''));
        $numVisible = trim((string) ($payload['num_visible'] ?? ''));
        if (($uuidFactura === '') === ($numVisible === '')) {
            throw SifException::validation('Provide exactly one invoice selector');
        }

        $input = $payload;
        unset($input['uuid_factura']);
        $input['method'] = 'TRANSFERENCIA';
        $input['external_bank_event_id'] = $externalEventId;

        if ($this->auditGateway === null) {
            return $this->finalizeResult(
                $uuidFactura !== ''
                    ? $this->manualPayments->registerByUuid($db, $uuidFactura, $input)
                    : $this->manualPayments->registerByNumVisible($db, $numVisible, $input),
                $actor
            );
        }

        return $this->auditGateway->run(
            $this->auditContext($actor, $payload),
            function (\PDO $transactionDb) use ($uuidFactura, $numVisible, $input, $actor): array {
                $result = $uuidFactura !== ''
                    ? $this->manualPayments->registerByUuidInTransaction(
                        $transactionDb,
                        $uuidFactura,
                        $input
                    )
                    : $this->manualPayments->registerByNumVisibleInTransaction(
                        $transactionDb,
                        $numVisible,
                        $input
                    );

                return $this->finalizeResult($result, $actor);
            }
        );
    }

    private function finalizeResult(array $result, array $actor): array
    {
        $result['status'] = ($result['idempotency_reused'] ?? false) ? 'REUSED' : 'CREATED';
        $result['request_id'] = (string) ($actor['request_id'] ?? '');

        return $result;
    }

    private function auditContext(array $actor, array $payload): array
    {
        $requestId = trim((string) ($actor['request_id'] ?? ''));
        $correlationId = trim((string) ($payload['correlation_id'] ?? ''));
        if ($correlationId === '') {
            $correlationId = $requestId;
        }

        return [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'action' => 'CREATE',
            'source_environment' => $this->sourceEnvironment,
            'source_channel' => 'INTRANET',
            'actor_type' => 'HUMAN',
            'actor_id' => trim((string) ($actor['actor_id'] ?? '')),
            'actor_role' => $this->actorRole($actor),
            'reason_code' => 'UC-022',
            'occurred_at' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
                ->format('Y-m-d H:i:s.u'),
        ];
    }

    private function actorRole(array $actor): string
    {
        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_filter(array_map(
                static fn (mixed $role): string => strtoupper(trim((string) $role)),
                $actor['roles']
            )))
            : [];

        foreach ($roles as $role) {
            if (in_array($role, $this->writeRoles, true)) {
                return $role;
            }
        }

        return $roles[0] ?? 'UNKNOWN';
    }

    private function assertAuthorized(array $actor): void
    {
        $actorId = trim((string) ($actor['actor_id'] ?? ''));
        $roles = is_array($actor['roles'] ?? null)
            ? array_map(static fn (mixed $role): string => strtoupper(trim((string) $role)), $actor['roles'])
            : [];

        if ($actorId === '' || $roles === []) {
            throw SifException::unauthorized('Invalid manual transfer actor');
        }

        if ($this->writeRoles === [] || array_intersect($roles, $this->writeRoles) === []) {
            throw SifException::forbidden('Actor cannot register manual transfers');
        }
    }

    private function normalizeEnvironment(string $environment): string
    {
        return match (strtolower(trim($environment))) {
            'production', 'prod' => 'PRODUCTION',
            'preproduction', 'pre', 'staging' => 'PREPRODUCTION',
            'test', 'testing' => 'TEST',
            'migration' => 'MIGRATION',
            default => 'DEVELOPMENT',
        };
    }
}
