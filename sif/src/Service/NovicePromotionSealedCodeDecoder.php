<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

/**
 * Private mail-worker cryptographic boundary, with NO database access.
 * Accepts the sealed code for an already validated delivery claim and
 * refuses wrong/missing keys, wrong entitlement AAD and hash mismatches.
 * Never expose its return value to a public endpoint or log it.
 */
final class NovicePromotionSealedCodeDecoder
{
    public function decode(array $sealed, string $wrappingKeyHex): string
    {
        $right = (string) ($sealed['uuid_entitlement'] ?? '');
        $digest = (string) ($sealed['code_hash'] ?? '');
        $nonce = $sealed['nonce'] ?? null;
        $tag = $sealed['tag'] ?? null;
        $ciphertext = $sealed['ciphertext'] ?? null;
        if ($right === '' || !preg_match('/^[a-f0-9]{64}$/D', $digest)
            || !is_string($nonce) || strlen($nonce) !== 12
            || !is_string($tag) || strlen($tag) !== 16
            || !is_string($ciphertext) || $ciphertext === ''
            || !preg_match('/^[0-9a-fA-F]{64}$/D', $wrappingKeyHex)
        ) {
            throw new \RuntimeException('Invalid sealed promotional delivery material.');
        }

        $key = hex2bin($wrappingKeyHex);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('Invalid promotion wrapping key.');
        }

        $code = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'UC111|' . $right
        );
        unset($key);
        if (!is_string($code)
            || !preg_match('/^NOV-[A-F0-9]{40}$/D', $code)
            || !hash_equals($digest, hash('sha256', $code))
        ) {
            throw new \RuntimeException('Promotional token could not be verified.');
        }
        return $code;
    }
}
