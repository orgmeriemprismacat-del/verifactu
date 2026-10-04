<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class RedsysGiftRecoveryBoundaryTest
{
    public function testGiftPipelinePersistsMoneyBeforeRecoverableDerivedArtifacts(): void
    {
        $service = $this->read('sif/src/Service/RedsysGiftInvoiceService.php');

        $invoice = strpos($service, '$this->invoices->issueInvoice($payload)');
        $entitlement = strpos($service, '$this->giftEntitlements->issue(');
        $notification = strpos($service, '$this->giftNotifications->enqueue(');

        Assert::same(true, $invoice !== false);
        Assert::same(true, $entitlement !== false);
        Assert::same(true, $notification !== false);
        Assert::same(true, $invoice < $entitlement);
        Assert::same(true, $entitlement < $notification);
    }

    public function testWorkerRetriesTechnicalFailureBeforeMarkingJobProcessed(): void
    {
        $worker = $this->read('sif/src/Service/RedsysCallbackWorker.php');

        $process = strpos($worker, '$this->processor->process($db, $job)');
        $processed = strpos($worker, '$this->queue->markProcessed(');
        $retry = strpos($worker, '$this->queue->markRetry(');

        Assert::same(true, $process !== false && $processed !== false && $retry !== false);
        Assert::same(true, $process < $processed);
        Assert::stringContainsString('catch (\\Throwable $exception)', $worker);
        Assert::stringContainsString("return ['ok' => false, 'status' => 'RETRY'];", $worker);
    }

    public function testGiftInvoiceServiceHasDuplicateReplayEvidence(): void
    {
        $test = $this->read('sif/tests/Integration/RedsysGiftInvoiceServiceTest.php');

        Assert::stringContainsString(
            'issueByGiftIdFromValidatedNotification($sifDb, $legacyDb, \'ORDERGIFT77\', 77)',
            $test
        );
        Assert::stringContainsString(
            "Assert::same(true, \$second['idempotency_reused']);",
            $test
        );
        Assert::stringContainsString(
            "\$first['gift_entitlement']['uuid_entitlement']",
            $test
        );
        Assert::stringContainsString(
            "\$first['uuid_payment'], \$second['uuid_payment']",
            $test
        );
    }

    public function testGiftNotificationOutboxIsIdempotentAndDoesNotPersistRawCode(): void
    {
        $test = $this->read('sif/tests/Integration/GiftPaymentNotificationServiceTest.php');

        Assert::stringContainsString("Assert::same(true, \$second['idempotency_reused']);", $test);
        Assert::stringContainsString('SECRET-GIFT-CODE', $test);
        Assert::stringContainsString(
            "str_contains((string) \$row['PAYLOAD_JSON'], 'SECRET-GIFT-CODE')",
            $test
        );
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
