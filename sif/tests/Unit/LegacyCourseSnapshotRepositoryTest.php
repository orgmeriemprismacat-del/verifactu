<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;

final class LegacyCourseSnapshotRepositoryTest
{
    public function testLoadsCourseSnapshotByIdpag(): void
    {
        $db = new LegacyCourseSnapshotSpyPdo([
            [
                'ID' => 300,
                'ANY' => 2026,
                'MES' => '06',
                'CURS' => 'ABC',
                'NOM' => 'Maria',
                'COGNOMS' => 'Exemple',
                'DNI' => '12345678Z',
                'CORREU' => 'maria@example.test',
                'ADRECA' => 'Carrer Exemple 1',
                'Codi_Postal' => '08001',
                'Poblacio' => 'Barcelona',
                'FACTURA_RELACIONADA' => 700,
                'A_PAGAR' => '240.00',
                'INSC CURS' => '1',
                'PAGAMENT' => '120.00',
                'FRACCIO' => 1,
            ],
            [
                'NOM_CURS' => 'Gestio emocional',
                'DATAI' => '2026-06-10',
                'DATAF' => '2026-06-20',
                'HORES' => '12',
            ],
        ]);

        $snapshot = (new LegacyCourseSnapshotRepository())->loadByIdpag($db, 300, '120.00');

        Assert::same(2, count($db->preparedSql));
        Assert::stringContainsString('FROM inscripcions', $db->preparedSql[0]);
        Assert::stringContainsString('IDPAG = ?', $db->preparedSql[0]);
        Assert::stringContainsString('FROM curs', $db->preparedSql[1]);
        Assert::same([300], $db->executedParams[0]);
        Assert::same([2026, '06', 'ABC'], $db->executedParams[1]);

        Assert::same(300, $snapshot['inscription']['ID']);
        Assert::same(300, $snapshot['inscription']['IDPAG']);
        Assert::same('Gestio emocional', $snapshot['course']['NOM_CURS']);
        Assert::same('120.00', $snapshot['payment']['amount']);

        $payload = (new LegacyCourseInvoicePayloadBuilder())->build($snapshot);
        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('120.00', $payload['totals']['total']);
        Assert::same('Curs Gestio emocional', $payload['lines'][0]['concept']);
    }

    public function testRejectsMissingInscription(): void
    {
        $db = new LegacyCourseSnapshotSpyPdo([false]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyCourseSnapshotRepository())->loadByIdpag($db, 999, '120.00');
        }, 409);
    }

    public function testRejectsMissingCourse(): void
    {
        $db = new LegacyCourseSnapshotSpyPdo([
            [
                'ID' => 300,
                'ANY' => 2026,
                'MES' => '06',
                'CURS' => 'ABC',
            ],
            false,
        ]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyCourseSnapshotRepository())->loadByIdpag($db, 300, '120.00');
        }, 409);
    }
}

final class LegacyCourseSnapshotSpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new LegacyCourseSnapshotSpyStatement($this);
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

final class LegacyCourseSnapshotSpyStatement extends \PDOStatement
{
    public function __construct(private LegacyCourseSnapshotSpyPdo $db)
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
