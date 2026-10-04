<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Tests\Support\TestDatabase;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(2);
}

$barrierDir = (string) ($argv[1] ?? '');
$worker = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($argv[2] ?? 'worker')) ?: 'worker';
$uuidFactura = trim((string) ($argv[3] ?? ''));
$holdLock = ((string) ($argv[4] ?? '0')) === '1';

if ($barrierDir === '' || $uuidFactura === '') {
    fwrite(STDERR, "Invalid worker arguments\n");
    exit(2);
}

try {
    $db = TestDatabase::connect();

    if (!is_dir($barrierDir) && !mkdir($barrierDir, 0700, true) && !is_dir($barrierDir)) {
        throw new RuntimeException('Could not create UC-005 concurrency barrier');
    }

    file_put_contents($barrierDir . '/started-' . $worker, (string) getmypid());

    $service = new ManualRectificationService(
        new ManualPaymentInvoiceRepository(),
        new RectificationRepository(),
        new ManualRectificationPayloadBuilder(),
        new InvoiceService(
            new TransactionRunner($db),
            new InvoicePayloadValidator(),
            new FiscalSequenceRepository(),
            new InvoiceRepository(new UuidGenerator(), new HashCalculator())
        )
    );

    $beforeCommit = null;
    if ($holdLock) {
        $beforeCommit = static function () use ($barrierDir, $worker): void {
            file_put_contents($barrierDir . '/locked-' . $worker, (string) getmypid());

            $deadline = microtime(true) + 10;
            while (!is_file($barrierDir . '/release-' . $worker)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('UC-005 concurrency lock hold timed out');
                }
                usleep(10000);
            }
        };
    }

    try {
        $result = $service->issueByUuid($db, $uuidFactura, [
            'amount' => '-40.00',
            'reason' => 'CONCURRENT_RECTIFICATION',
            'mode' => 'DIFERENCIES',
            'concept' => 'Rectificacio concurrent UC-005',
            'reference' => 'UC005-CONCURRENT-SAME-CORRECTION',
        ], $beforeCommit);

        echo json_encode([
            'ok' => true,
            'worker' => $worker,
            'uuid_factura' => (string) $result['uuid_factura'],
            'num_visible' => (string) $result['num_visible'],
            'idempotency_reused' => (bool) ($result['idempotency_reused'] ?? false),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    } catch (SifException $exception) {
        echo json_encode([
            'ok' => false,
            'worker' => $worker,
            'code' => (int) $exception->getCode(),
            'error' => $exception->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    }

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
