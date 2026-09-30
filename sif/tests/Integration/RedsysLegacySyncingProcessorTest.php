<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Service\RedsysJobProcessor;
use Prisma\Sif\Service\RedsysLegacySyncingProcessor;
use Prisma\Sif\Tests\Support\Assert;

final class RedsysLegacySyncingProcessorTest
{
    public function testPackFullPaymentProjectsEachInscriptionBeforeJobCompletion(): void
    {
        $legacyDb = new RedsysLegacySyncSpyPdo();
        $inner = new RedsysLegacySyncResultProcessor([
            'ok' => true,
            'uuid_factura' => '11111111-1111-4111-8111-111111111111',
            'num_visible' => 'A2026/15',
            'legacy_sync' => [
                'mode' => 'PACK_FULL_PAYMENT',
                'estat_cobrament' => 'PAID',
                'movement_date' => '2026-09-30 01:45:00',
                'relations' => [
                    ['source_type' => 'PACK', 'source_id' => 77],
                    ['source_type' => 'INSCRIPCIO', 'source_id' => 501],
                    ['source_type' => 'INSCRIPCIO', 'source_id' => 502],
                ],
            ],
        ]);

        $processor = new RedsysLegacySyncingProcessor(
            $inner,
            $legacyDb,
            new LegacySyncService(new LegacySyncRepository())
        );

        $result = $processor->process(new RedsysLegacySyncDummyPdo(), ['ID' => 1]);

        Assert::same(true, $result['legacy_sync_executed']);
        Assert::same(4, count($legacyDb->executions));

        $summaryWrites = array_values(array_filter(
            $legacyDb->executions,
            static fn (array $execution): bool => str_contains($execution['sql'], 'LOCATE(')
        ));
        $paymentWrites = array_values(array_filter(
            $legacyDb->executions,
            static fn (array $execution): bool => str_contains($execution['sql'], 'PAGAMENT = A_PAGAR')
        ));

        Assert::same(2, count($summaryWrites));
        Assert::same(2, count($paymentWrites));
        Assert::same(501, $summaryWrites[0]['params'][3]);
        Assert::same(502, $summaryWrites[1]['params'][3]);
        Assert::same('2026-09-30 01:45:00', $paymentWrites[0]['params'][0]);
        Assert::same(501, $paymentWrites[0]['params'][1]);
        Assert::same(502, $paymentWrites[1]['params'][1]);
        Assert::stringContainsString('LOCATE(?, COALESCE(OBSERVACIONS', $summaryWrites[0]['sql']);
        Assert::stringContainsString('DATA PAG', $paymentWrites[0]['sql']);
    }

    public function testProcessorWithoutLegacySyncDoesNotTouchLegacyDatabase(): void
    {
        $legacyDb = new RedsysLegacySyncSpyPdo();
        $processor = new RedsysLegacySyncingProcessor(
            new RedsysLegacySyncResultProcessor(['ok' => true, 'status' => 'NO_SYNC']),
            $legacyDb,
            new LegacySyncService(new LegacySyncRepository())
        );

        $result = $processor->process(new RedsysLegacySyncDummyPdo(), ['ID' => 2]);

        Assert::same('NO_SYNC', $result['status']);
        Assert::same([], $legacyDb->executions);
    }
}

final class RedsysLegacySyncResultProcessor implements RedsysJobProcessor
{
    public function __construct(private array $result)
    {
    }

    public function process(\PDO $sifDb, array $job): array
    {
        return $this->result;
    }
}

final class RedsysLegacySyncDummyPdo extends \PDO
{
    public function __construct()
    {
    }
}

final class RedsysLegacySyncSpyPdo extends \PDO
{
    public array $executions = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new RedsysLegacySyncSpyStatement($this, $query);
    }

    public function record(string $sql, array $params): void
    {
        $this->executions[] = ['sql' => $sql, 'params' => $params];
    }
}

final class RedsysLegacySyncSpyStatement extends \PDOStatement
{
    public function __construct(
        private RedsysLegacySyncSpyPdo $db,
        private string $sql
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $this->db->record($this->sql, $params ?? []);
        return true;
    }
}
