<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyPackSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyPackInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysPackInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysPackInvoiceServiceTest
{
    public function testIssuesInvoiceAndPaymentFromValidatedRedsysPackNotification(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysPackLegacySpyPdo(array_merge(
            $this->legacyPackRows(),
            $this->legacyPackRows()
        ));
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($notifications, $sifDb);

        $notifications->recordReceived(
            $sifDb,
            'ORDERPACK910',
            910,
            '210.00',
            '0000',
            true,
            ['source' => 'pack-test'],
            'VALIDATED'
        );

        $first = $service->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDERPACK910');
        $second = $service->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDERPACK910');

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $first['uuid_payment']);
        Assert::same('PAID', $first['legacy_sync']['estat_cobrament']);
        Assert::same(3, count($first['legacy_sync']['relations']));
        Assert::same('PACK', $first['legacy_sync']['relations'][0]['source_type']);
        Assert::same(77, $first['legacy_sync']['relations'][0]['source_id']);
        Assert::same('INSCRIPCIO', $first['legacy_sync']['relations'][1]['source_type']);
        Assert::same(501, $first['legacy_sync']['relations'][1]['source_id']);
        Assert::same('INSCRIPCIO', $first['legacy_sync']['relations'][2]['source_type']);
        Assert::same(502, $first['legacy_sync']['relations'][2]['source_id']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $sifDb->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(8, count($legacyDb->preparedSql));

        $invoice = $sifDb->query('SELECT IDEMPOTENCY_KEY, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $discountLine = $sifDb->query('SELECT DESC_ORIGEN, DESC_PCT, DESC_IMPORT, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia WHERE ORDRE = 2')
            ->fetch(\PDO::FETCH_ASSOC);
        $packRelation = $sifDb->query('SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, DS_ORDER FROM fact_rels WHERE SOURCE_TYPE = \'PACK\'')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $sifDb->query('SELECT METODE, IMPORT, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REDSYS|PACK|IDPAG:910|ORDER:ORDERPACK910', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('210.00', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('PACK', $discountLine['DESC_ORIGEN']);
        Assert::same('25.00', $discountLine['DESC_PCT']);
        Assert::same('30.00', $discountLine['DESC_IMPORT']);
        Assert::same('90.00', $discountLine['TOTAL']);
        Assert::same('INSCRIPCIO', $discountLine['SOURCE_TYPE']);
        Assert::same(502, (int) $discountLine['SOURCE_ID']);
        Assert::same('PACK', $packRelation['SOURCE_TYPE']);
        Assert::same(77, (int) $packRelation['SOURCE_ID']);
        Assert::same(910, (int) $packRelation['IDPAG']);
        Assert::same('ORDERPACK910', $packRelation['DS_ORDER']);
        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('210.00', $payment['IMPORT']);
        Assert::same('ORDERPACK910', $payment['DS_ORDER']);
        Assert::same(910, (int) $payment['IDPAG']);
    }

    public function testRejectsNonValidatedNotificationBeforeLoadingLegacySnapshot(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysPackLegacySpyPdo([]);
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $sifDb,
            'ORDERPACK911',
            911,
            '210.00',
            '0101',
            true,
            ['source' => 'pack-test'],
            'ERROR'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb, $notifications): void {
            $this->service($notifications, $sifDb)->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDERPACK911');
        }, 409);

        Assert::same([], $legacyDb->preparedSql);
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    private function service(RedsysNotificationRepository $notifications, \PDO $sifDb): RedsysPackInvoiceService
    {
        return new RedsysPackInvoiceService(
            $notifications,
            new LegacyPackSnapshotRepository(),
            new LegacyPackInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($sifDb)
        );
    }

    private function legacyPackRows(): array
    {
        return [
            [
                $this->legacyInscription(501, '06', 'ABC', '120.00', 901),
                $this->legacyInscription(502, '07', 'DEF', '90.00', 902),
            ],
            [
                'ID_PACK' => 77,
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
            'IDPAG' => 910,
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
            'OBSERVACIONS' => 'alta PACK|77',
            'pag_observacions' => '',
        ];
    }
}

final class RedsysPackLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new RedsysPackLegacySpyStatement($this);
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

final class RedsysPackLegacySpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private RedsysPackLegacySpyPdo $db)
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
