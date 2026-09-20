<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalRecordRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\FiscalRecordPayloadBuilder;
use Prisma\Sif\Service\FiscalRecordService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class FiscalRecordServiceTest
{
    public function testCreatesIdempotentCancellationAndMarksInvoiceCancelled(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $input = ['reason' => 'FACTURA_EMESA_PER_ERROR', 'created_by' => 'adam'];

        $first = $this->service($db)->createCancellationByUuid($invoice['uuid_factura'], $input);
        $second = $this->service($db)->createCancellationByUuid($invoice['uuid_factura'], $input);

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same('ANULACIO', $first['record_type']);
        Assert::same(2, $first['fiscal_order']);
        Assert::same($first['hash'], $second['hash']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same('CANCELLED', (string) $db->query('SELECT ESTAT_FACTURA FROM factura')->fetchColumn());

        $record = $db->query('SELECT TIPUS_REGISTRE, PAYLOAD_JSON FROM factura_registres ORDER BY FISCAL_ORDER DESC LIMIT 1')
            ->fetch(\PDO::FETCH_ASSOC);
        $payload = json_decode($record['PAYLOAD_JSON'], true);
        Assert::same('ANULACIO', $record['TIPUS_REGISTRE']);
        Assert::same('RegistroAnulacion', $payload['aeat_record_type']);
        Assert::same('ALTA', $payload['previous_record']['tipus_registre']);
    }

    public function testCreatesSubsanationWithSameInvoiceIdentifier(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'FISCAL|SUBSANATION|ORIGINAL',
        ]));

        $result = $this->service($db)->createSubsanationByNumVisible($invoice['num_visible'], [
            'reason' => 'REBUIG_AEAT',
            'subsanation_kind' => 'RECHAZO_PREVIO',
            'correction_summary' => 'Correccio del camp de destinatari',
        ]);

        Assert::same('SUBSANACIO', $result['record_type']);
        Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
        Assert::same($invoice['num_visible'], $result['num_visible']);
        Assert::same('ISSUED', (string) $db->query('SELECT ESTAT_FACTURA FROM factura')->fetchColumn());

        $payload = json_decode(
            (string) $db->query('SELECT PAYLOAD_JSON FROM factura_registres ORDER BY FISCAL_ORDER DESC LIMIT 1')->fetchColumn(),
            true
        );
        Assert::same('Subsanacion', $payload['aeat_record_type']);
        Assert::same('RECHAZO_PREVIO', $payload['subsanation_kind']);
    }

    public function testRejectsHistoricalNoVerifactuInvoice(): void
    {
        $db = TestDatabase::fresh();
        $db->exec(
            "INSERT INTO factura (
                UUID_FACTURA, IDEMPOTENCY_KEY, TIPUS_SERIE, ANY_FACT, NUM_SEQ, NUM_VISIBLE,
                TIPUS_FACTURA, DATA_EMISSIO, ESTAT_COBRAMENT, ESTAT_FACTURA, ESTAT_AEAT,
                BILLING_NOM_RAO, BILLING_NIF_CIF, IMPORT_BASE, BASE_IMPOSABLE, TOTAL, SOURCE_CHANNEL
            ) VALUES (
                'historical-uuid', 'HISTORICAL|1', 'H', 2024, 1, 'H2024/000001',
                'F1', NOW(), 'PAID', 'HISTORICAL', 'NO_VERIFACTU',
                'Historic Client', '12345678Z', 10, 10, 10, 'MIGRATION'
            )"
        );

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->createCancellationByUuid('historical-uuid', ['reason' => 'ERROR']);
        }, 422);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
    }

    public function testRejectsDifferentSecondCancellation(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'FISCAL|DOUBLE|CANCELLATION',
        ]));
        $service = $this->service($db);
        $service->createCancellationByUuid($invoice['uuid_factura'], ['reason' => 'FIRST_REASON']);

        Assert::throws(SifException::class, function () use ($service, $invoice): void {
            $service->createCancellationByUuid($invoice['uuid_factura'], ['reason' => 'SECOND_REASON']);
        }, 409);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
    }

    public function testAcceptsAllSupportedSubsanationKinds(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'FISCAL|ALL|SUBSANATION|KINDS',
        ]));
        $service = $this->service($db);

        foreach (['SUBSANACION', 'RECHAZO_PREVIO', 'SIN_REGISTRO_PREVIO'] as $index => $kind) {
            $service->createSubsanationByUuid($invoice['uuid_factura'], [
                'reason' => 'CORRECTION_' . ($index + 1),
                'subsanation_kind' => $kind,
            ]);
        }

        $rows = $db->query(
            "SELECT PAYLOAD_JSON FROM factura_registres WHERE TIPUS_REGISTRE = 'SUBSANACIO' ORDER BY FISCAL_ORDER"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $kinds = array_map(static function (string $json): string {
            $payload = json_decode($json, true);

            return $payload['subsanation_kind'];
        }, $rows);

        Assert::same(['SUBSANACION', 'RECHAZO_PREVIO', 'SIN_REGISTRO_PREVIO'], $kinds);
        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
    }

    private function service(\PDO $db): FiscalRecordService
    {
        return new FiscalRecordService(
            new TransactionRunner($db),
            new ManualPaymentInvoiceRepository(),
            new FiscalRecordRepository(new HashCalculator()),
            new FiscalRecordPayloadBuilder()
        );
    }
}
