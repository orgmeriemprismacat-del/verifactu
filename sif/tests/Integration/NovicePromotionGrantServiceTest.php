<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\NovicePromotionGrantService;
use Prisma\Sif\Service\NovicePromotionCodePreparationService;
use Prisma\Sif\Service\NovicePromotionGrantReconciler;
use Prisma\Sif\Service\NovicePromotionDeliveryAttemptService;
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

    public function testReusingCancelledOriginalDoesNotMintAnotherRight(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:6', 'JASOM', 'VALIDATED', 120, 120);
        $service = new NovicePromotionGrantService(new UuidGenerator());
        $first = $service->issueForOperation($db, $operation);

        $db->prepare("UPDATE commercial_entitlement SET STATUS = 'CANCELLED' WHERE UUID_ENTITLEMENT = ?")
            ->execute([$first['uuid_entitlement']]);
        $db->prepare("UPDATE commercial_operation SET STATUS = 'CANCELLED' WHERE UUID_OPERATION = ?")
            ->execute([$operation]);
        $db->prepare("UPDATE discount_validation SET STATUS = 'CANCELLED' WHERE UUID_OPERATION = ?")
            ->execute([$operation]);

        $repeat = $service->issueForOperation($db, $operation);
        Assert::same(true, $repeat['idempotency_reused']);
        Assert::same('CANCELLED', $repeat['status']);
        Assert::same($first['uuid_entitlement'], $repeat['uuid_entitlement']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    public function testPreexistingValidationEntitlementReferencePreventsSecondGrant(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:7', 'JASOM', 'VALIDATED', 120, 120);
        $db->prepare("UPDATE discount_validation SET FUTURE_ENTITLEMENT_REF = 'legacy-imported-right'
                      WHERE UUID_OPERATION = ?")->execute([$operation]);

        Assert::throws(SifException::class, static function () use ($db, $operation): void {
            (new NovicePromotionGrantService(new UuidGenerator()))->issueForOperation($db, $operation);
        }, 409);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    public function testInvoiceMustBelongToOriginEnrollment(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:8', 'JASOM', 'VALIDATED', 120, 120);
        $db->prepare("UPDATE commercial_operation SET SOURCE_ID = '999' WHERE UUID_OPERATION = ?")
            ->execute([$operation]);

        Assert::throws(SifException::class, static function () use ($db, $operation): void {
            (new NovicePromotionGrantService(new UuidGenerator()))->issueForOperation($db, $operation);
        }, 409);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    public function testPostPaymentReconciliationIssuesGrantOnlyOnce(): void
    {
        $db = TestDatabase::fresh();
        $this->createOrigin($db, 'student:novice:reconcile1', 'JASOM', 'VALIDATED', 120, 120);
        $reconciler = new NovicePromotionGrantReconciler(
            new NovicePromotionGrantService(new UuidGenerator())
        );

        $first = $reconciler->run($db);
        $second = $reconciler->run($db);

        Assert::same(1, $first['candidates']);
        Assert::same(1, $first['issued']);
        Assert::same(0, $first['conflicts']);
        Assert::same(0, $first['errors']);
        Assert::same(0, $second['candidates']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    public function testPostPaymentReconciliationWaitsForRemainingInstallment(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:reconcile2', 'JASOM', 'VALIDATED', 120, 50);
        $reconciler = new NovicePromotionGrantReconciler(
            new NovicePromotionGrantService(new UuidGenerator())
        );

        Assert::same(0, $reconciler->run($db)['candidates']);

        $statement = $db->prepare('SELECT UUID_FACTURA FROM commercial_operation WHERE UUID_OPERATION = ?');
        $statement->execute([$operation]);
        $invoiceUuid = (string) $statement->fetchColumn();
        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'NOVICE|REMAINING|' . $operation,
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '70.00',
            'movement_date' => '2026-09-23 10:00:00',
            'reference' => 'NOVICE-SECOND-' . $operation,
            'allocations' => [[
                'uuid_factura' => $invoiceUuid,
                'amount' => '70.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $after = $reconciler->run($db);
        Assert::same(1, $after['issued']);
        Assert::same(0, $after['conflicts']);
        Assert::same('120.00', (string) $db->query('SELECT ORIGINAL_CASH_AMOUNT FROM novice_promotion_grant')->fetchColumn());
    }

    public function testPostPaymentReconciliationSkipsRejectedOrPendingNovice(): void
    {
        $db = TestDatabase::fresh();
        $this->createOrigin($db, 'student:novice:reconcile3', 'JASOM', 'REQUESTED', 120, 120);
        $reconciler = new NovicePromotionGrantReconciler(
            new NovicePromotionGrantService(new UuidGenerator())
        );

        Assert::same(0, $reconciler->run($db)['candidates']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    public function testPreparesOneEncryptedRedeemableTokenWithoutReturningItToCaller(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:token1', 'JASOM', 'VALIDATED', 120, 120);
        $grant = (new NovicePromotionGrantService(new UuidGenerator()))
            ->issueForOperation($db, $operation);
        $service = new NovicePromotionCodePreparationService(new UuidGenerator());
        $secretKeyHex = str_repeat('a', 64); // Synthetic key for isolated TEST ONLY.

        $first = $service->prepare($db, $grant['uuid_entitlement'], $secretKeyHex, 'test-v1');
        $second = $service->prepare($db, $grant['uuid_entitlement'], $secretKeyHex, 'test-v1');

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same('ACTIVE', $first['entitlement_status']);
        Assert::same('PREPARED', $first['delivery_status']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_code_outbox')->fetchColumn());
        Assert::same(1, (int) $db->query("SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='ACTIVATE'")->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());

        $row = $db->query(
            'SELECT e.CODE_HASH, o.TOKEN_CIPHERTEXT, o.TOKEN_NONCE, o.TOKEN_TAG
             FROM novice_promotion_code_outbox o
             JOIN commercial_entitlement e ON e.UUID_ENTITLEMENT = o.UUID_ENTITLEMENT'
        )->fetch(\PDO::FETCH_ASSOC);
        Assert::same(64, strlen((string) $row['CODE_HASH']));
        Assert::same(12, strlen((string) $row['TOKEN_NONCE']));
        Assert::same(16, strlen((string) $row['TOKEN_TAG']));
        Assert::same(false, array_key_exists('token', $first));
        Assert::same(false, array_key_exists('token', $second));
        Assert::same(false, str_contains((string) $row['TOKEN_CIPHERTEXT'], 'NOV-'));
        $decoded = openssl_decrypt(
            (string) $row['TOKEN_CIPHERTEXT'],
            'aes-256-gcm',
            hex2bin($secretKeyHex),
            OPENSSL_RAW_DATA,
            (string) $row['TOKEN_NONCE'],
            (string) $row['TOKEN_TAG'],
            'UC111|' . $grant['uuid_entitlement']
        );
        Assert::matchesRegularExpression('/^NOV-[A-F0-9]{40}$/D', (string) $decoded);
        Assert::same(hash('sha256', (string) $decoded), $row['CODE_HASH']);
        Assert::same(false, openssl_decrypt(
            (string) $row['TOKEN_CIPHERTEXT'],
            'aes-256-gcm',
            hex2bin($secretKeyHex),
            OPENSSL_RAW_DATA,
            (string) $row['TOKEN_NONCE'],
            (string) $row['TOKEN_TAG'],
            'UC111|wrong-entitlement'
        ));
    }

    public function testCancelledNoviceGrantCannotPrepareNewCode(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:token2', 'JASOM', 'VALIDATED', 120, 120);
        $grant = (new NovicePromotionGrantService(new UuidGenerator()))
            ->issueForOperation($db, $operation);

        $db->prepare("UPDATE commercial_entitlement SET STATUS = 'CANCELLED' WHERE UUID_ENTITLEMENT = ?")
            ->execute([$grant['uuid_entitlement']]);

        Assert::throws(SifException::class, static function () use ($db, $grant): void {
            (new NovicePromotionCodePreparationService(new UuidGenerator()))
                ->prepare($db, $grant['uuid_entitlement'], str_repeat('a', 64), 'test-v1');
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_code_outbox')->fetchColumn());
    }

    public function testRefundedOriginCannotPrepareCodeAfterGrant(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:token-refunded', 'JASOM', 'VALIDATED', 120, 120);
        $grant = (new NovicePromotionGrantService(new UuidGenerator()))
            ->issueForOperation($db, $operation);

        $db->prepare("UPDATE factura f
                       JOIN novice_promotion_grant g ON g.UUID_FACTURA = f.UUID_FACTURA
                       SET f.ESTAT_COBRAMENT = 'PENDING'
                       WHERE g.UUID_ENTITLEMENT = ?")
            ->execute([$grant['uuid_entitlement']]);

        Assert::throws(SifException::class, static function () use ($db, $grant): void {
            (new NovicePromotionCodePreparationService(new UuidGenerator()))
                ->prepare($db, $grant['uuid_entitlement'], str_repeat('a', 64), 'test-v1');
        }, 409);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_code_outbox')->fetchColumn());
    }

    public function testFractionalCentDifferenceAfterGrantBlocksCodePreparation(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:token-cents', 'JASOM', 'VALIDATED', 120, 120);
        $grant = (new NovicePromotionGrantService(new UuidGenerator()))
            ->issueForOperation($db, $operation);

        // Simulate an inconsistent or tampered invoice whose total differs
        // from the real confirmed allocation by just ONE CENT.
        $db->prepare(
            'UPDATE factura f
             JOIN novice_promotion_grant g ON g.UUID_FACTURA = f.UUID_FACTURA
             SET f.TOTAL = 120.01 WHERE g.UUID_ENTITLEMENT = ?'
        )->execute([$grant['uuid_entitlement']]);

        Assert::throws(SifException::class, static function () use ($db, $grant): void {
            (new NovicePromotionCodePreparationService(new UuidGenerator()))
                ->prepare($db, $grant['uuid_entitlement'], str_repeat('a', 64), 'test-v1');
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_code_outbox')->fetchColumn());
    }

    public function testInvalidWrappingKeyNeverWritesOrActivatesToken(): void
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, 'student:novice:token3', 'JASOM', 'VALIDATED', 120, 120);
        $grant = (new NovicePromotionGrantService(new UuidGenerator()))
            ->issueForOperation($db, $operation);

        Assert::throws(\RuntimeException::class, static function () use ($db, $grant): void {
            (new NovicePromotionCodePreparationService(new UuidGenerator()))
                ->prepare($db, $grant['uuid_entitlement'], '', 'test-v1');
        });

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_code_outbox')->fetchColumn());
        Assert::same('ISSUED', (string) $db->query('SELECT STATUS FROM commercial_entitlement')->fetchColumn());
    }

    public function testDeliveryRequiresIndependentlyVerifiedRecipient(): void
    {
        [$db, $entitlement] = $this->deliveryFixture('student:novice:delivery1');
        $delivery = new NovicePromotionDeliveryAttemptService(new UuidGenerator());
        Assert::throws(SifException::class, static function () use ($db, $entitlement, $delivery): void {
            $delivery->claim($db, $entitlement);
        }, 409);

        Assert::same('PREPARED', (string) $db->query('SELECT STATUS FROM novice_promotion_code_outbox')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT ATTEMPTS FROM novice_promotion_code_outbox')->fetchColumn());
    }

    public function testDeliveryAttemptReusesSameEncryptedTokenAndObservesBackoff(): void
    {
        [$db, $entitlement] = $this->deliveryFixture('student:novice:delivery2');
        $this->seedVerifiedRecipientForTest($db, $entitlement);
        $service = new NovicePromotionDeliveryAttemptService(new UuidGenerator());
        $start = new \DateTimeImmutable('2026-09-23 11:00:00', new \DateTimeZone('UTC'));
        $originalHash = (string) $db->query('SELECT CODE_HASH FROM commercial_entitlement')->fetchColumn();
        $originalCiphertext = (string) $db->query('SELECT TOKEN_CIPHERTEXT FROM novice_promotion_code_outbox')->fetchColumn();

        $first = $service->claim($db, $entitlement, $start);
        Assert::same('CLAIMED', $first['status']);
        Assert::same('IN_FLIGHT', $service->claim($db, $entitlement, $start->modify('+1 minute'))['status']);
        Assert::same('FAILED', $service->recordResult($db, $entitlement, $first['claim_id'], false, $start)['status']);
        Assert::same('BACKOFF', $service->claim($db, $entitlement, $start->modify('+30 minutes'))['status']);

        $second = $service->claim($db, $entitlement, $start->modify('+61 minutes'));
        Assert::same('CLAIMED', $second['status']);
        Assert::notSame($first['claim_id'], $second['claim_id']);
        Assert::same('SENT', $service->recordResult($db, $entitlement, $second['claim_id'], true, $start->modify('+61 minutes'))['status']);
        Assert::same('ALREADY_SENT', $service->claim($db, $entitlement, $start->modify('+62 minutes'))['status']);
        Assert::same($originalHash, (string) $db->query('SELECT CODE_HASH FROM commercial_entitlement')->fetchColumn());
        Assert::same($originalCiphertext, (string) $db->query('SELECT TOKEN_CIPHERTEXT FROM novice_promotion_code_outbox')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT ATTEMPTS FROM novice_promotion_code_outbox')->fetchColumn());
        Assert::same(1, (int) $db->query("SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='DELIVER'")->fetchColumn());
    }

    public function testStaleDeliveryResultCannotConfirmReclaimedAttempt(): void
    {
        [$db, $entitlement] = $this->deliveryFixture('student:novice:delivery3');
        $this->seedVerifiedRecipientForTest($db, $entitlement);
        $service = new NovicePromotionDeliveryAttemptService(new UuidGenerator());
        $start = new \DateTimeImmutable('2026-09-23 11:00:00', new \DateTimeZone('UTC'));
        $first = $service->claim($db, $entitlement, $start);
        $second = $service->claim($db, $entitlement, $start->modify('+31 minutes'));
        Assert::same('CLAIMED', $second['status']);

        Assert::throws(SifException::class, static function () use ($service, $db, $entitlement, $first): void {
            $service->recordResult($db, $entitlement, $first['claim_id'], true);
        }, 409);
        Assert::same('SENDING', (string) $db->query('SELECT STATUS FROM novice_promotion_code_outbox')->fetchColumn());
        Assert::same('SENT', $service->recordResult($db, $entitlement, $second['claim_id'], true)['status']);
    }

    public function testCancelledOrRefundedOriginCannotClaimPreparedCode(): void
    {
        [$db, $entitlement] = $this->deliveryFixture('student:novice:delivery4');
        $this->seedVerifiedRecipientForTest($db, $entitlement);
        $service = new NovicePromotionDeliveryAttemptService(new UuidGenerator());

        $db->prepare("UPDATE commercial_entitlement SET STATUS = 'CANCELLED' WHERE UUID_ENTITLEMENT = ?")
            ->execute([$entitlement]);

        Assert::throws(SifException::class, static function () use ($service, $db, $entitlement): void {
            $service->claim($db, $entitlement);
        }, 409);

        $db->prepare("UPDATE commercial_entitlement SET STATUS = 'ACTIVE' WHERE UUID_ENTITLEMENT = ?")
            ->execute([$entitlement]);
        $db->prepare(
            "UPDATE factura f JOIN novice_promotion_grant g
             ON g.UUID_FACTURA = f.UUID_FACTURA
             SET f.ESTAT_COBRAMENT = 'PENDING' WHERE g.UUID_ENTITLEMENT = ?"
        )->execute([$entitlement]);
        Assert::throws(SifException::class, static function () use ($service, $db, $entitlement): void {
            $service->claim($db, $entitlement);
        }, 409);
        Assert::same(0, (int) $db->query('SELECT ATTEMPTS FROM novice_promotion_code_outbox')->fetchColumn());
    }

    private function deliveryFixture(string $holder): array
    {
        $db = TestDatabase::fresh();
        $operation = $this->createOrigin($db, $holder, 'JASOM', 'VALIDATED', 120, 120);
        $grant = (new NovicePromotionGrantService(new UuidGenerator()))
            ->issueForOperation($db, $operation);
        (new NovicePromotionCodePreparationService(new UuidGenerator()))
            ->prepare($db, $grant['uuid_entitlement'], str_repeat('a', 64), 'test-v1');

        return [$db, $grant['uuid_entitlement']];
    }

    private function seedVerifiedRecipientForTest(\PDO $db, string $entitlement): void
    {
        // Synthetic test data: the ACTUAL address-verification workflow has
        // NOT been implemented, so production MUST NOT write this row yet.
        $stmt = $db->prepare(
            'INSERT INTO novice_promotion_verified_recipient
             (UUID_ENTITLEMENT, EMAIL, VERIFIED_AT, VERIFICATION_REF, RECORDED_BY)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $entitlement, 'test-recipient@example.invalid',
            '2026-09-23 09:00:00', 'TEST|VERIFICATION|' . $entitlement,
            'test-only',
        ]);
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
