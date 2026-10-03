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

function normalizeLegacyCourseChangeMoney(mixed $value, string $field): string
{
	if (!is_numeric($value)) {
		throw new RuntimeException('Error: import no vàlid per a ' . $field . '.', 422);
	}
	$amount = (float) $value;
	if ($amount < 0) {
		throw new RuntimeException('Error: import negatiu no permès per a ' . $field . '.', 422);
	}
	return number_format($amount, 2, '.', '');
}

function loadLegacyCourseChangeSource(int $idInsc): array
{
	$conWeb = new ConnexioWeb();
	$conWeb->connectarBD();

	try {
		$stmt = $conWeb->prepare(
			"SELECT CURS, A_PAGAR, PAGAMENT, TIPUS_DESC, VALID_DESC
			 FROM inscripcions WHERE ID = ? LIMIT 1"
		);
		$stmt->bind_param("i", $idInsc);
		$stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows() !== 1) {
			$conWeb->closeStmt();
			throw new RuntimeException('Error: inscripció origen no trobada o ambigua.', 409);
		}
		$stmt->bind_result($course, $amount, $paid, $discountType, $discountStatus);
		$stmt->fetch();
		$conWeb->closeStmt();

		return [
			'course' => (string) $course,
			'amount' => normalizeLegacyCourseChangeMoney($amount, 'preu origen'),
			'paid' => normalizeLegacyCourseChangeMoney($paid, 'pagament origen'),
			'discount_type' => (int) $discountType,
			'discount_status' => (int) $discountStatus,
		];
	} finally {
		$conWeb->desconectarBD();
	}
}

function resolveLegacyPrismaStudentCourseChangePrice(
	int $any,
	string $mes,
	string $curs
): string {
	$conWeb = new ConnexioWeb();
	$conWeb->connectarBD();

	try {
		$idPreu = null;
		$hores = null;

		$stmt = $conWeb->prepare(
			"SELECT HORES, ID_PREU FROM curs
			 WHERE ANY = ? AND MES = ? AND CURS = ? LIMIT 2"
		);
		$stmt->bind_param("iss", $any, $mes, $curs);
		$stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows() === 1) {
			$stmt->bind_result($hores, $idPreu);
			$stmt->fetch();
			$conWeb->closeStmt();
		} else {
			$conWeb->closeStmt();
			$stmt = $conWeb->prepare(
				"SELECT HORES, ID_PREU FROM jornades
				 WHERE ANY = ? AND MES = ? AND CODI_CURS = ? LIMIT 2"
			);
			$stmt->bind_param("iss", $any, $mes, $curs);
			$stmt->execute();
			$stmt->store_result();
			if ($stmt->num_rows() !== 1) {
				$conWeb->closeStmt();
				throw new RuntimeException(
					'Error: no s\'ha pogut determinar una edició única per al canvi de curs.',
					409
				);
			}
			$stmt->bind_result($hores, $idPreu);
			$stmt->fetch();
			$conWeb->closeStmt();
		}

		$stmt = $conWeb->prepare(
			"SELECT IMPORT FROM preu
			 WHERE ID = ? AND DATAI <= CURRENT_TIMESTAMP
			   AND (DATAF IS NULL OR CURRENT_TIMESTAMP <= DATAF)"
		);
		$stmt->bind_param("i", $idPreu);
		$stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows() !== 1) {
			$conWeb->closeStmt();
			throw new RuntimeException('Error: tarifa base inexistent o ambigua.', 409);
		}
		$stmt->bind_result($basePrice);
		$stmt->fetch();
		$conWeb->closeStmt();

		$stmt = $conWeb->prepare(
			"SELECT PREU FROM descomptes
			 WHERE ID_PREU = ? AND TIPUS = 1
			   AND DATAI <= CURRENT_TIMESTAMP
			   AND (DATAF IS NULL OR CURRENT_TIMESTAMP <= DATAF)
			   AND (CURS = 'TOTS' OR CURS = ? OR CURS = ?)
			   AND (MES = 'TOTS' OR MES = ?)"
		);
		$horesSelector = (string) $hores;
		$stmt->bind_param("isss", $idPreu, $curs, $horesSelector, $mes);
		$stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows() !== 1) {
			$conWeb->closeStmt();
			throw new RuntimeException(
				'Error: tarifa Alumne PrisMa inexistent o ambigua per al curs destí.',
				409
			);
		}
		$stmt->bind_result($discountPrice);
		$stmt->fetch();
		$conWeb->closeStmt();

		$base = (float) normalizeLegacyCourseChangeMoney($basePrice, 'tarifa base destí');
		$discount = (float) normalizeLegacyCourseChangeMoney(
			$discountPrice,
			'tarifa Alumne PrisMa destí'
		);
		if ($base <= 0 || $discount <= 0 || $discount >= $base) {
			throw new RuntimeException(
				'Error: tarifa Alumne PrisMa incoherent amb la tarifa base del curs destí.',
				409
			);
		}

		return number_format($discount, 2, '.', '');
	} finally {
		$conWeb->desconectarBD();
	}
}

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

	/*
	 * UC-020/P06: TIPUS_DESC, VALID_DESC i imports AP no són autoritat del navegador.
	 * La inscripció origen es rellegeix sempre de BD. Si és Alumne PrisMa,
	 * la tarifa del curs destí es resol al servidor amb curs/hores/mes i unicitat.
	 */
	$source = loadLegacyCourseChangeSource($idInsc);
	$tipusDesc = (string) $source['discount_type'];
	$validDesc = (string) $source['discount_status'];

	if ((int) $tipusDesc === 1) {
		$apagarC = resolveLegacyPrismaStudentCourseChangePrice(
			(int) $anyC,
			$mesC,
			$cursC
		);
		$pagatC = $source['paid'];
		$despesesNormalitzades = normalizeLegacyCourseChangeMoney(
			$despesesC,
			'despeses de gestió'
		);
		$pendentC = number_format(
			(float) $apagarC + (float) $despesesNormalitzades - (float) $pagatC,
			2,
			'.',
			''
		);
		$despesesC = $despesesNormalitzades;
	}

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

		$standardTargetRaw = (int) $tipusDesc === 1
			? $apagarC
			: $_SESSION['intranet']->buscarPreuAPagar_modalCanviCurs(
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