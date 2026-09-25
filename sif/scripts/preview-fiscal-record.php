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

try {
    [$recordType, $selector, $input] = (new \Prisma\Sif\Cli\FiscalRecordArguments())->parse(array_slice($argv, 1));
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
    $previous = (new FiscalRecordRepository(new HashCalculator()))
        ->latestForInvoice($sifDb, $invoice['UUID_FACTURA']);
    if ($previous === null) {
        throw SifException::validation('Invoice has no fiscal registration record');
    }

    $builder = new FiscalRecordPayloadBuilder();
    $payload = $recordType === 'ANULACIO'
        ? $builder->cancellation($invoice, $previous, $input)
        : $builder->subsanation($invoice, $previous, $input);

    $records = new FiscalRecordRepository(new HashCalculator());
    $key = $builder->idempotencyKey($recordType, $invoice, $payload, $input);
    $existing = $records->findQueuedResult($sifDb, $key, false, $payload['request_hash']);
    $xml = null;
    if ($existing === null) {
        (new \Prisma\Sif\Service\FiscalRecordTransitionValidator())->validate($invoice, $previous, $payload);
        $payload = $records->previewPayload($sifDb, $recordType, $payload);
        if (isset($payload['aeat'])) {
            $xml = (new \Prisma\Sif\Aeat\XmlCodec())->request($payload['aeat']);
        }
    }

    echo json_encode([
        'ok' => true,
        'dry_run' => true,
        'idempotency_key' => 'AEAT|' . $key,
        'existing_result' => $existing,
        'xml_preview' => $xml,
        'advisory_only' => true,
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
