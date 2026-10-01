<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$enabled = filter_var(getenv('SIF_USOC_UI_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN);
if (!$enabled) {
    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'resolution' => 'FEATURE_DISABLED',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

require_once $root . '/LegacyUsocContext.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';
require_once $root . '/SifAuthenticatedActor.php';
require_once $root . '/SifInternalUsocClient.php';

$usuariObject = null;
$intranetObject = null;

try {
    [$usuariObject, $intranetObject] = LegacyUsocContext::open();
    LegacyInvoiceMutationAuthorization::assertSameOrigin();
    [$actorId, $roles] = SifAuthenticatedActor::fromUser($usuariObject);

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        return;
    }

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    $client = new SifInternalUsocClient();

    if ($action === 'view') {
        $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid enrollment id');
        $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid IDPAG');
        $response = $client->viewCase($actorId, $roles, $idInsc, $idpag);
    } else {
        LegacyInvoiceMutationAuthorization::assertCanEdit(
            $usuariObject,
            $intranetObject,
            '/alumnes/mostrar-alumne/'
        );

        if ($action === 'issue_entity_invoice') {
            $input = $payload['input'] ?? null;
            if (!is_array($input)) {
                throw new RuntimeException('Invalid USOC entity invoice input', 422);
            }
            $response = $client->issueEntityInvoice($actorId, $roles, $input);
        } elseif ($action === 'register_entity_payment') {
            $uuid = trim((string) ($payload['uuid_entity_invoice'] ?? ''));
            $payment = $payload['payment'] ?? null;
            if ($uuid === '' || !is_array($payment)) {
                throw new RuntimeException('Invalid USOC entity payment input', 422);
            }
            $response = $client->registerEntityPayment($actorId, $roles, $uuid, $payment);
        } elseif ($action === 'execute_cancellation') {
            $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid enrollment id');
            $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid IDPAG');
            $requestId = trim((string) ($payload['request_id'] ?? ''));
            $input = $payload['input'] ?? null;
            if (
                $requestId === ''
                || strlen($requestId) > 120
                || preg_match('/^[A-Za-z0-9._:-]+$/D', $requestId) !== 1
                || !is_array($input)
            ) {
                throw new RuntimeException('Invalid USOC cancellation input', 422);
            }
            $response = $client->executeCancellation(
                $actorId,
                $roles,
                $requestId,
                $idInsc,
                $idpag,
                $input
            );
        } elseif ($action === 'reconcile') {
            $idInsc = positiveInt($payload['id_insc'] ?? null, 'Invalid enrollment id');
            $idpag = positiveInt($payload['idpag'] ?? null, 'Invalid IDPAG');
            $response = $client->reconcile($actorId, $roles, $idInsc, $idpag);
        } else {
            throw new RuntimeException('Unknown USOC action', 422);
        }
    }

    $status = (int) ($response['_http_status'] ?? 200);
    unset($response['_http_status']);

    if ($status === 401 || $status >= 500 || $status === 0) {
        http_response_code(502);
        echo json_encode([
            'ok' => false,
            'error' => 'SIF USOC service unavailable',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    http_response_code($status >= 100 && $status <= 599 ? $status : 502);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    $status = $code >= 400 && $code <= 599 ? $code : 500;
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => $status >= 500 ? 'USOC operation failed' : $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    LegacyUsocContext::persist($usuariObject, $intranetObject);
}

function positiveInt(mixed $value, string $message): int
{
    if (!is_numeric($value) || (int) $value <= 0) {
        throw new RuntimeException($message, 422);
    }

    return (int) $value;
}
