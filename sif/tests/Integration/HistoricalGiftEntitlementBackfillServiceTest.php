<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Service\GiftEntitlementIssuerService;
use Prisma\Sif\Service\HistoricalGiftEntitlementBackfillService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class HistoricalGiftEntitlementBackfillServiceTest
{
    public function testInventoryDetectsReadyHistoricalGiftAndBackfillIsIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $this->createLegacyGiftTable($db);
        $this->insertLegacyGift($db, 77, 'HIST-GIFT-77', '120.00', 987, null);
        $evidence = $this->issueHistoricalGiftEvidence($db, 77, 987, '120.00');

        $service = $this->service();
        $before = $service->inventory($db, $db, 77);

        Assert::same(false, $before['ok']);
        Assert::same(1, $before['counts']['eligible']);
        Assert::same(1, $before['counts']['ready_backfill']);
        Assert::same(0, $before['counts']['blocked']);
        Assert::same('READY_BACKFILL', $before['items'][0]['status']);
        Assert::same($evidence['uuid_factura'], $before['items'][0]['uuid_factura']);
        Assert::same($evidence['uuid_payment'], $before['items'][0]['uuid_payment']);
        Assert::same(false, array_key_exists('gift_code', $before['items'][0]));

        $first = $service->backfill($db, $db, 77);
        $second = $service->backfill($db, $db, 77);
        $after = $service->inventory($db, $db, 77);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_entitlement'], $second['uuid_entitlement']);
        Assert::same('ENTITLED', $after['items'][0]['status']);
        Assert::same(true, $after['ok']);
        Assert::same(1, $after['counts']['entitled']);
        Assert::same(0, $after['counts']['ready_backfill']);
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_operation
             WHERE OPERATION_TYPE='GIFT_PURCHASE' AND SOURCE_TYPE='REGAL' AND SOURCE_ID='77'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement
             WHERE ENTITLEMENT_TYPE='GIFT'"
        )->fetchColumn());
    }

    public function testInventoryBlocksPaidUnusedGiftWithoutSifInvoice(): void
    {
        $db = TestDatabase::fresh();
        $this->createLegacyGiftTable($db);
        $this->insertLegacyGift($db, 78, 'HIST-GIFT-78', '90.00', 988, 0);

        $inventory = $this->service()->inventory($db, $db, 78);

        Assert::same(false, $inventory['ok']);
        Assert::same(1, $inventory['counts']['blocked']);
        Assert::same(
            'BLOCKED_NO_SIF_INVOICE',
            $inventory['items'][0]['status']
        );
    }

    public function testInventoryBlocksAmountMismatchInsteadOfInventingEvidence(): void
    {
        $db = TestDatabase::fresh();
        $this->createLegacyGiftTable($db);
        $this->insertLegacyGift($db, 79, 'HIST-GIFT-79', '120.00', 989, 0);
        $this->issueHistoricalGiftEvidence($db, 79, 989, '100.00');

        $inventory = $this->service()->inventory($db, $db, 79);

        Assert::same(false, $inventory['ok']);
        Assert::same(1, $inventory['counts']['blocked']);
        Assert::same(
            'BLOCKED_SIF_INVOICE_NOT_RECONCILED',
            $inventory['items'][0]['status']
        );
        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement"
        )->fetchColumn());
    }

    public function testUnpaidAndAlreadyUsedLegacyGiftsAreOutsideCoverageScope(): void
    {
        $db = TestDatabase::fresh();
        $this->createLegacyGiftTable($db);
        $this->insertLegacyGift($db, 80, 'HIST-GIFT-80', '120.00', 0, 0);
        $this->insertLegacyGift($db, 81, 'HIST-GIFT-81', '120.00', 990, 501);

        $inventory = $this->service()->inventory($db, $db);

        Assert::same(true, $inventory['ok']);
        Assert::same(0, $inventory['counts']['eligible']);
        Assert::same([], $inventory['items']);
    }

    private function service(): HistoricalGiftEntitlementBackfillService
    {
        $entitlements = new CommercialEntitlementRepository(new UuidGenerator());

        return new HistoricalGiftEntitlementBackfillService(
            $entitlements,
            new GiftEntitlementIssuerService(new UuidGenerator(), $entitlements)
        );
    }

    private function createLegacyGiftTable(\PDO $db): void
    {
        $db->exec(
            'CREATE TEMPORARY TABLE regal (
                ID INT PRIMARY KEY,
                CODI VARCHAR(200) NOT NULL,
                IMPORT DECIMAL(12,2) NOT NULL,
                FACT_REL INT NULL,
                USAT INT NULL,
                CCURS VARCHAR(80) NOT NULL,
                NOM_CURS VARCHAR(255) NULL
            )'
        );
    }

    private function insertLegacyGift(
        \PDO $db,
        int $id,
        string $code,
        string $amount,
        int $factRel,
        ?int $usedBy
    ): void {
        $statement = $db->prepare(
            'INSERT INTO regal
             (ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $id,
            $code,
            $amount,
            $factRel,
            $usedBy,
            'COURSE-' . $id,
            'Historical gift ' . $id,
        ]);
    }

    private function issueHistoricalGiftEvidence(
        \PDO $db,
        int $giftId,
        int $factRel,
        string $amount
    ): array {
        return IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'HIST|GIFT|' . $giftId,
                'source_channel' => 'INTRANET',
                'totals' => [
                    'import_base' => $amount,
                    'discount' => '0.00',
                    'taxable_base' => $amount,
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => $amount,
                ],
                'lines' => [[
                    'concept' => 'Historical gift ' . $giftId,
                    'detail' => 'Historical gift test evidence',
                    'quantity' => '1.00',
                    'unit_price' => $amount,
                    'base' => $amount,
                    'import_base' => $amount,
                    'discount_amount' => '0.00',
                    'taxable_base' => $amount,
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => $amount,
                    'source_type' => 'REGAL',
                    'source_id' => $giftId,
                ]],
                'relations' => [[
                    'source_type' => 'REGAL',
                    'source_id' => $giftId,
                    'factura_relacionada' => $factRel,
                    'ds_order' => 'HISTGIFT' . $giftId,
                    'visible_alumne' => 0,
                ]],
                'payment' => [
                    'idempotency_key' => 'HIST|GIFT|PAYMENT|' . $giftId,
                    'movement_type' => 'CHARGE',
                    'method' => 'TRANSFERENCIA',
                    'source_channel' => 'INTRANET',
                    'amount' => $amount,
                    'movement_date' => '2026-06-01 10:00:00',
                    'reference' => 'HIST-GIFT-' . $giftId,
                ],
            ])
        );
    }
}
