<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ManualTransferCommandService
{
    public function __construct(
        private ManualPaymentService $manualPayments,
        private array $writeRoles
    ) {
        $this->writeRoles = array_values(array_unique(array_filter(array_map(
            static fn (mixed $role): string => strtoupper(trim((string) $role)),
            $this->writeRoles
        ))));
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

        $result = $uuidFactura !== ''
            ? $this->manualPayments->registerByUuid($db, $uuidFactura, $input)
            : $this->manualPayments->registerByNumVisible($db, $numVisible, $input);

        $result['status'] = ($result['idempotency_reused'] ?? false) ? 'REUSED' : 'CREATED';
        $result['request_id'] = (string) ($actor['request_id'] ?? '');

        return $result;
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
}
