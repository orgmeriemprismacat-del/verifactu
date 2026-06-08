<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Tests\Support\Assert;

final class LegacyGiftSnapshotRepositoryTest
{
    public function testLoadsLegacyGiftSnapshotById(): void
    {
        $db = new LegacyGiftSpyPdo([$this->giftRow()]);

        $snapshot = (new LegacyGiftSnapshotRepository())->loadById($db, 77);

        Assert::same(77, $snapshot['gift']['ID']);
        Assert::same('REGAL-77', $snapshot['gift']['CODI']);
        Assert::same('Compradora Regal', $snapshot['gift']['NOMC']);
        Assert::same('Destinatari Regal', $snapshot['gift']['DESTI']);
        Assert::same('120.00', $snapshot['gift']['IMPORT']);
        Assert::same(1, count($db->preparedSql));
        Assert::stringContainsString('FROM regal', $db->preparedSql[0]);
        Assert::stringContainsString('WHERE ID = ?', $db->preparedSql[0]);
    }

    public function testLoadsLegacyGiftSnapshotByCode(): void
    {
        $db = new LegacyGiftSpyPdo([$this->giftRow()]);

        $snapshot = (new LegacyGiftSnapshotRepository())->loadByCode($db, 'REGAL-77');

        Assert::same(77, $snapshot['gift']['ID']);
        Assert::same('REGAL-77', $snapshot['gift']['CODI']);
        Assert::same(1, count($db->preparedSql));
        Assert::stringContainsString('WHERE CODI = ?', $db->preparedSql[0]);
    }

    public function testRejectsInvalidIdBeforeQueryingLegacy(): void
    {
        $db = new LegacyGiftSpyPdo([]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyGiftSnapshotRepository())->loadById($db, 0);
        }, 422);

        Assert::same([], $db->preparedSql);
    }

    public function testRejectsBlankGiftCodeBeforeQueryingLegacy(): void
    {
        $db = new LegacyGiftSpyPdo([]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyGiftSnapshotRepository())->loadByCode($db, '   ');
        }, 422);

        Assert::same([], $db->preparedSql);
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

final class LegacyGiftSpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new LegacyGiftSpyStatement($this);
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

final class LegacyGiftSpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private LegacyGiftSpyPdo $db)
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
