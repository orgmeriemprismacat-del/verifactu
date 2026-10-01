<?php

$root = dirname(__DIR__, 2);
chdir($root);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    return;
}

ob_start();
require_once $root . '/inc/comprovarSessio.php';
ob_end_clean();

require_once $root . '/LegacyInvoiceMutationAuthorization.php';
require_once $root . '/LegacyUsocLifecycleGuard.php';

$usuariObject = null;

try {
    if (!isset($configOk) || !$configOk || !isset($_SESSION['usuari'], $_SESSION['intranet'])) {
        throw new RuntimeException('Sessió no autoritzada.', 401);
    }

    $usuariObject = unserialize($_SESSION['usuari']);
    $intranetObject = unserialize($_SESSION['intranet']);
    if (!is_object($usuariObject) || !is_object($intranetObject)) {
        throw new RuntimeException('Sessió no vàlida.', 401);
    }

    LegacyInvoiceMutationAuthorization::assertSameOrigin();
    LegacyInvoiceMutationAuthorization::assertCanEdit(
        $usuariObject,
        $intranetObject,
        '/alumnes/mostrar-alumne/'
    );

    $csrfSession = (string) ($_SESSION['csrf_alumnes_lifecycle'] ?? '');
    $csrfHeader = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($csrfSession === '' || $csrfHeader === '' || !hash_equals($csrfSession, $csrfHeader)) {
        throw new RuntimeException('Token CSRF no vàlid.', 403);
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody === false ? '' : $rawBody, true);
    if (!is_array($payload)) {
        throw new RuntimeException('JSON no vàlid.', 400);
    }

    $idInscRaw = $payload['id_insc'] ?? null;
    if (filter_var($idInscRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new RuntimeException('Inscripció no vàlida.', 422);
    }

    $operation = strtolower(trim((string) ($payload['operation'] ?? '')));
    if (!in_array($operation, ['course_change', 'cancellation'], true)) {
        throw new RuntimeException('Operació lifecycle USOC no vàlida.', 422);
    }

    $lifecycle = new LegacyUsocLifecycleGuard();
    $idInsc = (int) $idInscRaw;

    $completedRequestId = '';
    if (
        $operation === 'cancellation'
        && isset($_SESSION['usoc_cancellation_execution'])
        && is_array($_SESSION['usoc_cancellation_execution'])
        && isset($_SESSION['usoc_cancellation_execution'][$idInsc])
    ) {
        $completedRequestId = trim(
            (string) $_SESSION['usoc_cancellation_execution'][$idInsc]
        );
    }

    if (
        $completedRequestId !== ''
        && $lifecycle->completedCancellationExecution(
            $usuariObject,
            $idInsc,
            $completedRequestId
        ) !== null
    ) {
        http_response_code(200);
        echo json_encode([
            'ok' => true,
            'guard' => [
                'tracked_usoc' => true,
                'allowed' => true,
                'reason' => 'USOC_CANCELLATION_EXECUTION_COMPLETED',
                'operation' => $operation,
                'id_insc' => $idInsc,
            ],
            'completed_request_id' => $completedRequestId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    $guard = $lifecycle->inspect(
        $usuariObject,
        $idInsc,
        $operation
    );

    $plan = null;
    if (
        ($guard['tracked_usoc'] ?? false) === true
        && ($guard['allowed'] ?? false) !== true
        && (string) ($guard['reason'] ?? '') === 'USOC_FINANCING_CASE_REQUIRES_ORCHESTRATION'
    ) {
        $plan = $lifecycle->plan($usuariObject, $idInsc, $operation);
    }

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'guard' => $guard,
        'plan' => $plan,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    $status = $code >= 400 && $code <= 599 ? $code : 500;
    http_response_code($status);

    echo json_encode([
        'ok' => false,
        'error' => $status >= 500
            ? 'No s’ha pogut validar l’estat fiscal abans de continuar.'
            : $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if (is_object($usuariObject)) {
        $_SESSION['usuari'] = serialize($usuariObject);
    }
    if (isset($intranetObject) && is_object($intranetObject)) {
        $_SESSION['intranet'] = serialize($intranetObject);
    }
}
