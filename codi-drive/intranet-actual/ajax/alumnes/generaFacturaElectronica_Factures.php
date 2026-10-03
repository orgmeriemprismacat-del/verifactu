<?php

// UC-004 cutover boundary.
//
// The invoice-before-payment screen now issues exclusively through the signed
// SIF command bridge:
//   browser -> sifFacturaAbansPagar.php -> SIF before-payment.php.
//
// Keep this legacy URL fail-closed so a stale client, bookmark or direct POST
// cannot bypass server-side authorization, CSRF, idempotency, fiscal chaining
// and the UC-004 coverage guard.
//
// The legacy Intranet::generarFacturaElectronica_Alumnes() implementation is
// retained only as historical code until the broader legacy cleanup. It is no
// longer an authorised fiscal entry point.

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

echo json_encode([
    'ok' => false,
    'error' => 'Flux llegat de factura abans de pagar retirat. Utilitza el circuit SIF.',
    'code' => 'UC004_LEGACY_MUTATION_RETIRED',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
