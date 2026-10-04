<?php

// UC-017: retirat. El checkout candidat crea la intenció al SIF i envia
// directament el formulari signat a Redsys. No s'accepta PII/import per GET
// ni s'envia cap correu d'"intent de pagament" abans del callback autoritatiu.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

echo 'Endpoint de preparació llegat retirat.';
