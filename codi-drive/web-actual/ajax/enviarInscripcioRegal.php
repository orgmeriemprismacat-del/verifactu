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

require_once '../regal/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include('../Text.php');
include("../Numero.php");
include('../Url.php');
include('../RegalCurs.php');
include("../Mail.php");
include("../MailSMTP.php");
include("../MailSMTPComvive.php");

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo '<p>Error: mètode no permès.</p>';
    return;
}

try {
    $preview = $_SESSION['uc017_gift_preview'] ?? null;
    $previewToken = trim((string) ($_POST['previewToken'] ?? ''));
    $codiCurs = strtoupper(trim((string) ($_POST['codiCurs'] ?? '')));

    if (!is_array($preview)
        || !isset($preview['csrf'], $preview['code'], $preview['course'], $preview['issued_at'])
        || $previewToken === ''
        || !hash_equals((string) $preview['csrf'], $previewToken)
        || !hash_equals((string) $preview['course'], $codiCurs)
        || (time() - (int) $preview['issued_at']) > 3600
    ) {
        throw new RuntimeException('INVALID_OR_EXPIRED_GIFT_PREVIEW');
    }

    $nom = trim((string) ($_POST['nom'] ?? ''));
    $cog = trim((string) ($_POST['cog'] ?? ''));
    $dni = trim((string) ($_POST['dni'] ?? ''));
    $telf = trim((string) ($_POST['telf'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $adreca = trim((string) ($_POST['adreca'] ?? ''));
    $cp = trim((string) ($_POST['codiPostal'] ?? ''));
    $poblacio = trim((string) ($_POST['poblacio'] ?? ''));
    $comentaris = trim((string) ($_POST['comentaris'] ?? ''));
    $codiRegal = (string) $preview['code'];
    $estilRegal = trim((string) ($_POST['estilRegal'] ?? ''));
    $desti = trim((string) ($_POST['desti'] ?? ''));
    $origen = trim((string) ($_POST['origen'] ?? ''));
    $dedicatoria = trim((string) ($_POST['dedicatoria'] ?? ''));

    $regal = new RegalCurs('ordinador');

    // Els quatre camps econòmics/descriptor ja no provenen del navegador.
    // RegalCurs els recalcula de manera autoritativa a partir de $codiCurs.
    $regal->enviarInscripcioRegal(
        $nom,
        $cog,
        $dni,
        $telf,
        $email,
        $adreca,
        $cp,
        $poblacio,
        $comentaris,
        $codiCurs,
        null,
        null,
        null,
        null,
        $codiRegal,
        $estilRegal,
        $origen,
        $desti,
        $dedicatoria
    );

    unset($_SESSION['uc017_gift_preview']);
}
catch(Throwable $e) {
    http_response_code(400);
    error_log('UC-017 gift reservation rejected: ' . get_class($e));
    echo '<p>Error en preparar la comanda del regal. Torna a iniciar el procés.</p>';
}
