<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Service\CourseLegacyPaymentSyncService;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Service\RedsysJobProcessor;
use Prisma\Sif\Service\RedsysLegacySyncingProcessor;
use Prisma\Sif\Tests\Support\Assert;

final class RedsysLegacySyncingProcessorCourseTest
{
    public function testCourseJobProjectsConfirmedPaymentToLegacy(): void
    {
        $sif = new RedsysLegacySyncingProcessorSifPdo('95.50');
        $legacy = new RedsysLegacySyncingProcessorLegacyPdo('95.50');
        $inner = new RedsysLegacySyncingProcessorInner();

        $processor = new RedsysLegacySyncingProcessor(
            $inner,
            $legacy,
            new LegacySyncService(new LegacySyncRepository()),
            new CourseLegacyPaymentSyncService()
        );

        $job = [
            'SOURCE_TYPE' => 'CURS',
            'SNAPSHOT_JSON' => json_encode([
                'inscription' => ['ID' => 410, 'IDPAG' => 400],
                'payment' => ['idpag' => 400, 'amount' => '95.50'],
            ], JSON_UNESCAPED_SLASHES),
        ];

        $result = $processor->process($sif, $job);

        Assert::same(true, $result['legacy_sync_executed']);
        Assert::same('95.50', $result['legacy_payment_sync']['projected_payment']);
        Assert::same('PAID', $result['legacy_payment_sync']['status']);
        Assert::same('95.50', $legacy->updatedPayment);
    }
}

final class RedsysLegacySyncingProcessorInner implements RedsysJobProcessor
{
    public function process(\PDO $sifDb, array $job): array
    {
        return [
            'ok' => true,
            'uuid_factura' => '11111111-1111-4111-8111-111111111111',
            'num_visible' => 'A2026/000001',
            'uuid_payment' => '22222222-2222-4222-8222-222222222222',
        ];
    }
}

final class RedsysLegacySyncingProcessorSifPdo extends \PDO
{
    public function __construct(private string $sum) {}

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new RedsysLegacySyncingProcessorSifStatement($this->sum);
    }
}

final class RedsysLegacySyncingProcessorSifStatement extends \PDOStatement
{
    public function __construct(private string $sum) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed { return $this->sum; }
}

final class RedsysLegacySyncingProcessorLegacyPdo extends \PDO
{
    public ?string $updatedPayment = null;
    private int $prepareCount = 0;

    public function __construct(private string $contractTotal) {}

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->prepareCount++;
        if ($this->prepareCount === 1) {
            return new RedsysLegacySyncingProcessorLegacySelectStatement($this->contractTotal);
        }

        return new RedsysLegacySyncingProcessorLegacyUpdateStatement($this);
    }
}

final class RedsysLegacySyncingProcessorLegacySelectStatement extends \PDOStatement
{
    public function __construct(private string $contractTotal) {}
    public function execute(?array $params = null): bool { return true; }

    public function fetch(
        int $mode = \PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return [
            'A_PAGAR' => $this->contractTotal,
            'PAGAMENT' => '0.00',
            'FRACCIO' => '',
            'INSC CURS' => '0',
        ];
    }
}

final class RedsysLegacySyncingProcessorLegacyUpdateStatement extends \PDOStatement
{
    private int $rows = 0;

    public function __construct(private RedsysLegacySyncingProcessorLegacyPdo $db) {}

    public function execute(?array $params = null): bool
    {
        $this->db->updatedPayment = (string) ($params[0] ?? '');
        $this->rows = 1;
        return true;
    }

    public function rowCount(): int
    {
        return $this->rows;
    }
}
