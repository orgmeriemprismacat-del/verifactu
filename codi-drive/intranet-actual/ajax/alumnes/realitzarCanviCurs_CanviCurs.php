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

	(new LegacyUsocLifecycleGuard())->assertMayUseLegacyMutation(
		$_SESSION['usuari'],
		$idInsc,
		'course_change'
	);

	$anyC = (string) ($_POST['any'] ?? '');
	$mesC = (string) ($_POST['mes'] ?? '');
	$cursC = (string) ($_POST['curs'] ?? '');
	$numeroCanvi = (string) ($_POST['numero'] ?? '');
	$apagarC = (string) ($_POST['apagar'] ?? '');
	$pagatC = (string) ($_POST['pagat'] ?? '');
	$pendentC = (string) ($_POST['pendent'] ?? '');
	$despesesC = (string) ($_POST['despeses'] ?? '');
	$obsCanvi = (string) ($_POST['obs'] ?? '');
	$motiuCanvi = trim((string) ($_POST['motiu'] ?? ''));
	$enviarCoreu = (string) ($_POST['enviarCoreu'] ?? '');
	$tipusDesc = (string) ($_POST['tipusDesc'] ?? '');
	$validDesc = (string) ($_POST['validDesc'] ?? '');

	if ($motiuCanvi === '') {
		http_response_code(422);
		throw new RuntimeException('Error: cal indicar el motiu del canvi.');
	}

	echo $_SESSION['intranet']->realitzarCanviCurs_modalCanviCurs(
		$idInsc,
		$anyC,
		$mesC,
		$cursC,
		$numeroCanvi,
		$apagarC,
		$pagatC,
		$pendentC,
		$despesesC,
		$obsCanvi,
		$motiuCanvi,
		$enviarCoreu,
		$tipusDesc,
		$validDesc
	);
} catch (Throwable $e) {
	$code = (int) $e->getCode();
	if ($code >= 400 && $code <= 599) {
		http_response_code($code);
	}
	echo $e->getMessage() !== '' ? $e->getMessage() : missatgeError($code);
} finally {
	if ($usuariDeserialitzat && is_object($_SESSION['usuari'] ?? null)) {
		$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	}
	if ($intranetDeserialitzada && is_object($_SESSION['intranet'] ?? null)) {
		$_SESSION['intranet'] = serialize($_SESSION['intranet']);
	}
}

?>