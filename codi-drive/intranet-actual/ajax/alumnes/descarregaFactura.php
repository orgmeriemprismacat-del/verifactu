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

    echo $intranet->generaFactura((int) $id, true);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
