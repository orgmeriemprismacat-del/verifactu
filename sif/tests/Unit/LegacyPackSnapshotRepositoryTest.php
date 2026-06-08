<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyPackSnapshotRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\LegacyPackInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;

final class LegacyPackSnapshotRepositoryTest
{
    public function testLoadsPackSnapshotByIdpag(): void
    {
        $db = new LegacyPackSnapshotSpyPdo([
            [
                [
                    'ID' => 301,
                    'IDPAG' => 900,
                    'ANY' => 2026,
                    'MES' => '06',
                    'CURS' => 'ABC',
                    'TIPUS_INSC' => 'P',
                    'NOM' => 'Maria',
                    'COGNOMS' => 'Exemple',
                    'DNI' => '12345678Z',
                    'CORREU' => 'maria@example.test',
                    'ADRECA' => 'Carrer Exemple 1',
                    'Codi_Postal' => '08001',
                    'Poblacio' => 'Barcelona',
                    'FACTURA_RELACIONADA' => 701,
                    'A_PAGAR' => '120.00',
                    'INSC CURS' => '1',
                    'PAGAMENT' => '0.00',
                    'FRACCIO' => 0,
                    'FRACCIONAT' => 0,
                    'OBSERVACIONS' => 'alta PACK|44',
                    'pag_observacions' => '',
                ],
                [
                    'ID' => 302,
                    'IDPAG' => 900,
                    'ANY' => 2026,
                    'MES' => '07',
                    'CURS' => 'DEF',
                    'TIPUS_INSC' => 'P',
                    'NOM' => 'Maria',
                    'COGNOMS' => 'Exemple',
                    'DNI' => '12345678Z',
                    'CORREU' => 'maria@example.test',
                    'ADRECA' => 'Carrer Exemple 1',
                    'Codi_Postal' => '08001',
                    'Poblacio' => 'Barcelona',
                    'FACTURA_RELACIONADA' => 702,
                    'A_PAGAR' => '90.00',
                    'INSC CURS' => '1',
                    'PAGAMENT' => '0.00',
                    'FRACCIO' => 0,
                    'FRACCIONAT' => 0,
                    'OBSERVACIONS' => 'alta PACK|44',
                    'pag_observacions' => '',
                ],
            ],
            [
                'ID_PACK' => 44,
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
        ]);

        $snapshot = (new LegacyPackSnapshotRepository())->loadByIdpag($db, 900, '210.00');

        Assert::same(4, count($db->preparedSql));
        Assert::stringContainsString('FROM inscripcions', $db->preparedSql[0]);
        Assert::stringContainsString('TIPUS_INSC = \'P\'', $db->preparedSql[0]);
        Assert::stringContainsString('FROM info_pack', $db->preparedSql[1]);
        Assert::stringContainsString('FROM curs', $db->preparedSql[2]);
        Assert::stringContainsString('FROM curs', $db->preparedSql[3]);
        Assert::same([900], $db->executedParams[0]);
        Assert::same([44], $db->executedParams[1]);
        Assert::same([2026, '06', 'ABC'], $db->executedParams[2]);
        Assert::same([2026, '07', 'DEF'], $db->executedParams[3]);

        Assert::same(44, $snapshot['pack']['ID_PACK']);
        Assert::same('Benestar docent', $snapshot['pack']['TITOL']);
        Assert::same('210.00', $snapshot['payment']['amount']);
        Assert::same(900, $snapshot['payment']['idpag']);
        Assert::same(2, count($snapshot['items']));
        Assert::same(301, $snapshot['items'][0]['inscription']['ID']);
        Assert::same('Gestio emocional', $snapshot['items'][0]['course']['NOM_CURS']);
        Assert::same(302, $snapshot['items'][1]['inscription']['ID']);
        Assert::same('Mindfulness a l aula', $snapshot['items'][1]['course']['NOM_CURS']);

        $payload = (new LegacyPackInvoicePayloadBuilder())->build($snapshot);
        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('210.00', $payload['totals']['total']);
        Assert::same('PACK', $payload['lines'][1]['discount_origin']);
    }

    public function testRejectsPackWithoutPackMarkerInObservations(): void
    {
        $db = new LegacyPackSnapshotSpyPdo([
            [
                [
                    'ID' => 301,
                    'IDPAG' => 900,
                    'ANY' => 2026,
                    'MES' => '06',
                    'CURS' => 'ABC',
                    'OBSERVACIONS' => 'sense marcador',
                ],
                [
                    'ID' => 302,
                    'IDPAG' => 900,
                    'ANY' => 2026,
                    'MES' => '07',
                    'CURS' => 'DEF',
                    'OBSERVACIONS' => 'sense marcador',
                ],
            ],
        ]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyPackSnapshotRepository())->loadByIdpag($db, 900, '210.00');
        }, 422);
    }

    public function testRejectsMissingPackInscriptions(): void
    {
        $db = new LegacyPackSnapshotSpyPdo([[]]);

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyPackSnapshotRepository())->loadByIdpag($db, 900, '210.00');
        }, 409);
    }
}

final class LegacyPackSnapshotSpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new LegacyPackSnapshotSpyStatement($this);
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

final class LegacyPackSnapshotSpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private LegacyPackSnapshotSpyPdo $db)
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
