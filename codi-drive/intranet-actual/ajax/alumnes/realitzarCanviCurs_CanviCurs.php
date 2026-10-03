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
require_once ('../../LegacyUsocCourseChangePricingSourceInterface.php');
require_once ('../../LegacyUsocCourseChangePricingMysqlSource.php');
require_once ('../../LegacyUsocCourseChangePricingResolver.php');
require_once ('../../SifAuthenticatedActor.php');
require_once ('../../SifInternalUsocClient.php');
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

	$pricingSource = new LegacyUsocCourseChangePricingMysqlSource();
	$sourceIdentity = $pricingSource->enrollment($idInsc);
	$isValidatedUsoc =
		(int) ($sourceIdentity['tipus_desc'] ?? 0) === 4
		&& (int) ($sourceIdentity['valid_desc'] ?? 0) === 1;

	if ($isValidatedUsoc) {
		if (
			filter_var(
				$numeroCanvi,
				FILTER_VALIDATE_INT,
				['options' => ['min_range' => 0, 'max_range' => 4]]
			) === false
		) {
			throw new RuntimeException('Error: número de canvi USOC no vàlid.', 422);
		}

		$pricing = (new LegacyUsocCourseChangePricingResolver($pricingSource))->resolve(
			$idInsc,
			$anyC,
			$mesC,
			$cursC,
			(int) $numeroCanvi
		);
		[$actorId, $roles] = SifAuthenticatedActor::fromUser($_SESSION['usuari']);

		$semantic = [
			'id_insc' => $idInsc,
			'idpag' => (int) $pricing['idpag'],
			'change_number' => (int) $numeroCanvi,
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
		$requestId = 'uc013-course-change-' . $idInsc . '-'
			. substr(hash('sha256', $semanticJson), 0, 32);

		$context = $_SESSION['sif_usoc_course_change'] ?? null;
		if (
			!is_array($context)
			|| (string) ($context['request_id'] ?? '') !== $requestId
			|| (string) ($context['actor_id'] ?? '') !== $actorId
			|| (int) ($context['source_id_insc'] ?? 0) !== $idInsc
			|| (int) ($context['source_idpag'] ?? 0) !== (int) $pricing['idpag']
			|| (int) ($context['destination_id_insc'] ?? 0) <= 0
			|| (int) ($context['destination_idpag'] ?? 0) <= 0
			|| (int) ($context['destination_idpag'] ?? 0) === (int) $pricing['idpag']
			|| preg_match(
				'/^SIF-USOC-CC:[a-f0-9]{32}$/D',
				(string) ($context['reservation_marker'] ?? '')
			) !== 1
		) {
			throw new RuntimeException(
				'Error: cal tornar a confirmar el preview USOC abans d’executar el canvi.',
				409
			);
		}

		$client = new SifInternalUsocClient();
		$prepareResponse = $client->prepareCourseChange(
			$actorId,
			$roles,
			$idInsc,
			(int) $pricing['idpag'],
			$requestId,
			$pricing['target']
		);
		$prepareStatus = (int) ($prepareResponse['_http_status'] ?? 0);
		unset($prepareResponse['_http_status']);
		if (
			$prepareStatus < 200
			|| $prepareStatus >= 300
			|| ($prepareResponse['ok'] ?? false) !== true
			|| !is_array($prepareResponse['preparation'] ?? null)
		) {
			throw new RuntimeException(
				'Error: el checkpoint USOC ja no és executable; torna a revisar el canvi.',
				409
			);
		}

		$preparation = $prepareResponse['preparation'];
		$preview = $preparation['preview'] ?? null;
		$target = is_array($preview) ? ($preview['target'] ?? null) : null;
		$studentFunds = is_array($preview)
			? ($preview['fund_plan']['payers']['student'] ?? null)
			: null;
		if (!is_array($target) || !is_array($studentFunds)) {
			throw new RuntimeException('Error: checkpoint USOC incomplet.', 409);
		}

		$expectedStudentTotal = number_format(
			(float) ($target['target_student_total'] ?? 0),
			2,
			'.',
			''
		);
		if (
			$expectedStudentTotal !== number_format(
				(float) ($context['target_student_total'] ?? -1),
				2,
				'.',
				''
			)
		) {
			throw new RuntimeException(
				'Error: l’import destí ha canviat després de reservar la matrícula.',
				409
			);
		}

		$apagarC = (string) ($target['target_student_course_amount'] ?? '');
		$despesesC = (string) ($target['management_fee'] ?? '0.00');
		$pagatC = (string) ($studentFunds['compensate_amount'] ?? '0.00');
		$pendentC = (string) ($studentFunds['amount_due'] ?? '0.00');
		$tipusDesc = '4';
		$validDesc = '1';

		if (!(bool) ($context['legacy_completed'] ?? false)) {
			$_SESSION['intranet']->realitzarCanviCurs_modalCanviCurs(
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
				$validDesc,
				[
					'destination_id_insc' => (int) $context['destination_id_insc'],
					'destination_idpag' => (int) $context['destination_idpag'],
					'reservation_marker' => (string) $context['reservation_marker'],
				]
			);

			$createdId = (int) $_SESSION['intranet']->getDarrerIdCanviCurs();
			if ($createdId !== (int) $context['destination_id_insc']) {
				throw new RuntimeException(
					'Error: el legacy no ha completat la matrícula destí reservada.',
					409
				);
			}

			$context['legacy_completed'] = true;
			$_SESSION['sif_usoc_course_change'] = $context;
		}

		$effectiveAt = trim((string) ($context['effective_at'] ?? ''));
		if ($effectiveAt === '') {
			$effectiveAt = trim((string) ($preparation['created_at'] ?? ''));
		}
		if ($effectiveAt === '') {
			throw new RuntimeException('Error: falta la data estable del checkpoint USOC.', 409);
		}

		$executionResponse = $client->executeCourseChange(
			$actorId,
			$roles,
			$requestId,
			$idInsc,
			(int) $pricing['idpag'],
			(int) $context['destination_id_insc'],
			['effective_at' => $effectiveAt]
		);
		$executionStatus = (int) ($executionResponse['_http_status'] ?? 0);
		unset($executionResponse['_http_status']);
		$execution = $executionResponse['execution'] ?? null;
		if (
			$executionStatus < 200
			|| $executionStatus >= 300
			|| ($executionResponse['ok'] ?? false) !== true
			|| !is_array($execution)
			|| (string) ($execution['state'] ?? '') !== 'COMPLETED'
		) {
			$error = trim((string) ($executionResponse['error'] ?? ''));
			throw new RuntimeException(
				$error !== ''
					? 'Error: ' . $error
					: 'Error: el SIF no ha pogut finalitzar el canvi de curs USOC.',
				$executionStatus >= 400 && $executionStatus <= 599
					? $executionStatus
					: 503
			);
		}

		unset($_SESSION['sif_usoc_course_change']);
		echo '<br />UC013_SIF_COMPLETED';
		return;
	}

	(new LegacyUsocLifecycleGuard())->assertMayUseLegacyMutation(
		$_SESSION['usuari'],
		$idInsc,
		'course_change'
	);

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