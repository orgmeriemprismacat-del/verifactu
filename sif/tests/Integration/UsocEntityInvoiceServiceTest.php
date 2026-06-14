<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;
use Prisma\Sif\Service\UsocEntityInvoiceService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocEntityInvoiceServiceTest
{
    public function testIssuesPendingEntityInvoiceFromExplicitBillingInput(): void
    {
        $sifDb = TestDatabase::fresh();
        $legacyDb = new UsocEntityLegacySpyPdo([
            $this->inscriptionRow(),
            $this->courseRow(),
            $this->inscriptionRow(),
            $this->courseRow(),
        ]);
        $service = $this->service($sifDb);

        $first = $service->issueEntityFromExplicitInput($legacyDb, $this->entityInput());
        $second = $service->issueEntityFromExplicitInput($legacyDb, $this->entityInput());

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(false, $first['payment_registered']);
        Assert::same('11111111-2222-3333-4444-555555555555', $first['student_invoice_uuid']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $sifDb->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $sifDb->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());

        $invoice = $sifDb->query('SELECT IDEMPOTENCY_KEY, TOTAL, ESTAT_COBRAMENT, SOURCE_CHANNEL FROM factura')
            ->fetch(\PDO::FETCH_ASSOC);
        $relation = $sifDb->query('SELECT SOURCE_TYPE, SOURCE_ID, RELATION_TYPE, VISIBLE_ALUMNE FROM fact_rels')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same(
            'INTRANET|USOC_ENTITAT|ID_INSC:880|FACT_ALUMNE:11111111-2222-3333-4444-555555555555',
            $invoice['IDEMPOTENCY_KEY']
        );
        Assert::same('25.00', $invoice['TOTAL']);
        Assert::same('PENDING', $invoice['ESTAT_COBRAMENT']);
        Assert::same('INTRANET', $invoice['SOURCE_CHANNEL']);
        Assert::same('INSCRIPCIO', $relation['SOURCE_TYPE']);
        Assert::same(880, (int) $relation['SOURCE_ID']);
        Assert::same('USOC_ENTITY', $relation['RELATION_TYPE']);
        Assert::same(0, (int) $relation['VISIBLE_ALUMNE']);
    }

    public function testRequiresExplicitEntityBillingBeforeLoadingLegacy(): void
    {
        $legacyDb = new UsocEntityLegacySpyPdo([]);
        $input = $this->entityInput();
        unset($input['billing']);

        Assert::throws(SifException::class, function () use ($legacyDb, $input): void {
            $this->service(TestDatabase::fresh())->issueEntityFromExplicitInput($legacyDb, $input);
        }, 422);

        Assert::same([], $legacyDb->preparedSql);
    }

    public function testRequiresStudentInvoiceUuidBeforeLoadingLegacy(): void
    {
        $legacyDb = new UsocEntityLegacySpyPdo([]);
        $input = $this->entityInput();
        unset($input['student_invoice_uuid']);

        Assert::throws(SifException::class, function () use ($legacyDb, $input): void {
            $this->service(TestDatabase::fresh())->issueEntityFromExplicitInput($legacyDb, $input);
        }, 422);

        Assert::same([], $legacyDb->preparedSql);
    }

    private function service(\PDO $sifDb): UsocEntityInvoiceService
    {
        return new UsocEntityInvoiceService(
            new LegacyUsocSnapshotRepository(),
            new LegacyUsocInvoicePayloadBuilder(),
            IssueInvoiceTest::serviceFor($sifDb)
        );
    }

    private function entityInput(): array
    {
        return [
            'idpag' => 980,
            'student_amount' => '75.00',
            'amount' => '25.00',
            'student_invoice_uuid' => '11111111-2222-3333-4444-555555555555',
            'created_by' => 'usoc-entity-preprod',
            'billing' => [
                'name' => 'USOC',
                'nif' => 'G00000000',
                'address' => 'Carrer Entitat 1',
                'cp' => '08001',
                'city' => 'Barcelona',
                'country' => 'ES',
                'email' => 'facturacio@usoc.example.test',
            ],
        ];
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

final class UsocEntityLegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct(private array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new UsocEntityLegacySpyStatement($this);
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

final class UsocEntityLegacySpyStatement extends \PDOStatement
{
    private mixed $row = null;

    public function __construct(private UsocEntityLegacySpyPdo $db)
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
