<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * INTERNAL UC-111 outbox preparation, AFTER the grant transaction commits.
 *
 * The redeemable token is random and never included in the public response,
 * logs, or any plaintext SQL column. It is stored encrypted solely to allow
 * a later trusted mail worker to retry delivery of the SAME code.
 *
 * This service does NOT send email, decrypt a token, or authorize redemption.
 * No HTTP endpoint should call it with a key supplied by the browser.
 */
final class NovicePromotionCodePreparationService
{
    public function __construct(private UuidGenerator $uuids)
    {
    }

    public function prepare(
        \PDO $db,
        string $uuidEntitlement,
        string $wrappingKeyHex,
        string $keyVersion,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Prepare token only after the grant transaction commits.');
        }

        if (trim($uuidEntitlement) === ''
            || !preg_match('/^[a-z0-9_-]{1,30}$/D', $keyVersion)
        ) {
            throw SifException::validation('Invalid entitlement reference or key version.');
        }

        // The encryption key must come from a secret store/env, never Git.
        if (!preg_match('/^[0-9a-fA-F]{64}$/D', $wrappingKeyHex)) {
            throw new \RuntimeException('Missing or invalid promotional token wrapping key.');
        }
        $key = hex2bin($wrappingKeyHex);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('Invalid promotional token wrapping key.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $right = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.CODE_HASH, e.STATUS, e.EXPIRES_AT,
                        e.ORIGIN_UUID_OPERATION, g.AVAILABLE_AMOUNT,
                        v.STATUS AS VALIDATION_STATUS,
                        o.STATUS AS DELIVERY_STATUS,
                        op.STATUS AS ORIGIN_STATUS,
                        f.ESTAT_COBRAMENT AS ORIGIN_INVOICE_PAYMENT_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 JOIN commercial_operation op ON op.UUID_OPERATION = g.ORIGIN_UUID_OPERATION
                 JOIN factura f ON f.UUID_FACTURA = g.UUID_FACTURA
                 LEFT JOIN novice_promotion_code_outbox o ON o.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [$uuidEntitlement]
            );

            if ($right === null) {
                throw SifException::conflict('Novice promotional grant does not exist.');
            }

            if ($right['DELIVERY_STATUS'] !== null) {
                if ($right['CODE_HASH'] === null) {
                    throw SifException::conflict('An existing promotion outbox has no redemption hash.');
                }

                $db->commit();
                return [
                    'uuid_entitlement' => $uuidEntitlement,
                    'entitlement_status' => (string) $right['STATUS'],
                    'delivery_status' => (string) $right['DELIVERY_STATUS'],
                    'idempotency_reused' => true,
                ];
            }

            if ($right['STATUS'] !== 'ISSUED'
                || $right['CODE_HASH'] !== null
                || $right['VALIDATION_STATUS'] !== 'VALIDATED'
                || !in_array((string) $right['ORIGIN_STATUS'], ['PAID', 'INVOICED', 'COMPLETED'], true)
                || $right['ORIGIN_INVOICE_PAYMENT_STATUS'] !== 'PAID'
                || $this->cents((string) $right['AVAILABLE_AMOUNT']) <= 0
                || trim((string) ($right['EXPIRES_AT'] ?? '')) === ''
                || (string) $right['EXPIRES_AT'] <= $timestamp
            ) {
                throw SifException::conflict('Novice promotion is not eligible for token activation.');
            }

            // 160 bits of cryptographic randomness; no names, DNI, invoices,
            // enrollment IDs or sequential identifiers appear in the token.
            $token = 'NOV-' . strtoupper(bin2hex(random_bytes(20)));
            $digest = hash('sha256', $token);
            $nonce = random_bytes(12);
            $tag = '';
            $ciphertext = openssl_encrypt(
                $token,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                'UC111|' . $uuidEntitlement,
                16
            );

            if ($ciphertext === false || strlen($tag) !== 16) {
                throw new \RuntimeException('Could not secure promotional delivery token.');
            }

            $stmt = $db->prepare(
                "UPDATE commercial_entitlement
                 SET CODE_HASH = ?, STATUS = 'ACTIVE'
                 WHERE UUID_ENTITLEMENT = ? AND STATUS = 'ISSUED' AND CODE_HASH IS NULL"
            );
            $stmt->execute([$digest, $uuidEntitlement]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Promotion changed during token preparation.');
            }

            $this->execute(
                $db,
                'INSERT INTO novice_promotion_code_outbox
                 (UUID_ENTITLEMENT, TOKEN_NONCE, TOKEN_TAG, TOKEN_CIPHERTEXT,
                  WRAP_KEY_VERSION, STATUS)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$uuidEntitlement, $nonce, $tag, $ciphertext, $keyVersion, 'PREPARED']
            );

            $this->execute(
                $db,
                'INSERT INTO commercial_entitlement_event
                 (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT, FROM_STATUS,
                  TO_STATUS, UUID_OPERATION, ACTOR_TYPE, ACTOR_ID, CORRELATION_ID,
                  CAUSATION_ID, REASON_CODE, CHANGESET_JSON, OCCURRED_AT)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)',
                [
                    $this->uuids->generate(),
                    $uuidEntitlement, 'ACTIVATE', 'SUCCESS',
                    'ISSUED', 'ACTIVE',
                    (string) $right['ORIGIN_UUID_OPERATION'],
                    'SYSTEM',
                    $uuidEntitlement,
                    $uuidEntitlement,
                    'NOVICE_TOKEN_PREPARED',
                    json_encode(['key_version' => $keyVersion], JSON_THROW_ON_ERROR),
                    $timestamp,
                ]
            );

            $db->commit();

            return [
                'uuid_entitlement' => $uuidEntitlement,
                'entitlement_status' => 'ACTIVE',
                'delivery_status' => 'PREPARED',
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        } finally {
            // Do not accidentally reuse this local key material as a token.
            unset($token, $ciphertext, $key);
        }
    }

    private function one(\PDO $db, string $sql, array $parameters): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($result) ? $result : null;
    }

    private function execute(\PDO $db, string $sql, array $parameters): void
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', trim($amount), $match)) {
            throw SifException::validation('Invalid promotional balance.');
        }

        return (int) $match[0] * 100 + (int) str_pad($match[1] ?? '', 2, '0');
    }
}
