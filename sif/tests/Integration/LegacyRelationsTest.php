<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class LegacyRelationsTest
{
    public function testFactRelsPreservesLegacyIdentifiers(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());

        $stmt = $db->prepare('SELECT FACTURA_RELACIONADA, IDPAG, DS_ORDER, SOURCE_TYPE, SOURCE_ID FROM fact_rels WHERE UUID_FACTURA = ?');
        $stmt->execute([$invoice['uuid_factura']]);
        $rel = $stmt->fetch(\PDO::FETCH_ASSOC);

        Assert::same(500, (int) $rel['FACTURA_RELACIONADA']);
        Assert::same(123, (int) $rel['IDPAG']);
        Assert::same('999999', $rel['DS_ORDER']);
        Assert::same('INSCRIPCIO', $rel['SOURCE_TYPE']);
        Assert::same(10, (int) $rel['SOURCE_ID']);
    }

    public function testLegacySyncRunsOnlyWhenCalledAfterSifSuccess(): void
    {
        $legacyDb = new LegacySpyPdo();
        $service = new LegacySyncService(new LegacySyncRepository());

        Assert::same([], $legacyDb->preparedSql);

        $service->syncAfterSifSuccess(
            $legacyDb,
            [
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 10,
                    'factura_relacionada' => 500,
                ],
                [
                    'source_type' => 'REGAL',
                    'source_id' => 99,
                    'factura_relacionada' => 700,
                ],
            ],
            '11111111-1111-4111-8111-111111111111',
            'A2026/000001',
            'PAID'
        );

        Assert::same(1, count($legacyDb->preparedSql));
        Assert::stringContainsString('UPDATE inscripcions', $legacyDb->preparedSql[0]);
        Assert::same([
            500,
            "\nSIF ",
            'A2026/000001',
            'PAID',
            '11111111-1111-4111-8111-111111111111',
            10,
        ], $legacyDb->executedParams[0]);
    }
}

final class LegacySpyPdo extends \PDO
{
    public array $preparedSql = [];
    public array $executedParams = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;

        return new LegacySpyStatement($this);
    }
}

final class LegacySpyStatement extends \PDOStatement
{
    public function __construct(private LegacySpyPdo $db)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->db->executedParams[] = $params ?? [];

        return true;
    }
}
