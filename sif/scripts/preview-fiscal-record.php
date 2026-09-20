<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalRecordRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Service\FiscalRecordPayloadBuilder;
use Prisma\Sif\Domain\HashCalculator;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$config = require dirname(__DIR__) . '/config/sif.php';
if (($config['env'] ?? 'local') === 'production') {
    fwrite(STDERR, "Refusing to preview fiscal records with SIF_ENV=production.\n");
    exit(1);
}

[$recordType, $selector, $input] = parsePreviewArgs(array_slice($argv, 1));

try {
    $sifDb = ConnectionFactory::make($config);
    $invoices = new ManualPaymentInvoiceRepository();
    $invoice = $selector['type'] === 'uuid'
        ? $invoices->findByUuid($sifDb, $selector['value'])
        : $invoices->findByNumVisible($sifDb, $selector['value']);
    if ($invoice === null) {
        throw SifException::validation('Unknown invoice');
    }
    if (($invoice['ESTAT_AEAT'] ?? '') === 'NO_VERIFACTU' || ($invoice['ESTAT_FACTURA'] ?? '') === 'HISTORICAL') {
        throw SifException::validation('Historical NO_VERIFACTU invoices do not accept fiscal records');
    }
    if ($recordType === 'SUBSANACIO' && ($invoice['ESTAT_FACTURA'] ?? '') === 'CANCELLED') {
        throw SifException::conflict('Cancelled invoices do not accept subsanation records');
    }

    $previous = (new FiscalRecordRepository(new HashCalculator()))
        ->latestForInvoice($sifDb, $invoice['UUID_FACTURA']);
    if ($previous === null) {
        throw SifException::validation('Invoice has no fiscal registration record');
    }

    $builder = new FiscalRecordPayloadBuilder();
    $payload = $recordType === 'ANULACIO'
        ? $builder->cancellation($invoice, $previous, $input)
        : $builder->subsanation($invoice, $previous, $input);

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'idempotency_key' => 'AEAT|' . $builder->idempotencyKey($recordType, $invoice, $payload, $input),
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

function parsePreviewArgs(array $args): array
{
    $type = strtoupper((string) previewOption($args, ['--type=']));
    $uuid = previewOption($args, ['--uuid-factura=', '--uuid=']);
    $number = previewOption($args, ['--num-visible=', '--num-fact=']);
    $selector = $uuid !== null
        ? ['type' => 'uuid', 'value' => $uuid]
        : ($number !== null ? ['type' => 'num_visible', 'value' => $number] : null);
    $input = [];

    foreach ([
        'reason' => ['--reason=', '--motiu='],
        'subsanation_kind' => ['--subsanation-kind=', '--tipus-subsanacio='],
        'detail' => ['--detail=', '--detall='],
        'correction_summary' => ['--correction-summary=', '--resum-correccio='],
        'created_by' => ['--created-by=', '--usuari='],
        'reference' => ['--reference=', '--referencia='],
    ] as $key => $prefixes) {
        $value = previewOption($args, $prefixes);
        if ($value !== null) {
            $input[$key] = $value;
        }
    }

    if (!in_array($type, ['ANULACIO', 'SUBSANACIO'], true) || $selector === null || !isset($input['reason'])) {
        previewUsage();
    }
    if ($type === 'SUBSANACIO' && !isset($input['subsanation_kind'])) {
        previewUsage();
    }

    return [$type, $selector, $input];
}

function previewOption(array $args, array $prefixes): ?string
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

function previewUsage(): void
{
    fwrite(STDERR, "Usage: php sif/scripts/preview-fiscal-record.php --type=ANULACIO|SUBSANACIO (--uuid-factura=UUID|--num-visible=NUM) --reason=REASON [--subsanation-kind=SUBSANACION|RECHAZO_PREVIO|SIN_REGISTRO_PREVIO] [--detail=TEXT] [--correction-summary=TEXT] [--created-by=USER] [--reference=REF]\n");
    exit(1);
}
