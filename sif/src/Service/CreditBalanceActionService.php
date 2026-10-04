<?php

namespace Prisma\Sif\Service;

final class CreditBalanceActionService
{
    public function __construct(
        private PaymentActionGateway $gateway,
        private CreditBalanceService $credits
    ) {
    }

    public function createCredit(array $auditContext, array $input): array
    {
        $context = array_merge($auditContext, [
            'action' => 'CREATE',
            'payment_idempotency_key' => $this->auditKey(
                $input['idempotency_key'] ?? null,
                'CREDITKEY'
            ),
        ]);

        return $this->gateway->run(
            $context,
            function (\PDO $db) use ($input): array {
                $result = $this->credits->createCreditInTransaction($db, $input);
                $result['payment_audit_changeset'] = [
                    'operation' => 'CREDIT_BALANCE_CREATE',
                    'uuid_credit' => $result['uuid_credit'] ?? null,
                    'import_disponible' => $result['import_disponible'] ?? null,
                    'estat' => $result['estat'] ?? null,
                ];

                return $result;
            }
        );
    }

    public function applyCreditByUuid(
        array $auditContext,
        string $uuidCredit,
        string $uuidFactura,
        array $input
    ): array {
        return $this->runCompensation(
            $auditContext,
            $input,
            fn (\PDO $db): array => $this->credits->applyCreditByUuidInTransaction(
                $db,
                $uuidCredit,
                $uuidFactura,
                $input
            )
        );
    }

    public function applyCreditByNumVisible(
        array $auditContext,
        string $uuidCredit,
        string $numVisible,
        array $input
    ): array {
        return $this->runCompensation(
            $auditContext,
            $input,
            fn (\PDO $db): array => $this->credits->applyCreditByNumVisibleInTransaction(
                $db,
                $uuidCredit,
                $numVisible,
                $input
            )
        );
    }

    private function runCompensation(
        array $auditContext,
        array $input,
        callable $operation
    ): array {
        $context = array_merge($auditContext, [
            'action' => 'LINK_COMPENSATION',
            'payment_idempotency_key' => $this->auditKey(
                $input['idempotency_key'] ?? null,
                'COMPKEY'
            ),
        ]);

        return $this->gateway->run(
            $context,
            function (\PDO $db) use ($operation): array {
                $result = $operation($db);
                $result['payment_audit_changeset'] = [
                    'operation' => 'CREDIT_COMPENSATION',
                    'uuid_credit' => $result['uuid_credit'] ?? null,
                    'uuid_factura' => $result['uuid_factura'] ?? null,
                    'import_disponible' => $result['import_disponible'] ?? null,
                    'credit_estat' => $result['credit_estat'] ?? null,
                ];

                return $result;
            }
        );
    }

    private function auditKey(mixed $value, string $prefix): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value, 'UTF-8') <= 120) {
            return $value;
        }

        return $prefix . '|SHA256:' . hash('sha256', $value);
    }
}
