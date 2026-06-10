<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Service\ManualGiftInvoicePayloadBuilder;
use Prisma\Sif\Service\ManualGiftInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualGiftInvoiceServiceTest
{
    public function testIssuesInvoiceAndPaymentFromManualGiftTransferById(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new ManualGiftLegacySpyPdo([$this->giftRow(), $this->giftRow()]);
        $service = $this->service($sifDb);
        $input = [
            'amount' => '120.00',
            'movement_date' => '2026-06-10 10:15:00',
            'reference' => 'TRFGIFT77',
            'bank' => 'CAIXA',
            'notes' => 'Regal validat manualment a Passar pagaments',
        ];

        $first = $service->issueByGiftIdFromManualPayment($legacyDb, 77, $input);
        $second = $service->issueByGiftIdFromManualPayment($legacyDb, 77, $input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $first['uuid_payment']);
        Assert::same('PAID', $first['legacy_sync']['estat_cobrament']);
        Assert::same(1, count($first['legacy_sync']['relations']));
        Assert::same('REGAL', $first['legacy_sync']['relations'][0]['source_type']);
        Assert::same(77, $first['legacy_sync']['relations'][0]['source_id']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $invoice = $sifDb->query('SELECT IDEMPOTENCY_KEY, SOURCE_CHANNEL, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $relation = $sifDb->query('SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, VISIBLE_ALUMNE FROM fact_rels')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $sifDb->query('SELECT IDEMPOTENCY_KEY, METODE, SOURCE_CHANNEL, IMPORT, PROVIDER_REF, IDPAG, REFERENCIA_BANCARIA FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('TRANSFERENCIA|REGAL|ID:77|REF:TRFGIFT77', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('120.00', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('REGAL', $relation['SOURCE_TYPE']);
        Assert::same(77, (int) $relation['SOURCE_ID']);
        Assert::same(null, $relation['IDPAG']);
        Assert::same(0, (int) $relation['VISIBLE_ALUMNE']);
        Assert::same('PAYMENT|TRANSFERENCIA|REGAL|ID:77|REF:TRFGIFT77', $payment['IDEMPOTENCY_KEY']);
        Assert::same('TRANSFERENCIA', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('120.00', $payment['IMPORT']);
        Assert::same('TRFGIFT77', $payment['PROVIDER_REF']);
        Assert::same(null, $payment['IDPAG']);
        Assert::same('TRFGIFT77', $payment['REFERENCIA_BANCARIA']);
    }

    public function testIssuesInvoiceAndPaymentFromManualGiftTransferByCode(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new ManualGiftLegacySpyPdo([$this->giftRow()]);

        $result = $this->service($sifDb)->issueByGiftCodeFromManualPayment($legacyDb, 'REGAL-77', [
            'amount' => '120.00',
            'movement_date' => '2026-06-10',
            'reference' => 'TRFGIFT-CODE',
        ]);

        Assert::same(true, $result['ok']);
        Assert::stringContainsString('WHERE CODI = ?', $legacyDb->preparedSql[0]);
        Assert::same([['REGAL-77']], $legacyDb->executedParams);
    }

    public function testRejectsInvalidGiftIdBeforeLoadingLegacySnapshot(): void
    {
        $legacyDb = new ManualGiftLegacySpyPdo([]);

        Assert::throws(SifException::class, function () use ($legacyDb): void {
            $this->service(TestDatabase::fresh())->issueByGiftIdFromManualPayment(
                $legacyDb,
                0,
                [
                    'amount' => '120.00',
                    'movement_date' => '2026-06-10',
                ]
            );
        }, 422);

        Assert::same([], $legacyDb->preparedSql);
    }

    public function testRejectsAmountMismatchBeforeIssuingInvoice(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new ManualGiftLegacySpyPdo([$this->giftRow()]);

        Assert::throws(SifException::class, function () use ($legacyDb, $sifDb): void {
            $this->service($sifDb)->issueByGiftIdFromManualPayment(
                $legacyDb,
                77,
                [
                    'amount' => '100.00',
                    'movement_date' => '2026-06-10',
                ]
            );
        }, 422);

        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    private function service(\PDO $sifDb): ManualGiftInvoiceService
    {
        return new ManualGiftInvoiceService(
            new LegacyGiftSnapshotRepository(),
            new ManualGiftInvoicePayloadBuilder(),
            IssueInvoiceTest::serviceFor($sifDb)
        );
    }

    private function giftRow(): array
    {
        return [
            'ID' => 77,
            'NOM_CURS' => 'Comunicacio assertiva',
            'CCURS' => 'COM',
            'NOMC' => 'Compradora Regal',
            'NIFC' => '55555555R',
            'MAILC' => 'compradora@example.test',
            'ADRECAC' => 'Carrer Regal 5',
            'POBLEC' => 'Barcelona',
            'CPC' => '08005',
            'CODI' => 'REGAL-77',
            'IMPORT' => '120.00',
            'FACT_REL' => 0,
            'ORIGEN' => 'Compradora Regal',
            'DESTI' => 'Destinatari Regal',
            'OBSERVACIONS' => 'Dedicatoria comercial',
        ];
    }
}

final class ManualGiftLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new ManualGiftLegacySpyStatement($this);
    }

    public function nextRow(): mixed
    {
        if ($this->rows === []) {
            return false;
        }

        return array_shift($this->rows);
    }

    public function recordParams(?array $params): void
    {
        $this->executedParams[] = $params ?? [];
    }
}

final class ManualGiftLegacySpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private ManualGiftLegacySpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->db->recordParams($params);
        $this->row = $this->db->nextRow();

        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $row = $this->row;
        $this->row = false;

        return $row;
    }
}
