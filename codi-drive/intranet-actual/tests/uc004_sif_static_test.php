<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$repo = dirname($root, 2);

$js = readFileOrFail($root . '/js/alumnes-genera-factura-abans-pagar.js');
$proxy = readFileOrFail($root . '/ajax/alumnes/sifFacturaAbansPagar.php');
$access = readFileOrFail($root . '/SifInvoiceBeforePaymentAccess.php');
$client = readFileOrFail($root . '/SifInternalApiClient.php');
$sifEndpoint = readFileOrFail($repo . '/sif/public/api/factures/before-payment.php');

foreach ([
    'generaFacturaElectronica_Factures.php',
    'calcularTextData.php',
    'mostraDadesFacturaElectronica_Factures.php',
    'mostraInscripcionsFacturaElectronica_Factures.php',
    'mostraPrevFactura_Factures.php',
    'descarregaFactura.php',
    'eliminarArxiu.php',
] as $forbidden) {
    assertFalse(
        str_contains($js, $forbidden),
        "UC-004 JS must not call legacy fiscal/document endpoint: {$forbidden}"
    );
}

foreach ([
    'sifFacturaAbansPagarToken.php',
    'sifFacturaAbansPagarEntitats.php',
    'sifFacturaAbansPagar.php',
    'expected_fingerprint',
    'X-CSRF-Token',
] as $required) {
    assertTrue(str_contains($js, $required), "Missing secure UC-004 JS token: {$required}");
}

foreach ([
    'SifInvoiceBeforePaymentAccess::resolve',
    'SifInvoiceBeforePaymentAccess::assertCsrf',
    'previewInvoiceBeforePayment',
    'confirmInvoiceBeforePayment',
    'SIF_INTERNAL_UC004_SIGNED_PATH',
] as $required) {
    assertTrue(str_contains($proxy, $required), "Missing UC-004 proxy control: {$required}");
}

foreach ([
    'consultaRolsUsuari',
    'consultaRolsEdiicio',
    'replaceRols',
    'random_bytes(32)',
    'hash_equals',
] as $required) {
    assertTrue(str_contains($access, $required), "Missing UC-004 access control: {$required}");
}

foreach ([
    'hash_hmac',
    'X-SIF-Request-Id',
    'X-SIF-Actor-Id',
    'X-SIF-Actor-Roles',
    'previewInvoiceBeforePayment',
    'confirmInvoiceBeforePayment',
] as $required) {
    assertTrue(str_contains($client, $required), "Missing internal API client control: {$required}");
}

foreach ([
    'InternalApiAuthenticator',
    'InternalInvoiceBeforePaymentScopeResolver',
    'InvoiceBeforePaymentCommandService',
    'InvoiceBeforePaymentCoverageRepository',
] as $required) {
    assertTrue(str_contains($sifEndpoint, $required), "Missing SIF UC-004 endpoint control: {$required}");
}

assertFalse(
    str_contains($sifEndpoint, "payload['created_by']"),
    'SIF UC-004 endpoint must derive created_by from the signed actor'
);

echo "PASS: UC-004 uses session role + CSRF + HMAC + authoritative SIF flow.\n";

function readFileOrFail(string $path): string
{
    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Could not read {$path}");
    }

    return $content;
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertFalse(bool $condition, string $message): void
{
    assertTrue(!$condition, $message);
}
