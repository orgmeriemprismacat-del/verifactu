<?php

$root = dirname(__DIR__, 2);
require_once $root . '/LegacyInvoiceReadContext.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();

    $dni = (string) ($_GET['dni'] ?? '');
    $email = (string) ($_GET['email'] ?? '');
    $factRel = (string) ($_GET['factRel'] ?? '');
    $factNum = (string) ($_GET['factNum'] ?? '');

    echo $intranet->buscarUsuaris_Factures($dni, $email, $factRel, $factNum);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
