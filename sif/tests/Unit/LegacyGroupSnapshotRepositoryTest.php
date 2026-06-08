<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyGroupSnapshotRepository;
use Prisma\Sif\Tests\Support\Assert;

final class LegacyGroupSnapshotRepositoryTest
{
    public function testLoadsLegacyGroupSnapshotByIdpag(): void
    {
        $db = new LegacyGroupSpyPdo([
            [
                $this->inscription(801, 'Anna', 'Participant', '120.00', 9101),
                $this->inscription(802, 'Biel', 'Participant', '80.00', 9102),
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
        ]);

        $snapshot = (new LegacyGroupSnapshotRepository())->loadByIdpag($db, 950, '200.00');

        Assert::same('200.00', $snapshot['payment']['amount']);
        Assert::same(950, $snapshot['payment']['idpag']);
        Assert::same('Responsable', $snapshot['responsible']['NOM']);
        Assert::same('44444444G', $snapshot['responsible']['DNI']);
        Assert::same(2, count($snapshot['items']));
        Assert::same(801, $snapshot['items'][0]['inscription']['ID']);
        Assert::same(950, $snapshot['items'][0]['inscription']['IDPAG']);
        Assert::same('Comunicacio assertiva', $snapshot['items'][0]['course']['NOM_CURS']);
        Assert::same(802, $snapshot['items'][1]['inscription']['ID']);
        Assert::same(4, count($db->preparedSql));
        Assert::stringContainsString('TIPUS_INSC = \'G\'', $db->preparedSql[0]);
        Assert::stringContainsString('respGrups', $db->preparedSql[1]);
        Assert::stringContainsString('FROM curs', $db->preparedSql[2]);
    }

    public function testRejectsInvalidIdpagBeforeQueryingLegacy(): void
    {
        $db = new LegacyGroupSpyPdo([]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyGroupSnapshotRepository())->loadByIdpag($db, 0, '200.00');
        }, 422);

        Assert::same([], $db->preparedSql);
    }

    private function inscription(int $id, string $name, string $surname, string $amount, int $facturaRelacionada): array
    {
        return [
            'ID' => $id,
            'IDPAG' => 950,
            'ANY' => 2026,
            'MES' => '06',
            'CURS' => 'COM',
            'Grup' => 'A',
            'TIPUS_INSC' => 'G',
            'NOM' => $name,
            'COGNOMS' => $surname,
            'DNI' => $id . 'Z',
            'CORREU' => strtolower($name) . '@example.test',
            'FACTURA_RELACIONADA' => $facturaRelacionada,
            'A_PAGAR' => $amount,
            'PAGAMENT' => '0.00',
            'INSC CURS' => '1',
            'FRACCIO' => 0,
            'FRACCIONAT' => 0,
            'pag_observacions' => '',
        ];
    }
}

final class LegacyGroupSpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new LegacyGroupSpyStatement($this);
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

final class LegacyGroupSpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private LegacyGroupSpyPdo $db)
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
