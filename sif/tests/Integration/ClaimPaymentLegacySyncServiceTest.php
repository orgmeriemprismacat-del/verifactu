<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ClaimPaymentLegacySyncService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ClaimPaymentLegacySyncServiceTest
{
    public function testBaselineMustMatchSifLedgerBeforeNewClaimCharge(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|LEGACY_BASELINE|MATCH',
                'emesa_abans_cobrament' => 1,
            ])
        );
        $service = new ClaimPaymentLegacySyncService();

        $matched = $service->assertBaselineSynchronized(
            $db,
            new ClaimPaymentLegacySyncSpyPdo('120.00', '0.00'),
            10,
            123,
            $invoice['uuid_factura']
        );
        Assert::same(true, $matched['synchronized']);
        Assert::same('0.00', $matched['legacy_payment']);
        Assert::same('0.00', $matched['sif_payment']);

        Assert::throws(SifException::class, function () use ($db, $invoice, $service): void {
            $service->assertBaselineSynchronized(
                $db,
                new ClaimPaymentLegacySyncSpyPdo('120.00', '20.00'),
                10,
                123,
                $invoice['uuid_factura']
            );
        }, 409);
    }

    public function testEmptyLegacyPaymentIsEquivalentToZeroBaseline(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|LEGACY_BASELINE|EMPTY',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $result = (new ClaimPaymentLegacySyncService())->assertBaselineSynchronized(
            $db,
            new ClaimPaymentLegacySyncSpyPdo('120.00', null),
            10,
            123,
            $invoice['uuid_factura']
        );

        Assert::same('0.00', $result['legacy_payment']);
        Assert::same(true, $result['synchronized']);
    }

    public function testProjectsInvoiceLedgerAfterClaimPayment(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|LEGACY_SYNC|PROJECT',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'CLAIM|RECEIPT:BANK_REFERENCE:LEGACY-SYNC-40',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '40.00',
            'movement_date' => '2026-10-04 03:00:00',
            'reference' => 'LEGACY-SYNC-40',
            'idpag' => 123,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '40.00',
                'allocation_type' => 'CLAIM_PAYMENT',
            ]],
        ]);

        $legacy = new ClaimPaymentLegacySyncSpyPdo('120.00', '0.00');
        $result = (new ClaimPaymentLegacySyncService())->syncAfterSifSuccess(
            $db,
            $legacy,
            10,
            123,
            $invoice['uuid_factura'],
            $invoice['num_visible'],
            '40.00'
        );

        Assert::same('0.00', $result['previous_legacy_payment']);
        Assert::same('40.00', $result['projected_payment']);
        Assert::same('40.00', $result['receipt_amount']);
        Assert::same('PARTIALLY_PAID', $result['status']);
        Assert::same(false, $result['already_synchronized']);
        Assert::same('40.00', $legacy->updatedPayment);
        Assert::same(10, $legacy->updatedIdInsc);
        Assert::same(123, $legacy->updatedIdpag);
    }

    public function testRetryAcceptsAlreadySynchronizedLegacyProjection(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|LEGACY_SYNC|RETRY',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'CLAIM|RECEIPT:BANK_REFERENCE:LEGACY-RETRY-40',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '40.00',
            'movement_date' => '2026-10-04 03:05:00',
            'reference' => 'LEGACY-RETRY-40',
            'idpag' => 123,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '40.00',
                'allocation_type' => 'CLAIM_PAYMENT',
            ]],
        ]);

        $legacy = new ClaimPaymentLegacySyncSpyPdo('120.00', '40.00');
        $result = (new ClaimPaymentLegacySyncService())->syncAfterSifSuccess(
            $db,
            $legacy,
            10,
            123,
            $invoice['uuid_factura'],
            $invoice['num_visible'],
            '40.00'
        );

        Assert::same(true, $result['already_synchronized']);
        Assert::same('40.00', $result['projected_payment']);
    }

    public function testRejectsProjectionWhenLegacyDeltaContainsUnmigratedHistory(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|LEGACY_SYNC|DELTA',
                'emesa_abans_cobrament' => 1,
            ])
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'CLAIM|RECEIPT:BANK_REFERENCE:LEGACY-DELTA-40',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '40.00',
            'movement_date' => '2026-10-04 03:10:00',
            'reference' => 'LEGACY-DELTA-40',
            'idpag' => 123,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '40.00',
                'allocation_type' => 'CLAIM_PAYMENT',
            ]],
        ]);

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            (new ClaimPaymentLegacySyncService())->syncAfterSifSuccess(
                $db,
                new ClaimPaymentLegacySyncSpyPdo('120.00', '10.00'),
                10,
                123,
                $invoice['uuid_factura'],
                $invoice['num_visible'],
                '40.00'
            );
        }, 409);
    }
}

final class ClaimPaymentLegacySyncSpyPdo extends \PDO
{
    public ?string $updatedPayment = null;
    public ?int $updatedIdInsc = null;
    public ?int $updatedIdpag = null;

    public function __construct(
        private string $contractTotal,
        private ?string $paid
    ) {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        if (str_starts_with(ltrim($query), 'SELECT A_PAGAR')) {
            return new ClaimPaymentLegacySyncSelectStatement(
                $this->contractTotal,
                $this->paid
            );
        }

        return new ClaimPaymentLegacySyncUpdateStatement($this);
    }
}

final class ClaimPaymentLegacySyncSelectStatement extends \PDOStatement
{
    public function __construct(
        private string $contractTotal,
        private ?string $paid
    ) {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(
        int $mode = \PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return [
            'A_PAGAR' => $this->contractTotal,
            'PAGAMENT' => $this->paid,
            'INSC CURS' => '0',
        ];
    }
}

final class ClaimPaymentLegacySyncUpdateStatement extends \PDOStatement
{
    private int $rows = 0;

    public function __construct(private ClaimPaymentLegacySyncSpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        $params ??= [];
        $this->db->updatedPayment = (string) ($params[0] ?? '');
        $this->db->updatedIdInsc = isset($params[8]) ? (int) $params[8] : null;
        $this->db->updatedIdpag = isset($params[9]) ? (int) $params[9] : null;
        $this->rows = 1;

        return true;
    }

    public function rowCount(): int
    {
        return $this->rows;
    }
}
