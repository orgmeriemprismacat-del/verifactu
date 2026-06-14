<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGroupSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyGroupInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysGroupInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysGroupInvoiceServiceTest
{
    public function testIssuesInvoiceAndPaymentFromValidatedRedsysGroupNotification(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysGroupLegacySpyPdo(array_merge(
            $this->legacyGroupRows(),
            $this->legacyGroupRows()
        ));
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($notifications, $sifDb);

        $notifications->recordReceived(
            $sifDb,
            'ORDERGROUP950',
            950,
            '200.00',
            '0000',
            true,
            ['source' => 'group-test'],
            'VALIDATED'
        );

        $first = $service->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDERGROUP950');
        $second = $service->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDERGROUP950');

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $first['uuid_payment']);
        Assert::same('PAID', $first['legacy_sync']['estat_cobrament']);
        Assert::same(3, count($first['legacy_sync']['relations']));
        Assert::same('GRUP', $first['legacy_sync']['relations'][0]['source_type']);
        Assert::same(950, $first['legacy_sync']['relations'][0]['source_id']);
        Assert::same(950, $first['legacy_sync']['relations'][0]['idpag']);
        Assert::same(0, $first['legacy_sync']['relations'][0]['visible_alumne']);
        Assert::same('INSCRIPCIO', $first['legacy_sync']['relations'][1]['source_type']);
        Assert::same(751, $first['legacy_sync']['relations'][1]['source_id']);
        Assert::same(0, $first['legacy_sync']['relations'][1]['visible_alumne']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $sifDb->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(3, (int) $sifDb->query('SELECT COUNT(*) FROM fact_rels WHERE VISIBLE_ALUMNE = 0')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(8, count($legacyDb->preparedSql));

        $invoice = $sifDb->query('SELECT IDEMPOTENCY_KEY, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $discountLine = $sifDb->query('SELECT DESC_ORIGEN, DESC_IMPORT, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia WHERE ORDRE = 2')
            ->fetch(\PDO::FETCH_ASSOC);
        $groupRelation = $sifDb->query('SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, DS_ORDER, VISIBLE_ALUMNE FROM fact_rels WHERE SOURCE_TYPE = \'GRUP\'')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $sifDb->query('SELECT METODE, IMPORT, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REDSYS|GRUP|IDPAG:950|ORDER:ORDERGROUP950', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('200.00', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('GRUP', $discountLine['DESC_ORIGEN']);
        Assert::same('20.00', $discountLine['DESC_IMPORT']);
        Assert::same('80.00', $discountLine['TOTAL']);
        Assert::same('INSCRIPCIO', $discountLine['SOURCE_TYPE']);
        Assert::same(752, (int) $discountLine['SOURCE_ID']);
        Assert::same('GRUP', $groupRelation['SOURCE_TYPE']);
        Assert::same(950, (int) $groupRelation['SOURCE_ID']);
        Assert::same(950, (int) $groupRelation['IDPAG']);
        Assert::same('ORDERGROUP950', $groupRelation['DS_ORDER']);
        Assert::same(0, (int) $groupRelation['VISIBLE_ALUMNE']);
        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('200.00', $payment['IMPORT']);
        Assert::same('ORDERGROUP950', $payment['DS_ORDER']);
        Assert::same(950, (int) $payment['IDPAG']);
    }

    public function testRejectsNonValidatedNotificationBeforeLoadingLegacySnapshot(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysGroupLegacySpyPdo([]);
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $sifDb,
            'ORDERGROUP951',
            951,
            '200.00',
            '0101',
            true,
            ['source' => 'group-test'],
            'ERROR'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb, $notifications): void {
            $this->service($notifications, $sifDb)->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDERGROUP951');
        }, 409);

        Assert::same([], $legacyDb->preparedSql);
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    private function service(RedsysNotificationRepository $notifications, \PDO $sifDb): RedsysGroupInvoiceService
    {
        return new RedsysGroupInvoiceService(
            $notifications,
            new LegacyGroupSnapshotRepository(),
            new LegacyGroupInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($sifDb)
        );
    }

    private function legacyGroupRows(): array
    {
        return [
            [
                $this->legacyInscription(751, 'Anna', 'Participant', '11111111A', '120.00', null, 9501),
                $this->legacyInscription(752, 'Biel', 'Participant', '22222222B', '80.00', '20.00', 9502),
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
        string $dni,
        string $amount,
        ?string $discount,
        int $facturaRelacionada
    ): array {
        $row = [
            'ID' => $id,
            'IDPAG' => 950,
            'ANY' => 2026,
            'MES' => '06',
            'CURS' => 'COM',
            'Grup' => 'A',
            'TIPUS_INSC' => 'G',
            'NOM' => $name,
            'COGNOMS' => $surname,
            'DNI' => $dni,
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

final class RedsysGroupLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new RedsysGroupLegacySpyStatement($this);
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

final class RedsysGroupLegacySpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private RedsysGroupLegacySpyPdo $db)
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
