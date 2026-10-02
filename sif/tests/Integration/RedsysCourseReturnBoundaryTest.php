<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysCourseReturnBoundaryTest
{
    public function testCheckoutUsesExistingAutomaticReturnPagesAndConfigurableSifCallback(): void
    {
        $source = $this->read('codi-drive/pay-prisma-cat-canvis-verifactu/pagina_efectuar_pagament_automatic.php');

        Assert::stringContainsString('SIF_REDSYS_CALLBACK_URL', $source);
        Assert::stringContainsString("str_starts_with(\$sifMerchantUrl, 'https://')", $source);
        Assert::stringContainsString('respostaOkPagamentAutomatic.php?', $source);
        Assert::stringContainsString('respostaKoPagamentAutomatic.php?', $source);
        Assert::stringContainsString("'order' => \$order", $source);
        Assert::stringContainsString("'idPag' => (int) \$idPag", $source);
        if (str_contains($source, "'email' => \$email")) {
            Assert::fail('Browser return URL must not expose participant email.');
        }
    }

    public function testReturnPagesNeverUpgradeBrowserReturnToConfirmedWithoutSif(): void
    {
        $helper = $this->read('codi-drive/pay-prisma-cat-canvis-verifactu/CoursePaymentReturnStatus.php');
        $ok = $this->read('codi-drive/pay-prisma-cat-canvis-verifactu/respostaOkPagamentAutomatic.php');
        $ko = $this->read('codi-drive/pay-prisma-cat-canvis-verifactu/respostaKoPagamentAutomatic.php');

        Assert::stringContainsString('SIF_REDSYS_COURSE_CUTOVER_ENABLED', $helper);
        Assert::stringContainsString('SIF_REDSYS_CALLBACK_URL', $helper);
        Assert::stringContainsString('$statusEnabled = $courseCutoverEnabled', $helper);
        Assert::stringContainsString("'UNVERIFIED'", $helper);
        if (str_contains($helper, "\$_GET['email']")) {
            Assert::fail('Authoritative return helper must not consume email from query string.');
        }
        Assert::stringContainsString("'status' => \$authoritative ? \$status : 'UNVERIFIED'", $helper);
        Assert::stringContainsString("if (\$status === 'CONFIRMED')", $helper);
        Assert::stringContainsString("uc014RenderPaymentReturn('OK')", $ok);
        Assert::stringContainsString("uc014RenderPaymentReturn('KO')", $ko);
        foreach ([$ok, $ko] as $returnPage) {
            Assert::stringContainsString("Cache-Control: private, no-store, max-age=0", $returnPage);
            Assert::stringContainsString("Referrer-Policy: no-referrer", $returnPage);
            Assert::stringContainsString("X-Content-Type-Options: nosniff", $returnPage);
        }

        if (str_contains($ok, "El pagament s'ha registrat correctament")) {
            Assert::fail('OK return must not claim success from browser redirect alone');
        }

        foreach ([
            'InvoiceService',
            'PaymentRepository',
            'issueInvoice',
            'INSERT INTO',
            'UPDATE ',
            'DELETE FROM',
        ] as $forbidden) {
            if (str_contains($helper, $forbidden)) {
                Assert::fail('Return helper must remain read-only: ' . $forbidden);
            }
        }
    }

    public function testStatusClientUsesAuthenticatedInternalApi(): void
    {
        $client = $this->read('codi-drive/pay-prisma-cat-canvis-verifactu/SifRedsysCourseStatusClient.php');
        $endpoint = $this->read('sif/public/api/redsys/course-status.php');
        $preflight = $this->read('sif/scripts/preflight-redsys-course.php');

        Assert::stringContainsString('/api/redsys/course-status.php', $client);
        Assert::stringContainsString('X-SIF-Signature:', $client);
        Assert::stringContainsString("REQUEST_METHOD", $endpoint);
        Assert::stringContainsString("PAYMENT_CHANNEL role is required", $endpoint);
        Assert::stringContainsString('RedsysCoursePaymentStatusService', $endpoint);
        Assert::stringContainsString("'course_status_endpoint_present'", $preflight);
    }

    private function read(string $relativePath): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relativePath;
        $content = file_get_contents($path);
        if ($content === false) {
            Assert::fail('Could not read ' . $relativePath);
        }

        return $content;
    }
}
