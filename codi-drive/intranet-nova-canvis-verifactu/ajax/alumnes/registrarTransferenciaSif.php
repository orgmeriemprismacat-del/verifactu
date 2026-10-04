<?php

include '../../ConnexioIntranet.php';
include '../../Text.php';
include '../../Usuari.php';
include '../../SifPaymentSessionGuard.php';
include '../../SifInternalApiClient.php';
include '../../SifManualTransferGateway.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        throw new RuntimeException('Method not allowed', 405);
    }

    $guard = new SifPaymentSessionGuard();
    $actor = $guard->actor();
    $guard->assertAnyRole((array) $actor['roles'], $guard->configuredManualTransferRoles());
    $guard->assertCsrf((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        throw new RuntimeException('Invalid JSON', 400);
    }

    $numFact = trim((string) ($payload['num_fact'] ?? ''));
    $amount = trim((string) ($payload['amount'] ?? ''));
    $movementDate = trim((string) ($payload['movement_date'] ?? ''));
    $bank = trim((string) ($payload['bank'] ?? ''));
    $externalBankEventId = trim((string) ($payload['external_bank_event_id'] ?? ''));
    $notes = trim((string) ($payload['notes'] ?? ''));

    if ($numFact === '') {
        throw new RuntimeException('Missing generated invoice number', 422);
    }
    if ($externalBankEventId === '') {
        throw new RuntimeException('Missing bank movement identifier', 422);
    }
    if (in_array(strtoupper($bank), ['TPV', 'REDSYS'], true)) {
        throw new RuntimeException('Card payments must use the Redsys flow', 422);
    }

    $result = SifManualTransferGateway::fromEnvironment()->register(
        (string) $actor['actor_id'],
        (array) $actor['roles'],
        $numFact,
        $amount,
        $movementDate,
        $externalBankEventId,
        $externalBankEventId,
        $bank,
        $notes !== '' ? $notes : null
    );

    http_response_code(200);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    $httpStatus = $code >= 400 && $code <= 599 ? $code : 500;
    $status = $httpStatus === 409 ? 'CONFLICT' : 'ERROR';

    http_response_code($httpStatus);
    echo json_encode([
        'ok' => false,
        'status' => $status,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
