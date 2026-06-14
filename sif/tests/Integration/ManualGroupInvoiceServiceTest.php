<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGroupSnapshotRepository;
use Prisma\Sif\Service\ManualGroupInvoicePayloadBuilder;
use Prisma\Sif\Service\ManualGroupInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualGroupInvoiceServiceTest
{
    public function testIssuesInvoiceAndPaymentFromManualGroupTransfer(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new ManualGroupLegacySpyPdo(array_merge(
            $this->legacyGroupRows(),
            $this->legacyGroupRows()
        ));
        $service = $this->service($sifDb);
        $input = [
            'amount' => '200.00',
            'movement_date' => '2026-06-11 09:15:00',
            'reference' => 'TRFGRUP970',
            'bank' => 'CAIXA',
            'notes' => 'Grup validat manualment a Passar pagaments',
        ];

        $first = $service->issueFromLegacyGroupPayment($legacyDb, 970, $input);
        $second = $service->issueFromLegacyGroupPayment($legacyDb, 970, $input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $first['uuid_payment']);
        Assert::same('PAID', $first['legacy_sync']['estat_cobrament']);
        Assert::same(3, count($first['legacy_sync']['relations']));
        Assert::same('GRUP', $first['legacy_sync']['relations'][0]['source_type']);
        Assert::same(970, $first['legacy_sync']['relations'][0]['source_id']);
        Assert::same(970, $first['legacy_sync']['relations'][0]['idpag']);
        Assert::same('INSCRIPCIO', $first['legacy_sync']['relations'][1]['source_type']);
        Assert::same(771, $first['legacy_sync']['relations'][1]['source_id']);
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

        Assert::same('TRANSFERENCIA|GRUP|IDPAG:970|REF:TRFGRUP970', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('200.00', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('PAYMENT|TRANSFERENCIA|GRUP|IDPAG:970|REF:TRFGRUP970', $payment['IDEMPOTENCY_KEY']);
        Assert::same('TRANSFERENCIA', $payment['METODE']);
        Assert::same('INTRANET', $payment['SOURCE_CHANNEL']);
        Assert::same('200.00', $payment['IMPORT']);
        Assert::same('TRFGRUP970', $payment['PROVIDER_REF']);
        Assert::same(970, (int) $payment['IDPAG']);
        Assert::same('TRFGRUP970', $payment['REFERENCIA_BANCARIA']);
    }

    public function testRejectsInvalidIdpagBeforeLoadingLegacySnapshot(): void
    {
        $legacyDb = new ManualGroupLegacySpyPdo([]);

        Assert::throws(SifException::class, function () use ($legacyDb): void {
            $this->service(TestDatabase::fresh())->issueFromLegacyGroupPayment(
                $legacyDb,
                0,
                [
                    'amount' => '200.00',
                    'movement_date' => '2026-06-11',
                ]
            );
        }, 422);

        Assert::same([], $legacyDb->preparedSql);
    }

    private function service(\PDO $sifDb): ManualGroupInvoiceService
    {
        return new ManualGroupInvoiceService(
            new LegacyGroupSnapshotRepository(),
            new ManualGroupInvoicePayloadBuilder(),
            IssueInvoiceTest::serviceFor($sifDb)
        );
    }

    private function legacyGroupRows(): array
    {
        return [
            [
                $this->legacyInscription(771, 'Anna', 'Participant', '120.00', null, 9701),
                $this->legacyInscription(772, 'Biel', 'Participant', '80.00', '20.00', 9702),
            ],
            [
                'NOM' => 'Responsable',
                'COGNOMS' => 'Grup',
                'DNI' => '44444444G',
                'CORREU' => 'resp@example.test',
                'ADRECA' => 'Carrer Grup 4',
                'Codi_Postal' => '08004',
                'Poblacio' => 'Barcelona',
            ],
            [
                'NOM_CURS' => 'Comunicacio assertiva',
                'DATAI' => '2026-06-10',
                'DATAF' => '2026-06-20',
                'HORES' => '12',
            ],
            [
                'NOM_CURS' => 'Comunicacio assertiva',
                'DATAI' => '2026-06-10',
                'DATAF' => '2026-06-20',
                'HORES' => '12',
            ],
        ];
    }

    private function legacyInscription(
        int $id,
        string $name,
        string $surname,
        string $amount,
        ?string $discount,
        int $facturaRelacionada
    ): array {
        $row = [
            'ID' => $id,
            'IDPAG' => 970,
            'ANY' => 2026,
            'MES' => '06',
            'CURS' => 'COM',
            'Grup' => 'A',
            'TIPUS_INSC' => 'G',
            'NOM' => $name,
            'COGNOMS' => $surname,
            'DNI' => $id . 'G',
            'CORREU' => strtolower($name) . '@example.test',
            'FACTURA_RELACIONADA' => $facturaRelacionada,
            'A_PAGAR' => $amount,
            'INSC CURS' => '1',
            'PAGAMENT' => '0.00',
            'FRACCIO' => 0,
            'FRACCIONAT' => 0,
            'pag_observacions' => '',
        ];

        if ($discount !== null) {
            $row['IMPORT_BASE'] = '100.00';
            $row['DESC_IMPORT'] = $discount;
            $row['DESC_ORIGEN'] = 'GRUP';
            $row['DESC_TEXT'] = 'Descompte grup';
        }

        return $row;
    }
}

final class ManualGroupLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new ManualGroupLegacySpyStatement($this);
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

final class ManualGroupLegacySpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private ManualGroupLegacySpyPdo $db)
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
