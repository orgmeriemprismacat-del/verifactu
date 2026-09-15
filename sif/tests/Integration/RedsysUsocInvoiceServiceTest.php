<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysUsocInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysUsocInvoiceServiceTest
{
    public function testSnapshotEntryRejectsMissingEntityAmountBeforeBuildingPayload(): void
    {
        $sifDb = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($notifications, $sifDb);

        $exception = Assert::throws(SifException::class, static function () use ($sifDb, $service): void {
            $service->issueFromIntentSnapshot($sifDb, 'ORDERUSOCINVALID', ['usoc' => []]);
        }, 422);

        Assert::same('Invalid Redsys USOC entity amount snapshot', $exception->getMessage());
    }

    public function testIssuesStudentInvoiceAndPaymentFromValidatedNotification(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysUsocLegacySpyPdo([
            $this->inscriptionRow(),
            $this->courseRow(),
            $this->inscriptionRow(),
            $this->courseRow(),
        ]);
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($notifications, $sifDb);

        $notifications->recordReceived(
            $sifDb,
            'ORDERUSOC980',
            980,
            '75.00',
            '0000',
            true,
            ['source' => 'usoc-test'],
            'VALIDATED'
        );

        $first = $service->issueStudentFromValidatedNotification($sifDb, $legacyDb, 'ORDERUSOC980', '25.00');
        $second = $service->issueStudentFromValidatedNotification($sifDb, $legacyDb, 'ORDERUSOC980', '25.00');

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same('PAID', $first['legacy_sync']['estat_cobrament']);
        Assert::same(true, $first['entity_invoice_pending']['requires_explicit_billing']);
        Assert::same('25.00', $first['entity_invoice_pending']['entity_amount']);
        Assert::same($first['uuid_factura'], $first['entity_invoice_pending']['student_invoice_uuid']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $invoice = $sifDb->query('SELECT IDEMPOTENCY_KEY, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $line = $sifDb->query('SELECT CONCEPTE, DESC_ORIGEN, DESC_IMPORT, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia')
            ->fetch(\PDO::FETCH_ASSOC);
        $relation = $sifDb->query('SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, DS_ORDER, VISIBLE_ALUMNE FROM fact_rels')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $sifDb->query('SELECT METODE, IMPORT, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REDSYS|USOC_ALUMNE|IDPAG:980|ORDER:ORDERUSOC980', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('75.00', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('Comunicacio assertiva', $line['CONCEPTE']);
        Assert::same('USOC', $line['DESC_ORIGEN']);
        Assert::same('25.00', $line['DESC_IMPORT']);
        Assert::same('75.00', $line['TOTAL']);
        Assert::same('INSCRIPCIO', $line['SOURCE_TYPE']);
        Assert::same(880, (int) $line['SOURCE_ID']);
        Assert::same('INSCRIPCIO', $relation['SOURCE_TYPE']);
        Assert::same(880, (int) $relation['SOURCE_ID']);
        Assert::same(980, (int) $relation['IDPAG']);
        Assert::same('ORDERUSOC980', $relation['DS_ORDER']);
        Assert::same(1, (int) $relation['VISIBLE_ALUMNE']);
        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('75.00', $payment['IMPORT']);
        Assert::same('ORDERUSOC980', $payment['DS_ORDER']);
        Assert::same(980, (int) $payment['IDPAG']);
    }

    public function testRejectsMissingUsocEntityAmountBeforeLoadingLegacy(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysUsocLegacySpyPdo([]);
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $sifDb,
            'ORDERUSOC981',
            981,
            '75.00',
            '0000',
            true,
            ['source' => 'usoc-test'],
            'VALIDATED'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb, $notifications): void {
            $this->service($notifications, $sifDb)
                ->issueStudentFromValidatedNotification($sifDb, $legacyDb, 'ORDERUSOC981', null);
        }, 422);

        Assert::same([], $legacyDb->preparedSql);
    }

    public function testRejectsNonValidatedNotificationBeforeLoadingLegacy(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysUsocLegacySpyPdo([]);
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $sifDb,
            'ORDERUSOC982',
            982,
            '75.00',
            '0101',
            true,
            ['source' => 'usoc-test'],
            'ERROR'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb, $notifications): void {
            $this->service($notifications, $sifDb)
                ->issueStudentFromValidatedNotification($sifDb, $legacyDb, 'ORDERUSOC982', '25.00');
        }, 409);

        Assert::same([], $legacyDb->preparedSql);
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    private function service(RedsysNotificationRepository $notifications, \PDO $sifDb): RedsysUsocInvoiceService
    {
        return new RedsysUsocInvoiceService(
            $notifications,
            new LegacyUsocSnapshotRepository(),
            new LegacyUsocInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($sifDb)
        );
    }

    private function inscriptionRow(): array
    {
        return [
            'ID' => 880,
            'ANY' => 2026,
            'MES' => '06',
            'CURS' => 'COM',
            'NOM' => 'Alumna',
            'COGNOMS' => 'USOC',
            'DNI' => '12345678Z',
            'CORREU' => 'alumna@example.test',
            'ADRECA' => 'Carrer Alumna 10',
            'Codi_Postal' => '08002',
            'Poblacio' => 'Barcelona',
            'FACTURA_RELACIONADA' => 1880,
            'A_PAGAR' => '75.00',
            'PAGAMENT' => 1,
            'IDPAG' => 980,
            'TIPUS_DESC' => 4,
            'VALID_DESC' => 1,
            'FRACCIO' => 0,
            'FRACCIONAT' => 0,
        ];
    }

    private function courseRow(): array
    {
        return [
            'NOM_CURS' => 'Comunicacio assertiva',
            'DATAI' => '2026-06-10',
            'DATAF' => '2026-06-20',
            'HORES' => 12,
        ];
    }
}

final class RedsysUsocLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new RedsysUsocLegacySpyStatement($this);
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

final class RedsysUsocLegacySpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private RedsysUsocLegacySpyPdo $db)
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
