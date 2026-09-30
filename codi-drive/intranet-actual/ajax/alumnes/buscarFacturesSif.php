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

    $criteria = [
        'uuid_factura' => trim((string) ($_POST['uuid_factura'] ?? '')),
        'num_visible' => trim((string) ($_POST['num_visible'] ?? '')),
        'billing_nif' => trim((string) ($_POST['billing_nif'] ?? '')),
        'billing_email' => trim((string) ($_POST['billing_email'] ?? '')),
        'factura_relacionada' => trim((string) ($_POST['factura_relacionada'] ?? '')),
    ];
    $criteria = array_filter(
        $criteria,
        static fn ($value): bool => $value !== ''
    );

    [$actorId, $roles] = SifAuthenticatedActor::fromUser($user);
    $response = (new SifInternalApiClient())->searchInvoices(
        $actorId,
        $roles,
        $criteria,
        50
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
