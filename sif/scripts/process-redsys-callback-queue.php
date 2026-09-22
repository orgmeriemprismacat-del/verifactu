<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\LegacyGiftSnapshotRepository;
use Prisma\Sif\Repository\LegacyGroupSnapshotRepository;
use Prisma\Sif\Repository\LegacyPackSnapshotRepository;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\LegacyGiftInvoicePayloadBuilder;
use Prisma\Sif\Service\LegacyGroupInvoicePayloadBuilder;
use Prisma\Sif\Service\LegacyPackInvoicePayloadBuilder;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\RedsysCallbackDispatcher;
use Prisma\Sif\Service\RedsysCallbackWorker;
use Prisma\Sif\Service\RedsysCourseInvoiceService;
use Prisma\Sif\Service\NovicePromotionInvoiceLinkService;
use Prisma\Sif\Service\NovicePromotionGrantService;
use Prisma\Sif\Service\RedsysGiftInvoiceService;
use Prisma\Sif\Service\RedsysGroupInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysPackInvoiceService;
use Prisma\Sif\Service\RedsysUsocInvoiceService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$baseDir = dirname(__DIR__);
$config = require $baseDir . '/config/sif.php';
if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing Redsys callback queue processing with SIF_ENV=production.\n");
    exit(1);
}

$limit = 25;
$workerId = '';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr($argument, strlen('--limit='));
    } elseif (str_starts_with($argument, '--worker-id=')) {
        $workerId = trim(substr($argument, strlen('--worker-id=')));
    }
}

if ($limit < 1 || $limit > 100 || $workerId === '') {
    fwrite(STDERR, "Usage: php sif/scripts/process-redsys-callback-queue.php --limit=25 --worker-id=pay-prisma-1\n");
    exit(1);
}

try {
    $db = ConnectionFactory::make($config);
    $notifications = new RedsysNotificationRepository();
    $invoiceService = new InvoiceService(
        new TransactionRunner($db),
        new InvoicePayloadValidator(),
        new FiscalSequenceRepository(),
        new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
        new PaymentPayloadValidator(),
        new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
    );
    $redsysPayloads = new RedsysInvoicePayloadBuilder($notifications);
    $noviceLinks = new NovicePromotionInvoiceLinkService();
    $noviceGrants = new NovicePromotionGrantService(new UuidGenerator());
    $dispatcher = new RedsysCallbackDispatcher([
        new RedsysCourseInvoiceService($notifications, new LegacyCourseSnapshotRepository(), new LegacyCourseInvoicePayloadBuilder(), $redsysPayloads, $invoiceService, $noviceLinks, $noviceGrants),
        new RedsysPackInvoiceService($notifications, new LegacyPackSnapshotRepository(), new LegacyPackInvoicePayloadBuilder(), $redsysPayloads, $invoiceService),
        new RedsysGroupInvoiceService($notifications, new LegacyGroupSnapshotRepository(), new LegacyGroupInvoicePayloadBuilder(), $redsysPayloads, $invoiceService),
        new RedsysGiftInvoiceService($notifications, new LegacyGiftSnapshotRepository(), new LegacyGiftInvoicePayloadBuilder(), $redsysPayloads, $invoiceService),
        new RedsysUsocInvoiceService($notifications, new LegacyUsocSnapshotRepository(), new LegacyUsocInvoicePayloadBuilder(), $redsysPayloads, $invoiceService),
    ]);
    $worker = new RedsysCallbackWorker(
        new RedsysCallbackQueueRepository(new UuidGenerator()),
        $dispatcher,
        new IncidentRepository(),
        5
    );
    $counts = ['claimed' => 0, 'processed' => 0, 'retried' => 0, 'incidents' => 0];

    for ($index = 0; $index < $limit; $index++) {
        $result = $worker->runOne($db, $workerId, new DateTimeImmutable());
        if ($result === null) {
            break;
        }

        $counts['claimed']++;
        $status = (string) ($result['status'] ?? 'PROCESSED');
        if ($status === 'RETRY') {
            $counts['retried']++;
        } elseif ($status === 'INCIDENT') {
            $counts['incidents']++;
        } else {
            $counts['processed']++;
        }
    }

    echo json_encode(['ok' => true] + $counts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()], JSON_PRETTY_PRINT), PHP_EOL;
    exit(1);
}
