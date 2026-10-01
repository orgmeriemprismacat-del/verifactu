<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialOperationPartyRepository;
use Prisma\Sif\Repository\CommercialOperationRepository;
use Prisma\Sif\Repository\DiscountValidationRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Service\CommercialOfferService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class CommercialOfferServiceTest
{
    public function testCreatesCommercialOperationDiscountValidationAndAuditEvent(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);

        $created = $service->createOrReuse($this->input());

        Assert::same(false, $created['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());

        $operation = $db->query('SELECT * FROM commercial_operation')->fetch(\PDO::FETCH_ASSOC);
        $validation = $db->query('SELECT * FROM discount_validation')->fetch(\PDO::FETCH_ASSOC);

        Assert::same($created['uuid_operation'], $operation['UUID_OPERATION']);
        Assert::same('120.00', number_format((float) $operation['GROSS_AMOUNT'], 2, '.', ''));
        Assert::same('30.00', number_format((float) $operation['DISCOUNT_AMOUNT'], 2, '.', ''));
        Assert::same('90.00', number_format((float) $operation['NET_AMOUNT'], 2, '.', ''));
        Assert::same('ALUMNE_PRISMA', $validation['DISCOUNT_TYPE']);
        Assert::same('AP-2026-09', $validation['RULE_VERSION']);
        Assert::same('30.00', number_format((float) $validation['RESULT_DISCOUNT_AMOUNT'], 2, '.', ''));
    }

    public function testEquivalentRequestReusesOperationAndValidationWithoutSecondEvent(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $input = $this->input();

        $first = $service->createOrReuse($input);
        $second = $service->createOrReuse($input);

        Assert::same($first['uuid_operation'], $second['uuid_operation']);
        Assert::same($first['uuid_validation'], $second['uuid_validation']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
    }

    public function testCreatesAndReusesCommercialPartiesInTheSameOffer(): void
    {
        $db = TestDatabase::fresh();
        $uuid = new UuidGenerator();
        $service = new CommercialOfferService(
            new TransactionRunner($db),
            new CommercialOperationRepository(),
            new DiscountValidationRepository(),
            new OperationalEventRepository($uuid),
            $uuid,
            new CommercialOperationPartyRepository()
        );
        $input = $this->input();
        $input['parties'] = [[
            'party_key' => 'student:501',
            'party_role' => 'PARTICIPANT',
            'nif_cif' => '12345678Z',
            'nom_rao' => 'Persona de prova',
            'email' => 'persona@example.invalid',
            'product_code' => 'CURS-TEST',
            'product_edition' => '2026-10',
            'line_amount' => '90.00',
            'snapshot' => [
                'source' => 'legacy_inscription',
                'source_id' => 501,
            ],
        ]];

        $first = $service->createOrReuse($input);
        $second = $service->createOrReuse($input);

        Assert::same($first['uuid_operation'], $second['uuid_operation']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation_party')->fetchColumn());

        $party = $db->query('SELECT * FROM commercial_operation_party')->fetch(\PDO::FETCH_ASSOC);
        Assert::same('student:501', $party['PARTY_KEY']);
        Assert::same('PARTICIPANT', $party['PARTY_ROLE']);
        Assert::same('90.00', number_format((float) $party['LINE_AMOUNT'], 2, '.', ''));
    }

    public function testSameOperationKeyWithDifferentPayloadConflicts(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $input = $this->input();
        $service->createOrReuse($input);

        $changed = $input;
        $changed['product_edition'] = '2026-11';

        Assert::throws(SifException::class, static function () use ($service, $changed): void {
            $service->createOrReuse($changed);
        }, 409);
    }

    public function testSameDiscountKeyCannotBeAttachedToDifferentOperation(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $first = $this->input();
        $service->createOrReuse($first);

        $second = $this->input();
        $second['idempotency_key'] = 'WEB|CURS|INSCRIPCIO:502|OFFER:1';
        $second['source_id'] = '502';

        Assert::throws(SifException::class, static function () use ($service, $second): void {
            $service->createOrReuse($second);
        }, 409);
    }

    public function testRejectsDiscountValidationAmountDifferentFromCommercialDiscount(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $input = $this->input();
        $input['discount']['result_discount_amount'] = '29.00';

        Assert::throws(SifException::class, static function () use ($service, $input): void {
            $service->createOrReuse($input);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
    }

    public function testRejectsInconsistentCommercialAmountsBeforeWriting(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $input = $this->input();
        $input['net_amount'] = '95.00';

        Assert::throws(SifException::class, static function () use ($service, $input): void {
            $service->createOrReuse($input);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
    }

    private function service(\PDO $db): CommercialOfferService
    {
        $uuid = new UuidGenerator();

        return new CommercialOfferService(
            new TransactionRunner($db),
            new CommercialOperationRepository(),
            new DiscountValidationRepository(),
            new OperationalEventRepository($uuid),
            $uuid
        );
    }

    private function input(): array
    {
        return [
            'idempotency_key' => 'WEB|CURS|INSCRIPCIO:501|OFFER:1',
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
                'price_id' => 17,
            ],
            'tax_snapshot' => [
                'tax_regime' => 'PENDING_FISCAL_CLASSIFICATION',
            ],
            'expires_at' => '2026-10-05 23:59:59',
            'created_by' => 'web-checkout',
            'correlation_id' => 'UC020-TEST-501',
            'actor_type' => 'CUSTOMER',
            'actor_id' => 'party-501',
            'occurred_at' => '2026-09-30 15:00:00',
            'discount' => [
                'idempotency_key' => 'WEB|CURS|INSCRIPCIO:501|DISCOUNT:AP',
                'discount_type' => 'ALUMNE_PRISMA',
                'subject_party_key' => 'party-501',
                'status' => 'ACCEPTED',
                'rule_version' => 'AP-2026-09',
                'rule_snapshot' => [
                    'policy' => 'ALUMNE_PRISMA',
                    'evaluation_at' => '2026-09-30 15:00:00',
                    'business_decisions' => [
                        'generated_counts' => null,
                        'unpaid_invoice_counts' => null,
                        'self_enrollment_counts' => null,
                    ],
                ],
                'requested_at' => '2026-09-30 15:00:00',
                'validated_at' => '2026-09-30 15:00:00',
                'validated_by' => 'policy:alumne-prisma',
                'result_discount_amount' => '30.00',
            ],
        ];
    }
}
