<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InternalInstallmentPaymentGateway
{
    public function __construct(
        private ManualInstallmentPaymentService $installments,
        private array $allowedRoles,
        private InstallmentPaymentAuditTrail $audit
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
        $actor['roles'] = $roles;

        $rawInput = $payload['input'] ?? [];
        $context = $this->audit->context(
            $actor,
            is_array($rawInput) ? $rawInput : []
        );

        if ($this->allowedRoles === [] || array_intersect($roles, $this->allowedRoles) === []) {
            $this->audit->rejected(
                $db,
                $context,
                'ACCESS_DENIED',
                'UC023_ACCESS_DENIED'
            );
            throw SifException::forbidden('Installment payment role is not authorized');
        }

        $this->audit->requested($db, $context);

        try {
            if (!is_array($rawInput)) {
                throw SifException::validation('Invalid installment input');
            }

            $input = $rawInput;

            // The authenticated server-side actor is authoritative. Never trust a
            // browser-supplied username for audit/idempotency.
            $input['user'] = $actorId;

            $uuidFactura = trim((string) ($payload['uuid_factura'] ?? ''));
            $numVisible = trim((string) ($payload['num_visible'] ?? ''));

            if (($uuidFactura === '') === ($numVisible === '')) {
                throw SifException::validation('Provide exactly one invoice identifier');
            }

            $afterPersist = function (
                \PDO $transactionDb,
                array $paymentPayload,
                array $result
            ) use ($context): void {
                $this->audit->succeeded(
                    $transactionDb,
                    $context,
                    $paymentPayload,
                    $result
                );
            };

            if ($uuidFactura !== '') {
                return $this->installments->registerByUuid(
                    $db,
                    $uuidFactura,
                    $input,
                    $afterPersist
                );
            }

            return $this->installments->registerByNumVisible(
                $db,
                $numVisible,
                $input,
                $afterPersist
            );
        } catch (\Throwable $exception) {
            try {
                $this->audit->failed($db, $context, $exception);
            } catch (\Throwable) {
                // Preserve the original payment failure. The REQUESTED event, when
                // successfully written, still proves that the operation was attempted.
            }

            throw $exception;
        }
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
