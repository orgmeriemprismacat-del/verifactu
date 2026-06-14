<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyUsocSnapshotRepository;
use Prisma\Sif\Service\LegacyUsocInvoicePayloadBuilder;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';

if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview USOC entity invoices with SIF_ENV=production.\n");
    exit(1);
}

$payloadFile = payloadFile(array_slice($argv, 1), 'preview');

try {
    $input = readPayloadFile($payloadFile);
    assertExplicitEntityInput($input);
    $idpag = positiveInt($input['idpag'], 'Invalid USOC IDPAG');
    $studentAmount = positiveMoney($input['student_amount'], 'Invalid USOC student amount');
    $entityAmount = positiveMoney($input['amount'], 'Invalid USOC entity amount');
    $legacyDb = ConnectionFactory::makeLegacy($config);
    $snapshot = (new LegacyUsocSnapshotRepository())->loadByIdpag($legacyDb, $idpag, $studentAmount, $entityAmount);
    $payload = (new LegacyUsocInvoicePayloadBuilder())->buildEntityPayload($snapshot, $input);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'payload_file' => $payloadFile,
        'student_invoice_uuid' => (string) $input['student_invoice_uuid'],
        'payment_registered' => false,
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
        throw SifException::validation('Could not read USOC entity payload file');
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        throw SifException::validation('Invalid USOC entity payload JSON');
    }

    return $payload;
}

function assertExplicitEntityInput(array $input): void
{
    foreach (['idpag', 'student_amount', 'amount', 'student_invoice_uuid', 'billing'] as $field) {
        if (!array_key_exists($field, $input) || $input[$field] === '') {
            throw SifException::validation("Missing USOC entity field {$field}");
        }
    }

    if (!is_array($input['billing'])) {
        throw SifException::validation('Missing USOC entity field billing');
    }

    foreach (['name', 'nif'] as $field) {
        if (!array_key_exists($field, $input['billing']) || trim((string) $input['billing'][$field]) === '') {
            throw SifException::validation("Missing USOC billing.{$field}");
        }
    }
}

function positiveInt(mixed $value, string $message): int
{
    if (!is_numeric($value)) {
        throw SifException::validation($message);
    }

    $intValue = (int) $value;
    if ($intValue <= 0) {
        throw SifException::validation($message);
    }

    return $intValue;
}

function positiveMoney(mixed $value, string $message): string
{
    if (!is_numeric($value)) {
        throw SifException::validation($message);
    }

    $amount = (float) $value;
    if ($amount <= 0.0) {
        throw SifException::validation($message);
    }

    return number_format($amount, 2, '.', '');
}

function usage(string $script): never
{
    fwrite(
        STDERR,
        "Usage: php sif/scripts/{$script}-usoc-entity.php --payload-file=payload.json\n"
    );
    exit(1);
}
