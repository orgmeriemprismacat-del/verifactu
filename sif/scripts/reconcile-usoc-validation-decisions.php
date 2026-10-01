<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\UsocValidationDecisionRepository;
use Prisma\Sif\Service\UsocValidationDecisionService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$args = array_slice($argv, 1);
$limit = parseLimit($args);
$allowProduction = in_array('--confirm-production', $args, true);

if (($config['env'] ?? 'local') === 'production' && !$allowProduction) {
    fwrite(
        STDERR,
        "Refusing production reconciliation without explicit --confirm-production.\n"
    );
    exit(1);
}

try {
    $db = ConnectionFactory::make($config);
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $repository = new UsocValidationDecisionRepository(new UuidGenerator());
    $service = new UsocValidationDecisionService($repository);

    $items = [];
    foreach ($repository->findRequested($db, $limit) as $decision) {
        $requestId = (string) $decision['REQUEST_ID'];
        $actorId = (string) $decision['ACTOR_ID'];

        try {
            $result = $service->complete($db, $legacyDb, $requestId, $actorId);
            $items[] = [
                'request_id' => $requestId,
                'id_insc' => (int) $decision['ID_INSC'],
                'state' => $result['state'] ?? null,
                'legacy_valid_desc' => $result['legacy_valid_desc'] ?? null,
                'review_reason' => $result['review_reason'] ?? null,
            ];
        } catch (\Throwable $exception) {
            $items[] = [
                'request_id' => $requestId,
                'id_insc' => (int) $decision['ID_INSC'],
                'state' => 'ERROR',
                'error' => $exception->getMessage(),
            ];
        }
    }

    $errors = count(array_filter(
        $items,
        static fn (array $item): bool => ($item['state'] ?? '') === 'ERROR'
    ));

    echo json_encode([
        'ok' => $errors === 0,
        'processed' => count($items),
        'errors' => $errors,
        'items' => $items,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;

    exit($errors === 0 ? 0 : 1);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

function parseLimit(array $args): int
{
    foreach ($args as $arg) {
        if (str_starts_with((string) $arg, '--limit=')) {
            $value = substr((string) $arg, strlen('--limit='));
            if (!ctype_digit($value) || (int) $value < 1 || (int) $value > 500) {
                throw new InvalidArgumentException('Invalid --limit value');
            }
            return (int) $value;
        }
    }

    return 100;
}
