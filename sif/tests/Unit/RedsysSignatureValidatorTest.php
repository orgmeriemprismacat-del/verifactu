<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\RedsysSignatureValidator;
use Prisma\Sif\Tests\Support\Assert;

final class RedsysSignatureValidatorTest
{
    public function testValidNotificationDecodesAndNormalizesSignedPayload(): void
    {
        $validator = new RedsysSignatureValidator('MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3');

        $payload = $validator->decodeAndVerify([
            'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
            'Ds_MerchantParameters' => 'eyJEc19PcmRlciI6Ik9SREVSMTIzIiwiRHNfQW1vdW50IjoiMTIwMDAiLCJEc19SZXNwb25zZSI6IjAwMDAiLCJEc19UcmFuc2FjdGlvblR5cGUiOiIwIiwiRHNfQ3VycmVuY3kiOiI5NzgiLCJEc19UZXJtaW5hbCI6IjEiLCJEc19EYXRlIjoiMDYvMDYvMjAyNiIsIkRzX0hvdXIiOiIxMDozMCJ9',
            'Ds_Signature' => 'KanI4nhDCZxf1ZsRKO06Wl4efSTMTfc8CVdyr-QlhLw=',
        ]);

        Assert::same('ORDER123', $payload['ds_order']);
        Assert::same('120.00', $payload['amount']);
        Assert::same('0000', $payload['response_code']);
        Assert::same('0', $payload['transaction_type']);
        Assert::same('978', $payload['currency_code']);
        Assert::same('EUR', $payload['currency']);
        Assert::same('1', $payload['terminal']);
        Assert::same('HMAC_SHA256_V1', $payload['signature_version']);
        Assert::same('8d4b744ee7c64f817594c7102b10d191ed99a26619a9f5da4501539984d079e1', $payload['payload_hash']);
        Assert::same(false, array_key_exists('idpag', $payload));
    }

    public function testValidSha512V2NotificationIsVerifiedAndNormalized(): void
    {
        $validator = new RedsysSignatureValidator(
            'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3',
            '999008881'
        );

        $payload = $validator->decodeAndVerify([
            'Ds_SignatureVersion' => 'HMAC_SHA512_V2',
            'Ds_MerchantParameters' => 'eyJEc19PcmRlciI6Ik9SREVSMTIzIiwiRHNfQW1vdW50IjoiMTIwMDAiLCJEc19SZXNwb25zZSI6IjAwMDAiLCJEc19UcmFuc2FjdGlvblR5cGUiOiIwIiwiRHNfTWVyY2hhbnRDb2RlIjoiOTk5MDA4ODgxIiwiRHNfQ3VycmVuY3kiOiI5NzgiLCJEc19UZXJtaW5hbCI6IjEiLCJEc19EYXRlIjoiMDYvMDYvMjAyNiIsIkRzX0hvdXIiOiIxMDozMCJ9',
            'Ds_Signature' => '9ZjKtSes8jN3xgG3uft2On93DzHrAFItWO6cvQvymSI6luDsCHqQKbHPXOxQ5_w97F5NejwiEYMxEFEdn4BMhg',
        ]);

        Assert::same('HMAC_SHA512_V2', $payload['signature_version']);
        Assert::same('999008881', $payload['merchant_code']);
        Assert::same('0', $payload['transaction_type']);
        Assert::same('120.00', $payload['amount']);
    }

    public function testExpectedMerchantCodeAcceptsMatchingSignedPayload(): void
    {
        $validator = new RedsysSignatureValidator(
            'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3',
            '999008881'
        );

        $payload = $validator->decodeAndVerify([
            'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
            'Ds_MerchantParameters' => 'eyJEc19PcmRlciI6Ik9SREVSMTIzIiwiRHNfQW1vdW50IjoiMTIwMDAiLCJEc19SZXNwb25zZSI6IjAwMDAiLCJEc19UcmFuc2FjdGlvblR5cGUiOiIwIiwiRHNfTWVyY2hhbnRDb2RlIjoiOTk5MDA4ODgxIiwiRHNfQ3VycmVuY3kiOiI5NzgiLCJEc19UZXJtaW5hbCI6IjEiLCJEc19EYXRlIjoiMDYvMDYvMjAyNiIsIkRzX0hvdXIiOiIxMDozMCJ9',
            'Ds_Signature' => 'K6NDUVPU8CgaxQm7degre7H2dtCZNQhso1otiU-yfdw=',
        ]);

        Assert::same('999008881', $payload['merchant_code']);
        Assert::same('0000', $payload['response_code']);
    }

    public function testExpectedMerchantCodeRejectsDifferentSignedMerchant(): void
    {
        $validator = new RedsysSignatureValidator(
            'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3',
            '111111111'
        );

        Assert::throws(SifException::class, function () use ($validator): void {
            $validator->decodeAndVerify([
                'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
                'Ds_MerchantParameters' => 'eyJEc19PcmRlciI6Ik9SREVSMTIzIiwiRHNfQW1vdW50IjoiMTIwMDAiLCJEc19SZXNwb25zZSI6IjAwMDAiLCJEc19UcmFuc2FjdGlvblR5cGUiOiIwIiwiRHNfTWVyY2hhbnRDb2RlIjoiOTk5MDA4ODgxIiwiRHNfQ3VycmVuY3kiOiI5NzgiLCJEc19UZXJtaW5hbCI6IjEiLCJEc19EYXRlIjoiMDYvMDYvMjAyNiIsIkRzX0hvdXIiOiIxMDozMCJ9',
                'Ds_Signature' => 'K6NDUVPU8CgaxQm7degre7H2dtCZNQhso1otiU-yfdw=',
            ]);
        }, 422);
    }

    public function testUnexpectedTransactionTypeIsRejected(): void
    {
        $validator = new RedsysSignatureValidator(
            'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3',
            '999008881'
        );

        Assert::throws(SifException::class, function () use ($validator): void {
            $validator->decodeAndVerify([
                'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
                'Ds_MerchantParameters' => 'eyJEc19PcmRlciI6Ik9SREVSMTIzIiwiRHNfQW1vdW50IjoiMTIwMDAiLCJEc19SZXNwb25zZSI6IjAwMDAiLCJEc19UcmFuc2FjdGlvblR5cGUiOiIxIiwiRHNfTWVyY2hhbnRDb2RlIjoiOTk5MDA4ODgxIiwiRHNfQ3VycmVuY3kiOiI5NzgiLCJEc19UZXJtaW5hbCI6IjEiLCJEc19EYXRlIjoiMDYvMDYvMjAyNiIsIkRzX0hvdXIiOiIxMDozMCJ9',
                'Ds_Signature' => 'gLpZvY9ZU_nfPWN5KLrd0r1AbF3rb7fxjOnIr9IVDr4=',
            ]);
        }, 422);
    }

    public function testMissingMerchantKeyRejectsNotificationBeforeTrustingPayload(): void
    {
        $validator = new RedsysSignatureValidator('');
        $merchantParameters = base64_encode(json_encode([
            'Ds_Order' => 'ORDER123',
            'Ds_Amount' => '12000',
            'Ds_Response' => '0000',
        ]));

        Assert::throws(SifException::class, function () use ($validator, $merchantParameters): void {
            $validator->decodeAndVerify([
                'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
                'Ds_MerchantParameters' => $merchantParameters,
                'Ds_Signature' => 'client-supplied-signature',
            ]);
        }, 422);
    }

    public function testAmountNormalizationUsesIntegerCents(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Service/RedsysSignatureValidator.php');
        if ($source === false) {
            Assert::fail('Could not read RedsysSignatureValidator source');
        }

        Assert::stringContainsString('amountFromCents', $source);
        Assert::stringContainsString('ctype_digit($raw)', $source);

        $normalize = strstr($source, 'private function normalizeAmount');
        if ($normalize === false) {
            Assert::fail('normalizeAmount not found');
        }
        $normalize = strstr($normalize, 'private function amountFromCents', true) ?: $normalize;
        if (str_contains($normalize, '(float)')) {
            Assert::fail('Redsys amount normalization must not use floating point arithmetic.');
        }
    }

    public function testSourceDoesNotHardcodeRedsysSecret(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Service/RedsysSignatureValidator.php');

        if ($source === false) {
            Assert::fail('Could not read RedsysSignatureValidator source');
        }

        Assert::stringContainsString('hash_equals(', $source);
        Assert::stringContainsString('openssl_encrypt(', $source);
        Assert::stringContainsString('SifException::validation', $source);

        if (preg_match('/[\'"][A-Za-z0-9+\/]{24,}[\'"]/', $source) === 1) {
            Assert::fail('RedsysSignatureValidator must not hardcode merchant secrets');
        }
    }
}
