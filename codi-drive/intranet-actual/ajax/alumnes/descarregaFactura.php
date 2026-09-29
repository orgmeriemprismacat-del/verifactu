<?php

require_once '../../lib/dompdf/autoload.inc.php';

$root = dirname(__DIR__, 2);
require_once $root . '/LegacyInvoiceReadContext.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';
require_once $root . '/SifInternalApiClient.php';
require_once $root . '/SifLegacyInvoiceMutationGuard.php';

$user = null;
$intranet = null;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo 'Error: mètode no permès';
    return;
}

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();
    LegacyInvoiceMutationAuthorization::assertSameOrigin();

    $id = trim((string) ($_POST['id'] ?? ''));
    if ($id === '' || !ctype_digit($id) || (int) $id <= 0) {
        throw new InvalidArgumentException('Factura relacionada no vàlida', 422);
    }

    (new SifLegacyInvoiceMutationGuard())->assertLegacyRelationAllowed(
        $user,
        (int) $id
    );

    $tempRoot = realpath(__DIR__);
    if ($tempRoot === false || !chdir($tempRoot)) {
        throw new RuntimeException('No es pot resoldre el directori temporal de factures', 500);
    }

    $filename = trim((string) $intranet->generaFactura((int) $id, true));
    if ($filename === ''
        || basename($filename) !== $filename
        || preg_match('/^[A-Za-z0-9._-]+\.pdf$/D', $filename) !== 1) {
        throw new RuntimeException('El generador ha retornat un nom de fitxer no vàlid', 500);
    }

    $generated = realpath($tempRoot . DIRECTORY_SEPARATOR . $filename);
    if ($generated === false
        || !is_file($generated)
        || !str_starts_with($generated, rtrim($tempRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('El PDF no s’ha pogut generar correctament', 500);
    }

    echo $filename;
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
