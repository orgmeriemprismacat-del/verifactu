<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Repository\FiscalQueueMetricsRepository;
use Prisma\Sif\Service\AeatPreflight;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$config['aeat']['evidence_directory'] = getenv('SIF_AEAT_EVIDENCE_DIR') ?: '';
$preflight = (new AeatPreflight())->check($config['aeat'] ?? []);
$result = [
    'ok' => false,
    'ready_to_send' => $preflight['ready'],
    'checks' => $preflight['checks'],
    'environment' => $config['env'] ?? 'local',
    'queue' => null,
    'alerts' => [],
];

try {
    $db = ConnectionFactory::make($config);
    $metrics = (new FiscalQueueMetricsRepository())->snapshot(
        $db,
        (int) (getenv('SIF_AEAT_STALE_LOCK_SECONDS') ?: 900)
    );
    $result['queue'] = $metrics;
    $deadLetterThreshold = (int) (getenv('SIF_AEAT_DEAD_LETTER_ALERT') ?: 1);
    $dueThreshold = (int) (getenv('SIF_AEAT_DUE_ALERT') ?: 100);

    if (($metrics['counts']['DEAD_LETTER'] ?? 0) >= $deadLetterThreshold) {
        $result['alerts'][] = 'DEAD_LETTER_THRESHOLD';
    }
    if (($metrics['due'] ?? 0) >= $dueThreshold) {
        $result['alerts'][] = 'DUE_QUEUE_THRESHOLD';
    }
    if (($metrics['stale_locks'] ?? 0) > 0) {
        $result['alerts'][] = 'STALE_WORKER_LOCK';
    }

    $result['ok'] = $preflight['ready'] && $result['alerts'] === [];
} catch (\Throwable $exception) {
    $result['database_error'] = $exception->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
exit($result['ok'] ? 0 : 1);
