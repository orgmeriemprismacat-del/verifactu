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

require_once $root . '/ConnexioWeb.php';
require_once $root . '/ConnexioIntranet.php';
require_once $root . '/LegacyInvoiceMutationAuthorization.php';
require_once $root . '/LegacyUsocCourseChangePricingSourceInterface.php';
require_once $root . '/LegacyUsocCourseChangePricingMysqlSource.php';
require_once $root . '/LegacyUsocCourseChangePricingResolver.php';
require_once $root . '/SifAuthenticatedActor.php';
require_once $root . '/SifInternalUsocClient.php';

$usuariObject = null;
$intranetObject = null;

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

    $idInsc = filter_var(
        $payload['id_insc'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $changeNumber = filter_var(
        $payload['change_number'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 4]]
    );
    $target = $payload['target'] ?? null;
    if ($idInsc === false || $changeNumber === false || !is_array($target)) {
        throw new RuntimeException('Dades del canvi USOC no vàlides.', 422);
    }

    $pricing = (new LegacyUsocCourseChangePricingResolver(
        new LegacyUsocCourseChangePricingMysqlSource()
    ))->resolve(
        (int) $idInsc,
        (string) ($target['year'] ?? ''),
        (string) ($target['month'] ?? ''),
        (string) ($target['course'] ?? ''),
        (int) $changeNumber
    );

    [$actorId, $roles] = SifAuthenticatedActor::fromUser($usuariObject);

    $fingerprint = [
        'id_insc' => (int) $idInsc,
        'idpag' => (int) $pricing['idpag'],
        'change_number' => (int) $changeNumber,
        'year' => (string) ($pricing['target']['year'] ?? ''),
        'month' => (string) ($pricing['target']['month'] ?? ''),
        'course' => (string) ($pricing['target']['course'] ?? ''),
        'actor_id' => $actorId,
    ];

    $context = is_array($_SESSION['sif_usoc_course_change'] ?? null)
        ? $_SESSION['sif_usoc_course_change']
        : [];
    $sameContext = isset($context['fingerprint'])
        && is_array($context['fingerprint'])
        && hash_equals(
            hash('sha256', json_encode($context['fingerprint'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            hash('sha256', json_encode($fingerprint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
        );

    if (!$sameContext) {
        $context = [
            'request_id' => 'uc013-course-change-' . (int) $idInsc . '-' . bin2hex(random_bytes(16)),
            'fingerprint' => $fingerprint,
            'effective_at' => date('Y-m-d H:i:s'),
            'target_id_insc' => null,
            'legacy_completed' => false,
        ];
    }

    $response = (new SifInternalUsocClient())->prepareCourseChange(
        $actorId,
        $roles,
        (string) $context['request_id'],
        (int) $idInsc,
        (int) $pricing['idpag'],
        $pricing['target']
    );

    $status = (int) ($response['_http_status'] ?? 0);
    unset($response['_http_status']);
    if ($status < 200 || $status >= 300 || ($response['ok'] ?? false) !== true) {
        $error = trim((string) ($response['error'] ?? ''));
        throw new RuntimeException(
            $status >= 400 && $status < 500 && $error !== ''
                ? $error
                : 'No s’ha pogut preparar el canvi de curs USOC.',
            $status >= 400 && $status <= 599 ? $status : 503
        );
    }

    $preparation = $response['preparation'] ?? null;
    if (!is_array($preparation) || (string) ($preparation['state'] ?? '') !== 'REQUESTED') {
        throw new RuntimeException('Checkpoint USOC no vàlid.', 409);
    }

    $context['pricing_target'] = $pricing['target'];
    $_SESSION['sif_usoc_course_change'] = $context;

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'request_id' => $context['request_id'],
        'pricing' => $pricing,
        'preparation' => $preparation,
        'resume_legacy' => (bool) ($context['legacy_completed'] ?? false),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    $status = $exception instanceof InvalidArgumentException
        ? 422
        : ($code >= 400 && $code <= 599 ? $code : 500);
    http_response_code($status);
    echo json_encode([
        'ok' => false,
        'error' => $status >= 500
            ? 'No s’ha pogut preparar el canvi de curs USOC.'
            : $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} finally {
    if (is_object($usuariObject)) {
        $_SESSION['usuari'] = serialize($usuariObject);
    }
    if (is_object($intranetObject)) {
        $_SESSION['intranet'] = serialize($intranetObject);
    }
}
