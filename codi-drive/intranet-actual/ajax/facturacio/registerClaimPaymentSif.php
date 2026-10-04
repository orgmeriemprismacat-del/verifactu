<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');
include ('../../LegacyInvoiceMutationAuthorization.php');
include ('../../SifAuthenticatedActor.php');
include ('../../SifInternalClaimPaymentClient.php');
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');

$usuariDeserialitzat = false;
$intranetDeserialitzada = false;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        throw new RuntimeException('Mètode no permès.', 405);
    }

    if (!isset($_SESSION['usuari'], $_SESSION['intranet'])) {
        throw new RuntimeException('Sessió no vàlida.', 401);
    }

    $_SESSION['usuari'] = unserialize($_SESSION['usuari']);
    $usuariDeserialitzat = true;
    $_SESSION['intranet'] = unserialize($_SESSION['intranet']);
    $intranetDeserialitzada = true;

    if (!is_object($_SESSION['usuari']) || !is_object($_SESSION['intranet'])) {
        throw new RuntimeException('Sessió no vàlida.', 401);
    }

    LegacyInvoiceMutationAuthorization::assertSameOrigin();

    $csrfSession = (string) ($_SESSION['csrf_claim_payment'] ?? '');
    $csrfReceived = (string) ($_POST['csrfToken'] ?? '');
    if (
        $csrfSession === ''
        || $csrfReceived === ''
        || !hash_equals($csrfSession, $csrfReceived)
    ) {
        throw new RuntimeException('Token CSRF no vàlid.', 403);
    }

    [$permissionPage, $claimPhase] = claimPaymentPageContext(
        (string) ($_SERVER['HTTP_REFERER'] ?? '')
    );
    LegacyInvoiceMutationAuthorization::assertCanEdit(
        $_SESSION['usuari'],
        $_SESSION['intranet'],
        $permissionPage
    );

    $idInsc = claimPaymentPositiveInt(
        $_POST['idInsc'] ?? null,
        'Inscripció no vàlida.'
    );
    $requestId = claimPaymentRequestId($_POST['requestId'] ?? null);

    if (!isset($_SESSION['claim_payment_requests']) || !is_array($_SESSION['claim_payment_requests'])) {
        $_SESSION['claim_payment_requests'] = [];
    }

    if (array_key_exists($requestId, $_SESSION['claim_payment_requests'])) {
        echo (string) $_SESSION['claim_payment_requests'][$requestId];
        return;
    }

    $externalReceiptType = strtoupper(trim((string) ($_POST['externalReceiptType'] ?? '')));
    if (!in_array($externalReceiptType, ['BANK_REFERENCE', 'DS_ORDER', 'PROVIDER_REF'], true)) {
        throw new RuntimeException('Tipus de referència externa no vàlid.', 422);
    }

    $externalReceiptId = claimPaymentIdentifier(
        $_POST['externalReceiptId'] ?? null,
        'Referència externa de cobrament no vàlida.'
    );

    $amountRaw = $_POST['amount'] ?? null;
    if (!is_numeric($amountRaw) || (float) $amountRaw <= 0.0) {
        throw new RuntimeException('Import de cobrament no vàlid.', 422);
    }
    $amount = number_format((float) $amountRaw, 2, '.', '');

    $movementDateRaw = trim((string) ($_POST['movementDate'] ?? ''));
    if ($movementDateRaw === '') {
        throw new RuntimeException('Data de cobrament obligatòria.', 422);
    }
    try {
        $movementDate = (new DateTimeImmutable(
            $movementDateRaw,
            new DateTimeZone('Europe/Madrid')
        ))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        throw new RuntimeException('Data de cobrament no vàlida.', 422);
    }

    $method = strtoupper(trim((string) ($_POST['method'] ?? 'TRANSFERENCIA')));
    if (!in_array($method, ['TRANSFERENCIA', 'MANUAL'], true)) {
        throw new RuntimeException('Mètode de cobrament no vàlid.', 422);
    }

    $bank = trim((string) ($_POST['bank'] ?? ''));
    if (mb_strlen($bank, 'UTF-8') > 80) {
        throw new RuntimeException('Banc no vàlid.', 422);
    }

    $notes = trim((string) ($_POST['notes'] ?? ''));
    if (mb_strlen($notes, 'UTF-8') > 1000) {
        throw new RuntimeException('Observacions massa llargues.', 422);
    }

    // The case identity is server-derived from the legacy inscription and the
    // page/phase. The browser cannot choose or replace it.
    $claimCaseId = 'LEGACY-INSC:' . $idInsc . ':' . $claimPhase;

    [$actorId, $actorRoles] = SifAuthenticatedActor::fromUser($_SESSION['usuari']);
    $client = new SifInternalClaimPaymentClient();
    $payment = [
        'amount' => $amount,
        'movement_date' => $movementDate,
        'method' => $method,
    ];
    if ($bank !== '') {
        $payment['bank'] = $bank;
    }
    if ($notes !== '') {
        $payment['notes'] = $notes;
    }

    $response = $client->registerByInscription(
        $actorId,
        $actorRoles,
        $idInsc,
        $claimCaseId,
        $externalReceiptType,
        $externalReceiptId,
        $payment
    );

    $sifStatus = (int) ($response['_http_status'] ?? 0);
    if (
        $sifStatus < 200
        || $sifStatus >= 300
        || ($response['ok'] ?? false) !== true
        || !is_array($response['payment'] ?? null)
    ) {
        $message = trim((string) ($response['error'] ?? ''));
        $status = $sifStatus >= 400 && $sifStatus <= 599 ? $sifStatus : 502;
        throw new RuntimeException(
            $message !== '' ? 'SIF: ' . $message : 'No s’ha pogut registrar el cobrament al SIF.',
            $status
        );
    }

    $json = json_encode(
        [
            'ok' => true,
            'payment' => $response['payment'],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );

    $_SESSION['claim_payment_requests'][$requestId] = $json;
    if (count($_SESSION['claim_payment_requests']) > 50) {
        $_SESSION['claim_payment_requests'] = array_slice(
            $_SESSION['claim_payment_requests'],
            -50,
            null,
            true
        );
    }

    echo $json;
} catch (Throwable $exception) {
    $code = (int) $exception->getCode();
    $status = $code >= 400 && $code <= 599 ? $code : 500;
    http_response_code($status);

    $message = $status >= 500
        ? 'No s’ha pogut registrar el cobrament.'
        : ($exception->getMessage() !== '' ? $exception->getMessage() : 'Petició no vàlida.');

    echo json_encode(
        ['ok' => false, 'error' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} finally {
    if ($usuariDeserialitzat && is_object($_SESSION['usuari'] ?? null)) {
        $_SESSION['usuari'] = serialize($_SESSION['usuari']);
    }
    if ($intranetDeserialitzada && is_object($_SESSION['intranet'] ?? null)) {
        $_SESSION['intranet'] = serialize($_SESSION['intranet']);
    }
}

function claimPaymentPageContext(string $referer): array
{
    $path = (string) parse_url($referer, PHP_URL_PATH);
    $map = [
        '/facturacio/primera-reclamacio/' => ['/facturacio/primera-reclamacio/', 'FIRST'],
        '/facturacio-primera-reclamacio-pagament.php' => ['/facturacio-primera-reclamacio-pagament.php', 'FIRST'],
        '/facturacio/recordatori-pagament/' => ['/facturacio/recordatori-pagament/', 'COURSE_END'],
        '/facturacio-recordatori-pagament-final.php' => ['/facturacio-recordatori-pagament-final.php', 'COURSE_END'],
        '/facturacio/reclamacio-final/' => ['/facturacio/reclamacio-final/', 'FINAL'],
        '/facturacio-reclamacio-final.php' => ['/facturacio-reclamacio-final.php', 'FINAL'],
        '/facturacio/morosos/' => ['/facturacio/morosos/', 'DEFAULTER'],
        '/facturacio-control-morosos.php' => ['/facturacio-control-morosos.php', 'DEFAULTER'],
    ];

    if (!isset($map[$path])) {
        throw new RuntimeException('Pàgina de reclamació no autoritzada.', 403);
    }

    return $map[$path];
}

function claimPaymentPositiveInt(mixed $value, string $message): int
{
    if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
        throw new RuntimeException($message, 422);
    }

    return (int) $value;
}

function claimPaymentRequestId(mixed $value): string
{
    $requestId = trim((string) $value);
    if (
        $requestId === ''
        || strlen($requestId) > 120
        || preg_match('/^[A-Za-z0-9._:-]+$/D', $requestId) !== 1
    ) {
        throw new RuntimeException('requestId no vàlid.', 422);
    }

    return $requestId;
}

function claimPaymentIdentifier(mixed $value, string $message): string
{
    $identifier = trim((string) $value);
    if (
        $identifier === ''
        || strlen($identifier) > 120
        || preg_match('/^[A-Za-z0-9._:\/-]+$/D', $identifier) !== 1
    ) {
        throw new RuntimeException($message, 422);
    }

    return $identifier;
}

?>
