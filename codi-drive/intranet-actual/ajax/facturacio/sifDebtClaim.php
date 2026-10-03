<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (!filter_var(getenv('SIF_DEBT_CLAIM_UI_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Debt claim SIF bridge disabled']);
    return;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

require_once $root . '/LegacyDebtClaimContext.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';
require_once $root . '/SifAuthenticatedActor.php';
require_once $root . '/SifInternalDebtClaimClient.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyDebtClaimContext::open();
    LegacyInvoiceMutationAuthorization::assertSameOrigin();
    if (strcasecmp((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') !== 0) {
        throw new RuntimeException('Petició AJAX no vàlida', 403);
    }

    $storedCsrf = LegacyDebtClaimContext::csrfToken();
    $receivedCsrf = trim((string) ($_POST['csrfToken'] ?? ''));
    if ($receivedCsrf === '' || !hash_equals($storedCsrf, $receivedCsrf)) {
        throw new RuntimeException('Token CSRF no vàlid', 403);
    }

    [$actorId, $roles] = SifAuthenticatedActor::fromUser($user);
    $client = new SifInternalDebtClaimClient();

    $action = strtolower(trim((string) ($_POST['action'] ?? '')));
    $selector = invoiceSelector($_POST);

    if ($action === 'preview') {
        sendDebtClaimResult($client->preview($actorId, $roles, $selector));
        return;
    }

    LegacyInvoiceMutationAuthorization::assertCanEdit(
        $user,
        $intranet,
        '/facturacio/morosos/'
    );

    if ($action === 'record_notice') {
        $stage = strtoupper(requiredDebtClaimString($_POST['stage'] ?? null, 'Falta etapa de reclamació'));
        if (!in_array($stage, ['FINAL_REMINDER', 'FIRST_CLAIM', 'FINAL_CLAIM'], true)) {
            throw new InvalidArgumentException('Etapa de reclamació no vàlida', 422);
        }

        $operationId = operationId($_POST['operation_id'] ?? null);
        $payload = array_merge($selector, [
            'action' => $stage,
            'idempotency_key' => debtClaimIdempotencyKey($stage, $selector, $operationId),
            'reason_code' => debtClaimReason($_POST['reason_code'] ?? null),
            'correlation_id' => 'INTRANET-DEBT-CLAIM|' . $operationId,
        ]);
        $notes = optionalDebtClaimString($_POST['notes'] ?? null, 4000);
        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        sendDebtClaimResult($client->recordNotice($actorId, $roles, $payload));
        return;
    }

    if ($action === 'reconcile_after_payment') {
        $operationId = operationId($_POST['operation_id'] ?? null);
        $payload = array_merge($selector, [
            'idempotency_key' => debtClaimIdempotencyKey('RECONCILE_AFTER_PAYMENT', $selector, $operationId),
            'reason_code' => debtClaimReason($_POST['reason_code'] ?? 'PAYMENT_RECONCILIATION'),
            'correlation_id' => 'INTRANET-DEBT-CLAIM|' . $operationId,
        ]);
        $uuidPayment = trim((string) ($_POST['uuid_payment'] ?? ''));
        if ($uuidPayment !== '') {
            if (preg_match('/^[0-9a-fA-F-]{36}$/D', $uuidPayment) !== 1) {
                throw new InvalidArgumentException('UUID de pagament no vàlid', 422);
            }
            $payload['uuid_payment'] = strtolower($uuidPayment);
        }

        sendDebtClaimResult($client->reconcileAfterPayment($actorId, $roles, $payload));
        return;
    }

    throw new InvalidArgumentException('Acció de morositat desconeguda', 422);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    $status = $code >= 400 && $code <= 599 ? $code : 500;
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => $status >= 500 ? 'Debt claim SIF operation failed' : $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    LegacyDebtClaimContext::persist($user, $intranet);
}

function invoiceSelector(array $input): array
{
    $uuid = trim((string) ($input['uuid_factura'] ?? ''));
    $num = trim((string) ($input['num_visible'] ?? ''));
    $idInsc = trim((string) ($input['id_insc'] ?? ''));

    $present = ($uuid !== '' ? 1 : 0)
        + ($num !== '' ? 1 : 0)
        + ($idInsc !== '' ? 1 : 0);
    if ($present !== 1) {
        throw new InvalidArgumentException(
            'Cal indicar exactament UUID_FACTURA, NUM_VISIBLE o ID_INSC',
            422
        );
    }

    if ($uuid !== '') {
        if (preg_match('/^[0-9a-fA-F-]{36}$/D', $uuid) !== 1) {
            throw new InvalidArgumentException('UUID de factura no vàlid', 422);
        }
        return ['uuid_factura' => strtolower($uuid)];
    }

    if ($num !== '') {
        if (strlen($num) > 30 || preg_match('/^[A-Za-z0-9\/_-]+$/D', $num) !== 1) {
            throw new InvalidArgumentException('Número visible de factura no vàlid', 422);
        }
        return ['num_visible' => $num];
    }

    if (preg_match('/^[1-9][0-9]*$/D', $idInsc) !== 1) {
        throw new InvalidArgumentException('ID_INSC no vàlid', 422);
    }

    return ['id_insc' => (string) ((int) $idInsc)];
}

function operationId(mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '' || strlen($value) > 120 || preg_match('/^[A-Za-z0-9._:-]+$/D', $value) !== 1) {
        throw new InvalidArgumentException('Identificador d’operació no vàlid', 422);
    }

    return $value;
}

function debtClaimIdempotencyKey(string $action, array $selector, string $operationId): string
{
    if (isset($selector['uuid_factura'])) {
        $invoice = 'UUID:' . $selector['uuid_factura'];
    } elseif (isset($selector['num_visible'])) {
        $invoice = 'NUM:' . $selector['num_visible'];
    } else {
        $invoice = 'INSC:' . $selector['id_insc'];
    }

    return 'DEBT_CLAIM|' . $action . '|' . hash('sha256', $invoice . '|' . $operationId);
}

function debtClaimReason(mixed $value): string
{
    $value = strtoupper(trim((string) $value));
    if ($value === '' || strlen($value) > 80 || preg_match('/^[A-Z0-9_-]+$/D', $value) !== 1) {
        throw new InvalidArgumentException('Motiu de reclamació no vàlid', 422);
    }

    return $value;
}

function requiredDebtClaimString(mixed $value, string $message): string
{
    $value = trim((string) $value);
    if ($value === '') {
        throw new InvalidArgumentException($message, 422);
    }

    return $value;
}

function optionalDebtClaimString(mixed $value, int $max): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    if (strlen($value) > $max) {
        throw new InvalidArgumentException('Camp massa llarg', 422);
    }

    return $value;
}

function sendDebtClaimResult(array $result): void
{
    $status = (int) ($result['_http_status'] ?? 200);
    unset($result['_http_status']);
    http_response_code($status >= 100 && $status <= 599 ? $status : 502);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
