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

    public function testRedsysCallbackEndpointBuildsCallbackServiceButDoesNotTrustPayloadSignature(): void
    {
        $source = $this->readEndpoint('api/redsys/callback.php');

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('JsonResponse::fromInput()', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new RedsysCallbackService(', $source);
        Assert::stringContainsString('new RedsysNotificationRepository()', $source);
        Assert::stringContainsString('$signatureValid = false;', $source);
        Assert::stringContainsString('$service->receiveCallback($db, $payload, $signatureValid)', $source);
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
