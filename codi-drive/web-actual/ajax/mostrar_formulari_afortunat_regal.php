<?php
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

session_set_cookie_params([
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../RegalCurs.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo '<p>Error: mètode no permès.</p>';
    return;
}

try {
    $csrf = trim((string) ($_POST['csrf'] ?? ''));
    $sessionCsrf = (string) ($_SESSION['uc017_gift_csrf'] ?? '');
    if ($csrf === '' || $sessionCsrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        throw new RuntimeException('INVALID_GIFT_FORM_CSRF');
    }

    $codiCurs = strtoupper(trim((string) ($_POST['codi'] ?? '')));
    if ($codiCurs === '' || preg_match('/^[A-Z0-9_-]{1,30}$/D', $codiCurs) !== 1) {
        throw new RuntimeException('INVALID_GIFT_COURSE');
    }

    $regal = new RegalCurs('ordinador');

    // El servidor recalcula preu/hores/descompte dins del mètode.
    // Destinatari/origen/dedicatòria es repoblen al DOM des de l'estat local;
    // no viatgen per URL ni són necessaris per construir el formulari.
    echo $regal->mostrarFormulariAfortunat($codiCurs, '', '', '', null, null, null);
}
catch(Throwable $e) {
    http_response_code(400);
    error_log('UC-017 gift form rejected: ' . get_class($e));
    echo '<p>Error en preparar el formulari del regal.</p>';
}
