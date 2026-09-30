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
require_once ('../../SifInternalApiClient.php');
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

	if (getenv('SIF_COURSE_CHANGE_PREVIEW_ENFORCED') === '1') {
		$actorText = $_SESSION['usuari']->getUsuari();
		$actorId = is_object($actorText) && method_exists($actorText, 'get')
			? trim((string) $actorText->get())
			: '';
		$roles = $_SESSION['usuari']->getRols();
		if (!is_array($roles)) {
			$roles = [];
		}
		if ($actorId === '' || $roles === []) {
			http_response_code(403);
			throw new RuntimeException('Error: actor SIF no vàlid.');
		}

		$manualPriceReason = trim((string) ($_POST['sif_manual_price_reason'] ?? ''));
		$expectedFiscalDecision = trim((string) ($_POST['sif_expected_fiscal_decision'] ?? ''));
		$expectedEconomicDecision = trim((string) ($_POST['sif_expected_economic_decision'] ?? ''));

		$source = loadLegacyCourseChangeSource($idInsc);
		$standardTargetRaw = $_SESSION['intranet']->buscarPreuAPagar_modalCanviCurs(
			$idInsc,
			$anyC,
			$mesC,
			$cursC,
			$source['amount'],
			$tipusDesc,
			$validDesc
		);
		$standardTargetAmount = normalizeLegacyCourseChangeMoney($standardTargetRaw, 'target price');

		$client = new SifInternalApiClient();
		$preview = $client->previewCourseChange($actorId, $roles, [
			'source_enrollment_id' => $idInsc,
			'source_course' => $source['course'],
			'target_course' => $cursC,
			'original_amount' => $source['amount'],
			'standard_target_amount' => $standardTargetAmount,
			'proposed_target_amount' => $apagarC,
			'paid_amount' => $source['paid'],
			'management_fee' => $despesesC,
			'manual_price_reason' => $manualPriceReason,
		]);

		$previewStatus = (int) ($preview['_http_status'] ?? 0);
		unset($preview['_http_status']);
		if (
			$previewStatus < 200
			|| $previewStatus >= 300
			|| ($preview['ok'] ?? false) !== true
			|| ($preview['can_confirm_legacy_change'] ?? false) !== true
		) {
			http_response_code(409);
			throw new RuntimeException('Error: el preflight SIF ha rebutjat el canvi.');
		}

		$impact = is_array($preview['impact'] ?? null) ? $preview['impact'] : [];
		$actualFiscalDecision = (string) ($impact['fiscal_decision'] ?? '');
		$actualEconomicDecision = (string) ($impact['economic_decision'] ?? '');
		if ($expectedFiscalDecision !== '' && $expectedFiscalDecision !== $actualFiscalDecision) {
			http_response_code(409);
			throw new RuntimeException('Error: la decisió fiscal ha canviat abans de confirmar.');
		}
		if ($expectedEconomicDecision !== '' && $expectedEconomicDecision !== $actualEconomicDecision) {
			http_response_code(409);
			throw new RuntimeException('Error: la decisió econòmica ha canviat abans de confirmar.');
		}

		$pagatC = normalizeLegacyCourseChangeMoney(
			$impact['paid_amount'] ?? $source['paid'],
			'paid amount'
		);
		$pendentC = number_format(
			(float) normalizeLegacyCourseChangeMoney($apagarC, 'target amount')
			+ (float) normalizeLegacyCourseChangeMoney($despesesC, 'management fee')
			- (float) $pagatC,
			2,
			'.',
			''
		);
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