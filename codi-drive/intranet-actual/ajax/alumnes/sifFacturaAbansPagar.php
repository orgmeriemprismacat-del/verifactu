<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

ob_start();
require_once $root . '/inc/comprovarSessio.php';
ob_end_clean();

if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari'], $_SESSION['intranet'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Session not authorized']);
    return;
}

require_once $root . '/SifInvoiceBeforePaymentAccess.php';
require_once $root . '/SifInternalApiClient.php';

$user = null;
$intranet = null;

try {
    $user = is_string($_SESSION['usuari'])
        ? unserialize($_SESSION['usuari'])
        : $_SESSION['usuari'];
    $intranet = is_string($_SESSION['intranet'])
        ? unserialize($_SESSION['intranet'])
        : $_SESSION['intranet'];

    if (!is_object($user) || !is_object($intranet)) {
        throw new RuntimeException('Invalid authenticated session', 401);
    }

    $actor = SifInvoiceBeforePaymentAccess::resolve($user, $intranet);
    SifInvoiceBeforePaymentAccess::assertCsrf($_SERVER);

    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (!str_starts_with($contentType, 'application/json')) {
        http_response_code(415);
        echo json_encode(['ok' => false, 'error' => 'Content-Type must be application/json']);
        return;
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        return;
    }

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    if (!in_array($action, ['preview', 'confirm'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Unknown invoice-before-payment action']);
        return;
    }

    $ids = $payload['inscription_ids'] ?? null;
    if (!is_array($ids) || $ids === [] || count($ids) > 200) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid inscription_ids']);
        return;
    }

    $normalizedIds = [];
    $seen = [];
    foreach ($ids as $value) {
        $string = trim((string) $value);
        if ($string === '' || !ctype_digit($string) || (int) $string <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid inscription id']);
            return;
        }

        $id = (int) $string;
        if (isset($seen[$id])) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Duplicate inscription id']);
            return;
        }

        $seen[$id] = true;
        $normalizedIds[] = $id;
    }

    sort($normalizedIds, SORT_NUMERIC);

    $entityId = (int) ($payload['entity_id'] ?? 0);
    if ($entityId <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid entity_id']);
        return;
    }

    $observations = trim((string) ($payload['observations'] ?? ''));
    if (mb_strlen($observations, 'UTF-8') > 500) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Observations are too long']);
        return;
    }

    $url = trim((string) (getenv('SIF_INTERNAL_UC004_URL') ?: ''));
    $signedPath = trim((string) (
        getenv('SIF_INTERNAL_UC004_SIGNED_PATH') ?: '/api/factures/before-payment.php'
    ));

    $client = new SifInternalApiClient($url, $signedPath);

    if ($action === 'preview') {
        $response = $client->previewInvoiceBeforePayment(
            $actor['actor_id'],
            $actor['roles'],
            $normalizedIds,
            $entityId,
            $observations
        );
    } else {
        $fingerprint = strtolower(trim((string) ($payload['expected_fingerprint'] ?? '')));
        if (preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid expected_fingerprint']);
            return;
        }

        $response = $client->confirmInvoiceBeforePayment(
            $actor['actor_id'],
            $actor['roles'],
            $normalizedIds,
            $entityId,
            $fingerprint,
            $observations
        );
    }

    $status = (int) ($response['_http_status'] ?? 0);
    unset($response['_http_status']);

    if ($status === 401 || $status === 0 || $status >= 500) {
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
    $status = (int) $exception->getCode();
    if ($status === 403) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invoice-before-payment access denied']);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'SIF invoice-before-payment proxy failed']);
    }
} finally {
    if (is_object($user)) {
        $_SESSION['usuari'] = serialize($user);
    }
    if (is_object($intranet)) {
        $_SESSION['intranet'] = serialize($intranet);
    }
}
