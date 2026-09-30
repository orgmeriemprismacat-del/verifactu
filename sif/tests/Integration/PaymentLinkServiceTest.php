<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialOperationRepository;
use Prisma\Sif\Repository\DiscountValidationRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\PaymentLinkRepository;
use Prisma\Sif\Service\CommercialOfferService;
use Prisma\Sif\Service\PaymentLinkService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class PaymentLinkServiceTest
{
    public function testIssuesOpaqueLinkStoresOnlyHashAndResolvesActiveLink(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOperation($db);
        $service = $this->service($db, 'opaque-test-token');

        $link = $service->issue([
            'uuid_operation' => $operation['uuid_operation'],
            'expected_amount' => '90.00',
            'currency' => 'EUR',
            'expires_at' => '2026-10-05 20:00:00',
            'payer_party_key' => 'party-501',
            'created_by' => 'web-checkout',
        ]);

        Assert::same('opaque-test-token', $link['token']);
        Assert::same('ACTIVE', $link['status']);

        $stored = $db->query('SELECT * FROM payment_link')->fetch(\PDO::FETCH_ASSOC);
        Assert::same(hash('sha256', 'opaque-test-token'), $stored['TOKEN_HASH']);
        Assert::notSame('opaque-test-token', $stored['TOKEN_HASH']);

        $resolved = $service->resolve('opaque-test-token', '2026-10-01 12:00:00');
        Assert::same($operation['uuid_operation'], $resolved['uuid_operation']);
        Assert::same('90.00', $resolved['expected_amount']);
        Assert::same('OFFERED', $resolved['operation_status']);

        $accessed = (string) $db->query('SELECT LAST_ACCESSED_AT FROM payment_link')->fetchColumn();
        Assert::same('2026-10-01 12:00:00', $accessed);
    }

    public function testRejectsLinkAmountAboveCommercialNetAmount(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOperation($db);
        $service = $this->service($db, 'too-high-token');

        Assert::throws(SifException::class, static function () use ($service, $operation): void {
            $service->issue([
                'uuid_operation' => $operation['uuid_operation'],
                'expected_amount' => '90.01',
                'currency' => 'EUR',
                'expires_at' => '2026-10-05 20:00:00',
            ]);
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_link')->fetchColumn());
    }

    public function testRejectsLinkCurrencyDifferentFromCommercialOperation(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOperation($db);
        $service = $this->service($db, 'currency-token');

        Assert::throws(SifException::class, static function () use ($service, $operation): void {
            $service->issue([
                'uuid_operation' => $operation['uuid_operation'],
                'expected_amount' => '90.00',
                'currency' => 'USD',
                'expires_at' => '2026-10-05 20:00:00',
            ]);
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_link')->fetchColumn());
    }

    public function testRejectsLinkExpiryAfterCommercialOperationExpiry(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOperation($db);
        $service = $this->service($db, 'late-expiry-token');

        Assert::throws(SifException::class, static function () use ($service, $operation): void {
            $service->issue([
                'uuid_operation' => $operation['uuid_operation'],
                'expected_amount' => '90.00',
                'currency' => 'EUR',
                'expires_at' => '2026-10-06 00:00:00',
            ]);
        }, 422);
    }

    public function testRevokedLinkCannotBeResolvedAndRevokeIsIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOperation($db);
        $service = $this->service($db, 'revoke-token');

        $link = $service->issue([
            'uuid_operation' => $operation['uuid_operation'],
            'expected_amount' => '45.00',
            'currency' => 'EUR',
            'expires_at' => '2026-10-05 20:00:00',
        ]);

        $first = $service->revoke(
            $link['uuid_payment_link'],
            'OFFER_REPLACED',
            'test',
            null,
            '2026-10-01 11:00:00'
        );
        $second = $service->revoke(
            $link['uuid_payment_link'],
            'OFFER_REPLACED',
            'test',
            null,
            '2026-10-01 11:00:00'
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);

        Assert::throws(SifException::class, static function () use ($service): void {
            $service->resolve('revoke-token', '2026-10-01 12:00:00');
        }, 403);
    }

    public function testExpiredLinkCannotBeResolved(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOperation($db);
        $service = $this->service($db, 'expired-token');

        $service->issue([
            'uuid_operation' => $operation['uuid_operation'],
            'expected_amount' => '90.00',
            'currency' => 'EUR',
            'expires_at' => '2026-10-01 10:00:00',
        ]);

        Assert::throws(SifException::class, static function () use ($service): void {
            $service->resolve('expired-token', '2026-10-01 10:00:01');
        }, 403);
    }

    private function createOperation(\PDO $db): array
    {
        $uuid = new UuidGenerator();
        $service = new CommercialOfferService(
            new TransactionRunner($db),
            new CommercialOperationRepository(),
            new DiscountValidationRepository(),
            new OperationalEventRepository($uuid),
            $uuid
        );

        return $service->createOrReuse([
            'idempotency_key' => 'WEB|CURS|INSCRIPCIO:501|OFFER:LINK',
            'operation_type' => 'COURSE_ENROLLMENT',
            'source_channel' => 'WEB',
            'source_type' => 'INSCRIPCIO',
            'source_id' => '501',
            'product_type' => 'CURS',
            'product_code' => 'CURS-TEST',
            'product_edition' => '2026-10',
            'classification' => 'SALE',
            'classification_reason' => 'COURSE_ENROLLMENT',
            'status' => 'OFFERED',
            'currency' => 'EUR',
            'gross_amount' => '120.00',
            'discount_amount' => '30.00',
            'net_amount' => '90.00',
            'price_snapshot' => [
                'gross_amount' => '120.00',
                'discount_amount' => '30.00',
                'net_amount' => '90.00',
            ],
            'tax_snapshot' => ['tax_regime' => 'PENDING_FISCAL_CLASSIFICATION'],
            'expires_at' => '2026-10-05 23:59:59',
            'created_by' => 'test',
            'correlation_id' => 'UC020-LINK-501',
            'actor_type' => 'SYSTEM',
            'actor_id' => 'test',
            'occurred_at' => '2026-09-30 15:00:00',
            'discount' => [
                'idempotency_key' => 'WEB|CURS|INSCRIPCIO:501|DISCOUNT:LINK',
                'discount_type' => 'ALUMNE_PRISMA',
                'subject_party_key' => 'party-501',
                'status' => 'ACCEPTED',
                'rule_version' => 'AP-2026-09',
                'rule_snapshot' => ['policy' => 'ALUMNE_PRISMA'],
                'requested_at' => '2026-09-30 15:00:00',
                'validated_at' => '2026-09-30 15:00:00',
                'validated_by' => 'policy:alumne-prisma',
                'result_discount_amount' => '30.00',
            ],
        ]);
    }

    private function service(\PDO $db, string $token): PaymentLinkService
    {
        return new PaymentLinkService(
            new TransactionRunner($db),
            new CommercialOperationRepository(),
            new PaymentLinkRepository(),
            new UuidGenerator(),
            static fn (): string => $token
        );
    }
}
