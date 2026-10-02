<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class InvoiceIssueHttpEndpointTest
{
    public function testGenericIssueEndpointRequiresSignedInternalActorAndWriteScope(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/public/api/factures/issue.php');
        if ($source === false) {
            Assert::fail('Could not read invoice issue HTTP endpoint');
        }

        Assert::stringContainsString('InternalApiAuthenticator', $source);
        Assert::stringContainsString('InternalApiRequestRepository', $source);
        Assert::stringContainsString('InternalInvoiceIssueScopeResolver', $source);
        Assert::stringContainsString('InternalInvoiceIssuePayloadPolicy', $source);
        Assert::stringContainsString('invoice_issue_signed_path', $source);
        Assert::stringContainsString("['PROD', 'PRODUCTION', 'PREPROD', 'PREPRODUCTION']", $source);
        Assert::stringContainsString('REQUEST_METHOD', $source);

        if (str_contains($source, 'JsonResponse::fromInput()')) {
            Assert::fail('Signed invoice issue endpoint must authenticate the exact raw request body.');
        }

        Assert::stringContainsString("['ok' => false, 'error' => 'Internal server error']", $source);
    }
}
