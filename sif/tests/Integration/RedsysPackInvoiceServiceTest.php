<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\LegacyPackSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyPackInvoicePayloadBuilder;
use Prisma\Sif\Service\PackEnrollmentFundAllocationService;
use Prisma\Sif\Service\PackPaymentNotificationService;
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

    public function testIntentSnapshotCreatesOneDurableNotificationAcrossRetry(): void
    {
        $sifDb = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();
        $service = new RedsysPackInvoiceService(
            $notifications,
            new LegacyPackSnapshotRepository(),
            new LegacyPackInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($sifDb),
            new PackPaymentNotificationService(
                new NotificationOutboxRepository(new UuidGenerator())
            ),
            new PackEnrollmentFundAllocationService(
                new EnrollmentFundMovementRepository(new UuidGenerator())
            )
        );

        $notifications->recordReceived(
            $sifDb,
            'ORDERPACKINTENT910',
            910,
            '210.00',
            '0000',
            true,
            ['source' => 'pack-intent-test'],
            'VALIDATED'
        );

        $snapshot = [
            'pack' => [
                'ID_PACK' => 77,
                'TITOL' => 'Benestar docent',
                'CODI' => 'BDOC',
            ],
            'payment' => [
                'idpag' => 910,
                'amount' => '210.00',
            ],
            'items' => [
                [
                    'ordinal' => 1,
                    'inscription' => $this->legacyInscription(501, '06', 'ABC', '120.00', 901) + [
                        'IMPORT_BASE' => '120.00',
                        'DESC_IMPORT' => '0.00',
                        'DESC_PCT' => '0.00',
                        'TOTAL' => '120.00',
                    ],
                    'course' => ['NOM_CURS' => 'Gestio emocional'],
                ],
                [
                    'ordinal' => 2,
                    'inscription' => $this->legacyInscription(502, '07', 'DEF', '90.00', 902) + [
                        'IMPORT_BASE' => '120.00',
                        'DESC_IMPORT' => '30.00',
                        'DESC_PCT' => '25.00',
                        'TOTAL' => '90.00',
                    ],
                    'course' => ['NOM_CURS' => 'Mindfulness a l aula'],
                ],
            ],
        ];

        $first = $service->issueFromIntentSnapshot($sifDb, 'ORDERPACKINTENT910', $snapshot);
        $second = $service->issueFromIntentSnapshot($sifDb, 'ORDERPACKINTENT910', $snapshot);

        Assert::same(false, $first['notification_outbox']['idempotency_reused']);
        Assert::same(true, $second['notification_outbox']['idempotency_reused']);
        Assert::same('PENDING', $first['notification_outbox']['status']);
        Assert::same('PACK_FULL_PAYMENT', $first['legacy_sync']['mode']);
        Assert::same(2, $first['fund_allocations']['count']);
        Assert::same('210.00', $first['fund_allocations']['amount']);
        Assert::same(2, $second['fund_allocations']['count']);
        Assert::same(true, $second['fund_allocations']['movements'][0]['idempotency_reused']);
        Assert::same(true, $second['fund_allocations']['movements'][1]['idempotency_reused']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
        Assert::same(2, (int) $sifDb->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
        Assert::same(
            '210.00',
            number_format(
                (float) $sifDb->query('SELECT SUM(IMPORT) FROM enrollment_fund_movement')->fetchColumn(),
                2,
                '.',
                ''
            )
        );

        $outbox = $sifDb->query(
            "SELECT IDEMPOTENCY_KEY, TEMPLATE_CODE, RECIPIENT_TYPE, UUID_FACTURA, UUID_PAYMENT, STATUS
             FROM notification_outbox"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('NOTIFY|PACK_PAYMENT_CONFIRMED|ORDER:ORDERPACKINTENT910', $outbox['IDEMPOTENCY_KEY']);
        Assert::same('PACK_PAYMENT_CONFIRMED', $outbox['TEMPLATE_CODE']);
        Assert::same('ALUMNE', $outbox['RECIPIENT_TYPE']);
        Assert::same($first['uuid_factura'], $outbox['UUID_FACTURA']);
        Assert::same($first['uuid_payment'], $outbox['UUID_PAYMENT']);
        Assert::same('PENDING', $outbox['STATUS']);

        $fundRows = $sifDb->query(
            "SELECT ID_INSC_DESTI, IMPORT, UUID_PAYMENT, UUID_FACTURA
             FROM enrollment_fund_movement
             ORDER BY ORDRE"
        )->fetchAll(\PDO::FETCH_ASSOC);
        Assert::same(501, (int) $fundRows[0]['ID_INSC_DESTI']);
        Assert::same('120.00', $fundRows[0]['IMPORT']);
        Assert::same(502, (int) $fundRows[1]['ID_INSC_DESTI']);
        Assert::same('90.00', $fundRows[1]['IMPORT']);
        Assert::same($first['uuid_payment'], $fundRows[0]['UUID_PAYMENT']);
        Assert::same($first['uuid_factura'], $fundRows[1]['UUID_FACTURA']);
    }

    public function testRejectsPackWhenValidatedRedsysAmountDiffersFromInvoiceLines(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysPackLegacySpyPdo($this->legacyPackRows());
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($notifications, $sifDb);

        $notifications->recordReceived(
            $sifDb,
            'ORDERPACKMISMATCH',
            910,
            '205.00',
            '0000',
            true,
            ['source' => 'pack-test'],
            'VALIDATED'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb, $service): void {
            $service->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDERPACKMISMATCH');
        }, 409);

        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRejectsLegacyPackWithoutCompleteCommercialSnapshot(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysPackLegacySpyPdo([
            [
                $this->legacyInscriptionWithoutSnapshot(501, '06', 'ABC', '120.00', 901),
                $this->legacyInscriptionWithoutSnapshot(502, '07', 'DEF', '90.00', 902),
            ],
            [
                'ID_PACK' => 77,
                'TITOL' => 'Benestar docent',
                'CODI' => 'BDOC',
            ],
        ]);
        $notifications = new RedsysNotificationRepository();

        $notifications->recordReceived(
            $sifDb,
            'ORDERPACKNOSNAPSHOT',
            910,
            '210.00',
            '0000',
            true,
            ['source' => 'pack-test'],
            'VALIDATED'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb, $notifications): void {
            $this->service($notifications, $sifDb)
                ->issueFromValidatedNotification($sifDb, $legacyDb, 'ORDERPACKNOSNAPSHOT');
        }, 409);

        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
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
                $this->legacyInscription(501, '06', 'ABC', '120.00', 901, 1, '120.00', '0.00', '0.00'),
                $this->legacyInscription(502, '07', 'DEF', '90.00', 902, 2, '120.00', '30.00', '25.00'),
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

    private function legacyInscription(
        int $id,
        string $month,
        string $course,
        string $amount,
        int $facturaRelacionada,
        int $ordinal = 1,
        ?string $base = null,
        string $discount = '0.00',
        string $discountPct = '0.00'
    ): array {
        $base ??= $amount;

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
            'OBSERVACIONS' => sprintf(
                'alta PACK|77 PACK_ORDINAL|%d PACK_BASE|%s PACK_DISCOUNT|%s PACK_DISCOUNT_PCT|%s PACK_TOTAL|%s',
                $ordinal,
                $base,
                $discount,
                $discountPct,
                $amount
            ),
            'pag_observacions' => '',
        ];
    }

    private function legacyInscriptionWithoutSnapshot(
        int $id,
        string $month,
        string $course,
        string $amount,
        int $facturaRelacionada
    ): array {
        $row = $this->legacyInscription($id, $month, $course, $amount, $facturaRelacionada);
        $row['OBSERVACIONS'] = 'alta PACK|77';

        return $row;
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
