<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialOperationRepository;
use Prisma\Sif\Repository\PaymentLinkRepository;

final class PaymentLinkService
{
    private \Closure $tokenFactory;

    public function __construct(
        private TransactionRunner $transactions,
        private CommercialOperationRepository $operations,
        private PaymentLinkRepository $links,
        private UuidGenerator $uuidGenerator,
        ?\Closure $tokenFactory = null
    ) {
        $this->tokenFactory = $tokenFactory
            ?? static fn (): string => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function issue(array $input): array
    {
        $uuidOperation = $this->requiredString($input['uuid_operation'] ?? null, 'uuid_operation');
        $expectedAmount = $this->amount($input['expected_amount'] ?? null);
        $currency = strtoupper($this->requiredString($input['currency'] ?? 'EUR', 'currency'));
        if (strlen($currency) !== 3) {
            throw SifException::validation('Payment link currency must have three characters');
        }

        $expiresAt = $this->requiredString($input['expires_at'] ?? null, 'expires_at');
        if (strtotime($expiresAt) === false) {
            throw SifException::validation('Payment link expires_at must be a valid date/time');
        }

        $plainToken = ($this->tokenFactory)();
        if (!is_string($plainToken) || trim($plainToken) === '') {
            throw new \RuntimeException('Payment link token factory returned an empty token');
        }
        $plainToken = trim($plainToken);
        $tokenHash = hash('sha256', $plainToken);

        return $this->transactions->run(function (\PDO $db) use (
            $input,
            $uuidOperation,
            $expectedAmount,
            $currency,
            $expiresAt,
            $plainToken,
            $tokenHash
        ): array {
            $operation = $this->operations->findByUuid($db, $uuidOperation, true);
            if ($operation === null) {
                throw SifException::notFound('Commercial operation not found for payment link');
            }

            $operationExpiry = $operation['EXPIRES_AT'] ?? null;
            if (
                is_string($operationExpiry)
                && $operationExpiry !== ''
                && strtotime($expiresAt) > strtotime($operationExpiry)
            ) {
                throw SifException::validation(
                    'Payment link cannot expire after the commercial operation'
                );
            }

            $netAmount = number_format((float) $operation['NET_AMOUNT'], 2, '.', '');
            if ($this->cents($expectedAmount) > $this->cents($netAmount)) {
                throw SifException::conflict(
                    'Payment link expected amount cannot exceed commercial operation net amount'
                );
            }

            $uuidPaymentLink = $this->uuidGenerator->generate();
            $this->links->insert($db, [
                'uuid_payment_link' => $uuidPaymentLink,
                'uuid_operation' => $uuidOperation,
                'token_hash' => $tokenHash,
                'status' => 'ACTIVE',
                'payer_party_key' => $this->nullableString($input['payer_party_key'] ?? null),
                'expected_amount' => $expectedAmount,
                'currency' => $currency,
                'expires_at' => $expiresAt,
                'revoked_at' => null,
                'revoked_by' => null,
                'revoke_reason' => null,
                'replaced_by_uuid' => null,
                'last_accessed_at' => null,
                'created_by' => $this->nullableString($input['created_by'] ?? null),
            ]);

            return [
                'uuid_payment_link' => $uuidPaymentLink,
                'uuid_operation' => $uuidOperation,
                'status' => 'ACTIVE',
                'expected_amount' => $expectedAmount,
                'currency' => $currency,
                'expires_at' => $expiresAt,
                'token' => $plainToken,
            ];
        });
    }

    public function resolve(string $plainToken, ?string $accessedAt = null): array
    {
        $plainToken = trim($plainToken);
        if ($plainToken === '') {
            throw SifException::validation('Payment link token is required');
        }

        $accessedAt ??= date('Y-m-d H:i:s');
        $tokenHash = hash('sha256', $plainToken);

        return $this->transactions->run(function (\PDO $db) use ($tokenHash, $accessedAt): array {
            $link = $this->links->findByTokenHash($db, $tokenHash, true);
            if ($link === null) {
                throw SifException::notFound('Payment link not found');
            }
            if ((string) $link['STATUS'] !== 'ACTIVE') {
                throw SifException::forbidden('Payment link is not active');
            }

            $expiresAt = (string) $link['EXPIRES_AT'];
            if (strtotime($expiresAt) < strtotime($accessedAt)) {
                throw SifException::forbidden('Payment link has expired');
            }

            $operation = $this->operations->findByUuid(
                $db,
                (string) $link['UUID_OPERATION'],
                true
            );
            if ($operation === null) {
                throw SifException::notFound('Commercial operation for payment link not found');
            }

            $operationExpiry = $operation['EXPIRES_AT'] ?? null;
            if (
                is_string($operationExpiry)
                && $operationExpiry !== ''
                && strtotime($operationExpiry) < strtotime($accessedAt)
            ) {
                throw SifException::forbidden('Commercial operation has expired');
            }

            $this->links->markAccessed($db, (string) $link['UUID_PAYMENT_LINK'], $accessedAt);

            return [
                'uuid_payment_link' => (string) $link['UUID_PAYMENT_LINK'],
                'uuid_operation' => (string) $link['UUID_OPERATION'],
                'status' => (string) $link['STATUS'],
                'expected_amount' => number_format((float) $link['EXPECTED_AMOUNT'], 2, '.', ''),
                'currency' => (string) $link['CURRENCY'],
                'expires_at' => $expiresAt,
                'operation_status' => (string) $operation['STATUS'],
                'operation_net_amount' => number_format((float) $operation['NET_AMOUNT'], 2, '.', ''),
            ];
        });
    }

    public function revoke(
        string $uuidPaymentLink,
        string $reason,
        ?string $revokedBy = null,
        ?string $replacedByUuid = null,
        ?string $revokedAt = null
    ): array {
        $uuidPaymentLink = $this->requiredString($uuidPaymentLink, 'uuid_payment_link');
        $reason = $this->requiredString($reason, 'revoke_reason');
        $revokedAt ??= date('Y-m-d H:i:s');

        return $this->transactions->run(function (\PDO $db) use (
            $uuidPaymentLink,
            $reason,
            $revokedBy,
            $replacedByUuid,
            $revokedAt
        ): array {
            $current = $this->links->findByUuid($db, $uuidPaymentLink, true);
            if ($current === null) {
                throw SifException::notFound('Payment link not found');
            }
            if ((string) $current['STATUS'] === 'REVOKED') {
                return [
                    'uuid_payment_link' => $uuidPaymentLink,
                    'status' => 'REVOKED',
                    'idempotency_reused' => true,
                ];
            }

            $changed = $this->links->revoke(
                $db,
                $uuidPaymentLink,
                $revokedAt,
                $this->nullableString($revokedBy),
                $reason,
                $this->nullableString($replacedByUuid)
            );
            if (!$changed) {
                throw SifException::conflict('Payment link state changed concurrently');
            }

            return [
                'uuid_payment_link' => $uuidPaymentLink,
                'status' => 'REVOKED',
                'idempotency_reused' => false,
            ];
        });
    }

    private function amount(mixed $value): string
    {
        if (!is_numeric($value) || (float) $value <= 0) {
            throw SifException::validation('Payment link expected amount must be positive');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function cents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function requiredString(mixed $value, string $field): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            throw SifException::validation('Missing payment link field: ' . $field);
        }

        return $value;
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : trim((string) $value);
    }
}
