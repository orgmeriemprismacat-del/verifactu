<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\RedsysDsOrderGenerator;
use Prisma\Sif\Service\RedsysGiftPaymentIntentService;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysGiftPaymentIntentServiceTest
{
    public function testCreatesIntentFromAuthoritativeLegacyGift(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysGiftIntentLegacySpyPdo([$this->giftRow()]);

        $result = $this->service()->create($sifDb, $legacyDb, [
            'gift_id' => 77,
            'terminal' => '1',
            'ds_order' => '770000000001',
            'created_by' => 'pay-prisma-cat',
        ]);

        Assert::same('770000000001', $result['ds_order']);
        Assert::same('120.00', $result['amount']);
        Assert::same(77, $result['gift_id']);

        $row = $sifDb->query(
            "SELECT SOURCE_TYPE, SOURCE_ID, EXPECTED_AMOUNT, IDPAG, SNAPSHOT_JSON
             FROM redsys_payment_intent WHERE DS_ORDER='770000000001'"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REGAL', $row['SOURCE_TYPE']);
        Assert::same('77', (string) $row['SOURCE_ID']);
        Assert::same('120.00', number_format((float) $row['EXPECTED_AMOUNT'], 2, '.', ''));
        Assert::same(null, $row['IDPAG']);

        $snapshot = json_decode((string) $row['SNAPSHOT_JSON'], true);
        Assert::same('REGAL-77', $snapshot['gift']['CODI']);
        Assert::same('120.00', $snapshot['gift']['IMPORT']);
    }

    public function testReusesSinglePendingIntentForSameGift(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysGiftIntentLegacySpyPdo([
            $this->giftRow(),
            $this->giftRow(),
        ]);

        $first = $this->service()->create($sifDb, $legacyDb, [
            'gift_id' => 77,
            'terminal' => '1',
            'ds_order' => '770000000011',
            'created_by' => 'pay-prisma-cat',
        ]);
        $second = $this->service()->create($sifDb, $legacyDb, [
            'gift_id' => 77,
            'terminal' => '1',
            'created_by' => 'pay-prisma-cat',
        ]);

        Assert::same('770000000011', $first['ds_order']);
        Assert::same('770000000011', $second['ds_order']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(
            1,
            (int) $sifDb->query(
                "SELECT COUNT(*) FROM redsys_payment_intent
                 WHERE SOURCE_TYPE='REGAL' AND SOURCE_ID='77'"
            )->fetchColumn()
        );
    }

    public function testRejectsNewIntentAfterValidatedPaymentForSameGift(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new RedsysGiftIntentLegacySpyPdo([
            $this->giftRow(),
            $this->giftRow(),
        ]);

        $this->service()->create($sifDb, $legacyDb, [
            'gift_id' => 77,
            'terminal' => '1',
            'ds_order' => '770000000012',
        ]);

        (new \Prisma\Sif\Repository\RedsysNotificationRepository())->recordReceived(
            $sifDb,
            '770000000012',
            null,
            '120.00',
            '0000',
            true,
            ['source' => 'gift-intent-test'],
            'VALIDATED'
        );

        Assert::throws(SifException::class, function () use ($sifDb, $legacyDb): void {
            $this->service()->create($sifDb, $legacyDb, [
                'gift_id' => 77,
                'terminal' => '1',
            ]);
        }, 409);

        Assert::same(
            1,
            (int) $sifDb->query(
                "SELECT COUNT(*) FROM redsys_payment_intent
                 WHERE SOURCE_TYPE='REGAL' AND SOURCE_ID='77'"
            )->fetchColumn()
        );
    }

    public function testRejectsAlreadyInvoicedGift(): void
    {
        $sifDb = TestDatabase::fresh();
        $row = $this->giftRow();
        $row['FACT_REL'] = 123;

        Assert::throws(SifException::class, function () use ($sifDb, $row): void {
            $this->service()->create(
                $sifDb,
                new RedsysGiftIntentLegacySpyPdo([$row]),
                [
                    'gift_id' => 77,
                    'terminal' => '1',
                    'ds_order' => '770000000002',
                ]
            );
        }, 409);

        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testRejectsInvalidTerminalAndOrder(): void
    {
        $sifDb = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($sifDb): void {
            $this->service()->create(
                $sifDb,
                new RedsysGiftIntentLegacySpyPdo([$this->giftRow()]),
                ['gift_id' => 77, 'terminal' => 'A', 'ds_order' => '770000000003']
            );
        }, 422);

        Assert::throws(SifException::class, function () use ($sifDb): void {
            $this->service()->create(
                $sifDb,
                new RedsysGiftIntentLegacySpyPdo([$this->giftRow()]),
                ['gift_id' => 77, 'terminal' => '1', 'ds_order' => 'BAD-ORDER']
            );
        }, 422);
    }

    private function service(): RedsysGiftPaymentIntentService
    {
        return new RedsysGiftPaymentIntentService(
            new LegacyGiftSnapshotRepository(),
            new RedsysPaymentIntentService(
                new RedsysPaymentIntentRepository(),
                new UuidGenerator()
            ),
            new RedsysDsOrderGenerator()
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
            'OBSERVACIONS' => 'Dedicatoria',
        ];
    }
}

final class RedsysGiftIntentLegacySpyPdo extends \PDO
{
    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new RedsysGiftIntentLegacySpyStatement($this);
    }

    public function nextRow(): mixed
    {
        return array_shift($this->rows) ?: false;
    }
}

final class RedsysGiftIntentLegacySpyStatement extends \PDOStatement
{
    public function __construct(private RedsysGiftIntentLegacySpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(
        int $mode = \PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->db->nextRow();
    }
}
