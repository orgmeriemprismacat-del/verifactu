<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../ConnexioMoodle.php');
include ('../../ConnexioMoodleAntic.php');
include ('../../Text.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../Date.php');
include ('../../Mail.php');
include ('../../inc/missatgesError.php');
include ('../../LegacyInvoiceMutationAuthorization.php');
include ('../../LegacyUsocLifecycleGuard.php');
session_start();

$usuariDeserialitzat = false;
$intranetDeserialitzada = false;

try {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		header('Allow: POST');
		http_response_code(405);
		throw new RuntimeException('Error: mètode no permès.');
	}

	if (!isset($_SESSION['usuari'], $_SESSION['intranet'])) {
		http_response_code(401);
		throw new RuntimeException('Error: sessió no vàlida.');
	}

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$usuariDeserialitzat = true;
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);
	$intranetDeserialitzada = true;

	if (!is_object($_SESSION['usuari']) || !is_object($_SESSION['intranet'])) {
		http_response_code(401);
		throw new RuntimeException('Error: sessió no vàlida.');
	}

	$csrfSessio = (string) ($_SESSION['csrf_alumnes_lifecycle'] ?? '');
	$csrfRebut = (string) ($_POST['csrfToken'] ?? '');
	if ($csrfSessio === '' || $csrfRebut === '' || !hash_equals($csrfSessio, $csrfRebut)) {
		http_response_code(403);
		throw new RuntimeException('Error: token CSRF no vàlid.');
	}

	LegacyInvoiceMutationAuthorization::assertSameOrigin();
	LegacyInvoiceMutationAuthorization::assertCanEdit(
		$_SESSION['usuari'],
		$_SESSION['intranet'],
		'/alumnes/mostrar-alumne/'
	);

	$idInscRaw = $_POST['idinsc'] ?? null;
	if (filter_var($idInscRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
		http_response_code(422);
		throw new RuntimeException('Error: inscripció no vàlida.');
	}
	$idInsc = (int) $idInscRaw;
	$motiu = trim((string) ($_POST['motiu'] ?? ''));
	$enviarCoreu = (string) ($_POST['enviarCoreu'] ?? '');

	if ($motiu === '') {
		http_response_code(422);
		throw new RuntimeException('Error: cal indicar el motiu de baixa.');
	}

	(new LegacyUsocLifecycleGuard())->assertMayUseLegacyMutation(
		$_SESSION['usuari'],
		$idInsc,
		'cancellation'
	);

	echo $_SESSION['intranet']->confirmaBaixa_modalDonarBaixa(
		$idInsc,
		$motiu,
		$enviarCoreu
	);
} catch (Throwable $e) {
	$code = (int) $e->getCode();
	$status = $code >= 400 && $code <= 599 ? $code : 500;
	http_response_code($status);

	if ($status >= 500) {
		echo 'Error: no s’ha pogut completar l’operació.';
	} else {
		echo $e->getMessage() !== '' ? $e->getMessage() : missatgeError($status);
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