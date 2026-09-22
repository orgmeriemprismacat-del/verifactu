<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\NovicePromotionGrantReconciler;
use Prisma\Sif\Service\NovicePromotionGrantService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Production reconciliation requires an approved deployment and runbook.\n");
    exit(1);
}

$limit = 100;
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--limit=(\d{1,3})$/D', $argument, $matches)) {
        fwrite(STDERR, "Usage: php sif/scripts/reconcile-novice-promotions.php --limit=100\n");
        exit(1);
    }
    $limit = (int) $matches[1];
}

try {
    $db = ConnectionFactory::make($config);
    $reconciler = new NovicePromotionGrantReconciler(
        new NovicePromotionGrantService(new UuidGenerator())
    );
    $result = $reconciler->run($db, $limit);
    echo json_encode(['ok' => $result['conflicts'] === 0 && $result['errors'] === 0] + $result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), PHP_EOL;
    exit($result['conflicts'] === 0 && $result['errors'] === 0 ? 0 : 1);
} catch (Throwable $exception) {
    // Do not print SQL parameters, identifiers, applicant data or the trace.
    fwrite(STDERR, '[RECONCILIATION FAILED] ' . $exception::class . PHP_EOL);
    exit(1);
}
