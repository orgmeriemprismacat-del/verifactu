<?php

$root = dirname(__DIR__, 2);
require_once $root . '/LegacyInvoiceReadContext.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['GET', 'POST'], true)) {
        throw new RuntimeException('Mètode no permès', 405);
    }
    if ($method === 'POST') {
        LegacyInvoiceMutationAuthorization::assertSameOrigin();
    }
    $request = $method === 'POST' ? $_POST : $_GET;

    $dni = (string) ($request['dni'] ?? '');
    $email = (string) ($request['email'] ?? '');
    $factRel = (string) ($request['factRel'] ?? '');
    $factNum = (string) ($request['factNum'] ?? '');

    echo $intranet->buscarUsuaris_Factures($dni, $email, $factRel, $factNum);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
