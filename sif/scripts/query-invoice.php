<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\InvoiceQueryService;
use Prisma\Sif\Service\ResolvedInvoiceVisibilityPolicy;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing privileged invoice queries with SIF_ENV=production.\n");
    exit(1);
}

$uuid = optionValue(array_slice($argv, 1), ['--uuid=', '--uuid-factura=']);
if ($uuid === null) {
    usage();
}

try {
    $db = ConnectionFactory::make($config);
    $service = new InvoiceQueryService(
        $db,
        new InvoiceReadRepository(),
        new ResolvedInvoiceVisibilityPolicy()
    );

    $actor = [
        'actor_id' => 'cli-preproduction',
        'actor_type' => 'TECHNICAL_TEST',
        'invoice_scope' => [
            'all' => true,
            'projection' => 'FULL',
        ],
    ];

    $result = $service->view($actor, $uuid);
    $result['read_only'] = true;

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'read_only' => true,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

function optionValue(array $args, array $prefixes): ?string
{
    foreach ($args as $arg) {
        foreach ($prefixes as $prefix) {
            if (str_starts_with((string) $arg, $prefix)) {
                $value = trim(substr((string) $arg, strlen($prefix)));

                return $value === '' ? null : $value;
            }
        }
    }

    return null;
}

function usage(): never
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/query-invoice.php --uuid=UUID_FACTURA\n"
    );
    exit(1);
}
