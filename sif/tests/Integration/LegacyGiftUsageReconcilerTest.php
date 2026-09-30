<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\LegacyGiftUsageReconciler;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class LegacyGiftUsageReconcilerTest
{
    public function testMarksGiftUsedOnceAndReusesSameEnrollment(): void
    {
        $db = $this->fixture();
        $service = new LegacyGiftUsageReconciler();

        $first = $service->reconcile($db, 'GIFT-LEGACY-001', 501);
        $second = $service->reconcile($db, 'GIFT-LEGACY-001', 501);

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(501, $first['enrollment_id']);
        Assert::same(
            501,
            (int) $db->query(
                "SELECT USAT FROM regal WHERE CODI='GIFT-LEGACY-001'"
            )->fetchColumn()
        );
    }

    public function testRejectsAnotherEnrollmentAfterGiftWasReconciled(): void
    {
        $db = $this->fixture();
        $service = new LegacyGiftUsageReconciler();
        $service->reconcile($db, 'GIFT-LEGACY-001', 501);

        Assert::throws(SifException::class, static function () use (
            $db,
            $service
        ): void {
            $service->reconcile($db, 'GIFT-LEGACY-001', 999);
        }, 409);

        Assert::same(
            501,
            (int) $db->query(
                "SELECT USAT FROM regal WHERE CODI='GIFT-LEGACY-001'"
            )->fetchColumn()
        );
    }

    public function testPreexistingSameEnrollmentIsSafeReplay(): void
    {
        $db = $this->fixture();
        $db->exec('UPDATE regal SET USAT = 501');

        $result = (new LegacyGiftUsageReconciler())->reconcile(
            $db,
            'GIFT-LEGACY-001',
            501
        );

        Assert::same(true, $result['idempotency_reused']);
        Assert::same('RECONCILED', $result['status']);
    }

    private function fixture(): \PDO
    {
        $db = TestDatabase::fresh();
        $db->exec(
            'CREATE TEMPORARY TABLE regal (
                ID INT PRIMARY KEY,
                CODI VARCHAR(200) NOT NULL UNIQUE,
                USAT INT NULL
            )'
        );
        $db->exec(
            "INSERT INTO regal (ID, CODI, USAT)
             VALUES (77, 'GIFT-LEGACY-001', NULL)"
        );

        return $db;
    }
}
