<?php

$root = dirname(__DIR__, 2);
require_once $root . '/Date.php';
require_once $root . '/LegacyInvoiceReadContext.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();

    $dni = (string) ($_GET['dni'] ?? '');
    $cercaPer = (string) ($_GET['cercaPer'] ?? '');

    echo $intranet->mostrarTotesFacturesUsuari_Factures($dni, $cercaPer);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
