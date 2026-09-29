<?php

$root = dirname(__DIR__, 2);
require_once $root . '/ConnexioMoodle.php';
require_once $root . '/ConnexioMoodleAntic.php';
require_once $root . '/Date.php';
require_once $root . '/LegacyInvoiceReadContext.php';
require_once $root . '/SifInternalApiClient.php';
require_once $root . '/SifLegacyInvoiceMutationGuard.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();

    $id = trim((string) ($_GET['id'] ?? ''));
    if (!ctype_digit($id) || (int) $id <= 0) {
        throw new InvalidArgumentException('Identificador de factura no vàlid', 422);
    }

    (new SifLegacyInvoiceMutationGuard())->assertLegacyMutationAllowed(
        $user,
        (int) $id
    );

    echo $intranet->modalAnularFactura_Factures((int) $id);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
