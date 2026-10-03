<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\DebtClaimCaseRepository;
use Prisma\Sif\Repository\DebtSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Service\DebtClaimCoordinator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing debt claim preview with SIF_ENV=production.\n");
    exit(1);
}

$criteria = selector(array_slice($argv, 1));
if ($criteria === []) {
    fwrite(STDERR, "Usage: php sif/scripts/preview-debt-claim.php (--uuid-factura=UUID|--num-visible=NUM)\n");
    exit(1);
}

try {
    $db = ConnectionFactory::make($config);
    $u = new UuidGenerator();
    $service = new DebtClaimCoordinator(
        $db,
        new TransactionRunner($db),
        new DebtSnapshotRepository(),
        new DebtClaimCaseRepository($u),
        new NotificationOutboxRepository($u),
        new OperationalEventRepository($u),
        ['CLI_DEBT_CLAIM'],
        ['CLI_DEBT_CLAIM']
    );
    $result = $service->preview([
        'actor_type' => 'SYSTEM',
        'actor_id' => 'cli-preview-debt-claim',
        'roles' => ['CLI_DEBT_CLAIM'],
        'request_id' => 'CLI-PREVIEW-' . bin2hex(random_bytes(8)),
    ], $criteria);
    $result['dry_run'] = true;
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'dry_run' => true, 'error' => $e->getMessage(), 'code' => $e->getCode()], JSON_PRETTY_PRINT), PHP_EOL;
    exit(1);
}

function selector(array $args): array
{
    foreach ($args as $arg) {
        if (str_starts_with((string) $arg, '--uuid-factura=')) {
            return ['uuid_factura' => trim(substr((string) $arg, 15))];
        }
        if (str_starts_with((string) $arg, '--num-visible=')) {
            return ['num_visible' => trim(substr((string) $arg, 14))];
        }
    }
    return [];
}
