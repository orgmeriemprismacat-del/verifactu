<?php

$root = dirname(__DIR__, 2);
require_once $root . '/LegacyInvoiceReadContext.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();

    $dnies = (string) ($_GET['dnies'] ?? '');
    $orderBy = (string) ($_GET['orderBy'] ?? '');
    $asc = (string) ($_GET['asc'] ?? '');

    echo $intranet->mostrarTaulaUsuaris2_Alumnes($dnies, $orderBy, $asc);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
