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
    public function testInventoryMarksExactUnusedPaidGiftReadyWithoutExposingRawCode(): void
    {
        [$db, $code] = $this->fixture(false, true);

        $result = $this->service()->inventory($db, $db);

        Assert::same(1, $result['summary']['total']);
        Assert::same(1, $result['summary']['ready_to_backfill']);
        Assert::same(1, $result['summary']['blocking_unused']);
        Assert::same('READY_TO_BACKFILL', $result['items'][0]['status']);
        Assert::same(hash('sha256', $code), $result['items'][0]['code_hash']);
        Assert::same(
            false,
            str_contains(
                json_encode($result, JSON_UNESCAPED_SLASHES),
                $code
            )
        );
    }

    public function testBackfillCreatesOneGiftEntitlementAndReplayReusesIt(): void
    {
        [$db] = $this->fixture(false, true);
        $service = $this->service();

        $first = $service->backfillOne($db, $db, 77);
        $second = $service->backfillOne($db, $db, 77);

        Assert::same('BACKFILLED', $first['status']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same('ENTITLEMENT_PRESENT', $second['status']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_operation
             WHERE OPERATION_TYPE='GIFT_PURCHASE' AND SOURCE_ID='77'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement
             WHERE ENTITLEMENT_TYPE='GIFT'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event
             WHERE ACTION='ISSUE'"
        )->fetchColumn());
    }

    public function testUnusedGiftWithoutSifInvoiceBlocksDeploymentInventory(): void
    {
        [$db] = $this->fixture(false, false);

        $result = $this->service()->inventory($db, $db);

        Assert::same('MISSING_SIF_INVOICE', $result['items'][0]['status']);
        Assert::same(1, $result['summary']['needs_review']);
        Assert::same(1, $result['summary']['blocking_unused']);
    }

    public function testUnusedGiftWithExpiredEntitlementStillBlocksCoverage(): void
    {
        [$db, $code] = $this->fixture(false, false);
        $hash = hash('sha256', $code);
        $db->prepare(
            'INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
              ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON, FACE_VALUE,
              CURRENCY, STATUS, ISSUED_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            '22222222-2222-4222-8222-222222222222',
            'GIFT',
            $hash,
            CommercialEntitlementRepository::unclaimedGiftHolderKey($hash),
            'GIFT_V1',
            '{}',
            '120.00',
            'EUR',
            'EXPIRED',
            '2026-01-01 10:00:00',
            'GIFT|ENTITLEMENT|TEST:77',
        ]);

        $result = $this->service()->inventory($db, $db);

        Assert::same(
            'CONFLICT_UNUSED_LEGACY_UNUSABLE_SIF',
            $result['items'][0]['status']
        );
        Assert::same(1, $result['summary']['blocking_unused']);
    }

    public function testUnpaidLegacyGiftDoesNotCreateRightOrBlockCoverage(): void
    {
        [$db] = $this->fixture(false, false);
        $db->exec('UPDATE regal SET FACT_REL = NULL WHERE ID = 77');

        $result = $this->service()->inventory($db, $db);

        Assert::same('UNPAID_LEGACY_GIFT_NO_RIGHT', $result['items'][0]['status']);
        Assert::same(1, $result['summary']['unpaid_no_right']);
        Assert::same(0, $result['summary']['blocking_unused']);
        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement"
        )->fetchColumn());
    }

    public function testSifPaidEvidenceWithoutLegacyFactRelNeedsReview(): void
    {
        [$db] = $this->fixture(false, true);
        $db->exec('UPDATE regal SET FACT_REL = NULL WHERE ID = 77');

        $result = $this->service()->inventory($db, $db);

        Assert::same(
            'LEGACY_PAYMENT_MARKER_MISSING_REVIEW',
            $result['items'][0]['status']
        );
        Assert::same(1, $result['summary']['blocking_unused']);
    }

    public function testAlreadyUsedHistoricalGiftIsCataloguedButNotBackfilled(): void
    {
        [$db] = $this->fixture(true, false);

        $result = $this->service()->inventory($db, $db);

        Assert::same('HISTORICAL_USED_NO_BACKFILL', $result['items'][0]['status']);
        Assert::same(1, $result['summary']['historical_used_no_backfill']);
        Assert::same(0, $result['summary']['blocking_unused']);
    }

    private function service(): HistoricalGiftEntitlementBackfillService
    {
        $entitlements = new CommercialEntitlementRepository(new UuidGenerator());

        return new HistoricalGiftEntitlementBackfillService(
            $entitlements,
            new GiftEntitlementIssuerService(new UuidGenerator(), $entitlements)
        );
    }

    private function fixture(bool $used, bool $withSifEvidence): array
    {
        $db = TestDatabase::fresh();
        $code = 'HISTORICAL-GIFT-SECRET-77';

        $db->exec(
            'CREATE TEMPORARY TABLE regal (
                ID INT PRIMARY KEY,
                CODI VARCHAR(200) NOT NULL,
                IMPORT DECIMAL(12,2) NOT NULL,
                FACT_REL INT NULL,
                USAT INT NULL,
                CCURS VARCHAR(80) NULL,
                NOM_CURS VARCHAR(255) NULL
            )'
        );
        $statement = $db->prepare(
            'INSERT INTO regal
             (ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            77,
            $code,
            '120.00',
            987,
            $used ? 501 : null,
            'COURSE-TEST',
            'Curs històric de prova',
        ]);

        if ($withSifEvidence) {
            $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
                Fixtures::invoicePayload([
                    'idempotency_key' => 'HISTORIC|GIFT|INVOICE|77',
                    'totals' => [
                        'import_base' => '120.00',
                        'taxable_base' => '120.00',
                        'total' => '120.00',
                    ],
                    'lines' => [[
                        'concept' => 'Regal històric',
                        'detail' => 'Backfill test',
                        'quantity' => '1.00',
                        'unit_price' => '120.00',
                        'base' => '120.00',
                        'import_base' => '120.00',
                        'discount_amount' => '0.00',
                        'taxable_base' => '120.00',
                        'iva_regim' => 'EXEMPT',
                        'iva_pct' => '0.00',
                        'iva_import' => '0.00',
                        'total' => '120.00',
                        'source_type' => 'REGAL',
                        'source_id' => 77,
                    ]],
                    'relations' => [[
                        'source_type' => 'REGAL',
                        'source_id' => 77,
                        'factura_relacionada' => 987,
                        'visible_alumne' => 0,
                    ]],
                ])
            );

            RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
                'idempotency_key' => 'HISTORIC|GIFT|PAYMENT|77',
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'WEB',
                'amount' => '120.00',
                'movement_date' => '2026-08-01 10:00:00',
                'reference' => 'HIST-GIFT-77',
                'allocations' => [[
                    'uuid_factura' => $invoice['uuid_factura'],
                    'amount' => '120.00',
                    'allocation_type' => 'INVOICE_PAYMENT',
                ]],
            ]);
        }

        return [$db, $code];
    }
}
