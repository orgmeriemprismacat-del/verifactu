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
require_once $root . '/LegacyUsocCourseChangeDestinationStoreInterface.php';
require_once $root . '/LegacyUsocCourseChangeDestinationMysqlStore.php';
require_once $root . '/LegacyUsocCourseChangeDestinationReservationService.php';
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
    $targetInput = $payload['target'] ?? null;
    if ($idInsc === false || $changeNumber === false || !is_array($targetInput)) {
        throw new RuntimeException('Dades del canvi USOC no vàlides.', 422);
    }

    $pricing = (new LegacyUsocCourseChangePricingResolver(
        new LegacyUsocCourseChangePricingMysqlSource()
    ))->resolve(
        (int) $idInsc,
        (string) ($targetInput['year'] ?? ''),
        (string) ($targetInput['month'] ?? ''),
        (string) ($targetInput['course'] ?? ''),
        (int) $changeNumber
    );

    [$actorId, $roles] = SifAuthenticatedActor::fromUser($usuariObject);

    $semantic = [
        'id_insc' => (int) $idInsc,
        'idpag' => (int) $pricing['idpag'],
        'change_number' => (int) $changeNumber,
        'year' => (string) ($pricing['target']['year'] ?? ''),
        'month' => (string) ($pricing['target']['month'] ?? ''),
        'course' => (string) ($pricing['target']['course'] ?? ''),
        'price_id' => (int) ($pricing['target']['price_id'] ?? 0),
        'target_standard_course_amount' => (string) ($pricing['target']['target_standard_course_amount'] ?? ''),
        'target_student_course_amount' => (string) ($pricing['target']['target_student_course_amount'] ?? ''),
        'management_fee' => (string) ($pricing['target']['management_fee'] ?? ''),
    ];
    $semanticJson = json_encode(
        $semantic,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $requestId = 'uc013-course-change-' . (int) $idInsc . '-'
        . substr(hash('sha256', $semanticJson), 0, 32);

    $client = new SifInternalUsocClient();

    // A lost HTTP response after a successful execution must not turn a
    // completed course change into a new legacy/fiscal attempt.
    $statusResponse = $client->courseChangeExecutionStatus(
        $actorId,
        $roles,
        $requestId
    );
    $executionStatusCode = (int) ($statusResponse['_http_status'] ?? 0);
    unset($statusResponse['_http_status']);

    if (
        $executionStatusCode >= 200
        && $executionStatusCode < 300
        && ($statusResponse['ok'] ?? false) === true
        && is_array($statusResponse['execution'] ?? null)
    ) {
        $existingExecution = $statusResponse['execution'];
        if (
            (int) ($existingExecution['ID_INSC'] ?? 0) !== (int) $idInsc
            || (int) ($existingExecution['IDPAG'] ?? 0) !== (int) $pricing['idpag']
            || (string) ($existingExecution['OPERATION'] ?? '') !== 'COURSE_CHANGE'
        ) {
            throw new RuntimeException(
                'El checkpoint USOC existent no correspon a aquest canvi.',
                409
            );
        }

        $existingState = strtoupper(
            trim((string) ($existingExecution['STATE'] ?? ''))
        );
        if ($existingState === 'COMPLETED') {
            $completedResult = json_decode(
                (string) ($existingExecution['RESULT_JSON'] ?? ''),
                true
            );
            if (
                !is_array($completedResult)
                || (string) ($completedResult['request_id'] ?? '') !== $requestId
                || (int) ($completedResult['source_id_insc'] ?? 0) !== (int) $idInsc
                || (int) ($completedResult['source_idpag'] ?? 0) !== (int) $pricing['idpag']
                || ($completedResult['legacy_handoff_completed'] ?? false) !== true
            ) {
                throw new RuntimeException(
                    'El canvi USOC consta completat però la seva evidència és incoherent.',
                    409
                );
            }

            http_response_code(200);
            echo json_encode([
                'ok' => true,
                'already_completed' => true,
                'request_id' => $requestId,
                'execution' => $completedResult,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        if ($existingState === 'REVIEW_REQUIRED') {
            throw new RuntimeException(
                'El canvi USOC requereix revisió abans de continuar.',
                409
            );
        }

        if ($existingState !== 'REQUESTED') {
            throw new RuntimeException(
                'El canvi USOC està en un estat que no permet continuar.',
                409
            );
        }
    } elseif ($executionStatusCode !== 409) {
        $error = trim((string) ($statusResponse['error'] ?? ''));
        throw new RuntimeException(
            $executionStatusCode >= 400 && $executionStatusCode < 500 && $error !== ''
                ? $error
                : 'No s’ha pogut verificar l’estat del canvi de curs USOC.',
            $executionStatusCode >= 400 && $executionStatusCode <= 599
                ? $executionStatusCode
                : 503
        );
    }

    $response = $client->prepareCourseChange(
        $actorId,
        $roles,
        (int) $idInsc,
        (int) $pricing['idpag'],
        $requestId,
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

    $previewTarget = $preparation['preview']['target'] ?? null;
    if (!is_array($previewTarget)) {
        throw new RuntimeException('El checkpoint USOC no conté els imports destí.', 409);
    }
    $targetStudentTotal = trim((string) ($previewTarget['target_student_total'] ?? ''));
    if ($targetStudentTotal === '') {
        throw new RuntimeException('El checkpoint USOC no conté el total alumne destí.', 409);
    }

    $reservation = (new LegacyUsocCourseChangeDestinationReservationService(
        new LegacyUsocCourseChangeDestinationMysqlStore()
    ))->reserve(
        $requestId,
        (int) $idInsc,
        (string) ($pricing['target']['year'] ?? ''),
        (string) ($pricing['target']['month'] ?? ''),
        (string) ($pricing['target']['course'] ?? ''),
        $targetStudentTotal
    );

    $bindingResponse = $client->bindCourseChangeDestination(
        $actorId,
        $roles,
        $requestId,
        (int) $idInsc,
        (int) $pricing['idpag'],
        (int) $reservation['destination_id_insc'],
        (int) $reservation['destination_idpag'],
        (string) $reservation['reservation_marker'],
        $targetStudentTotal
    );

    $bindingStatus = (int) ($bindingResponse['_http_status'] ?? 0);
    unset($bindingResponse['_http_status']);
    if (
        $bindingStatus < 200
        || $bindingStatus >= 300
        || ($bindingResponse['ok'] ?? false) !== true
        || !is_array($bindingResponse['binding'] ?? null)
    ) {
        $error = trim((string) ($bindingResponse['error'] ?? ''));
        throw new RuntimeException(
            $bindingStatus >= 400 && $bindingStatus < 500 && $error !== ''
                ? $error
                : 'No s’ha pogut vincular el destí reservat al checkpoint USOC.',
            $bindingStatus >= 400 && $bindingStatus <= 599 ? $bindingStatus : 503
        );
    }

    $previous = is_array($_SESSION['sif_usoc_course_change'] ?? null)
        ? $_SESSION['sif_usoc_course_change']
        : [];
    $sameRequest = (string) ($previous['request_id'] ?? '') === $requestId;

    $_SESSION['sif_usoc_course_change'] = [
        'request_id' => $requestId,
        'actor_id' => $actorId,
        'semantic' => $semantic,
        'effective_at' => trim((string) ($preparation['created_at'] ?? '')) !== ''
            ? (string) $preparation['created_at']
            : date('Y-m-d H:i:s'),
        'source_id_insc' => (int) $idInsc,
        'source_idpag' => (int) $pricing['idpag'],
        'destination_id_insc' => (int) $reservation['destination_id_insc'],
        'destination_idpag' => (int) $reservation['destination_idpag'],
        'reservation_marker' => (string) $reservation['reservation_marker'],
        'target_student_total' => $targetStudentTotal,
        'legacy_completed' => $sameRequest
            ? (bool) ($previous['legacy_completed'] ?? false)
            : false,
    ];

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'request_id' => $requestId,
        'pricing' => $pricing,
        'preparation' => $preparation,
        'reservation' => $reservation,
        'binding' => $bindingResponse['binding'],
        'resume_legacy' => (bool) $_SESSION['sif_usoc_course_change']['legacy_completed'],
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
