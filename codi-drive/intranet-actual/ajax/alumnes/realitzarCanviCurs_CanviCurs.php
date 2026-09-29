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

		$manualPriceReason = trim((string) ($_GET['sif_manual_price_reason'] ?? ''));
		$expectedFiscalDecision = trim((string) ($_GET['sif_expected_fiscal_decision'] ?? ''));
		$expectedEconomicDecision = trim((string) ($_GET['sif_expected_economic_decision'] ?? ''));

		if ($actorId === '' || $roles === []) {
			throw new Exception('Invalid SIF course change actor', 422);
		}

		$source = loadLegacyCourseChangeSource((int) $idInsc);
		$sourceCourse = $source['course'];
		$originalAmount = $source['amount'];
		$legacyPaidAmount = $source['paid'];

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
		$standardTargetAmount = normalizeLegacyMoney($standardTargetRaw, 'target price');

		$client = new SifInternalApiClient();
		$preview = $client->previewCourseChange($actorId, $roles, [
			'source_enrollment_id' => (int) $idInsc,
			'source_course' => $sourceCourse,
			'target_course' => (string) $cursC,
			'original_amount' => $originalAmount,
			'standard_target_amount' => $standardTargetAmount,
			'proposed_target_amount' => (string) $apagarC,
			'paid_amount' => $legacyPaidAmount,
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

		// Quan el SIF pot reconstruir fons reals, no es permet que el navegador
		// imposi un PAGAT diferent. En absència de factura SIF, el servei retorna
		// el valor llegat obtingut al servidor.
		$pagatC = normalizeLegacyMoney(
			$impact['paid_amount'] ?? $legacyPaidAmount,
			'paid amount'
		);
		$pendentC = number_format(
			(float) $apagarC + (float) $despesesC - (float) $pagatC,
			2,
			'.',
			''
		);
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

function loadLegacyCourseChangeSource(int $idInsc): array
{
	if ($idInsc <= 0) {
		throw new Exception('Invalid enrollment id', 422);
	}

	$connection = new ConnexioWeb();

	try {
		$connection->connectarBD();
		$stmt = $connection->prepare(
			'SELECT `INSC CURS`, A_PAGAR, PAGAMENT
			 FROM inscripcions
			 WHERE ID = ?
			 LIMIT 1'
		);
		$stmt->bind_param('i', $idInsc);
		$stmt->execute();
		$stmt->store_result();
		$stmt->bind_result($course, $amount, $paid);

		if (!$stmt->fetch()) {
			throw new Exception('Enrollment not found', 404);
		}

		return [
			'course' => trim((string) $course),
			'amount' => normalizeLegacyMoney($amount, 'original amount'),
			'paid' => normalizeLegacyMoney($paid ?? 0, 'paid amount'),
		];
	}
	finally {
		try {
			$connection->closeStmt();
		}
		catch(Throwable $_ignored) {
		}

		if (isset($connection->connexio) && $connection->connexio instanceof mysqli) {
			$connection->desconectarBD();
		}
	}
}

function normalizeLegacyMoney(mixed $value, string $field): string
{
	$text = str_replace(',', '.', trim(strip_tags((string) $value)));
	if ($text === '') {
		$text = '0';
	}
	if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $text)) {
		throw new Exception('Invalid ' . $field, 422);
	}

	return number_format((float) $text, 2, '.', '');
}

?>
