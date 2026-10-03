<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class Uc002PaymentApiBoundaryTest
{
    public function testPaymentRegistrationApiIsPostOnlyAndUsesInternalAuthentication(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/public/api/payments/register.php');

        if (!is_string($source)) {
            Assert::fail('Could not load UC-002 payment registration API');
        }

        Assert::stringContainsString("REQUEST_METHOD", $source);
        Assert::stringContainsString("!== 'POST'", $source);
        Assert::stringContainsString('InternalApiAuthenticator', $source);
        Assert::stringContainsString('InternalApiRequestRepository', $source);
        Assert::stringContainsString("'payment_signed_path'", $source);
        Assert::stringContainsString("'write_roles'", $source);
        Assert::stringContainsString('array_intersect($roles, $allowedRoles)', $source);
        Assert::stringContainsString("Payment registration role is not authorized", $source);
        Assert::stringContainsString("Cache-Control: private, no-store", $source);
    }

    public function testPaymentRegistrationApiUsesSignedActorInsteadOfPayloadActor(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root . '/public/api/payments/register.php');

        if (!is_string($source)) {
            Assert::fail('Could not load UC-002 payment registration API');
        }

        Assert::stringContainsString("$result['actor_id'] = (string) ($actor['actor_id'] ?? '')", $source);
        Assert::stringContainsString("$result['request_id'] = (string) ($actor['request_id'] ?? '')", $source);
        Assert::same(false, str_contains($source, "$payload['actor_id']"));
    }

    public function testPaymentRegistrationConfigurationIsFailClosedByDefault(): void
    {
        $root = dirname(__DIR__, 2);
        $config = file_get_contents($root . '/config/sif.php');

        if (!is_string($config)) {
            Assert::fail('Could not load SIF payment configuration');
        }

        Assert::stringContainsString('SIF_INTERNAL_PAYMENT_SIGNED_PATH', $config);
        Assert::stringContainsString('SIF_PAYMENT_WRITE_ROLES', $config);
        Assert::stringContainsString("getenv('SIF_PAYMENT_WRITE_ROLES') ?: ''", $config);
    }
}
