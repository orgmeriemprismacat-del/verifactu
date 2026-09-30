<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (!filter_var(getenv('SIF_USOC_UI_ENABLED') ?: '0', FILTER_VALIDATE_BOOLEAN)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'USOC UI disabled']);
    return;
}

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
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

    $csrfSessio = (string) ($_SESSION['csrf_usoc_financament'] ?? '');
    $csrfRebut = (string) ($_POST['csrfToken'] ?? '');
    if ($csrfSessio === '' || $csrfRebut === '' || !hash_equals($csrfSessio, $csrfRebut)) {
        throw new RuntimeException('Token CSRF no vàlid', 403);
    }

    [$actorId, $roles] = SifAuthenticatedActor::fromUser($usuariObject);
    $client = new SifInternalUsocClient();
    $action = strtolower(trim((string) ($_POST['action'] ?? '')));

    if ($action === 'view') {
        $result = $client->viewCase(
            $actorId,
            $roles,
            positiveInt($_POST['id_insc'] ?? null, 'ID inscripció no vàlid'),
            positiveInt($_POST['idpag'] ?? null, 'IDPAG no vàlid')
        );
        sendResult($result);
        return;
    }

    LegacyInvoiceMutationAuthorization::assertCanEdit(
        $usuariObject,
        $intranetObject,
        '/alumnes/mostrar-alumne/'
    );

    if ($action === 'reconcile') {
        $result = $client->reconcile(
            $actorId,
            $roles,
            positiveInt($_POST['id_insc'] ?? null, 'ID inscripció no vàlid'),
            positiveInt($_POST['idpag'] ?? null, 'IDPAG no vàlid')
        );
        sendResult($result);
        return;
    }

    if ($action === 'issue_entity_invoice') {
        $input = [
            'id_insc' => positiveInt($_POST['id_insc'] ?? null, 'ID inscripció no vàlid'),
            'idpag' => positiveInt($_POST['idpag'] ?? null, 'IDPAG no vàlid'),
            'student_invoice_uuid' => requiredString($_POST['student_invoice_uuid'] ?? null, 'Falta UUID factura alumne'),
            'student_amount' => positiveMoney($_POST['student_amount'] ?? null, 'Import alumne no vàlid'),
            'amount' => positiveMoney($_POST['amount'] ?? null, 'Import USOC no vàlid'),
            'billing' => [
                'name' => requiredString($_POST['billing_name'] ?? null, 'Falta raó social'),
                'nif' => requiredString($_POST['billing_nif'] ?? null, 'Falta NIF/CIF'),
                'email' => optionalString($_POST['billing_email'] ?? null),
                'address' => optionalString($_POST['billing_address'] ?? null),
                'cp' => optionalString($_POST['billing_cp'] ?? null),
                'city' => optionalString($_POST['billing_city'] ?? null),
                'province' => optionalString($_POST['billing_province'] ?? null),
                'country' => strtoupper(optionalString($_POST['billing_country'] ?? null) ?? 'ES'),
            ],
        ];

        sendResult($client->issueEntityInvoice($actorId, $roles, $input));
        return;
    }

    if ($action === 'register_entity_payment') {
        $uuid = requiredString($_POST['uuid_entity_invoice'] ?? null, 'Falta UUID factura entitat');
        $payment = [
            'amount' => positiveMoney($_POST['amount'] ?? null, 'Import de cobrament no vàlid'),
            'movement_date' => requiredString($_POST['movement_date'] ?? null, 'Falta data de moviment'),
            'method' => paymentMethod($_POST['method'] ?? null),
        ];

        foreach ([
            'reference' => $_POST['reference'] ?? null,
            'bank' => $_POST['bank'] ?? null,
            'notes' => $_POST['notes'] ?? null,
        ] as $key => $value) {
            $normalized = optionalString($value);
            if ($normalized !== null) {
                $payment[$key] = $normalized;
            }
        }

        sendResult($client->registerEntityPayment($actorId, $roles, $uuid, $payment));
        return;
    }

    throw new RuntimeException('Acció USOC desconeguda', 422);
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

function sendResult(array $result): void
{
    $status = (int) ($result['_http_status'] ?? 200);
    unset($result['_http_status']);
    http_response_code($status >= 100 && $status <= 599 ? $status : 502);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function positiveInt(mixed $value, string $message): int
{
    if (!is_numeric($value) || (int) $value <= 0) {
        throw new InvalidArgumentException($message, 422);
    }
    return (int) $value;
}

function positiveMoney(mixed $value, string $message): string
{
    if (!is_numeric($value) || (float) $value <= 0.0) {
        throw new InvalidArgumentException($message, 422);
    }
    return number_format((float) $value, 2, '.', '');
}

function requiredString(mixed $value, string $message): string
{
    $string = trim((string) $value);
    if ($string === '') {
        throw new InvalidArgumentException($message, 422);
    }
    return $string;
}

function optionalString(mixed $value): ?string
{
    $string = trim((string) $value);
    return $string === '' ? null : $string;
}

function paymentMethod(mixed $value): string
{
    $method = strtoupper(trim((string) $value));
    if (!in_array($method, ['TRANSFERENCIA', 'MANUAL'], true)) {
        throw new InvalidArgumentException('Mètode de cobrament no vàlid', 422);
    }
    return $method;
}

?>