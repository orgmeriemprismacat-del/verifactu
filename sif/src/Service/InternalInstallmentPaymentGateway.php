<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InternalInstallmentPaymentGateway
{
    public function __construct(
        private ManualInstallmentPaymentService $installments,
        private array $allowedRoles
    ) {
        $this->allowedRoles = $this->normalizeRoles($this->allowedRoles);
    }

    public function register(\PDO $db, array $actor, array $payload): array
    {
        $actorId = trim((string) ($actor['actor_id'] ?? ''));
        $roles = $actor['roles'] ?? [];
        if ($actorId === '' || !is_array($roles)) {
            throw SifException::forbidden('Authenticated installment actor is required');
        }

        $roles = $this->normalizeRoles($roles);
        if ($this->allowedRoles === [] || array_intersect($roles, $this->allowedRoles) === []) {
            throw SifException::forbidden('Installment payment role is not authorized');
        }

        $input = $payload['input'] ?? [];
        if (!is_array($input)) {
            throw SifException::validation('Invalid installment input');
        }

        // The authenticated server-side actor is authoritative. Never trust a
        // browser-supplied username for audit/idempotency.
        $input['user'] = $actorId;

        $uuidFactura = trim((string) ($payload['uuid_factura'] ?? ''));
        $numVisible = trim((string) ($payload['num_visible'] ?? ''));

        if (($uuidFactura === '') === ($numVisible === '')) {
            throw SifException::validation('Provide exactly one invoice identifier');
        }

        if ($uuidFactura !== '') {
            return $this->installments->registerByUuid($db, $uuidFactura, $input);
        }

        return $this->installments->registerByNumVisible($db, $numVisible, $input);
    }

    private function normalizeRoles(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }

        return array_keys($normalized);
    }
}
