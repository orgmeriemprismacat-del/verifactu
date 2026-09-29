<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

ob_start();
require_once $root . '/inc/comprovarSessio.php';
ob_end_clean();

if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Session not authorized']);
    return;
}

require_once $root . '/SifPanelLaunchToken.php';

$usuariObject = null;

try {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        return;
    }

    $csrf = (string) ($payload['csrf_token'] ?? '');
    $stored = (string) ($_SESSION['sif_verifactu_csrf'] ?? '');
    if ($stored === '' || $csrf === '' || !hash_equals($stored, $csrf)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        return;
    }

    $usuariObject = unserialize($_SESSION['usuari']);
    if (!is_object($usuariObject)) {
        throw new RuntimeException('Invalid authenticated session');
    }

    $actorText = $usuariObject->getUsuari();
    $actorId = is_object($actorText) && method_exists($actorText, 'get')
        ? trim((string) $actorText->get())
        : '';
    $roles = $usuariObject->getRols();
    if (!is_array($roles)) {
        $roles = [];
    }
    if ($actorId === '' || $roles === []) {
        throw new RuntimeException('Authenticated actor has no usable identity or roles');
    }

    $launch = (new SifPanelLaunchToken())->create($actorId, $roles);
    echo json_encode(['ok' => true] + $launch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'SIF panel launch failed']);
} finally {
    if (is_object($usuariObject)) {
        $_SESSION['usuari'] = serialize($usuariObject);
    }
}
