<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');
include ('../../LegacyInvoiceMutationAuthorization.php');
include ('../../SifAuthenticatedActor.php');
include ('../../SifInternalInstallmentClient.php');
session_start();

$useSif = filter_var(
	getenv('SIF_INSTALLMENT_PAYMENT_ENFORCED') ?: '0',
	FILTER_VALIDATE_BOOLEAN
);
if ($useSif) {
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store, max-age=0');
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

	$csrfSessio = (string) ($_SESSION['csrf_alumnes_pagaments'] ?? '');
	$csrfRebut = (string) ($_POST['csrfToken'] ?? '');
	if ($csrfSessio === '' || $csrfRebut === '' || !hash_equals($csrfSessio, $csrfRebut)) {
		http_response_code(403);
		throw new RuntimeException('Error: token CSRF no vàlid.');
	}

	LegacyInvoiceMutationAuthorization::assertSameOrigin();
	LegacyInvoiceMutationAuthorization::assertCanEdit(
		$_SESSION['usuari'],
		$_SESSION['intranet'],
		'/alumnes-pagaments.php'
	);

	$idTipusRaw = $_POST['id'] ?? null;
	if (filter_var($idTipusRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
		http_response_code(422);
		throw new RuntimeException('Error: identificador d’inscripció no vàlid.');
	}
	$idTipus = (int) $idTipusRaw;

	$tipus = trim((string) ($_POST['tipus'] ?? ''));
	$pagament = trim((string) ($_POST['pagament'] ?? ''));
	$dataPag = trim((string) ($_POST['dataPag'] ?? ''));
	$banc = trim((string) ($_POST['banc'] ?? ''));
	$obs = trim((string) ($_POST['obs'] ?? ''));
	$numFact = trim((string) ($_POST['numFact'] ?? ''));
	$efact = (string) ($_POST['efact'] ?? '0');
	$operationId = trim((string) ($_POST['operationId'] ?? ''));
	$idInscSifRaw = $_POST['idInsc'] ?? null;
	$externalReference = trim((string) ($_POST['externalReference'] ?? ''));

	if ($tipus === '') {
		http_response_code(422);
		throw new RuntimeException('Error: falta el tipus de cobrament.');
	}
	if (!is_numeric($pagament) || (float) $pagament <= 0) {
		http_response_code(422);
		throw new RuntimeException('Error: import del cobrament no vàlid.');
	}
	if ($dataPag === '' || preg_match('/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}(?::\d{2})?)?$/D', $dataPag) !== 1) {
		http_response_code(422);
		throw new RuntimeException('Error: data del cobrament no vàlida.');
	}
	if ($banc === '') {
		http_response_code(422);
		throw new RuntimeException('Error: cal indicar el banc.');
	}
	if (
		$operationId === ''
		|| strlen($operationId) > 120
		|| preg_match('/^[A-Za-z0-9._:-]+$/D', $operationId) !== 1
	) {
		http_response_code(422);
		throw new RuntimeException('Error: identificador d’operació no vàlid.');
	}

	$idInscSif = null;
	if ($useSif && $numFact === '') {
		http_response_code(409);
		throw new RuntimeException(
			'Error: cal emetre la factura abans de registrar el cobrament al SIF.'
		);
	}

	if ($useSif) {
		if (filter_var($idInscSifRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
			http_response_code(422);
			throw new RuntimeException('Error: identificador real d’inscripció no vàlid.');
		}
		$idInscSif = (int) $idInscSifRaw;

		if ($externalReference === '' || strlen($externalReference) > 120) {
			http_response_code(422);
			throw new RuntimeException('Error: cal indicar una referència bancària o DS_ORDER vàlida.');
		}
	}

	if ($useSif) {
		$tipusNormalitzat = strtoupper(trim($tipus));
		if (!in_array($tipusNormalitzat, ['I', 'INDIVIDUAL', 'INSCRIPCIO', 'INSCRIPCIÓ'], true)) {
			http_response_code(422);
			throw new RuntimeException(
				'Error: aquest tipus de cobrament encara no està integrat amb UC-023.'
			);
		}

		[$actorId, $actorRoles] = SifAuthenticatedActor::fromUser($_SESSION['usuari']);
		$sifInput = [
			'amount' => number_format((float) str_replace(',', '.', $pagament), 2, '.', ''),
			'movement_date' => $dataPag,
			'id_insc' => $idInscSif,
			'bank' => $banc,
			'notes' => $obs,
			'operation_id' => $operationId,
		];

		if (strtoupper($banc) === 'TPV') {
			$sifInput['ds_order'] = $externalReference;
		}
		else {
			$sifInput['reference'] = $externalReference;
		}

		$response = (new SifInternalInstallmentClient())->register(
			$actorId,
			$actorRoles,
			null,
			$numFact,
			$sifInput
		);

		$status = (int) ($response['_http_status'] ?? 0);
		if ($status < 200 || $status >= 300 || ($response['ok'] ?? false) !== true) {
			$message = trim((string) ($response['error'] ?? ''));
			$http = $status >= 400 && $status <= 599 ? $status : 502;
			http_response_code($http);
			throw new RuntimeException(
				$message !== ''
					? 'Error SIF UC-023: ' . $message
					: 'Error SIF UC-023: no s’ha pogut registrar la fracció.'
			);
		}

		$uuidPayment = trim((string) ($response['uuid_payment'] ?? ''));
		$reused = ($response['idempotency_reused'] ?? false) === true;
		$reconciled = ($response['reconciled_existing'] ?? false) === true;
		echo json_encode([
			'ok' => true,
			'status' => $reused ? 'REUSED' : 'CREATED',
			'uuid_payment' => $uuidPayment,
			'idempotency_reused' => $reused,
			'reconciled_existing' => $reconciled,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	} else {
		// Compatibilitat temporal: mateix comportament funcional llegat, però la
		// mutació ja queda protegida per POST + sessió + CSRF + origen + permís.
		echo $_SESSION['intranet']->efectuarPagament(
			$idTipus,
			$tipus,
			$pagament,
			$dataPag,
			$banc,
			$obs,
			$numFact,
			$efact
		);
	}
} catch (Throwable $e) {
	$code = (int) $e->getCode();
	$status = http_response_code();
	if (!is_int($status) || $status < 400) {
		$status = $code >= 400 && $code <= 599 ? $code : 500;
		http_response_code($status);
	}

	if ($useSif) {
		$message = $e->getMessage() !== '' ? $e->getMessage() : 'No s’ha pogut completar el cobrament.';
		$typedStatus = $status === 409
			? 'CONFLICT'
			: ($status >= 500 ? 'PENDING_RETRY' : 'ERROR');

		echo json_encode([
			'ok' => false,
			'status' => $typedStatus,
			'error' => $message,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
	else if ($status >= 500 && !str_starts_with($e->getMessage(), 'Error SIF UC-023:')) {
		echo 'Error: no s’ha pogut completar el cobrament.';
	}
	else {
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
