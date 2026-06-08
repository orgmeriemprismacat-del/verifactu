<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Tests\Support\Assert;

final class LegacyUsocSnapshotRepositoryTest
{
    public function testLoadsValidatedUsocSnapshotByIdpag(): void
    {
        $db = new LegacyUsocSpyPdo([$this->inscriptionRow(), $this->courseRow()]);

        $snapshot = (new LegacyUsocSnapshotRepository())->loadByIdpag($db, 980, '75.00', '25.00');

        Assert::same(880, $snapshot['inscription']['ID']);
        Assert::same(980, $snapshot['inscription']['IDPAG']);
        Assert::same(4, $snapshot['inscription']['TIPUS_DESC']);
        Assert::same(1, $snapshot['inscription']['VALID_DESC']);
        Assert::same('Comunicacio assertiva', $snapshot['course']['NOM_CURS']);
        Assert::same('75.00', $snapshot['usoc']['student_amount']);
        Assert::same('25.00', $snapshot['usoc']['entity_amount']);
        Assert::same('75.00', $snapshot['payment']['amount']);
        Assert::same(980, $snapshot['payment']['idpag']);
        Assert::same(2, count($db->preparedSql));
        Assert::stringContainsString('FROM inscripcions', $db->preparedSql[0]);
        Assert::stringContainsString('IDPAG = ?', $db->preparedSql[0]);
        Assert::stringContainsString('FROM curs', $db->preparedSql[1]);
        Assert::same([[980], [2026, '06', 'COM']], $db->executedParams);
    }

    public function testRejectsInvalidIdpagBeforeQueryingLegacy(): void
    {
        $db = new LegacyUsocSpyPdo([]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyUsocSnapshotRepository())->loadByIdpag($db, 0, '75.00', '25.00');
        }, 422);

        Assert::same([], $db->preparedSql);
    }

    public function testRejectsNonUsocInscriptionBeforeLoadingCourse(): void
    {
        $row = $this->inscriptionRow();
        $row['TIPUS_DESC'] = 3;
        $db = new LegacyUsocSpyPdo([$row]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyUsocSnapshotRepository())->loadByIdpag($db, 980, '75.00', '25.00');
        }, 409);

        Assert::same(1, count($db->preparedSql));
    }

    public function testRejectsNonValidatedUsocInscriptionBeforeLoadingCourse(): void
    {
        $row = $this->inscriptionRow();
        $row['VALID_DESC'] = 0;
        $db = new LegacyUsocSpyPdo([$row]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyUsocSnapshotRepository())->loadByIdpag($db, 980, '75.00', '25.00');
        }, 409);

        Assert::same(1, count($db->preparedSql));
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

final class LegacyUsocSpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new LegacyUsocSpyStatement($this);
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

final class LegacyUsocSpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private LegacyUsocSpyPdo $db)
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
