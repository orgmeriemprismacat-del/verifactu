<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Tests\Support\Assert;

final class TransactionRunnerTest
{
    public function testRunCommitsSuccessfulCallback(): void
    {
        $db = new SpyPdo();
        $runner = new TransactionRunner($db);

        $result = $runner->run(static fn (\PDO $pdo): string => $pdo instanceof SpyPdo ? 'ok' : 'wrong');

        Assert::same('ok', $result);
        Assert::same(['begin', 'commit'], $db->calls);
    }

    public function testRunRollsBackWhenCallbackThrows(): void
    {
        $db = new SpyPdo();
        $runner = new TransactionRunner($db);

        try {
            $runner->run(static function (): void {
                throw new \RuntimeException('boom');
            });
            Assert::fail('Expected exception was not thrown');
        } catch (\RuntimeException $exception) {
            Assert::same('boom', $exception->getMessage());
        }

        Assert::same(['begin', 'rollback'], $db->calls);
    }
}

final class SpyPdo extends \PDO
{
    public array $calls = [];
    private bool $active = false;

    public function __construct()
    {
    }

    public function beginTransaction(): bool
    {
        $this->calls[] = 'begin';
        $this->active = true;
        return true;
    }

    public function commit(): bool
    {
        $this->calls[] = 'commit';
        $this->active = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->calls[] = 'rollback';
        $this->active = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->active;
    }
}
