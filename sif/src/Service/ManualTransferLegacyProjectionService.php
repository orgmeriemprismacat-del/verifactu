<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Repository\PaymentActionEventWriter;

final class ManualTransferLegacyProjectionService
{
    public function __construct(
        private \PDO $sifDb,
        private PaymentActionEventWriter $events,
        private GeneratedInvoiceLegacyPaymentSyncService $sync,
        private string $sourceEnvironment = 'DEVELOPMENT'
    ) {
        $this->sourceEnvironment = $this->normalizeEnvironment($this->sourceEnvironment);
    }

    public function project(
        \PDO $legacyDb,
        array $actor,
        array $payload,
        array $paymentResult
    ): array {
        $context = $this->context($actor, $payload, $paymentResult);

        $this->events->append($this->sifDb, array_merge($context, [
            'result' => 'REQUESTED',
            'is_terminal' => false,
        ]));

        try {
            $result = $this->sync->sync(
                $this->sifDb,
                $legacyDb,
                (string) ($paymentResult['uuid_factura'] ?? ''),
                (string) ($paymentResult['num_visible'] ?? ''),
                trim((string) ($payload['movement_date'] ?? '')),
                'TRANSFERENCIA'
            );

            $this->events->append($this->sifDb, array_merge($context, [
                'result' => 'SUCCEEDED',
                'is_terminal' => true,
                'changeset' => [
                    'projection_status' => $result['status'] ?? null,
                    'confirmed_amount' => $result['confirmed_amount'] ?? null,
                    'projected_amount' => $result['projected_amount'] ?? null,
                ],
            ]));

            return $result;
        } catch (\Throwable $exception) {
            try {
                $this->events->append($this->sifDb, array_merge($context, [
                    'result' => 'FAILED',
                    'is_terminal' => true,
                    'error_code' => $this->errorCode($exception),
                ]));
            } catch (\Throwable) {
                // Preserve the projection error. The caller will return PENDING_RETRY.
            }

            throw $exception;
        }
    }

    private function context(array $actor, array $payload, array $paymentResult): array
    {
        $requestId = trim((string) ($actor['request_id'] ?? ''));
        $correlationId = trim((string) ($payload['correlation_id'] ?? ''));
        if ($correlationId === '') {
            $correlationId = $requestId;
        }

        $roles = is_array($actor['roles'] ?? null)
            ? array_values(array_filter(array_map(
                static fn (mixed $role): string => strtoupper(trim((string) $role)),
                $actor['roles']
            )))
            : [];

        return [
            'uuid_payment' => $paymentResult['uuid_payment'] ?? null,
            'payment_idempotency_key' => $paymentResult['payment_idempotency_key'] ?? null,
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'action' => 'SYNC_LEGACY',
            'source_environment' => $this->sourceEnvironment,
            'source_channel' => 'LEGACY_SYNC',
            'actor_type' => 'HUMAN',
            'actor_id' => trim((string) ($actor['actor_id'] ?? '')),
            'actor_role' => $roles[0] ?? 'UNKNOWN',
            'reason_code' => 'UC-022',
            'occurred_at' => (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))
                ->format('Y-m-d H:i:s.u'),
        ];
    }

    private function errorCode(\Throwable $exception): string
    {
        $class = strtoupper(str_replace('\\', '_', $exception::class));

        return substr($class, 0, 80);
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
