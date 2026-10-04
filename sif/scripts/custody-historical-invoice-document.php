<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Service\HistoricalInvoiceDocumentCustodyService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
$environment = strtolower(trim((string) ($config['env'] ?? 'local')));
if (in_array($environment, ['prod', 'production'], true)) {
    fwrite(STDERR, "Historical document custody refuses SIF_ENV=production.\n");
    exit(1);
}

$args = [
    'uuid' => null,
    'type' => null,
    'source_file' => null,
    'actor_id' => null,
    'correlation_id' => null,
];
foreach (array_slice($argv, 1) as $arg) {
    foreach ([
        '--uuid=' => 'uuid',
        '--type=' => 'type',
        '--source-file=' => 'source_file',
        '--actor-id=' => 'actor_id',
        '--correlation-id=' => 'correlation_id',
    ] as $prefix => $key) {
        if (str_starts_with((string) $arg, $prefix)) {
            $args[$key] = substr((string) $arg, strlen($prefix));
        }
    }
}

foreach (['uuid', 'type', 'source_file', 'actor_id'] as $required) {
    if (trim((string) ($args[$required] ?? '')) === '') {
        fwrite(STDERR, "Missing required --" . str_replace('_', '-', $required) . ".\n");
        exit(1);
    }
}

try {
    $db = ConnectionFactory::make($config);
    $documentRoot = trim((string) ($config['documents']['root'] ?? ''));
    if ($documentRoot === '') {
        throw new RuntimeException('SIF_DOCUMENT_ROOT is required for historical custody');
    }

    $service = new HistoricalInvoiceDocumentCustodyService(
        new TransactionRunner($db),
        $documentRoot,
        (int) ($config['documents']['max_bytes'] ?? 20971520)
    );
    $result = $service->custody(
        (string) $args['uuid'],
        (string) $args['type'],
        (string) $args['source_file'],
        [
            'actor_type' => 'HUMAN',
            'actor_id' => (string) $args['actor_id'],
            'actor_role' => 'MIGRATION_OPERATOR',
            'request_id' => $args['correlation_id'] ?: null,
            'correlation_id' => $args['correlation_id'] ?: null,
        ]
    );
    $result['environment'] = (string) ($config['env'] ?? 'local');
    $result['production_authorized'] = false;

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}
