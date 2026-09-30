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

	$resultat = (string) $_SESSION['intranet']->sendMsgValidatCurosDescomptes($idInsc, $verificat);

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

?>