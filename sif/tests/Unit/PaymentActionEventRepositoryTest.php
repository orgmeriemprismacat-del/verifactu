<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Tests\Support\Assert;

final class PaymentActionEventRepositoryTest
{
    public function testRequestedEventCannotBeTerminal(): void
    {
        $repository = new PaymentActionEventRepository(new UuidGenerator());
        $pdo = new class extends \PDO {
            public function __construct()
            {
            }
        };

        Assert::throws(SifException::class, static function () use ($repository, $pdo): void {
            $repository->append($pdo, self::event(['result' => 'REQUESTED', 'is_terminal' => true]));
        });
    }

    public function testTerminalResultIsRequiredForNonRequestedEvent(): void
    {
        $repository = new PaymentActionEventRepository(new UuidGenerator());
        $pdo = new class extends \PDO {
            public function __construct()
            {
            }
        };

        Assert::throws(SifException::class, static function () use ($repository, $pdo): void {
            $repository->append($pdo, self::event(['result' => 'SUCCEEDED', 'is_terminal' => false]));
        });
    }

    public function testUnknownResultIsRejectedBeforeDatabaseWrite(): void
    {
        $repository = new PaymentActionEventRepository(new UuidGenerator());
        $pdo = new class extends \PDO {
            public function __construct()
            {
            }
        };

        Assert::throws(SifException::class, static function () use ($repository, $pdo): void {
            $repository->append($pdo, self::event(['result' => 'DONE', 'is_terminal' => true]));
        });
    }

    public function testUnknownActionIsRejectedBeforeDatabaseWrite(): void
    {
        $repository = new PaymentActionEventRepository(new UuidGenerator());
        $pdo = new class extends \PDO {
            public function __construct()
            {
            }
        };

        Assert::throws(SifException::class, static function () use ($repository, $pdo): void {
            $repository->append($pdo, self::event(['action' => 'REGISTER_PAYMENT']));
        });
    }

    private static function event(array $overrides): array
    {
        return array_merge([
            'request_id' => 'req-1',
            'correlation_id' => 'corr-1',
            'action' => 'CREATE',
            'result' => 'REQUESTED',
            'is_terminal' => false,
            'source_environment' => 'TEST',
            'source_channel' => 'SIF_API',
            'actor_type' => 'HUMAN',
            'occurred_at' => '2026-09-15 12:00:00.000000',
        ], $overrides);
    }
}
