<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\RedsysCoursePaymentIntentService;
use Prisma\Sif\Service\RedsysDsOrderGenerator;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysCoursePaymentIntentServiceTest
{
    public function testCreatesIntentFromAuthoritativePendingBalance(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = $this->legacyDb(false);
        $service = $this->service();

        $result = $service->create($sifDb, $legacyDb, [
            'idpag' => 700,
            'requested_amount' => '100.00',
            'terminal' => '1',
            'ds_order' => '700000000001',
        ]);

        Assert::same('700000000001', $result['ds_order']);
        Assert::same('100.00', $result['amount']);
        Assert::same('100.00', $result['pending_before']);
        Assert::same(710, $result['source_id']);

        $row = $sifDb->query("SELECT EXPECTED_AMOUNT, SOURCE_TYPE, SOURCE_ID, SNAPSHOT_JSON FROM redsys_payment_intent WHERE DS_ORDER='700000000001'")
            ->fetch(\PDO::FETCH_ASSOC);
        Assert::same('100.00', number_format((float) $row['EXPECTED_AMOUNT'], 2, '.', ''));
        Assert::same('CURS', $row['SOURCE_TYPE']);
        Assert::same('710', (string) $row['SOURCE_ID']);

        $snapshot = json_decode((string) $row['SNAPSHOT_JSON'], true);
        Assert::same('100.00', $snapshot['payment']['amount']);
        Assert::same('20.00', $snapshot['payment']['paid_before']);
        Assert::same('120.00', $snapshot['payment']['contract_total']);
    }

    public function testRejectsPartialAmountWhenEnrollmentIsNotFractional(): void
    {
        $sifDb = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($sifDb): void {
            $this->service()->create($sifDb, $this->legacyDb(false), [
                'idpag' => 700,
                'requested_amount' => '50.00',
                'terminal' => '1',
                'ds_order' => '700000000002',
            ]);
        }, 409);

        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testAllowsPartialAmountWhenEnrollmentIsFractional(): void
    {
        $sifDb = TestDatabase::fresh();

        $result = $this->service()->create($sifDb, $this->legacyDb(true), [
            'idpag' => 700,
            'requested_amount' => '50.00',
            'terminal' => '1',
            'ds_order' => '700000000003',
        ]);

        Assert::same('50.00', $result['amount']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testRejectsAmountAboveAuthoritativePendingBalance(): void
    {
        $sifDb = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($sifDb): void {
            $this->service()->create($sifDb, $this->legacyDb(true), [
                'idpag' => 700,
                'requested_amount' => '100.01',
                'terminal' => '1',
                'ds_order' => '700000000004',
            ]);
        }, 422);
    }

    private function service(): RedsysCoursePaymentIntentService
    {
        return new RedsysCoursePaymentIntentService(
            new LegacyCourseSnapshotRepository(),
            new RedsysPaymentIntentService(
                new RedsysPaymentIntentRepository(),
                new UuidGenerator()
            ),
            new RedsysDsOrderGenerator()
        );
    }

    private function legacyDb(bool $fractional): RedsysCourseIntentLegacySpyPdo
    {
        return new RedsysCourseIntentLegacySpyPdo([
            [
                'ID' => 710,
                'ANY' => 2026,
                'MES' => '10',
                'CURS' => 'ABC',
                'NOM' => 'Persona',
                'COGNOMS' => 'Prova',
                'DNI' => '00000000T',
                'CORREU' => 'prova@example.invalid',
                'ADRECA' => 'Carrer prova',
                'Codi_Postal' => '17000',
                'Poblacio' => 'Girona',
                'FACTURA_RELACIONADA' => null,
                'A_PAGAR' => '120.00',
                'INSC CURS' => '0',
                'PAGAMENT' => '20.00',
                'FRACCIONAT' => $fractional ? 1 : 0,
                'FRACCIO' => '',
            ],
            [
                'NOM_CURS' => 'Curs de prova',
                'DATAI' => '2026-10-01',
                'DATAF' => '2026-10-31',
                'HORES' => 30,
            ],
        ]);
    }
}

final class RedsysCourseIntentLegacySpyPdo extends \PDO
{
    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new RedsysCourseIntentLegacySpyStatement($this);
    }

    public function nextRow(): mixed
    {
        return array_shift($this->rows) ?: false;
    }
}

final class RedsysCourseIntentLegacySpyStatement extends \PDOStatement
{
    public function __construct(private RedsysCourseIntentLegacySpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->db->nextRow();
    }
}
