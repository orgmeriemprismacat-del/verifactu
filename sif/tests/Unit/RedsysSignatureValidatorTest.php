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
            'Ds_MerchantParameters' => 'eyJEc19PcmRlciI6Ik9SREVSMTIzIiwiRHNfQW1vdW50IjoiMTIwMDAiLCJEc19SZXNwb25zZSI6IjAwMDAiLCJEc19DdXJyZW5jeSI6Ijk3OCIsIkRzX1Rlcm1pbmFsIjoiMSIsIkRzX0RhdGUiOiIwNi8wNi8yMDI2IiwiRHNfSG91ciI6IjEwOjMwIn0=',
            'Ds_Signature' => 'Sf9vai8reepW5G-M5aE8DEs6Z6UAfeRIhYh8oXS6110=',
        ]);

        Assert::same('ORDER123', $payload['ds_order']);
        Assert::same('120.00', $payload['amount']);
        Assert::same('0000', $payload['response_code']);
        Assert::same('978', $payload['currency_code']);
        Assert::same('EUR', $payload['currency']);
        Assert::same('1', $payload['terminal']);
        Assert::same('HMAC_SHA256_V1', $payload['signature_version']);
        Assert::same('8d4b744ee7c64f817594c7102b10d191ed99a26619a9f5da4501539984d079e1', $payload['payload_hash']);
        Assert::same(false, array_key_exists('idpag', $payload));
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
                'Ds_MerchantParameters' => $merchantParameters,
                'Ds_Signature' => 'client-supplied-signature',
            ]);
        }, 422);
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
