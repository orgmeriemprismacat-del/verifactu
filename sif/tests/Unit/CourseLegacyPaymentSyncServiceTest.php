<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\CourseLegacyPaymentSyncService;
use Prisma\Sif\Tests\Support\Assert;

final class CourseLegacyPaymentSyncServiceTest
{
    public function testProjectsConfirmedSifPaymentsIdempotently(): void
    {
        $sif = new CourseLegacySyncSifPdo('75.00');
        $legacy = new CourseLegacySyncLegacyPdo('120.00', '20.00');
        $service = new CourseLegacyPaymentSyncService();

        $result = $service->sync($sif, $legacy, 700, 710, 'uuid-factura-1', 'A2026/10');

        Assert::same('75.00', $result['confirmed_amount']);
        Assert::same('75.00', $result['projected_payment']);
        Assert::same('PARTIALLY_PAID', $result['status']);
        Assert::same('75.00', $legacy->updatedPayment);
    }

    public function testCapsProjectionAtContractTotal(): void
    {
        $sif = new CourseLegacySyncSifPdo('150.00');
        $legacy = new CourseLegacySyncLegacyPdo('120.00', '20.00');
        $service = new CourseLegacyPaymentSyncService();

        $result = $service->sync($sif, $legacy, 700, 710, 'uuid-factura-2', 'A2026/11');

        Assert::same('120.00', $result['projected_payment']);
        Assert::same('PAID', $result['status']);
        Assert::same('120.00', $legacy->updatedPayment);
    }
}

final class CourseLegacySyncSifPdo extends \PDO
{
    public function __construct(private string $sum) {}
    public function prepare(string $query, array $options = []): \PDOStatement|false
    { return new CourseLegacySyncSifStatement($this->sum); }
}

final class CourseLegacySyncSifStatement extends \PDOStatement
{
    public function __construct(private string $sum) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed { return $this->sum; }
}

final class CourseLegacySyncLegacyPdo extends \PDO
{
    public ?string $updatedPayment = null;
    private int $prepareCount = 0;
    public function __construct(private string $total, private string $paid) {}
    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->prepareCount++;
        if ($this->prepareCount === 1) {
            return new CourseLegacySyncLegacySelectStatement($this->total, $this->paid);
        }
        return new CourseLegacySyncLegacyUpdateStatement($this);
    }
}

final class CourseLegacySyncLegacySelectStatement extends \PDOStatement
{
    public function __construct(private string $total, private string $paid) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return ['A_PAGAR' => $this->total, 'PAGAMENT' => $this->paid, 'FRACCIO' => '', 'INSC CURS' => '0'];
    }
}

final class CourseLegacySyncLegacyUpdateStatement extends \PDOStatement
{
    private int $rows = 0;
    public function __construct(private CourseLegacySyncLegacyPdo $db) {}
    public function execute(?array $params = null): bool
    {
        $this->db->updatedPayment = (string) ($params[0] ?? '');
        $this->rows = 1;
        return true;
    }
    public function rowCount(): int { return $this->rows; }
}
