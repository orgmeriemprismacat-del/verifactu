<?php

$root = dirname(__DIR__, 2);
chdir($root);

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

ob_start();
require_once $root . '/inc/comprovarSessio.php';
ob_end_clean();

if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari'])) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Session not authorized']);
    return;
}

require_once $root . '/SifInternalDocumentClient.php';

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
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        return;
    }

    $documentId = (int) ($payload['document_id'] ?? 0);
    if ($documentId <= 0) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Invalid document id']);
        return;
    }

    $response = (new SifInternalDocumentClient())->download(
        $actorId,
        $roles,
        $documentId
    );

    $status = (int) ($response['status'] ?? 0);
    $headers = is_array($response['headers'] ?? null) ? $response['headers'] : [];
    $bytes = (string) ($response['bytes'] ?? '');

    if ($status !== 200) {
        header('Content-Type: application/json; charset=utf-8');

        if ($status === 403) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Document access denied']);
            return;
        }

        if ($status === 404) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Document not found']);
            return;
        }

        if ($status === 409) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'error' => 'Document integrity check failed']);
            return;
        }

        if ($status === 503) {
            http_response_code(503);
            echo json_encode(['ok' => false, 'error' => 'Document unavailable']);
            return;
        }

        http_response_code(502);
        echo json_encode(['ok' => false, 'error' => 'SIF document service unavailable']);
        return;
    }

    $contentType = strtolower(trim((string) ($headers['content-type'] ?? 'application/octet-stream')));
    if (!str_starts_with($contentType, 'application/pdf')
        && !str_starts_with($contentType, 'application/xml')
        && $contentType !== 'application/octet-stream') {
        $contentType = 'application/octet-stream';
    }

    $documentType = strtoupper(trim((string) ($headers['x-sif-document-type'] ?? 'BIN')));
    $extension = $documentType === 'PDF' ? 'pdf' : ($documentType === 'XML' ? 'xml' : 'bin');
    $filename = 'factura-document-' . $documentId . '.' . $extension;

    http_response_code(200);
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . strlen($bytes));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    echo $bytes;
} catch (Throwable $exception) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'SIF document proxy failed']);
} finally {
    if (is_object($usuariObject)) {
        $_SESSION['usuari'] = serialize($usuariObject);
    }
}
