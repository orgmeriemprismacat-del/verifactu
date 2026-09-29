<?php

$root = dirname(__DIR__, 2);
require_once $root . '/Date.php';
require_once $root . '/LegacyInvoiceReadContext.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();

    $idInsc = trim((string) ($_GET['idInsc'] ?? ''));
    if (!ctype_digit($idInsc) || (int) $idInsc <= 0) {
        throw new InvalidArgumentException('Identificador d’inscripció no vàlid', 422);
    }

    echo $intranet->modalConsultaFactura_resultatCerca((int) $idInsc);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
