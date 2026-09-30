<?php

$root = dirname(__DIR__, 2);
require_once $root . '/LegacyInvoiceReadContext.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';
require_once $root . '/SifAuthenticatedActor.php';
require_once $root . '/SifInternalDocumentClient.php';

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$user = null;
$intranet = null;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo 'Mètode no permès';
    return;
}

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();
    LegacyInvoiceMutationAuthorization::assertSameOrigin();

    $documentId = trim((string) ($_POST['document_id'] ?? ''));
    if (!ctype_digit($documentId) || (int) $documentId <= 0) {
        throw new InvalidArgumentException('Document SIF no vàlid', 422);
    }

    [$actorId, $roles] = SifAuthenticatedActor::fromUser($user);
    $result = (new SifInternalDocumentClient())->download(
        $actorId,
        $roles,
        (int) $documentId
    );

    $status = (int) ($result['status'] ?? 500);
    if ($status < 200 || $status >= 300) {
        $payload = json_decode((string) ($result['bytes'] ?? ''), true);
        $error = is_array($payload) ? trim((string) ($payload['error'] ?? '')) : '';
        throw new RuntimeException($error !== '' ? $error : 'No s’ha pogut descarregar el document SIF', $status);
    }

    $headers = is_array($result['headers'] ?? null) ? $result['headers'] : [];
    $bytes = (string) ($result['bytes'] ?? '');
    if ($bytes === '') {
        throw new RuntimeException('El document SIF és buit', 503);
    }

    $contentType = trim((string) ($headers['content-type'] ?? 'application/octet-stream'));
    $contentDisposition = trim((string) ($headers['content-disposition'] ?? 'attachment; filename="factura"'));

    header('Content-Type: ' . $contentType);
    header('Content-Disposition: ' . $contentDisposition);
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
