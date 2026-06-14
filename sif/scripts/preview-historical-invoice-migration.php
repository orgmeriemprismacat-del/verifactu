<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\HistoricalInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview historical invoice migrations with SIF_ENV=production.\n");
    exit(1);
}

$payloadFile = payloadFile(array_slice($argv, 1), 'preview');

try {
    $input = readPayloadFile($payloadFile);
    $payload = (new HistoricalInvoicePayloadBuilder())->build($input);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'payload_file' => $payloadFile,
        'payload' => $payload,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(0);
} catch (\Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'dry_run' => true,
        'error' => $exception->getMessage(),
        'code' => $exception->getCode(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    exit(1);
}

function payloadFile(array $args, string $script): string
{
    foreach ($args as $arg) {
        $arg = (string) $arg;
        if (str_starts_with($arg, '--payload-file=')) {
            $path = trim(substr($arg, strlen('--payload-file=')));

            return $path === '' ? usage($script) : $path;
        }

        if (!str_starts_with($arg, '--')) {
            return $arg;
        }
    }

    usage($script);
}

function readPayloadFile(string $payloadFile): array
{
    $json = file_get_contents($payloadFile);
    if ($json === false) {
        throw SifException::validation('Could not read historical invoice migration payload file');
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid historical invoice migration payload JSON');
    }

    return $payload;
}

function usage(string $script): never
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/{$script}-historical-invoice-migration.php --payload-file=payload.json\n"
    );
    exit(1);
}
