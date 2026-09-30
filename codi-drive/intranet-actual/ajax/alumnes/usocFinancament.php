<?php

include ('../../Text.php');
include ('../../Usuari.php');
include ('../../SifInternalUsocClient.php');
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

$usuariDeserialitzat = false;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        throw new RuntimeException('Method not allowed');
    }

    if (!isset($_SESSION['usuari'])) {
        http_response_code(401);
        throw new RuntimeException('Sessió no vàlida');
    }

    $_SESSION['usuari'] = unserialize($_SESSION['usuari']);
    $usuariDeserialitzat = true;
    if (!is_object($_SESSION['usuari'])) {
        http_response_code(401);
        throw new RuntimeException('Sessió no vàlida');
    }

    $csrfSessio = (string) ($_SESSION['csrf_usoc_financament'] ?? '');
    $csrfRebut = (string) ($_POST['csrfToken'] ?? '');
    if ($csrfSessio === '' || $csrfRebut === '' || !hash_equals($csrfSessio, $csrfRebut)) {
        http_response_code(403);
        throw new RuntimeException('Token CSRF no vàlid');
    }

    $actorId = trim((string) $_SESSION['usuari']->getUsuari()->get());
    $roles = (array) $_SESSION['usuari']->getRols();
    if ($actorId === '' || $roles === []) {
        http_response_code(403);
        throw new RuntimeException('Actor SIF no autoritzat');
    }

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

        $result = $client->issueEntityInvoice($actorId, $roles, $input);
        sendResult($result);
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

        $result = $client->registerEntityPayment($actorId, $roles, $uuid, $payment);
        sendResult($result);
        return;
    }

    http_response_code(422);
    throw new InvalidArgumentException('Acció USOC desconeguda');
} catch (Throwable $exception) {
    if (http_response_code() < 400) {
        http_response_code(500);
    }

    echo json_encode([
        'ok' => false,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if ($usuariDeserialitzat && is_object($_SESSION['usuari'] ?? null)) {
        $_SESSION['usuari'] = serialize($_SESSION['usuari']);
    }
}

function sendResult(array $result): void
{
    $status = (int) ($result['_http_status'] ?? 200);
    unset($result['_http_status']);

    if ($status >= 400 && $status <= 599) {
        http_response_code($status);
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function positiveInt(mixed $value, string $message): int
{
    if (!is_numeric($value) || (int) $value <= 0) {
        http_response_code(422);
        throw new InvalidArgumentException($message);
    }
    return (int) $value;
}

function positiveMoney(mixed $value, string $message): string
{
    if (!is_numeric($value) || (float) $value <= 0.0) {
        http_response_code(422);
        throw new InvalidArgumentException($message);
    }
    return number_format((float) $value, 2, '.', '');
}

function requiredString(mixed $value, string $message): string
{
    $string = trim((string) $value);
    if ($string === '') {
        http_response_code(422);
        throw new InvalidArgumentException($message);
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
        http_response_code(422);
        throw new InvalidArgumentException('Mètode de cobrament no vàlid');
    }
    return $method;
}

?>