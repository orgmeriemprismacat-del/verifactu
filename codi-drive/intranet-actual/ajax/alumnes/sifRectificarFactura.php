<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

if (!filter_var(
    getenv('SIF_UC005_RECTIFICATION_UI_ENABLED') ?: '0',
    FILTER_VALIDATE_BOOLEAN
)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'UC-005 rectification UI is disabled']);
    return;
}

require_once $root . '/LegacyInvoiceReadContext.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';
require_once $root . '/SifAuthenticatedActor.php';
require_once $root . '/SifRectificationAccess.php';
require_once $root . '/SifInternalApiClient.php';

$user = null;
$intranet = null;

try {
    [$user, $intranet] = LegacyInvoiceReadContext::open();
    LegacyInvoiceMutationAuthorization::assertSameOrigin();
    $actor = SifRectificationAccess::resolve($user, $intranet);
    SifRectificationAccess::assertCsrf($_SERVER);

    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (!str_starts_with($contentType, 'application/json')) {
        http_response_code(415);
        echo json_encode(['ok' => false, 'error' => 'Content-Type must be application/json']);
        return;
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        return;
    }

    $action = strtolower(trim((string) ($payload['action'] ?? '')));
    if (!in_array($action, ['preview', 'confirm'], true)) {
        throw new RuntimeException('Acció UC-005 desconeguda', 422);
    }

    $uuidFactura = strtolower(trim((string) ($payload['uuid_factura'] ?? '')));
    if (!isUuid($uuidFactura)) {
        throw new RuntimeException('UUID de factura SIF invàlid', 422);
    }

    $classificationEventUuid = strtolower(trim((string) (
        $payload['classification_event_uuid'] ?? ''
    )));
    if (!isUuid($classificationEventUuid)) {
        throw new RuntimeException('UUID de decisió UC-74 invàlid', 422);
    }

    $correction = normalizeCorrection($payload['correction'] ?? null);

    $url = trim((string) (getenv('SIF_RECTIFICATION_API_URL') ?: ''));
    $signedPath = trim((string) (
        getenv('SIF_INTERNAL_RECTIFICATION_SIGNED_PATH') ?: '/api/factures/rectify.php'
    ));
    $client = new SifInternalApiClient(
        null,
        null,
        null,
        null,
        10,
        null,
        null,
        $url,
        $signedPath
    );

    if ($action === 'preview') {
        $response = $client->previewRectification(
            $actor['actor_id'],
            $actor['roles'],
            $uuidFactura,
            $correction,
            $classificationEventUuid
        );
    } else {
        $fingerprint = strtolower(trim((string) ($payload['expected_fingerprint'] ?? '')));
        if (preg_match('/^[a-f0-9]{64}$/D', $fingerprint) !== 1) {
            throw new RuntimeException('Fingerprint de preview invàlid', 422);
        }

        $response = $client->confirmRectification(
            $actor['actor_id'],
            $actor['roles'],
            $uuidFactura,
            $correction,
            $classificationEventUuid,
            $fingerprint
        );
    }

    $status = (int) ($response['_http_status'] ?? 0);
    unset($response['_http_status']);

    if ($status === 401 || $status === 0 || $status >= 500) {
        http_response_code(502);
        echo json_encode(
            ['ok' => false, 'error' => 'SIF rectification service unavailable'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
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
        'error' => $status >= 500
            ? 'SIF rectification proxy failed'
            : $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    LegacyInvoiceReadContext::persist($user, $intranet);
}

function normalizeCorrection(mixed $value): array
{
    if (!is_array($value)) {
        throw new RuntimeException('Bloc de correcció invàlid', 422);
    }

    $reason = strtoupper(trim((string) ($value['reason'] ?? $value['motiu'] ?? '')));
    if ($reason === '' || mb_strlen($reason, 'UTF-8') > 80) {
        throw new RuntimeException('Motiu de rectificació invàlid', 422);
    }

    $mode = strtoupper(trim((string) ($value['mode'] ?? $value['mode_rectificacio'] ?? '')));
    if (!in_array($mode, ['DIFERENCIES', 'SUBSTITUCIO'], true)) {
        throw new RuntimeException('Mode de rectificació invàlid', 422);
    }

    $result = [
        'reason' => $reason,
        'mode' => $mode,
    ];

    $amount = $value['amount'] ?? $value['import'] ?? null;
    if ($amount !== null && $amount !== '') {
        if (!is_numeric($amount)) {
            throw new RuntimeException('Import de rectificació invàlid', 422);
        }
        $result['amount'] = number_format((float) $amount, 2, '.', '');
    }

    foreach ([
        'concept' => 180,
        'detail' => 1000,
        'reference' => 100,
    ] as $field => $maxLength) {
        $aliases = match ($field) {
            'concept' => ['concept', 'concepte'],
            'detail' => ['detail', 'details', 'detall'],
            'reference' => ['reference', 'referencia'],
        };
        foreach ($aliases as $alias) {
            if (!array_key_exists($alias, $value)) {
                continue;
            }
            $text = trim((string) $value[$alias]);
            if ($text !== '' && mb_strlen($text, 'UTF-8') > $maxLength) {
                throw new RuntimeException('Camp de rectificació massa llarg: ' . $field, 422);
            }
            if ($text !== '') {
                $result[$field] = $text;
            }
            break;
        }
    }

    if (array_key_exists('fiscal', $value)) {
        $result['fiscal'] = normalizeFiscal($value['fiscal']);
    }

    if (array_key_exists('billing', $value)) {
        if ($mode !== 'SUBSTITUCIO') {
            throw new RuntimeException('El receptor només es pot corregir en SUBSTITUCIO', 422);
        }
        $result['billing'] = normalizeBilling($value['billing']);
    }

    return $result;
}

function normalizeFiscal(mixed $value): array
{
    if (!is_array($value)) {
        throw new RuntimeException('Bloc fiscal invàlid', 422);
    }

    $result = [];
    foreach (['import_base', 'taxable_base', 'iva_pct', 'iva_import', 'total'] as $field) {
        if (!array_key_exists($field, $value) || !is_numeric($value[$field])) {
            throw new RuntimeException('Camp fiscal invàlid: ' . $field, 422);
        }
        $result[$field] = number_format((float) $value[$field], 2, '.', '');
    }

    $regime = strtoupper(trim((string) ($value['iva_regim'] ?? '')));
    if ($regime === '' || mb_strlen($regime, 'UTF-8') > 20) {
        throw new RuntimeException('Règim IVA invàlid', 422);
    }
    $result['iva_regim'] = $regime;

    $exemption = strtoupper(trim((string) (
        $value['exemption_reason'] ?? $value['causa_exempcio_no_subjecta'] ?? ''
    )));
    if ($exemption !== '') {
        if (preg_match('/^E[1-8]$/D', $exemption) !== 1) {
            throw new RuntimeException('Causa d’exempció invàlida', 422);
        }
        $result['exemption_reason'] = $exemption;
    }

    return $result;
}

function normalizeBilling(mixed $value): array
{
    if (!is_array($value)) {
        throw new RuntimeException('Bloc de receptor invàlid', 422);
    }

    $aliases = [
        'name' => ['name', 'nom_rao', 'billing_name'],
        'nif' => ['nif', 'nif_cif', 'billing_nif'],
        'address' => ['address', 'adreca'],
        'cp' => ['cp', 'postal_code'],
        'city' => ['city', 'poblacio'],
        'province' => ['province', 'provincia'],
        'country' => ['country', 'pais'],
        'email' => ['email', 'correu'],
    ];
    $limits = [
        'name' => 180,
        'nif' => 20,
        'address' => 180,
        'cp' => 10,
        'city' => 120,
        'province' => 120,
        'country' => 2,
        'email' => 180,
    ];

    $result = [];
    foreach ($aliases as $target => $keys) {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }
            $text = trim((string) $value[$key]);
            if ($text !== '' && mb_strlen($text, 'UTF-8') > $limits[$target]) {
                throw new RuntimeException('Camp de receptor massa llarg: ' . $target, 422);
            }
            if ($text !== '') {
                $result[$target] = $target === 'country' ? strtoupper($text) : $text;
            }
            break;
        }
    }

    if (($result['name'] ?? '') === '' || ($result['nif'] ?? '') === '') {
        throw new RuntimeException('El receptor corregit requereix nom i NIF/CIF', 422);
    }

    return $result;
}

function isUuid(string $value): bool
{
    return preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
        $value
    ) === 1;
}
