<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Service\GeneratedInvoiceLegacyPaymentSyncService;
use Prisma\Sif\Tests\Support\Assert;

final class GeneratedInvoiceLegacyPaymentSyncServiceTest
{
    public function testProjectsAggregateSifAmountAcrossLegacyInvoiceMembers(): void
    {
        $sif = new GeneratedInvoiceLegacySyncSifPdo('120.00');
        $legacy = new GeneratedInvoiceLegacySyncLegacyPdo([
            ['ID' => 10, 'A_PAGAR' => '100.00'],
            ['ID' => 20, 'A_PAGAR' => '50.00'],
        ]);

        $result = (new GeneratedInvoiceLegacyPaymentSyncService())->sync(
            $sif,
            $legacy,
            'uuid-factura-1',
            'A2026/100',
            '2026-10-03 18:30:00',
            'TRANSFERENCIA'
        );

        Assert::same('120.00', $result['confirmed_amount']);
        Assert::same('120.00', $result['projected_amount']);
        Assert::same('PARTIALLY_PAID', $result['status']);
        Assert::same('100.00', $legacy->memberPayments[10]['payment']);
        Assert::same(true, $legacy->memberPayments[10]['paid']);
        Assert::same('20.00', $legacy->memberPayments[20]['payment']);
        Assert::same(false, $legacy->memberPayments[20]['paid']);
        Assert::same('A2026/100', $legacy->invoiceNumber);
        Assert::same('TRANSFERENCIA', $legacy->invoiceMethod);
        Assert::same(true, $legacy->committed);
    }

    public function testProjectionCapsOverpaymentAtLegacyContractTotal(): void
    {
        $sif = new GeneratedInvoiceLegacySyncSifPdo('190.00');
        $legacy = new GeneratedInvoiceLegacySyncLegacyPdo([
            ['ID' => 10, 'A_PAGAR' => '100.00'],
            ['ID' => 20, 'A_PAGAR' => '50.00'],
        ]);

        $result = (new GeneratedInvoiceLegacyPaymentSyncService())->sync(
            $sif,
            $legacy,
            'uuid-factura-2',
            'A2026/101',
            '2026-10-03 18:45:00',
            'TRANSFERENCIA'
        );

        Assert::same('190.00', $result['confirmed_amount']);
        Assert::same('150.00', $result['projected_amount']);
        Assert::same('PAID', $result['status']);
        Assert::same('100.00', $legacy->memberPayments[10]['payment']);
        Assert::same('50.00', $legacy->memberPayments[20]['payment']);
    }
}

final class GeneratedInvoiceLegacySyncSifPdo extends \PDO
{
    public function __construct(private string $confirmed) {}

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new GeneratedInvoiceLegacySyncSifStatement($this->confirmed);
    }
}

final class GeneratedInvoiceLegacySyncSifStatement extends \PDOStatement
{
    public function __construct(private string $confirmed) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed { return $this->confirmed; }
}

final class GeneratedInvoiceLegacySyncLegacyPdo extends \PDO
{
    public array $memberPayments = [];
    public ?string $invoiceNumber = null;
    public ?string $invoiceMethod = null;
    public bool $committed = false;
    private bool $active = false;
    private int $prepareCount = 0;

    public function __construct(private array $members) {}

    public function beginTransaction(): bool
    {
        $this->active = true;
        return true;
    }

    public function commit(): bool
    {
        $this->committed = true;
        $this->active = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->active = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->active;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->prepareCount++;

        return match ($this->prepareCount) {
            1 => new GeneratedInvoiceLegacyInvoiceStatement(),
            2 => new GeneratedInvoiceLegacyMembersStatement($this->members),
            3, 4 => new GeneratedInvoiceLegacyMemberUpdateStatement($this),
            default => new GeneratedInvoiceLegacyInvoiceUpdateStatement($this),
        };
    }
}

final class GeneratedInvoiceLegacyInvoiceStatement extends \PDOStatement
{
    public function execute(?array $params = null): bool { return true; }

    public function fetch(
        int $mode = \PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return ['factura_relacionada' => 500];
    }
}

final class GeneratedInvoiceLegacyMembersStatement extends \PDOStatement
{
    public function __construct(private array $members) {}
    public function execute(?array $params = null): bool { return true; }

    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->members;
    }
}

final class GeneratedInvoiceLegacyMemberUpdateStatement extends \PDOStatement
{
    private int $rows = 0;

    public function __construct(private GeneratedInvoiceLegacySyncLegacyPdo $db) {}

    public function execute(?array $params = null): bool
    {
        $id = (int) ($params[4] ?? 0);
        $this->db->memberPayments[$id] = [
            'payment' => (string) ($params[0] ?? ''),
            'paid' => (int) ($params[1] ?? 0) === 1,
            'date' => $params[2] ?? null,
            'partial' => (int) ($params[3] ?? 0) === 1,
        ];
        $this->rows = 1;

        return true;
    }

    public function rowCount(): int { return $this->rows; }
}

final class GeneratedInvoiceLegacyInvoiceUpdateStatement extends \PDOStatement
{
    private int $rows = 0;

    public function __construct(private GeneratedInvoiceLegacySyncLegacyPdo $db) {}

    public function execute(?array $params = null): bool
    {
        $this->db->invoiceMethod = (string) ($params[1] ?? '');
        $this->db->invoiceNumber = (string) ($params[2] ?? '');
        $this->rows = 1;

        return true;
    }

    public function rowCount(): int { return $this->rows; }
}
