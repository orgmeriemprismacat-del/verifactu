<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\HistoricalInvoiceMigrationRepository;
use Prisma\Sif\Service\HistoricalInvoiceMigrationService;
use Prisma\Sif\Service\HistoricalInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class HistoricalInvoiceMigrationGovernanceTest
{
    public function testWritesOperationalAndAuditEventsForCreateAndReuse(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);

        $first = $service->importHistoricalInvoice($this->input());
        $second = $service->importHistoricalInvoice($this->input());

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['correlation_id'], $second['correlation_id']);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM sif_audit_event')->fetchColumn());

        $operational = $db->query(
            'SELECT OPERATION_TYPE, FISCAL_IMPACT, ECONOMIC_IMPACT, REASON_CODE
             FROM operational_event
             ORDER BY ID'
        )->fetchAll(\PDO::FETCH_ASSOC);
        Assert::same('HISTORICAL_INVOICE_IMPORT', $operational[0]['OPERATION_TYPE']);
        Assert::same('HISTORICAL_NO_VERIFACTU', $operational[0]['FISCAL_IMPACT']);
        Assert::same('NONE', $operational[0]['ECONOMIC_IMPACT']);
        Assert::same('HISTORICAL_INVOICE_IMPORTED', $operational[0]['REASON_CODE']);
        Assert::same('HISTORICAL_INVOICE_REUSED', $operational[1]['REASON_CODE']);
    }

    public function testRejectsNumberClaimedByDifferentIdempotencyKey(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);

        $first = $this->input();
        $first['idempotency_key'] = 'HISTORIC|ONE';
        $service->importHistoricalInvoice($first);

        $second = $this->input();
        $second['idempotency_key'] = 'HISTORIC|TWO';

        $exception = Assert::throws(
            SifException::class,
            fn (): array => $service->importHistoricalInvoice($second),
            409
        );
        Assert::stringContainsString('already assigned', $exception->getMessage());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
    }

    public function testRejectsCurrentYearHistoricalNumberWithoutFiscalSequenceCheckpoint(): void
    {
        $db = TestDatabase::fresh();
        $input = $this->input();
        $year = (int) (new \DateTimeImmutable(
            'now',
            new \DateTimeZone('Europe/Madrid')
        ))->format('Y');
        $input['num_visible'] = sprintf('A%d/000123', $year);
        $input['issue_date'] = sprintf('%d-03-15 10:00:00', $year);

        $exception = Assert::throws(
            SifException::class,
            fn (): array => $this->service($db)->importHistoricalInvoice($input),
            409
        );

        Assert::stringContainsString('no fiscal sequence checkpoint', $exception->getMessage());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM fiscal_sequence')->fetchColumn());
    }

    public function testRejectsHistoricalNumberAheadOfActiveFiscalSequence(): void
    {
        $db = TestDatabase::fresh();
        $db->prepare(
            'INSERT INTO fiscal_sequence (TIPUS_SERIE, ANY_FACT, LAST_NUM) VALUES (?, ?, ?)'
        )->execute(['A', 2024, 100]);

        $exception = Assert::throws(
            SifException::class,
            fn (): array => $this->service($db)->importHistoricalInvoice($this->input()),
            409
        );

        Assert::stringContainsString('active fiscal sequence', $exception->getMessage());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(100, (int) $db->query(
            "SELECT LAST_NUM FROM fiscal_sequence WHERE TIPUS_SERIE = 'A' AND ANY_FACT = 2024"
        )->fetchColumn());
    }

    public function testAllowsHistoricalNumberBehindActiveFiscalSequenceWithoutMutatingIt(): void
    {
        $db = TestDatabase::fresh();
        $db->prepare(
            'INSERT INTO fiscal_sequence (TIPUS_SERIE, ANY_FACT, LAST_NUM) VALUES (?, ?, ?)'
        )->execute(['A', 2024, 200]);

        $result = $this->service($db)->importHistoricalInvoice($this->input());

        Assert::same(false, $result['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(200, (int) $db->query(
            "SELECT LAST_NUM FROM fiscal_sequence WHERE TIPUS_SERIE = 'A' AND ANY_FACT = 2024"
        )->fetchColumn());
    }

    public function testIdempotentRetryStillWorksAfterSequenceBecomesActive(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $first = $service->importHistoricalInvoice($this->input());

        $db->prepare(
            'INSERT INTO fiscal_sequence (TIPUS_SERIE, ANY_FACT, LAST_NUM) VALUES (?, ?, ?)'
        )->execute(['A', 2024, 100]);

        $second = $service->importHistoricalInvoice($this->input());

        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
    }

    private function service(\PDO $db): HistoricalInvoiceMigrationService
    {
        return new HistoricalInvoiceMigrationService(
            new TransactionRunner($db),
            new HistoricalInvoicePayloadBuilder(),
            new HistoricalInvoiceMigrationRepository(new UuidGenerator())
        );
    }

    private function input(): array
    {
        return [
            'num_visible' => 'A2024/000123',
            'issue_date' => '2024-03-15 10:00:00',
            'payment_status' => 'PAID',
            'legacy_id' => 9123,
            'factura_relacionada' => 700,
            'created_by' => 'uc011-governance-test',
            'actor_type' => 'PROCESS',
            'actor_role' => 'MIGRATION_OPERATOR',
            'request_id' => 'req-uc011-9123',
            'correlation_id' => 'corr-uc011-9123',
            'billing' => [
                'name' => 'Client Historic',
                'nif' => '12345678Z',
                'country' => 'ES',
            ],
            'totals' => [
                'import_base' => '100.00',
                'discount' => '0.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '100.00',
            ],
            'lines' => [[
                'concept' => 'Factura historica',
                'quantity' => '1.00',
                'unit_price' => '100.00',
                'base' => '100.00',
                'import_base' => '100.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '100.00',
                'source_type' => 'HISTORIC_WEB_FACTURES',
                'source_id' => 9123,
            ]],
        ];
    }
}
