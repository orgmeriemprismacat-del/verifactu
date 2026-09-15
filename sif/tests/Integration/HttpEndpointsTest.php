<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class HttpEndpointsTest
{
    public function testIssueEndpointBuildsInvoiceServiceFromJsonPayload(): void
    {
        $source = $this->readEndpoint('api/factures/issue.php');

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('JsonResponse::fromInput()', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new InvoiceService(', $source);
        Assert::stringContainsString('new InvoicePayloadValidator()', $source);
        Assert::stringContainsString('new FiscalSequenceRepository()', $source);
        Assert::stringContainsString('new InvoiceRepository(', $source);
        Assert::stringContainsString('new PaymentPayloadValidator()', $source);
        Assert::stringContainsString('new PaymentRepository(', $source);
        Assert::stringContainsString('new PaymentStatusCalculator()', $source);
        Assert::stringContainsString('$service->issueInvoice($payload)', $source);
    }

    public function testRegisterPaymentEndpointBuildsPaymentServiceFromJsonPayload(): void
    {
        $source = $this->readEndpoint('api/payments/register.php');

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('JsonResponse::fromInput()', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new PaymentService(', $source);
        Assert::stringContainsString('new PaymentPayloadValidator()', $source);
        Assert::stringContainsString('new PaymentRepository(', $source);
        Assert::stringContainsString('new PaymentStatusCalculator()', $source);
        Assert::stringContainsString('$service->registerPayment($payload)', $source);
    }

    public function testRedsysCallbackEndpointBuildsCallbackServiceWithRealSignatureValidator(): void
    {
        $source = $this->readEndpoint('api/redsys/callback.php');

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('RedsysSignatureValidator', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new RedsysCallbackService(', $source);
        Assert::stringContainsString('new RedsysPaymentIntentRepository()', $source);
        Assert::stringContainsString('new RedsysNotificationRepository()', $source);
        Assert::stringContainsString('new RedsysCallbackQueueRepository(new UuidGenerator())', $source);
        Assert::stringContainsString('$validator->decodeAndVerify($_POST)', $source);
        Assert::stringContainsString('$service->receiveCallback($db, $payload, true)', $source);

        if (str_contains($source, '$_GET')) {
            Assert::fail('Redsys callback endpoint must not use query-string fiscal context');
        }

        if (str_contains($source, '$signatureValid = false;')) {
            Assert::fail('Redsys callback endpoint must use the real signature validator once wired');
        }

        if (str_contains($source, 'JsonResponse::fromInput()')) {
            Assert::fail('Redsys callback endpoint must read the signed Redsys POST, not JSON input');
        }
    }

    public function testJsonResponseSupportsInvalidJsonAndThrowableResponses(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Http/JsonResponse.php');

        Assert::stringContainsString('Content-Type: application/json; charset=utf-8', $source);
        Assert::stringContainsString('Invalid JSON', $source);
        Assert::stringContainsString('fromThrowable', $source);
        Assert::stringContainsString('JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES', $source);
    }

    private function readEndpoint(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/public/' . $path);

        if ($source === false) {
            Assert::fail("Could not read endpoint {$path}");
        }

        return $source;
    }
}
