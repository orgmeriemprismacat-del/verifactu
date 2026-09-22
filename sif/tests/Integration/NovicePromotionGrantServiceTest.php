<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\NovicePromotionGrantService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NovicePromotionGrantServiceTest
{
    public function testIssuesOnceForApprovedFullyPaidJasomWithoutCreatingAnotherPayment(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:1', 'JASOM', 'VALIDATED', 120, 120);
        $service = new NovicePromotionGrantService(new UuidGenerator());
        $now = new \DateTimeImmutable('2026-09-22 12:00:00', new \DateTimeZone('Europe/Madrid'));

        $first = $service->issueForOperation($db, $operation, $now);
        $second = $service->issueForOperation($db, $operation, $now);

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_entitlement'], $second['uuid_entitlement']);
        Assert::same('120.00', $first['original_amount']);
        Assert::same('2027-09-22 10:00:00', $first['expires_at']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_entitlement_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());

        $row = $db->query('SELECT e.CODE_HASH, e.FACE_VALUE, g.AVAILABLE_AMOUNT, e.ENTITLEMENT_TYPE
                             FROM novice_promotion_grant g
                             JOIN commercial_entitlement e ON e.UUID_ENTITLEMENT = g.UUID_ENTITLEMENT')
            ->fetch(\PDO::FETCH_ASSOC);
        Assert::same(null, $row['CODE_HASH']); // A code is NOT silently sent by this service.
        Assert::same('120.00', $row['FACE_VALUE']);
        Assert::same('120.00', $row['AVAILABLE_AMOUNT']);
        Assert::same('FUTURE_DISCOUNT', $row['ENTITLEMENT_TYPE']);
    }

    public function testPartialPaymentCannotMintNovicePromotion(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:2', 'JASOM', 'VALIDATED', 120, 50);
        Assert::throws(SifException::class, static function () use ($db, $operation): void {
            (new NovicePromotionGrantService(new UuidGenerator()))->issueForOperation($db, $operation);
        }, 409);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    public function testUnapprovedNoviceCannotReceivePromotionEvenWhenPaid(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:3', 'JASOM', 'REQUESTED', 120, 120);
        Assert::throws(SifException::class, static function () use ($db, $operation): void {
            (new NovicePromotionGrantService(new UuidGenerator()))->issueForOperation($db, $operation);
        }, 409);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    public function testOtherCourseCannotBeOrigin(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:4', 'ALTRE', 'VALIDATED', 120, 120);
        Assert::throws(SifException::class, static function () use ($db, $operation): void {
            (new NovicePromotionGrantService(new UuidGenerator()))->issueForOperation($db, $operation);
        }, 409);
    }

    public function testSecondJasomForSameHolderCannotIssueAnotherPromotion(): void
    {
        $db = TestDatabase::fresh();
        $service = new NovicePromotionGrantService(new UuidGenerator());
        $firstOperation = $this->createOrigin($db, 'student:novice:5', 'JASOM', 'VALIDATED', 120, 120);
        $service->issueForOperation($db, $firstOperation);

        $secondOperation = $this->createOrigin($db, 'student:novice:5', 'JASOM', 'VALIDATED', 120, 120);
        Assert::throws(SifException::class, static function () use ($db, $service, $secondOperation): void {
            $service->issueForOperation($db, $secondOperation);
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    private function createOrigin(
        \PDO $db,
        string $holder,
        string $courseCode,
        string $validationStatus,
        int $invoiceAmount,
        int $paidAmount
    ): string {
        $uuid = new UuidGenerator();
        $operation = $uuid->generate();
        $validation = $uuid->generate();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'NOVICE|INVOICE|' . $operation,
            'emesa_abans_cobrament' => 1,
            'totals' => [
                'import_base' => $invoiceAmount . '.00',
                'taxable_base' => $invoiceAmount . '.00',
                'total' => $invoiceAmount . '.00',
            ],
            'lines' => [[
                'concept' => 'JASOM de prova',
                'detail' => 'JASOM test',
                'quantity' => '1.00',
                'unit_price' => $invoiceAmount . '.00',
                'base' => $invoiceAmount . '.00',
                'import_base' => $invoiceAmount . '.00',
                'discount_amount' => '0.00',
                'taxable_base' => $invoiceAmount . '.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => $invoiceAmount . '.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
            ]],
        ]));

        $statement = $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON,
              TAX_SNAPSHOT_JSON, UUID_FACTURA)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $operation, 'NOVICE|OPERATION|' . $operation, 'ENROLLMENT', 'WEB',
            'CURS', '10', 'CURS', $courseCode, 'BILLABLE', 'NORMAL',
            'PAID', 'EUR', $invoiceAmount . '.00', '0.00',
            $invoiceAmount . '.00', '{}', '{}', $invoice['uuid_factura'],
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation_party
             (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NOM_RAO, SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$operation, $holder, 'PARTICIPANT', 'Estudiant de prova', '{}']);

        $db->prepare(
            'INSERT INTO discount_validation
             (UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
              STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, REQUESTED_AT,
              VALIDATED_AT, VALIDATED_BY, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $validation, $operation, NovicePromotionGrantService::VALIDATION_TYPE,
            $holder, $validationStatus, NovicePromotionGrantService::RULE_VERSION,
            '{}', '2026-09-01 10:00:00',
            $validationStatus === 'VALIDATED' ? '2026-09-02 10:00:00' : null,
            $validationStatus === 'VALIDATED' ? 'secretaria-test' : null,
            'NOVICE|VALIDATION|' . $validation,
        ]);

        if ($paidAmount > 0) {
            RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
                'idempotency_key' => 'NOVICE|PAYMENT|' . $operation,
                'movement_type' => 'CHARGE',
                'method' => 'TRANSFERENCIA',
                'source_channel' => 'INTRANET',
                'amount' => $paidAmount . '.00',
                'movement_date' => '2026-09-22 10:00:00',
                'reference' => 'NOVICE-TEST-' . $operation,
                'allocations' => [[
                    'uuid_factura' => $invoice['uuid_factura'],
                    'amount' => $paidAmount . '.00',
                    'allocation_type' => 'INVOICE_PAYMENT',
                ]],
            ]);
        }

        return $operation;
    }
}
