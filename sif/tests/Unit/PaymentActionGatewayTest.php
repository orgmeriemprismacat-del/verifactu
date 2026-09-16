<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\PaymentActionEventWriter;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Tests\Support\Assert;

final class PaymentActionGatewayTest
{
    public function testAuditFailureBlocksOperationBeforeMutation(): void
    {
        $db = new PaymentActionGatewaySpyPdo();
        $writer = new PaymentActionGatewaySpyWriter();
        $writer->throwOnCall = 1;
        $gateway = new PaymentActionGateway($db, new TransactionRunner($db), $writer);
        $called = false;

        Assert::throws(\RuntimeException::class, function () use ($gateway, &$called): void {
            $gateway->run($this->event(), function () use (&$called): void {
                $called = true;
            });
        });

        Assert::same(false, $called);
        Assert::same([], $db->calls);
        Assert::same([], $writer->events);
    }

    public function testSuccessfulOperationWritesRequestedAndTerminalEvent(): void
    {
        $db = new PaymentActionGatewaySpyPdo();
        $writer = new PaymentActionGatewaySpyWriter();
        $gateway = new PaymentActionGateway($db, new TransactionRunner($db), $writer);

        $result = $gateway->run($this->event(), static fn (): array => [
            'ok' => true,
            'uuid_payment' => 'pay-1',
            'idempotency_reused' => false,
        ]);

        Assert::same(true, $result['ok']);
        Assert::same(['begin', 'commit'], $db->calls);
        Assert::same('REQUESTED', $writer->events[0]['result']);
        Assert::same(false, $writer->events[0]['is_terminal']);
        Assert::same('SUCCEEDED', $writer->events[1]['result']);
        Assert::same(true, $writer->events[1]['is_terminal']);
        Assert::same('pay-1', $writer->events[1]['uuid_payment']);
    }

    public function testIdempotentReuseIsRecordedAsReused(): void
    {
        $db = new PaymentActionGatewaySpyPdo();
        $writer = new PaymentActionGatewaySpyWriter();
        $gateway = new PaymentActionGateway($db, new TransactionRunner($db), $writer);

        $gateway->run($this->event(), static fn (): array => [
            'ok' => true,
            'uuid_payment' => 'pay-1',
            'idempotency_reused' => true,
        ]);

        Assert::same('REUSED', $writer->events[1]['result']);
    }

    public function testOperationFailureWritesFailedEventAndRethrows(): void
    {
        $db = new PaymentActionGatewaySpyPdo();
        $writer = new PaymentActionGatewaySpyWriter();
        $gateway = new PaymentActionGateway($db, new TransactionRunner($db), $writer);

        Assert::throws(\DomainException::class, function () use ($gateway): void {
            $gateway->run($this->event(), static function (): void {
                throw new \DomainException('domain rejected');
            });
        });

        Assert::same(['begin', 'rollback'], $db->calls);
        Assert::same('REQUESTED', $writer->events[0]['result']);
        Assert::same('FAILED', $writer->events[1]['result']);
        Assert::same(true, $writer->events[1]['is_terminal']);
        Assert::same('DOMAINEXCEPTION', $writer->events[1]['error_code']);
    }

    public function testTerminalAuditFailureRollsBackOperationAndRecordsFailure(): void
    {
        $db = new PaymentActionGatewaySpyPdo();
        $writer = new PaymentActionGatewaySpyWriter();
        $writer->throwOnCall = 2;
        $gateway = new PaymentActionGateway($db, new TransactionRunner($db), $writer);

        Assert::throws(\RuntimeException::class, function () use ($gateway): void {
            $gateway->run($this->event(), static fn (): array => ['ok' => true]);
        });

        Assert::same(['begin', 'rollback'], $db->calls);
        Assert::same('REQUESTED', $writer->events[0]['result']);
        Assert::same('FAILED', $writer->events[1]['result']);
    }

    private function event(): array
    {
        return [
            'request_id' => 'req-1',
            'correlation_id' => 'corr-1',
            'action' => 'CREATE',
            'source_environment' => 'TEST',
            'source_channel' => 'INTRANET',
            'actor_type' => 'HUMAN',
            'actor_id' => 'user-1',
            'actor_role' => 'PABLO_GESTIO_SECRETARIA',
            'payment_idempotency_key' => 'TRANSFERENCIA|REF:ABC123',
            'occurred_at' => '2026-09-16 09:00:00.000000',
        ];
    }
}

final class PaymentActionGatewaySpyWriter implements PaymentActionEventWriter
{
    public array $events = [];
    public ?int $throwOnCall = null;
    private int $calls = 0;

    public function append(\PDO $db, array $event): string
    {
        $this->calls++;

        if ($this->throwOnCall === $this->calls) {
            throw new \RuntimeException('audit unavailable');
        }

        $this->events[] = $event;

        return 'event-' . $this->calls;
    }
}

final class PaymentActionGatewaySpyPdo extends \PDO
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
