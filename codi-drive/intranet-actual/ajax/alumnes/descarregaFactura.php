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

    $configuredTempRoot = trim((string) (
        getenv('SIF_LEGACY_INVOICE_TEMP_ROOT')
            ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'prisma-uc007-invoices')
    ));
    if ($configuredTempRoot === '') {
        throw new RuntimeException('No hi ha directori temporal privat configurat', 500);
    }
    if (!is_dir($configuredTempRoot)
        && !mkdir($configuredTempRoot, 0700, true)
        && !is_dir($configuredTempRoot)) {
        throw new RuntimeException('No es pot crear el directori temporal privat de factures', 500);
    }

    $tempRoot = realpath($configuredTempRoot);
    if ($tempRoot === false || !is_dir($tempRoot) || !is_writable($tempRoot) || !chdir($tempRoot)) {
        throw new RuntimeException('No es pot resoldre el directori temporal privat de factures', 500);
    }

    $filename = trim((string) $intranet->generaFactura((int) $id, true, false));
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

    $bytes = file_get_contents($generated);
    if ($bytes === false) {
        throw new RuntimeException('No s’ha pogut llegir el PDF temporal', 500);
    }

    // UC-007: el temporal no es publica com a URL. Es llegeix i s'elimina
    // abans de lliurar els bytes per la resposta autenticada.
    if (!unlink($generated)) {
        throw new RuntimeException('No s’ha pogut eliminar el PDF temporal', 500);
    }

    http_response_code(200);
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($bytes));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
