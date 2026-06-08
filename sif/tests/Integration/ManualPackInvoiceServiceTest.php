<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyPackSnapshotRepository;
use Prisma\Sif\Service\ManualPackInvoicePayloadBuilder;
use Prisma\Sif\Service\ManualPackInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualPackInvoiceServiceTest
{
    public function testIssuesInvoiceAndPaymentFromManualPackTransfer(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new ManualPackLegacySpyPdo(array_merge(
            $this->legacyPackRows(),
            $this->legacyPackRows()
        ));
        $service = $this->service($sifDb);
        $input = [
            'amount' => '210.00',
            'movement_date' => '2026-06-08 10:15:00',
            'reference' => 'TRFPACK930',
            'bank' => 'CAIXA',
            'notes' => 'Pack validat manualment a Passar pagaments',
        ];

        $first = $service->issueFromLegacyPackPayment($legacyDb, 930, $input);
        $second = $service->issueFromLegacyPackPayment($legacyDb, 930, $input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $first['uuid_payment']);
        Assert::same('PAID', $first['legacy_sync']['estat_cobrament']);
        Assert::same(3, count($first['legacy_sync']['relations']));
        Assert::same('PACK', $first['legacy_sync']['relations'][0]['source_type']);
        Assert::same(88, $first['legacy_sync']['relations'][0]['source_id']);
        Assert::same(930, $first['legacy_sync']['relations'][0]['idpag']);
        Assert::same('INSCRIPCIO', $first['legacy_sync']['relations'][1]['source_type']);
        Assert::same(531, $first['legacy_sync']['relations'][1]['source_id']);
        Assert::same(930, $first['legacy_sync']['relations'][1]['idpag']);
        Assert::same('INSCRIPCIO', $first['legacy_sync']['relations'][2]['source_type']);
        Assert::same(532, $first['legacy_sync']['relations'][2]['source_id']);
        Assert::same(930, $first['legacy_sync']['relations'][2]['idpag']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $sifDb->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(8, count($legacyDb->preparedSql));

        $invoice = $sifDb->query('SELECT IDEMPOTENCY_KEY, SOURCE_CHANNEL, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $sifDb->query('SELECT IDEMPOTENCY_KEY, METODE, SOURCE_CHANNEL, IMPORT, PROVIDER_REF, IDPAG, REFERENCIA_BANCARIA FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('TRANSFERENCIA|PACK|IDPAG:930|REF:TRFPACK930', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('210.00', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('PAYMENT|TRANSFERENCIA|PACK|IDPAG:930|REF:TRFPACK930', $payment['IDEMPOTENCY_KEY']);
        Assert::same('TRANSFERENCIA', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('210.00', $payment['IMPORT']);
        Assert::same('TRFPACK930', $payment['PROVIDER_REF']);
        Assert::same(930, (int) $payment['IDPAG']);
        Assert::same('TRFPACK930', $payment['REFERENCIA_BANCARIA']);
    }

    public function testRejectsInvalidIdpagBeforeLoadingLegacySnapshot(): void
    {
        $legacyDb = new ManualPackLegacySpyPdo([]);

        Assert::throws(SifException::class, function () use ($legacyDb): void {
            $this->service(TestDatabase::fresh())->issueFromLegacyPackPayment(
                $legacyDb,
                0,
                [
                    'amount' => '210.00',
                    'movement_date' => '2026-06-08',
                ]
            );
        }, 422);

        Assert::same([], $legacyDb->preparedSql);
    }

    private function service(\PDO $sifDb): ManualPackInvoiceService
    {
        return new ManualPackInvoiceService(
            new LegacyPackSnapshotRepository(),
            new ManualPackInvoicePayloadBuilder(),
            IssueInvoiceTest::serviceFor($sifDb)
        );
    }

    private function legacyPackRows(): array
    {
        return [
            [
                $this->legacyInscription(531, '06', 'ABC', '120.00', 931),
                $this->legacyInscription(532, '07', 'DEF', '90.00', 932),
            ],
            [
                'ID_PACK' => 88,
                'TITOL' => 'Benestar docent',
                'CODI' => 'BDOC',
            ],
            [
                'NOM_CURS' => 'Gestio emocional',
                'DATAI' => '2026-06-10',
                'DATAF' => '2026-06-20',
                'HORES' => '12',
            ],
            [
                'NOM_CURS' => 'Mindfulness a l aula',
                'DATAI' => '2026-07-10',
                'DATAF' => '2026-07-20',
                'HORES' => '12',
            ],
        ];
    }

    private function legacyInscription(int $id, string $month, string $course, string $amount, int $facturaRelacionada): array
    {
        return [
            'ID' => $id,
            'IDPAG' => 930,
            'ANY' => 2026,
            'MES' => $month,
            'CURS' => $course,
            'TIPUS_INSC' => 'P',
            'NOM' => 'Maria',
            'COGNOMS' => 'Exemple',
            'DNI' => '12345678Z',
            'CORREU' => 'maria@example.test',
            'ADRECA' => 'Carrer Exemple 1',
            'Codi_Postal' => '08001',
            'Poblacio' => 'Barcelona',
            'FACTURA_RELACIONADA' => $facturaRelacionada,
            'A_PAGAR' => $amount,
            'INSC CURS' => '1',
            'PAGAMENT' => '0.00',
            'FRACCIO' => 0,
            'FRACCIONAT' => 0,
            'OBSERVACIONS' => 'alta PACK|88',
            'pag_observacions' => '',
        ];
    }
}

final class ManualPackLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new ManualPackLegacySpyStatement($this);
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

final class ManualPackLegacySpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private ManualPackLegacySpyPdo $db)
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
        if (is_array($this->row) && array_is_list($this->row)) {
            return array_shift($this->row) ?: false;
        }

        $row = $this->row;
        $this->row = false;

        return $row;
    }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if (is_array($this->row) && array_is_list($this->row)) {
            return $this->row;
        }

        if (is_array($this->row)) {
            return [$this->row];
        }

        return [];
    }
}
