<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Service\GiftRedemptionTrustedContextResolver;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftRedemptionTrustedContextResolverTest
{
    public function testResolvesCanonicalHolderAndPriceOnlyFromCommittedData(): void
    {
        [$db, $code] = $this->fixture();

        $context = (new GiftRedemptionTrustedContextResolver(
            new CommercialEntitlementRepository(new UuidGenerator())
        ))->resolve($db, $db, 501, $code);

        Assert::same(
            'person:id:' . hash('sha256', '12345678Z'),
            $context['holder_party_key']
        );
        Assert::same('UNCLAIMED', $context['entitlement']['holder_state']);
        Assert::same('COURSE-TEST', $context['trusted_price_snapshot']['product_code']);
        Assert::same('2026/09', $context['trusted_price_snapshot']['product_edition']);
        Assert::same('120.00', $context['trusted_price_snapshot']['gross_amount']);
        Assert::same('EUR', $context['trusted_price_snapshot']['currency']);
        Assert::same(
            GiftRedemptionTrustedContextResolver::PRICE_RULE_VERSION,
            $context['trusted_price_snapshot']['price_rule_version']
        );
        Assert::same(
            'GIFT_REDEMPTION_NON_BILLABLE',
            $context['trusted_price_snapshot']['tax_snapshot']['reason']
        );
    }

    public function testGenericHoursGiftAllowsChosenCourseWithMatchingEditionHours(): void
    {
        [$db, $code] = $this->fixture();
        $db->exec(
            'CREATE TEMPORARY TABLE curs (
                CURS VARCHAR(80) NOT NULL,
                ANY INT NOT NULL,
                MES VARCHAR(12) NOT NULL,
                HORES INT NOT NULL
            )'
        );
        $db->exec(
            "INSERT INTO curs (CURS, ANY, MES, HORES)
             VALUES ('COURSE-TEST', 2026, '09', 30)"
        );
        $db->exec("UPDATE regal SET CCURS='30'");

        $context = (new GiftRedemptionTrustedContextResolver(
            new CommercialEntitlementRepository(new UuidGenerator())
        ))->resolve($db, $db, 501, $code);

        Assert::same('COURSE-TEST', $context['trusted_price_snapshot']['product_code']);
    }

    public function testGenericHoursGiftRejectsChosenCourseWithDifferentEditionHours(): void
    {
        [$db, $code] = $this->fixture();
        $db->exec(
            'CREATE TEMPORARY TABLE curs (
                CURS VARCHAR(80) NOT NULL,
                ANY INT NOT NULL,
                MES VARCHAR(12) NOT NULL,
                HORES INT NOT NULL
            )'
        );
        $db->exec(
            "INSERT INTO curs (CURS, ANY, MES, HORES)
             VALUES ('COURSE-TEST', 2026, '09', 30)"
        );
        $db->exec("UPDATE regal SET CCURS='40'");

        Assert::throws(SifException::class, function () use ($db, $code): void {
            (new GiftRedemptionTrustedContextResolver(
                new CommercialEntitlementRepository(new UuidGenerator())
            ))->resolve($db, $db, 501, $code);
        }, 409);
    }

    public function testRejectsGiftAlreadyClaimedByAnotherCanonicalParticipant(): void
    {
        [$db, $code] = $this->fixture();
        $db->exec(
            "UPDATE commercial_entitlement
             SET HOLDER_PARTY_KEY='person:id:someone-else'"
        );

        Assert::throws(SifException::class, function () use ($db, $code): void {
            (new GiftRedemptionTrustedContextResolver(
                new CommercialEntitlementRepository(new UuidGenerator())
            ))->resolve($db, $db, 501, $code);
        }, 409);
    }

    private function fixture(): array
    {
        $db = TestDatabase::fresh();
        $code = 'GIFT-CONTEXT-001';
        $hash = hash('sha256', $code);

        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID INT PRIMARY KEY,
                ANY INT NOT NULL,
                MES VARCHAR(12) NOT NULL,
                CURS VARCHAR(80) NOT NULL,
                DNI VARCHAR(20) NOT NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL,
                FACTURA_RELACIONADA VARCHAR(30) NULL,
                pag_observacions VARCHAR(255) NULL,
                OBSERVACIONS VARCHAR(255) NULL
            )'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE regal (
                ID INT PRIMARY KEY,
                CODI VARCHAR(200) NOT NULL,
                IMPORT DECIMAL(12,2) NOT NULL,
                FACT_REL VARCHAR(30) NULL,
                USAT INT NULL,
                CCURS VARCHAR(80) NULL
            )'
        );

        $statement = $db->prepare(
            'INSERT INTO inscripcions
             (ID, ANY, MES, CURS, DNI, A_PAGAR, FACTURA_RELACIONADA,
              pag_observacions, OBSERVACIONS)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            501,
            2026,
            '09',
            'COURSE-TEST',
            '12.345.678-z',
            '0.00',
            '987',
            $code,
            'CURS REGAL',
        ]);

        $statement = $db->prepare(
            'INSERT INTO regal (ID, CODI, IMPORT, FACT_REL, USAT, CCURS)
             VALUES (?, ?, ?, ?, NULL, ?)'
        );
        $statement->execute([77, $code, '120.00', '987', 'COURSE-TEST']);

        $db->prepare(
            'INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
              ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON, FACE_VALUE,
              CURRENCY, STATUS, ISSUED_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            '11111111-1111-4111-8111-111111111111',
            'GIFT',
            $hash,
            CommercialEntitlementRepository::unclaimedGiftHolderKey($hash),
            'GIFT_V1',
            '{}',
            '120.00',
            'EUR',
            'ACTIVE',
            '2026-09-30 10:00:00',
            'GIFT|ENTITLEMENT|TEST:77',
        ]);

        return [$db, $code];
    }
}
