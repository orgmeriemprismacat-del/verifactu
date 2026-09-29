<?php
/**
 * Endpoint llegat substituït pel processament únic i autoritzat del lot AO.
 * No permetre invocacions directes que eludeixin CSRF, rol d'edició i ID_INSC.
 * La pantalla actual envia una única petició a processarLotAO.php.
 */
http_response_code(410);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok' => false,
    'error' => 'ENDPOINT_AO_SUBSTITUIT',
    'message' => 'Actualitza la pàgina de pujada d’aules obertes.'
], JSON_UNESCAPED_UNICODE);
