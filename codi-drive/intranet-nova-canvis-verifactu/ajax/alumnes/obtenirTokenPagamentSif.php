<?php

include '../../ConnexioIntranet.php';
include '../../Text.php';
include '../../Usuari.php';
include '../../SifPaymentSessionGuard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'status' => 'ERROR', 'error' => 'Method not allowed']);
        return;
    }

    $guard = new SifPaymentSessionGuard();
    $actor = $guard->actor();

    echo json_encode([
        'ok' => true,
        'csrf_token' => $guard->csrfToken(),
        'actor_id' => $actor['actor_id'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    http_response_code($code >= 400 && $code <= 599 ? $code : 500);
    echo json_encode([
        'ok' => false,
        'status' => 'ERROR',
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
