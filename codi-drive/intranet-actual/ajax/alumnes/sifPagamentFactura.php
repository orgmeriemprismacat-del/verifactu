<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
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

require_once $root . '/SifExistingInvoicePaymentAccess.php';
require_once $root . '/SifInternalApiClient.php';
require_once $root . '/Uc002LegacyPaymentProjectionApplier.php';
require_once $root . '/ConnexioWeb.php';

$user = null;
$intranet = null;

try {
    $user = is_string($_SESSION['usuari']) ? unserialize($_SESSION['usuari']) : $_SESSION['usuari'];
    $intranet = is_string($_SESSION['intranet']) ? unserialize($_SESSION['intranet']) : $_SESSION['intranet'];

    if (!is_object($user) || !is_object($intranet)) {
        throw new RuntimeException('Invalid authenticated session', 401);
    }

    if (!SifExistingInvoicePaymentAccess::authoritativeEnabled()) {
        throw new RuntimeException('Authoritative UC-002 payment flow is not enabled', 409);
    }

    $actor = SifExistingInvoicePaymentAccess::resolve($user, $intranet);
    SifExistingInvoicePaymentAccess::assertCsrf($_SERVER);

    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (!str_starts_with($contentType, 'application/json')) {
        throw new RuntimeException('Content-Type must be application/json', 415);
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid JSON', 400);
    }

    $numVisible = trim((string) ($payload['num_visible'] ?? ''));
    $amountRaw = trim(str_replace(',', '.', (string) ($payload['amount'] ?? '')));
    $movementDate = trim((string) ($payload['movement_date'] ?? ''));
    $bank = trim((string) ($payload['bank'] ?? ''));
    $notes = trim((string) ($payload['notes'] ?? ''));
    $reference = trim((string) ($payload['reference'] ?? ''));
    $requestId = strtolower(trim((string) ($payload['request_id'] ?? '')));

    if ($numVisible === '' || strlen($numVisible) > 30) {
        throw new RuntimeException('Invalid SIF invoice number', 422);
    }
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $amountRaw) || (float) $amountRaw <= 0.0) {
        throw new RuntimeException('Invalid payment amount', 422);
    }
    if ($movementDate === '' || strlen($movementDate) > 40) {
        throw new RuntimeException('Invalid payment date', 422);
    }
    if ($bank === '' || mb_strlen($bank, 'UTF-8') > 120) {
        throw new RuntimeException('Invalid payment bank', 422);
    }
    if (mb_strlen($notes, 'UTF-8') > 500 || mb_strlen($reference, 'UTF-8') > 160) {
        throw new RuntimeException('Payment notes or reference are too long', 422);
    }
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $requestId) !== 1) {
        throw new RuntimeException('Invalid payment request id', 422);
    }

    $payment = [
        'idempotency_key' => 'INTRANET|UC002|REQ:' . $requestId,
        'amount' => number_format((float) $amountRaw, 2, '.', ''),
        'movement_date' => $movementDate,
        'bank' => $bank,
        'method' => 'TRANSFERENCIA',
    ];
    if ($notes !== '') {
        $payment['notes'] = $notes;
    }
    if ($reference !== '') {
        $payment['reference'] = $reference;
    }

    $client = new SifInternalApiClient();
    $response = $client->registerExistingInvoicePayment(
        $actor['actor_id'],
        $actor['roles'],
        ['num_visible' => $numVisible],
        $payment
    );

    $status = (int) ($response['_http_status'] ?? 0);
    unset($response['_http_status']);

    if ($status === 0 || $status === 401 || $status >= 500) {
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'error' => 'SIF internal payment service unavailable',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($status < 200 || $status >= 300 || ($response['payment_committed'] ?? false) !== true) {
        http_response_code($status >= 400 && $status <= 499 ? $status : 502);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if (($response['legacy_projection_status'] ?? '') !== 'READY'
        || !is_array($response['legacy_projection'] ?? null)
    ) {
        http_response_code(202);
        $response['legacy_sync_status'] = 'PENDING_RETRY';
        $response['warning'] = 'Pagament SIF confirmat; sincronització llegada pendent.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    try {
        $legacySync = (new Uc002LegacyPaymentProjectionApplier())->apply(
            $response['legacy_projection'],
            $movementDate,
            (string) ($response['uuid_payment'] ?? '')
        );
        $response['legacy_sync_status'] = 'SYNCED';
        $response['legacy_sync'] = $legacySync;
        http_response_code(200);
    } catch (Throwable $syncException) {
        // Payment is already committed in SIF. Never report it as an uncommitted
        // economic failure: the same request_id can safely retry only the sync.
        http_response_code(202);
        $response['legacy_sync_status'] = 'PENDING_RETRY';
        $response['warning'] = 'Pagament SIF confirmat; sincronització llegada pendent.';
        $response['legacy_sync_error'] = substr(
            str_replace(["\r", "\n"], ' ', $syncException->getMessage()),
            0,
            240
        );
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $status = (int) $exception->getCode();
    http_response_code($status >= 400 && $status <= 599 ? $status : 500);
    echo json_encode([
        'ok' => false,
        'error' => $status >= 500
            ? 'UC-002 payment proxy failed'
            : $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if (is_object($user)) {
        $_SESSION['usuari'] = serialize($user);
    }
    if (is_object($intranet)) {
        $_SESSION['intranet'] = serialize($intranet);
    }
}
