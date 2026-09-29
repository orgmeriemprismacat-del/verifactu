<?php

require dirname(__DIR__) . '/src/autoload.php';

use Prisma\Sif\Aeat\{ClientCertificate, EvidenceStore, SerialWorker, SoapTransport};
use Prisma\Sif\Database\ConnectionFactory;

if (PHP_SAPI !== 'cli' || getenv('SIF_ENV') !== 'preproduction'
    || !in_array('--send-test', $argv, true)) {
    fwrite(STDERR, "Requires CLI, SIF_ENV=preproduction and --send-test. Only AEAT external tests are supported.\n");
    exit(1);
}
try {
    $config = require dirname(__DIR__) . '/config/sif.php';
    $aeat = $config['aeat'];
    $transport = new SoapTransport(new ClientCertificate($aeat['certificate_path'], $aeat['certificate_password']),
        new EvidenceStore(getenv('SIF_AEAT_EVIDENCE_DIR') ?: ''),
        $aeat['endpoint'] ?: SoapTransport::TEST_ENDPOINT,
        getenv('SIF_AEAT_CA_FILE') ?: null);
    $worker = new SerialWorker(ConnectionFactory::make($config), $transport,
        $aeat['max_attempts'], $aeat['base_retry_seconds'], $aeat['max_retry_seconds']);
    $result = $worker->runOnce(in_array('--recover-stale', $argv, true));
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), PHP_EOL;
    exit($result['ok'] ? 0 : 1);
} catch (\Throwable $error) {
    // Neither exception traces nor configuration may expose certificate secrets.
    fwrite(STDERR, "AEAT worker failed; check private configuration, schema and queue evidence.\n");
    exit(1);
}
