<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\NovicePromotionGrantService;
use Prisma\Sif\Service\NovicePromotionInvoiceLinkService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NovicePromotionInvoiceLinkServiceTest
{
    public function testPreparedApprovedJasomIsLinkedOnlyAfterRealInvoiceIssueAndPayment(): void
    {
        [$db, $operation, $invoice] = $this->fixture('READY_FOR_PAYMENT', 'VALIDATED', 120);

        $linked = (new NovicePromotionInvoiceLinkService())->attach($db, 10, $invoice);
        Assert::same('ELIGIBLE_FOR_GRANT', $linked['status']);
        Assert::same($operation, $linked['uuid_operation']);
        Assert::same(true, $linked['grant_eligible']);

        $grantService = new NovicePromotionGrantService(new UuidGenerator());
        $first = $grantService->issueForOperation($db, $operation);
        $second = $grantService->issueForOperation($db, $operation);
        Assert::same('120.00', $first['original_amount']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    public function testPendingSecretaryDecisionPreventsLinkingAndGrant(): void
    {
        [$db, $operation, $invoice] = $this->fixture('PENDING_VALIDATION', null, 120);
        Assert::throws(SifException::class, static function () use ($db, $invoice): void {
            (new NovicePromotionInvoiceLinkService())->attach($db, 10, $invoice);
        }, 409);

        Assert::same(null, $db->query('SELECT UUID_FACTURA FROM commercial_operation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
    }

    public function testRejectedNoviceDecisionAllowsCoursePaymentButNotPromotion(): void
    {
        [$db, $operation, $invoice] = $this->fixture('READY_FOR_PAYMENT', 'REJECTED', 120);
        $linked = (new NovicePromotionInvoiceLinkService())->attach($db, 10, $invoice);
        Assert::same('DENIED', $linked['status']);
        Assert::same(false, $linked['grant_eligible']);
        Assert::same('PAID', (string) $db->query('SELECT STATUS FROM commercial_operation')->fetchColumn());
    }

    public function testUnstagedJasomIsReportedInsteadOfInventingApproval(): void
    {
        [$db, $operation, $invoice] = $this->fixture('READY_FOR_PAYMENT', 'VALIDATED', 120);
        $db->prepare('DELETE FROM discount_validation WHERE UUID_OPERATION = ?')->execute([$operation]);
        $db->prepare('DELETE FROM commercial_operation_party WHERE UUID_OPERATION = ?')->execute([$operation]);
        $db->prepare('DELETE FROM commercial_operation WHERE UUID_OPERATION = ?')->execute([$operation]);

        $linked = (new NovicePromotionInvoiceLinkService())->attach($db, 10, $invoice);
        Assert::same('NOT_STAGED', $linked['status']);
        Assert::same(false, $linked['grant_eligible']);
    }

    public function testOnlyAfterFinalInstallmentCanPromotionBeGranted(): void
    {
        [$db, $operation, $invoice] = $this->fixture('READY_FOR_PAYMENT', 'VALIDATED', 50);
        $links = new NovicePromotionInvoiceLinkService();
        $first = $links->attach($db, 10, $invoice);
        Assert::same('WAITING_FULL_PAYMENT', $first['status']);
        Assert::same(false, $first['grant_eligible']);

        $this->payment($db, $invoice, '70.00', 'SECOND-INSTALLMENT');
        $after = $links->attach($db, 10, $invoice);
        Assert::same('ELIGIBLE_FOR_GRANT', $after['status']);
        $granted = (new NovicePromotionGrantService(new UuidGenerator()))
            ->issueForOperation($db, $operation);
        Assert::same('120.00', $granted['original_amount']);
    }

    public function testSeparateFiftyAndSeventyEuroInvoicesGrantOneHundredTwentyOnlyAfterBothPayments(): void
    {
        [$db, $operation, $firstInvoice] = $this->fixture('READY_FOR_PAYMENT', 'VALIDATED', 50, 50);
        $links = new NovicePromotionInvoiceLinkService();
        $first = $links->attach($db, 10, $firstInvoice);
        Assert::same('WAITING_FULL_PAYMENT', $first['status']);
        Assert::same(1, $first['linked_invoice_count']);

        $secondInvoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'NOVICE|LINK|SECOND-INSTALLMENT|' . $operation,
            'emesa_abans_cobrament' => 1,
            'totals' => [
                'import_base' => '70.00',
                'taxable_base' => '70.00',
                'total' => '70.00',
            ],
            'lines' => [[
                'unit_price' => '70.00',
                'base' => '70.00',
                'import_base' => '70.00',
                'taxable_base' => '70.00',
                'total' => '70.00',
            ]],
        ]))['uuid_factura'];

        $this->payment($db, $secondInvoice, '70.00', 'SECOND-INSTALLMENT');
        $linked = $links->attach($db, 10, $secondInvoice);
        Assert::same('ELIGIBLE_FOR_GRANT', $linked['status']);
        Assert::same(2, $linked['linked_invoice_count']);
        Assert::same($firstInvoice, (string) $db->query('SELECT UUID_FACTURA FROM commercial_operation')->fetchColumn());

        $granted = (new NovicePromotionGrantService(new UuidGenerator()))
            ->issueForOperation($db, $operation);
        Assert::same('120.00', $granted['original_amount']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());

        $snapshot = (string) $db->query('SELECT RULE_SNAPSHOT_JSON FROM commercial_entitlement')->fetchColumn();
        $decoded = json_decode($snapshot, true, 512, JSON_THROW_ON_ERROR);
        Assert::same(2, count($decoded['origin_invoice_refs']));
    }

    private function fixture(string $operationStatus, ?string $validationStatus, int $firstPayment, int $firstInvoiceAmount = 120): array
    {
        $db = TestDatabase::fresh();
        $operation = (new UuidGenerator())->generate();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'NOVICE|LINK|INVOICE|' . $operation,
            'emesa_abans_cobrament' => 1,
            'totals' => [
                'import_base' => $firstInvoiceAmount . '.00',
                'taxable_base' => $firstInvoiceAmount . '.00',
                'total' => $firstInvoiceAmount . '.00',
            ],
            'lines' => [[
                'unit_price' => $firstInvoiceAmount . '.00',
                'base' => $firstInvoiceAmount . '.00',
                'import_base' => $firstInvoiceAmount . '.00',
                'taxable_base' => $firstInvoiceAmount . '.00',
                'total' => $firstInvoiceAmount . '.00',
            ]],
        ]))['uuid_factura'];

        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, CLASSIFICATION,
              CLASSIFICATION_REASON, STATUS, CURRENCY, GROSS_AMOUNT, DISCOUNT_AMOUNT,
              NET_AMOUNT, PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $operation, 'NOVICE|LINK|OP|' . $operation, 'ENROLLMENT', 'WEB',
            'CURS', '10', 'CURS', 'JASOM', 'BILLABLE', 'NOVICE_DECIDED',
            $operationStatus, 'EUR', '120.00', '0.00', '120.00', '{}', '{}',
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation_party
             (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NIF_CIF, NOM_RAO, SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$operation, 'student:linker:canonical', 'PARTICIPANT', '12345678Z', 'Persona de prova', '{}']);

        if ($validationStatus !== null) {
            $db->prepare(
                'INSERT INTO discount_validation
                 (UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
                  STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, REQUESTED_AT,
                  VALIDATED_AT, VALIDATED_BY, IDEMPOTENCY_KEY)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                (new UuidGenerator())->generate(), $operation, NovicePromotionGrantService::VALIDATION_TYPE,
                'student:linker:canonical', $validationStatus,
                NovicePromotionGrantService::RULE_VERSION, '{}', '2026-09-01 10:00:00',
                $validationStatus === 'VALIDATED' ? '2026-09-02 10:00:00' : null,
                $validationStatus === 'VALIDATED' ? 'secretaria-test' : null,
                'NOVICE|LINK|VALIDATION|' . $operation,
            ]);
        }

        if ($firstPayment > 0) {
            $this->payment($db, $invoice, $firstPayment . '.00', 'FIRST-INSTALLMENT');
        }

        return [$db, $operation, $invoice];
    }

    private function payment(\PDO $db, string $invoice, string $amount, string $source): void
    {
        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'NOVICE|LINK|' . $source . '|' . $invoice,
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => '2026-09-22 10:00:00',
            'reference' => $source,
            'allocations' => [[
                'uuid_factura' => $invoice,
                'amount' => $amount,
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);
    }
}
