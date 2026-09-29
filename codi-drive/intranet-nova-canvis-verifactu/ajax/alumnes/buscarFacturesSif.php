<?php

$intranetRoot = dirname(__DIR__, 2);
if (!chdir($intranetRoot)) {
    throw new RuntimeException('No es pot resoldre l\'arrel de la intranet');
}

include('inc/comprovarSessio.php');
include('SifInternalClient.php');

try {
    if (empty($configOk) || !isset($_SESSION['usuari'])) {
        throw new RuntimeException('Sessió no vàlida', 401);
    }

    $_SESSION['usuari'] = unserialize($_SESSION['usuari']);

    $criteria = [
        'uuid_factura' => isset($_GET['uuid_factura']) ? trim((string) $_GET['uuid_factura']) : '',
        'num_visible' => isset($_GET['num_visible']) ? trim((string) $_GET['num_visible']) : '',
        'billing_nif' => isset($_GET['billing_nif']) ? trim((string) $_GET['billing_nif']) : '',
        'billing_email' => isset($_GET['billing_email']) ? trim((string) $_GET['billing_email']) : '',
        'factura_relacionada' => isset($_GET['factura_relacionada']) ? trim((string) $_GET['factura_relacionada']) : '',
    ];

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $result = (new SifInternalClient())->searchInvoices($_SESSION['usuari'], $criteria, $limit);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $_SESSION['usuari'] = serialize($_SESSION['usuari']);
} catch (Throwable $e) {
    http_response_code($e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (isset($_SESSION['usuari']) && is_object($_SESSION['usuari'])) {
        $_SESSION['usuari'] = serialize($_SESSION['usuari']);
    }
}
