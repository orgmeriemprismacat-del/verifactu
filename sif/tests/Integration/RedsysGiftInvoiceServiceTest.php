<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyGiftInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysGiftInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysGiftInvoiceServiceTest
{
    public function testSnapshotEntryRejectsMissingGiftIdBeforeNotificationLookup(): void
    {
        $sifDb = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($notifications, $sifDb);

        $exception = Assert::throws(SifException::class, static function () use ($sifDb, $service): void {
            $service->issueFromIntentSnapshot($sifDb, 'ORDERGIFTINVALID', ['gift' => []]);
        }, 422);

        Assert::same('Invalid Redsys gift snapshot ID', $exception->getMessage());
    }

    public function testIssuesGiftInvoiceAndPaymentFromValidatedNotificationById(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysGiftLegacySpyPdo([$this->giftRow(), $this->giftRow()]);
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($notifications, $sifDb);

        $notifications->recordReceived(
            $sifDb,
            'ORDERGIFT77',
            null,
            '120.00',
            '0000',
            true,
            ['source' => 'gift-test'],
            'VALIDATED'
        );

        $first = $service->issueByGiftIdFromValidatedNotification($sifDb, $legacyDb, 'ORDERGIFT77', 77);
        $second = $service->issueByGiftIdFromValidatedNotification($sifDb, $legacyDb, 'ORDERGIFT77', 77);

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

        $invoice = $sifDb->query('SELECT IDEMPOTENCY_KEY, TOTAL, ESTAT_COBRAMENT FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $line = $sifDb->query('SELECT CONCEPTE, TOTAL, SOURCE_TYPE, SOURCE_ID FROM factura_linia')
            ->fetch(\PDO::FETCH_ASSOC);
        $relation = $sifDb->query('SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, DS_ORDER, VISIBLE_ALUMNE FROM fact_rels')
            ->fetch(\PDO::FETCH_ASSOC);
        $payment = $sifDb->query('SELECT METODE, IMPORT, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REDSYS|REGAL|IDPAG:NULL|ORDER:ORDERGIFT77', $invoice['IDEMPOTENCY_KEY']);
        Assert::same('120.00', $invoice['TOTAL']);
        Assert::same('PAID', $invoice['ESTAT_COBRAMENT']);
        Assert::same('Curs regal Comunicacio assertiva', $line['CONCEPTE']);
        Assert::same('120.00', $line['TOTAL']);
        Assert::same('REGAL', $line['SOURCE_TYPE']);
        Assert::same(77, (int) $line['SOURCE_ID']);
        Assert::same('REGAL', $relation['SOURCE_TYPE']);
        Assert::same(77, (int) $relation['SOURCE_ID']);
        Assert::same(null, $relation['IDPAG']);
        Assert::same('ORDERGIFT77', $relation['DS_ORDER']);
        Assert::same(0, (int) $relation['VISIBLE_ALUMNE']);
        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('120.00', $payment['IMPORT']);
        Assert::same('ORDERGIFT77', $payment['DS_ORDER']);
        Assert::same(null, $payment['IDPAG']);
    }

    public function testIssuesGiftInvoiceFromValidatedNotificationByCode(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysGiftLegacySpyPdo([$this->giftRow()]);
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $sifDb,
            'ORDERGIFT78',
            null,
            '120.00',
            '0000',
            true,
            ['source' => 'gift-test'],
            'VALIDATED'
        );

        $result = $this->service($notifications, $sifDb)
            ->issueByGiftCodeFromValidatedNotification($sifDb, $legacyDb, 'ORDERGIFT78', 'REGAL-77');

        Assert::same(true, $result['ok']);
        Assert::stringContainsString('WHERE CODI = ?', $legacyDb->preparedSql[0]);
        Assert::same([['REGAL-77']], $legacyDb->executedParams);
    }

    public function testRejectsNonValidatedNotificationBeforeLoadingLegacyGift(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysGiftLegacySpyPdo([]);
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $sifDb,
            'ORDERGIFT79',
            null,
            '120.00',
            '0101',
            true,
            ['source' => 'gift-test'],
            'ERROR'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb, $notifications): void {
            $this->service($notifications, $sifDb)
                ->issueByGiftIdFromValidatedNotification($sifDb, $legacyDb, 'ORDERGIFT79', 77);
        }, 409);

        Assert::same([], $legacyDb->preparedSql);
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRejectsAmountMismatchBeforeIssuingGiftInvoice(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysGiftLegacySpyPdo([$this->giftRow()]);
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $sifDb,
            'ORDERGIFT80',
            null,
            '100.00',
            '0000',
            true,
            ['source' => 'gift-test'],
            'VALIDATED'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb, $notifications): void {
            $this->service($notifications, $sifDb)
                ->issueByGiftIdFromValidatedNotification($sifDb, $legacyDb, 'ORDERGIFT80', 77);
        }, 409);

        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    private function service(RedsysNotificationRepository $notifications, \PDO $sifDb): RedsysGiftInvoiceService
    {
        return new RedsysGiftInvoiceService(
            $notifications,
            new LegacyGiftSnapshotRepository(),
            new LegacyGiftInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
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

final class RedsysGiftLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new RedsysGiftLegacySpyStatement($this);
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

final class RedsysGiftLegacySpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private RedsysGiftLegacySpyPdo $db)
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
