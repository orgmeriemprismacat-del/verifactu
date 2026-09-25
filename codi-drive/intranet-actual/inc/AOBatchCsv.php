<?php
/**
 * Funcions pures de codificació/escapat del CSV d'aules obertes.
 * PHP >= 8.0; el fitxer no executa operacions de BD o HTTP en carregar-se.
 */
declare(strict_types=1);

final class AOCsvException extends RuntimeException {}

function aoCsvCell($value): string
{
    $value = (string) $value;
    if (preg_match('/[\r\n]/', $value) ||
        preg_match('/^\s*[=+\-@]/u', $value)) {
        throw new AOCsvException('DADES_CSV_INVALIDES');
    }
    if (!mb_check_encoding($value, 'UTF-8')) {
        throw new AOCsvException('CODIFICACIO_INVALIDA');
    }
    $encoded = mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    if (mb_convert_encoding($encoded, 'UTF-8', 'ISO-8859-1') !== $value) {
        throw new AOCsvException('CARACTER_NO_ADMES_CSV');
    }
    return $encoded;
}

function aoWriteCsvRow($handle, array $fields): void
{
    $encoded = array_map('aoCsvCell', $fields);
    if (fputcsv($handle, $encoded, ';', '"', '') === false) {
        throw new AOCsvException('ERROR_ESCRIPTURA_CSV');
    }
}
