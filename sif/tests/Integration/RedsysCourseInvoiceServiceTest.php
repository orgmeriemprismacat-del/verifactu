<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysCourseInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysCourseInvoiceServiceTest
{
    public function testIssuesInvoiceAndPaymentFromValidatedRedsysCourseNotification(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysCourseLegacySpyPdo([
            $this->legacyInscription(),
            $this->legacyCourse(),
            $this->legacyInscription(),
            $this->legacyCourse(),
        ]);
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($notifications, $sifDb);

        $notifications->recordReceived(
            $sifDb,
            'ORDER400',
            400,
            '95.50',
            '0000',
            true,
            ['source' => 'test'],
            'VALIDATED'
        );

        $first = $service->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDER400');
        $second = $service->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDER400');

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $first['uuid_payment']);
        Assert::same('PAID', $first['legacy_sync']['estat_cobrament']);
        Assert::same('INSCRIPCIO', $first['legacy_sync']['relations'][0]['source_type']);
        Assert::same(410, $first['legacy_sync']['relations'][0]['source_id']);
        Assert::same(810, $first['legacy_sync']['relations'][0]['factura_relacionada']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(4, count($legacyDb->preparedSql));

        $invoice = $sifDb->query('SELECT IDEMPOTENCY_KEY, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $line = $sifDb->query('SELECT CONCEPTE, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $sifDb->query('SELECT METODE, IMPORT, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REDSYS|CURS|IDPAG:400|ORDER:ORDER400', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('95.50', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('Curs Llenguatge musical', $line['CONCEPTE']);
        Assert::same('95.50', $line['TOTAL']);
        Assert::same('INSCRIPCIO', $line['SOURCE_TYPE']);
        Assert::same(410, (int) $line['SOURCE_ID']);
        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('95.50', $payment['IMPORT']);
        Assert::same('ORDER400', $payment['DS_ORDER']);
        Assert::same(400, (int) $payment['IDPAG']);
    }

    public function testRejectsNonValidatedNotificationBeforeLoadingLegacySnapshot(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysCourseLegacySpyPdo([]);
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $sifDb,
            'ORDER401',
            401,
            '95.50',
            '0101',
            true,
            ['source' => 'test'],
            'ERROR'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb, $notifications): void {
            $this->service($notifications, $sifDb)->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDER401');
        }, 409);

        Assert::same([], $legacyDb->preparedSql);
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    private function service(RedsysNotificationRepository $notifications, \PDO $sifDb): RedsysCourseInvoiceService
    {
        return new RedsysCourseInvoiceService(
            $notifications,
            new LegacyCourseSnapshotRepository(),
            new LegacyCourseInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($sifDb)
        );
    }

    private function legacyInscription(): array
    {
        return [
            'ID' => 410,
            'ANY' => 2026,
            'MES' => '07',
            'CURS' => 'LM',
            'NOM' => 'Joan',
            'COGNOMS' => 'Mostra',
            'DNI' => '87654321Z',
            'CORREU' => 'joan@example.test',
            'ADRECA' => 'Carrer Musica 2',
            'Codi_Postal' => '08002',
            'Poblacio' => 'Barcelona',
            'FACTURA_RELACIONADA' => 810,
            'A_PAGAR' => '95.50',
            'INSC CURS' => '1',
            'PAGAMENT' => '0.00',
            'FRACCIO' => 0,
        ];
    }

    private function legacyCourse(): array
    {
        return [
            'NOM_CURS' => 'Llenguatge musical',
            'DATAI' => '2026-07-01',
            'DATAF' => '2026-07-31',
            'HORES' => '20',
        ];
    }
}

final class RedsysCourseLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new RedsysCourseLegacySpyStatement($this);
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

final class RedsysCourseLegacySpyStatement extends \PDOStatement
{
    public function __construct(private RedsysCourseLegacySpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->db->recordParams($params);

        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->db->nextRow();
    }
}
