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
require_once ('../../SifInternalApiClient.php');
session_start();

try {

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$idInsc 			= $_GET['idinsc'];
	$anyC 				= $_GET['any'];
	$mesC 				= $_GET['mes'];
	$cursC 				= $_GET['curs'];
	$numeroCanvi 		= $_GET['numero'];
	$apagarC	 		= $_GET['apagar'];
	$pagatC				= $_GET['pagat'];
	$pendentC 			= $_GET['pendent'] ?? ((float) $apagarC - (float) $pagatC);
	$despesesC 		= $_GET['despeses'];
	$obsCanvi 			= $_GET['obs'];
	$motiuCanvi 		= $_GET['motiu'];
	$enviarCoreu 		= $_GET['enviarCoreu'];
	$tipusDesc 			= $_GET['tipusDesc'];
	$validDesc 			= $_GET['validDesc'];

	if (getenv('SIF_COURSE_CHANGE_PREVIEW_ENFORCED') === '1') {
		$actorText = $_SESSION['usuari']->getUsuari();
		$actorId = is_object($actorText) && method_exists($actorText, 'get')
			? trim((string) $actorText->get())
			: '';
		$roles = $_SESSION['usuari']->getRols();
		if (!is_array($roles)) {
			$roles = [];
		}

		$sourceCourse = trim((string) ($_GET['sif_source_course'] ?? ''));
		$originalAmount = trim((string) ($_GET['sif_original_amount'] ?? ''));
		$manualPriceReason = trim((string) ($_GET['sif_manual_price_reason'] ?? ''));
		$expectedFiscalDecision = trim((string) ($_GET['sif_expected_fiscal_decision'] ?? ''));
		$expectedEconomicDecision = trim((string) ($_GET['sif_expected_economic_decision'] ?? ''));

		if ($actorId === '' || $roles === [] || $sourceCourse === '' || $originalAmount === '') {
			throw new Exception('Invalid SIF course change context', 422);
		}

		// Recalcular al servidor el preu estàndard de destí amb la mateixa lògica
		// llegada que alimenta el formulari. El preu manual continua separat.
		$standardTargetRaw = $_SESSION['intranet']->buscarPreuAPagar_modalCanviCurs(
			$idInsc,
			$anyC,
			$mesC,
			$cursC,
			$originalAmount,
			$tipusDesc,
			$validDesc
		);
		$standardTargetAmount = str_replace(',', '.', trim(strip_tags((string) $standardTargetRaw)));
		if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $standardTargetAmount)) {
			throw new Exception('Could not resolve authoritative target price', 422);
		}

		$client = new SifInternalApiClient();
		$preview = $client->previewCourseChange($actorId, $roles, [
			'source_enrollment_id' => (int) $idInsc,
			'source_course' => $sourceCourse,
			'target_course' => (string) $cursC,
			'original_amount' => $originalAmount,
			'standard_target_amount' => $standardTargetAmount,
			'proposed_target_amount' => (string) $apagarC,
			'paid_amount' => (string) $pagatC,
			'management_fee' => (string) $despesesC,
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
			throw new Exception('SIF course change preflight rejected', 409);
		}

		$impact = is_array($preview['impact'] ?? null) ? $preview['impact'] : [];
		$actualFiscalDecision = (string) ($impact['fiscal_decision'] ?? '');
		$actualEconomicDecision = (string) ($impact['economic_decision'] ?? '');

		if (
			$expectedFiscalDecision !== ''
			&& $expectedFiscalDecision !== $actualFiscalDecision
		) {
			throw new Exception('SIF fiscal decision changed before confirmation', 409);
		}

		if (
			$expectedEconomicDecision !== ''
			&& $expectedEconomicDecision !== $actualEconomicDecision
		) {
			throw new Exception('SIF economic decision changed before confirmation', 409);
		}
	}

	echo $_SESSION['intranet']->realitzarCanviCurs_modalCanviCurs($idInsc, $anyC,
	$mesC, $cursC, $numeroCanvi, $apagarC, $pagatC, $pendentC, $despesesC,
	$obsCanvi, $motiuCanvi, $enviarCoreu, $tipusDesc, $validDesc);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
