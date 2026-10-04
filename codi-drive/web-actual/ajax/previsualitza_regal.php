<?php

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
        throw new RuntimeException('INVALID_GIFT_PREVIEW_CSRF');
    }

    $codiCurs = strtoupper(trim((string) ($_POST['codi'] ?? '')));
    $estil = trim((string) ($_POST['estil'] ?? ''));
    $desti = trim((string) ($_POST['desti'] ?? ''));
    $origen = trim((string) ($_POST['origen'] ?? ''));
    $dedicatoria = trim((string) ($_POST['dedicatoria'] ?? ''));
    $dispositiu = trim((string) ($_POST['dispositiu'] ?? 'ordinador'));

    if ($codiCurs === '' || preg_match('/^[A-Z0-9_-]{1,30}$/D', $codiCurs) !== 1) {
        throw new RuntimeException('INVALID_GIFT_COURSE');
    }

    $preview = $_SESSION['uc017_gift_preview'] ?? null;
    $expired = !is_array($preview)
        || !isset($preview['issued_at'])
        || (time() - (int) $preview['issued_at']) > 3600;
    $sameCourse = is_array($preview)
        && hash_equals((string) ($preview['course'] ?? ''), $codiCurs);

    if ($expired || !$sameCourse) {
        $preview = [
            'course' => $codiCurs,
            'code' => uc017GenerateUniqueGiftCode(),
            'csrf' => bin2hex(random_bytes(32)),
            'issued_at' => time(),
        ];
        $_SESSION['uc017_gift_preview'] = $preview;
    }

    $regal = new RegalCurs($dispositiu);
    $mostrar = $regal->mostrarPrevisualitzacio(
        $codiCurs,
        $estil,
        (string) $preview['code'],
        $origen,
        $desti,
        $dedicatoria
    );

    $csrf = htmlspecialchars((string) $preview['csrf'], ENT_QUOTES, 'UTF-8');
    echo $mostrar;
    echo "<span id='uc017-gift-preview-token' data-token='{$csrf}' hidden></span>";
}
catch(Throwable $e) {
    if ($e->getCode()==404)
        echo mostrarPagina404();
    else
        echo missatgeError($e->getCode());
}

function uc017GenerateUniqueGiftCode(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $connexio = new ConnexioBBDDSTMT();
    $connexio->connectarBD();

    try {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $stmt = $connexio->prepare('SELECT ID FROM regal WHERE CODI=? LIMIT 1');
            $stmt->bind_param('s', $code);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows() > 0;
            $connexio->closeStmt();

            if (!$exists) {
                return $code;
            }
        }
    } finally {
        $connexio->desconectarBD();
    }

    throw new RuntimeException('GIFT_CODE_GENERATION_EXHAUSTED');
}
