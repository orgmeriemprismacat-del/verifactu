<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class InvoiceBeforePaymentHttpEndpointTest
{
    public function testEndpointUsesSignedInternalActorAndAuthoritativeCommandService(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/public/api/factures/before-payment.php'
        );

        if ($source === false) {
            Assert::fail('Could not read invoice-before-payment HTTP endpoint');
        }

        Assert::stringContainsString('InternalApiAuthenticator', $source);
        Assert::stringContainsString('InternalApiRequestRepository', $source);
        Assert::stringContainsString('InternalInvoiceBeforePaymentScopeResolver', $source);
        Assert::stringContainsString('invoice_before_payment_signed_path', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacy($config)', $source);
        Assert::stringContainsString('ConnectionFactory::makeLegacyIntranet($config)', $source);
        Assert::stringContainsString('InvoiceBeforePaymentCommandService', $source);
        Assert::stringContainsString('$commands->preview(', $source);
        Assert::stringContainsString('$commands->confirm(', $source);
        Assert::stringContainsString("(string) \$actor['actor_id']", $source);
        Assert::stringContainsString('$coverage = new InvoiceBeforePaymentCoverageRepository();', $source);
        Assert::stringContainsString('new OperationalEventRepository(new UuidGenerator())', $source);
        Assert::stringContainsString('$sifDb,\n        $coverage\n    );', $source);

        $authStart = strpos($source, '))->authenticate(');
        $authEnd = $authStart === false ? false : strpos($source, ');', $authStart);
        if ($authStart === false || $authEnd === false) {
            Assert::fail('Could not isolate InternalApiAuthenticator::authenticate() call.');
        }
        $authBlock = substr($source, $authStart, $authEnd - $authStart + 2);
        if (str_contains($authBlock, '$coverage')) {
            Assert::fail('Coverage repository must not be passed to InternalApiAuthenticator.');
        }
        Assert::stringContainsString('InvoiceBeforePaymentDocumentQueueService', $source);
        Assert::stringContainsString('new DocumentJobRepository()', $source);
        Assert::stringContainsString("documentsConfig['generator_version']", $source);

        if (str_contains($source, "payload['created_by']")) {
            Assert::fail('HTTP endpoint must not trust created_by from the request payload.');
        }

        if (str_contains($source, 'JsonResponse::fromInput()')) {
            Assert::fail('Signed endpoint must authenticate the exact raw request body.');
        }

        if (
            str_contains($source, 'use Prisma\\Sif\\Service\\PaymentService;')
            || str_contains($source, 'new PaymentService(')
            || str_contains($source, 'registerPayment(')
        ) {
            Assert::fail('UC-004 HTTP endpoint must not register an initial payment.');
        }
    }
}
