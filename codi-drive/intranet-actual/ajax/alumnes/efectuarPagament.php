<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
session_start();

header('Cache-Control: no-store');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo 'Error: mètode no permès';
    return;
}

$usuariObject = null;
$intranetObject = null;

try {
    if (!isset($_SESSION['usuari'], $_SESSION['intranet'])) {
        throw new RuntimeException('Sessió no autoritzada', 401);
    }

    $usuariObject = unserialize($_SESSION['usuari']);
    $intranetObject = unserialize($_SESSION['intranet']);

    if (!is_object($usuariObject) || !is_object($intranetObject)) {
        throw new RuntimeException('Sessió no vàlida', 401);
    }

    assertPaymentMutationSameOrigin();
    assertPaymentMutationPermission($usuariObject, $intranetObject);

    $idTipus = trim((string) ($_POST['id'] ?? ''));
    $tipus = trim((string) ($_POST['tipus'] ?? ''));
    $pagament = trim((string) ($_POST['pagament'] ?? ''));
    $dataPag = trim((string) ($_POST['dataPag'] ?? ''));
    $banc = trim((string) ($_POST['banc'] ?? ''));
    $obs = trim((string) ($_POST['obs'] ?? ''));
    $numFact = trim((string) ($_POST['numFact'] ?? ''));
    $efact = trim((string) ($_POST['efact'] ?? ''));

    if ($idTipus === '' || $tipus === '') {
        throw new InvalidArgumentException('Identificador o tipus de pagament no vàlid', 422);
    }

    if (!is_numeric($pagament) || (float) $pagament <= 0.0) {
        throw new InvalidArgumentException('Import de pagament no vàlid', 422);
    }

    if ($dataPag === '' || $banc === '') {
        throw new InvalidArgumentException('Data i banc són obligatoris', 422);
    }

    if (!in_array($efact, ['0', '1'], true)) {
        throw new InvalidArgumentException('Indicador de factura electrònica no vàlid', 422);
    }

    echo $intranetObject->efectuarPagament(
        $idTipus,
        $tipus,
        $pagament,
        $dataPag,
        $banc,
        $obs,
        $numFact,
        $efact
    );
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo 'Error: ' . $exception->getMessage();
} finally {
    if (is_object($usuariObject)) {
        $_SESSION['usuari'] = serialize($usuariObject);
    }
    if (is_object($intranetObject)) {
        $_SESSION['intranet'] = serialize($intranetObject);
    }
}

function assertPaymentMutationPermission($user, $intranet): void
{
    if (!method_exists($user, 'tePermisVisualitzacio')
        || !method_exists($intranet, 'consultaRolsEdiicio')) {
        throw new RuntimeException('No es pot validar el permís de pagaments', 403);
    }

    $roles = (string) $intranet->consultaRolsEdiicio('/alumnes/pagaments/');
    if ($roles === '' || !$user->tePermisVisualitzacio($roles)) {
        throw new RuntimeException('No tens permisos per registrar pagaments', 403);
    }
}

function assertPaymentMutationSameOrigin(): void
{
    $fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite === 'cross-site') {
        throw new RuntimeException('Origen de petició no autoritzat', 403);
    }

    $configured = getenv('INTRANET_ALLOWED_ORIGINS') ?: 'https://intranet.prisma.cat';
    $allowedOrigins = array_values(array_filter(array_map(
        static fn (string $value): string => rtrim(trim($value), '/'),
        preg_split('/[;,]/', $configured) ?: []
    )));

    if ($allowedOrigins === []) {
        throw new RuntimeException('No hi ha orígens de la intranet configurats', 403);
    }

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '' && !in_array(rtrim($origin, '/'), $allowedOrigins, true)) {
        throw new RuntimeException('Origen de petició no autoritzat', 403);
    }

    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($origin === '' && $referer !== '') {
        $scheme = (string) parse_url($referer, PHP_URL_SCHEME);
        $host = (string) parse_url($referer, PHP_URL_HOST);
        if ($scheme === '' || $host === '') {
            throw new RuntimeException('Origen de petició no autoritzat', 403);
        }

        $refererOrigin = $scheme . '://' . $host;
        $port = parse_url($referer, PHP_URL_PORT);
        if ($port !== null) {
            $refererOrigin .= ':' . $port;
        }

        if (!in_array($refererOrigin, $allowedOrigins, true)) {
            throw new RuntimeException('Origen de petició no autoritzat', 403);
        }
    }

    $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
    if ($requestedWith !== 'xmlhttprequest') {
        throw new RuntimeException('Petició AJAX requerida', 403);
    }
}
