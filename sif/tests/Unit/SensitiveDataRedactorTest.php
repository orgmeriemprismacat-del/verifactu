<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\SensitiveDataRedactor;
use Prisma\Sif\Tests\Support\Assert;

final class SensitiveDataRedactorTest
{
    public function testRedactsKeyedSecretsAndLuhnValidPan(): void
    {
        $redactor = new SensitiveDataRedactor();
        $input = 'payment failed pan=4111111111111111 cvv=123 Ds_Signature=abcdef secret=topsecret '
            . 'card seen as 4111 1111 1111 1111 order=123456';

        $result = $redactor->redact($input);

        Assert::stringContainsString('pan=[REDACTED]', $result);
        Assert::stringContainsString('cvv=[REDACTED]', $result);
        Assert::stringContainsString('Ds_Signature=[REDACTED]', $result);
        Assert::stringContainsString('secret=[REDACTED]', $result);
        Assert::stringContainsString('[REDACTED_PAN]', $result);
        Assert::stringContainsString('order=123456', $result);

        foreach (['4111111111111111', '4111 1111 1111 1111', 'topsecret', 'abcdef'] as $secret) {
            if (str_contains($result, $secret)) {
                Assert::fail('Sensitive value was not redacted: ' . $secret);
            }
        }
    }

    public function testDoesNotRedactArbitraryNonLuhnIdentifiers(): void
    {
        $redactor = new SensitiveDataRedactor();
        $input = 'resource=1234567890123456 queue=1234567890123';

        Assert::same($input, $redactor->redact($input));
    }

    public function testRedactsQuotedJsonLikeSensitiveFields(): void
    {
        $redactor = new SensitiveDataRedactor();
        $result = $redactor->redact('{"card_number":"4111111111111111","password":"demo-pass"}');

        Assert::stringContainsString('"card_number":[REDACTED]', $result);
        Assert::stringContainsString('"password":[REDACTED]', $result);
        if (str_contains($result, '4111111111111111') || str_contains($result, 'demo-pass')) {
            Assert::fail('Quoted sensitive value was not redacted.');
        }
    }
}
