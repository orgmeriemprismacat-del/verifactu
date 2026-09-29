<?php

require_once '../../lib/dompdf/autoload.inc.php';

$root = dirname(__DIR__, 2);
require_once $root . '/LegacyInvoiceReadContext.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();

    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '' || !ctype_digit($id)) {
        throw new InvalidArgumentException('Factura relacionada no vàlida', 422);
    }

    echo $intranet->generaFactura((int) $id, true);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
