<?php

$root = dirname(__DIR__, 2);
require_once $root . '/LegacyInvoiceReadContext.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';
require_once $root . '/SifAuthenticatedActor.php';
require_once $root . '/SifInternalApiClient.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

$user = null;
$intranet = null;

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Mètode no permès']);
    return;
}

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();
    LegacyInvoiceMutationAuthorization::assertSameOrigin();

    if (!filter_var(getenv('SIF_UC007_QUERY_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
        throw new RuntimeException('Consulta SIF UC-007 desactivada', 503);
    }

    $uuidFactura = trim((string) ($_POST['uuid_factura'] ?? ''));
    if ($uuidFactura === '') {
        throw new InvalidArgumentException('UUID de factura obligatori', 422);
    }

    [$actorId, $roles] = SifAuthenticatedActor::fromUser($user);
    $response = (new SifInternalApiClient())->viewInvoice(
        $actorId,
        $roles,
        $uuidFactura
    );

    $status = (int) ($response['_http_status'] ?? 200);
    unset($response['_http_status']);
    http_response_code($status > 0 ? $status : 200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}
