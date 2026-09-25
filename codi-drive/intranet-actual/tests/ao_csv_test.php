<?php
/** Proves CLI sense BD ni sessió: php tests/ao_csv_test.php */
declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/AOBatchCsv.php';

if (!extension_loaded('mbstring')) {
    fwrite(STDERR, "SKIP: cal l'extensió mbstring per executar la prova CSV.\n");
    exit(77);
}

function aoAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$cases = 0;
foreach ([
    ['text corrent', 'Alba', 'Alba'],
    ['accents ISO', 'Meriem i Núria', 'Meriem i Núria'],
    ['punt i coma', 'Família; Montseny', 'Família; Montseny'],
    ['cometes CSV', 'Nom "entre cometes"', 'Nom "entre cometes"'],
] as [$label, $original, $expected]) {
    $stream = fopen('php://temp', 'w+');
    aoWriteCsvRow($stream, [$original, 'ca']);
    rewind($stream);
    $parsed = fgetcsv($stream, 0, ';', '"', '');
    aoAssert(is_array($parsed), $label . ': falta fila');
    aoAssert(mb_convert_encoding($parsed[0], 'UTF-8', 'ISO-8859-1') === $expected,
             $label . ': valor alterat');
    aoAssert($parsed[1] === 'ca', $label . ': segona columna alterada');
    fclose($stream);
    ++$cases;
}

foreach ([
    "Salt\nde línia" => 'DADES_CSV_INVALIDES',
    '=SUM(1+1)' => 'DADES_CSV_INVALIDES',
    '+1+2' => 'DADES_CSV_INVALIDES',
    '@cmd' => 'DADES_CSV_INVALIDES',
    '🙂' => 'CARACTER_NO_ADMES_CSV',
] as $value => $expectedCode) {
    try {
        aoCsvCell($value);
        throw new RuntimeException('No rebutja valor invàlid: ' . $expectedCode);
    } catch (AOCsvException $error) {
        aoAssert($error->getMessage() === $expectedCode,
                 'Codi erroni per a entrada invàlida');
    }
    ++$cases;
}
echo "PASS: " . $cases . " proves CSV (escapat, accents i dades invàlides).\n";
