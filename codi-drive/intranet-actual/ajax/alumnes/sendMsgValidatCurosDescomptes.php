<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../ConnexioMoodle.php');
include ('../../ConnexioMoodleAntic.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');
include ('../../SifInternalUsocClient.php');
include ('../../SifAuthenticatedActor.php');
include ('../../LegacyDiscountValidationLookup.php');
session_start();

$usuariDeserialitzat = false;
$intranetDeserialitzada = false;

try {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		header('Allow: POST');
		http_response_code(405);
		throw new Exception('Error: mètode no permès.');
	}

	if (!isset($_SESSION['usuari'], $_SESSION['intranet'])) {
		http_response_code(401);
		throw new Exception('Error: sessió no vàlida.');
	}

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$usuariDeserialitzat = true;
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);
	$intranetDeserialitzada = true;

	if (!is_object($_SESSION['usuari']) || !is_object($_SESSION['intranet'])) {
		http_response_code(401);
		throw new Exception('Error: sessió no vàlida.');
	}

	$csrfSessio = (string) ($_SESSION['csrf_validar_descomptes'] ?? '');
	$csrfRebut = (string) ($_POST['csrfToken'] ?? '');
	if ($csrfSessio === '' || $csrfRebut === '' || !hash_equals($csrfSessio, $csrfRebut)) {
		http_response_code(403);
		throw new Exception('Error: token CSRF no vàlid.');
	}

	$rolsEdicio = (string) $_SESSION['intranet']->consultaRolsEdiicio('/alumnes/validar-descomptes/');
	if ($rolsEdicio === '' || !$_SESSION['usuari']->tePermisVisualitzacio($rolsEdicio)) {
		http_response_code(403);
		throw new Exception('Error: no tens permisos per validar descomptes.');
	}

	$idInscRaw = $_POST['idInsc'] ?? null;
	$verificatRaw = $_POST['verificat'] ?? null;
	$requestId = trim((string) ($_POST['requestId'] ?? ''));

	if (filter_var($idInscRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
		http_response_code(422);
		throw new Exception('Error: inscripció no vàlida.');
	}

	if (!in_array((string) $verificatRaw, ['0', '1'], true)) {
		http_response_code(422);
		throw new Exception('Error: decisió de validació no vàlida.');
	}

	if (
		$requestId === ''
		|| strlen($requestId) > 120
		|| preg_match('/^[A-Za-z0-9._:-]+$/D', $requestId) !== 1
	) {
		http_response_code(422);
		throw new Exception('Error: requestId no vàlid.');
	}

	if (!isset($_SESSION['validar_descomptes_requests']) || !is_array($_SESSION['validar_descomptes_requests'])) {
		$_SESSION['validar_descomptes_requests'] = [];
	}

	if (array_key_exists($requestId, $_SESSION['validar_descomptes_requests'])) {
		echo (string) $_SESSION['validar_descomptes_requests'][$requestId];
		return;
	}

	$idInsc = (int) $idInscRaw;
	$verificat = (int) $verificatRaw;
	$desiredValidDesc = $verificat === 1 ? 1 : 2;
	$isUsoc = (new LegacyDiscountValidationLookup())->isUsoc($idInsc);
	$beginDecision = ['tracked' => false, 'should_apply_legacy' => true];
	$sifClient = null;
	$actorId = '';
	$actorRoles = [];

	if ($isUsoc) {
		[$actorId, $actorRoles] = SifAuthenticatedActor::fromUser($_SESSION['usuari']);
		$sifClient = new SifInternalUsocClient();
		$beginResponse = $sifClient->beginValidationDecision(
			$actorId,
			$actorRoles,
			$requestId,
			$idInsc,
			$desiredValidDesc
		);
		$beginDecision = assertSifValidationDecisionResponse($beginResponse);

		if ((string) ($beginDecision['state'] ?? '') === 'REVIEW_REQUIRED') {
			http_response_code(409);
			throw new Exception('Error: la decisió USOC requereix revisió manual abans de continuar.');
		}
	}

	if (
		$isUsoc
		&& ($beginDecision['should_apply_legacy'] ?? false) !== true
	) {
		$resultat = 'La decisió USOC ja constava aplicada i ha quedat conciliada amb el SIF.';
	} else {
		$resultat = (string) $_SESSION['intranet']->sendMsgValidatCurosDescomptes($idInsc, $verificat);

		if ($isUsoc) {
			$completeResponse = $sifClient->completeValidationDecision(
				$actorId,
				$actorRoles,
				$requestId
			);
			$completeDecision = assertSifValidationDecisionResponse($completeResponse);
			if ((string) ($completeDecision['state'] ?? '') !== 'COMMITTED') {
				http_response_code(409);
				throw new Exception(
					'Error: la decisió legacy s’ha aplicat però la conciliació SIF ha quedat pendent de revisió.'
				);
			}
		}
	}

	$_SESSION['validar_descomptes_requests'][$requestId] = $resultat;
	if (count($_SESSION['validar_descomptes_requests']) > 50) {
		$_SESSION['validar_descomptes_requests'] = array_slice(
			$_SESSION['validar_descomptes_requests'],
			-50,
			null,
			true
		);
	}

	echo $resultat;

} catch (Exception $e) {
	if ($e->getCode() !== 0) {
		echo missatgeError($e->getCode());
	} else {
		echo $e->getMessage();
	}
} finally {
	if ($usuariDeserialitzat && is_object($_SESSION['usuari'] ?? null)) {
		$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	}
	if ($intranetDeserialitzada && is_object($_SESSION['intranet'] ?? null)) {
		$_SESSION['intranet'] = serialize($_SESSION['intranet']);
	}
}


function assertSifValidationDecisionResponse(array $response): array
{
	$status = (int) ($response['_http_status'] ?? 0);
	if ($status < 200 || $status >= 300 || ($response['ok'] ?? false) !== true) {
		$message = trim((string) ($response['error'] ?? ''));
		throw new Exception(
			$message !== ''
				? 'Error SIF USOC: ' . $message
				: 'Error SIF USOC: no s’ha pogut registrar la decisió.'
		);
	}

	$decision = $response['decision'] ?? null;
	if (!is_array($decision)) {
		throw new Exception('Error SIF USOC: resposta de decisió no vàlida.');
	}

	return $decision;
}

?>