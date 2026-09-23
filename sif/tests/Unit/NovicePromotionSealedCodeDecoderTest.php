<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\NovicePromotionSealedCodeDecoder;
use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionSealedCodeDecoderTest
{
    public function testDecodesOnlyTheOriginalCodeForTheBoundEntitlement(): void
    {
        [$sealed, $hexKey, $code] = $this->fixture();

        Assert::same($code, (new NovicePromotionSealedCodeDecoder())->decode($sealed, $hexKey));
        Assert::same($code, (new NovicePromotionSealedCodeDecoder())->decode($sealed, $hexKey));
    }

    public function testRejectsWrongKey(): void
    {
        [$sealed] = $this->fixture();

        Assert::throws(\RuntimeException::class, static function () use ($sealed): void {
            (new NovicePromotionSealedCodeDecoder())->decode($sealed, str_repeat('b', 64));
        });
    }

    public function testRejectsWrongEntitlementEvenWithCorrectKey(): void
    {
        [$sealed, $hexKey] = $this->fixture();
        $sealed['uuid_entitlement'] = 'other-entitlement';

        Assert::throws(\RuntimeException::class, static function () use ($sealed, $hexKey): void {
            (new NovicePromotionSealedCodeDecoder())->decode($sealed, $hexKey);
        });
    }

    public function testRejectsChangedCodeHash(): void
    {
        [$sealed, $hexKey] = $this->fixture();
        $sealed['code_hash'] = str_repeat('0', 64);

        Assert::throws(\RuntimeException::class, static function () use ($sealed, $hexKey): void {
            (new NovicePromotionSealedCodeDecoder())->decode($sealed, $hexKey);
        });
    }

    public function testRejectsMalformedCiphertextOrKeyBeforeDecrypting(): void
    {
        [$sealed, $hexKey] = $this->fixture();
        $sealed['tag'] = 'too-short';

        Assert::throws(\RuntimeException::class, static function () use ($sealed, $hexKey): void {
            (new NovicePromotionSealedCodeDecoder())->decode($sealed, $hexKey);
        });
        Assert::throws(\RuntimeException::class, static function () use ($sealed): void {
            (new NovicePromotionSealedCodeDecoder())->decode($sealed, 'not-a-key');
        });
    }

    private function fixture(): array
    {
        // Synthetic data exclusively for a pure unit test: NO DB or SMTP.
        $entitlement = 'entitlement-unit-test';
        $code = 'NOV-' . strtoupper(bin2hex(random_bytes(20)));
        $hexKey = str_repeat('a', 64);
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $code,
            'aes-256-gcm',
            hex2bin($hexKey),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'UC111|' . $entitlement,
            16
        );
        if (!is_string($ciphertext)) {
            throw new \RuntimeException('Cannot initialize encryption fixture.');
        }
        return [[
            'uuid_entitlement' => $entitlement,
            'code_hash' => hash('sha256', $code),
            'nonce' => $nonce,
            'tag' => $tag,
            'ciphertext' => $ciphertext,
        ], $hexKey, $code];
    }
}
