<?php

// UC-004 cutover boundary.
//
// The invoice-before-payment screen issues exclusively through the signed SIF
// command bridge:
//   browser -> sifFacturaAbansPagar.php -> SIF /api/factures/before-payment.php.
//
// Keep this historical URL fail-closed so stale clients or direct POSTs cannot
// bypass session/role checks, CSRF, HMAC authentication, idempotency, fiscal
// chaining and the UC-004 inscription coverage guard.

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

echo json_encode([
    'ok' => false,
    'error' => 'Flux llegat de factura abans de pagar retirat. Utilitza el circuit SIF.',
    'code' => 'UC004_LEGACY_MUTATION_RETIRED',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
