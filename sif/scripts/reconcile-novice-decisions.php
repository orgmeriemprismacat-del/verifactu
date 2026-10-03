<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\NovicePromotionDecisionReconciler;
use Prisma\Sif\Service\NovicePromotionSecretaryDecisionProjector;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
if (($config['env'] ?? '') !== 'test') {
    fwrite(STDERR, "Novice decision reconciliation is restricted to SIF_ENV=test until preproduction approval.\n");
    exit(1);
}

$limit = 100;
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--limit=(\d{1,3})$/D', $argument, $matches)) {
        fwrite(STDERR, "Usage: php sif/scripts/reconcile-novice-decisions.php --limit=100\n");
        exit(1);
    }
    $limit = (int) $matches[1];
}

try {
    $sifDb = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);

    $databaseName = (string) $sifDb->query('SELECT DATABASE()')->fetchColumn();
    if (!preg_match('/^sif_test(?:_[a-z0-9_]+)?$/D', $databaseName)) {
        throw new RuntimeException('Decision reconciliation is allowed only on isolated sif_test databases.');
    }

    $actorId = trim((string) (getenv('SIF_NOVICE_DECISION_RECONCILE_ACTOR') ?: 'system:novice-decision-reconciler'));

    $result = (new NovicePromotionDecisionReconciler(
        new NovicePromotionSecretaryDecisionProjector(new UuidGenerator())
    ))->run($sifDb, $legacyDb, $actorId, $limit);

    $ok = $result['conflicts'] === 0 && $result['errors'] === 0;
    echo json_encode(['ok' => $ok] + $result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), PHP_EOL;
    exit($ok ? 0 : 1);
} catch (Throwable $exception) {
    fwrite(STDERR, '[DECISION RECONCILIATION FAILED] ' . $exception::class . PHP_EOL);
    exit(1);
}
