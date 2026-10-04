<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class ManualInstallmentPaymentPreproductionScriptTest
{
    public function testScriptBuildsInstallmentProcessorWithoutIssuingInvoices(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/scripts/process-manual-installment.php');

        if ($source === false) {
            Assert::fail('Could not read manual installment processor script');
        }

        Assert::stringContainsString('/src/autoload.php', $source);
        Assert::stringContainsString('PHP_SAPI !== \'cli\'', $source);
        Assert::stringContainsString('SIF_ENV=production', $source);
        Assert::stringContainsString('ConnectionFactory::make($config)', $source);
        Assert::stringContainsString('new PaymentService(', $source);
        Assert::stringContainsString('new InternalInstallmentPaymentGateway(', $source);
        Assert::stringContainsString('new InstallmentPaymentAuditTrail(', $source);
        Assert::stringContainsString('new PaymentActionEventRepository(', $source);
        Assert::stringContainsString('new OperationalEventRepository(', $source);
        Assert::stringContainsString('new SifAuditEventRepository(', $source);
        Assert::stringContainsString('new ManualInstallmentPaymentService(', $source);
        Assert::stringContainsString('new ManualPaymentInvoiceRepository()', $source);
        Assert::stringContainsString('new ManualInstallmentPaymentPayloadBuilder()', $source);
        Assert::stringContainsString('--uuid-factura=', $source);
        Assert::stringContainsString('--num-visible=', $source);
        Assert::stringContainsString('--id-insc=', $source);
        Assert::stringContainsString('--user=', $source);
        Assert::stringContainsString("'audit_source_channel' => 'CLI'", $source);
        Assert::stringContainsString("'roles' => ['ADMIN_TOOL']", $source);
        Assert::stringContainsString("'request_id' => \$requestId", $source);
        Assert::stringContainsString('$gateway->register(', $source);
        Assert::stringContainsString('JSON_PRETTY_PRINT', $source);

        if (str_contains($source, 'new InvoiceService(') || str_contains($source, 'issueInvoice(')) {
            Assert::fail('Manual installment processor must not issue invoices.');
        }

        if (str_contains($source, 'LegacySyncService')) {
            Assert::fail('Manual installment processor must not sync legacy in this cut.');
        }
    }
}
