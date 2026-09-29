<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

ob_start();
require_once $root . '/inc/comprovarSessio.php';
$sessionValidationOutput = ob_get_clean();

if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Session not authorized']);
    return;
}

require_once $root . '/SifInternalApiClient.php';

$usuariObject = null;

try {
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

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        return;
    }

    $client = new SifInternalApiClient();
    $action = strtolower(trim((string) ($payload['action'] ?? '')));

    if ($action === 'view') {
        $response = $client->viewInvoice(
            $actorId,
            $roles,
            (string) ($payload['uuid_factura'] ?? '')
        );
    } elseif ($action === 'search') {
        $criteria = $payload['criteria'] ?? [];
        if (!is_array($criteria)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid invoice search criteria']);
            return;
        }

        $response = $client->searchInvoices(
            $actorId,
            $roles,
            $criteria,
            (int) ($payload['limit'] ?? 50)
        );
    } else {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Unknown invoice query action']);
        return;
    }

    $status = (int) ($response['_http_status'] ?? 200);
    unset($response['_http_status']);

    if ($status === 401 || $status >= 500 || $status === 0) {
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'error' => 'SIF internal service unavailable',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    http_response_code($status >= 100 && $status <= 599 ? $status : 502);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'SIF invoice query failed',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if (is_object($usuariObject)) {
        $_SESSION['usuari'] = serialize($usuariObject);
    }
}
