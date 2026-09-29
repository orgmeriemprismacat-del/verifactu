<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Numero.php");
include("../PaginaConfirmacioTastet.php");

try {
	$encr = isset($_GET['keyEncr']) ? trim($_GET['keyEncr']) : '';
	$urlTastet = isset($_GET['urlTastet']) ? trim($_GET['urlTastet']) : '';

	if ($encr == '') {
		echo missatgeError('1401');
		return;
	}

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	$stmt = $connexio->prepare($cnsParam);
	$stmt->bind_param("s", $tipusParam);
	$tipusParam = 'keyEncriptar';
	$stmt->execute();
	$stmt->bind_result($keyEncr);
	$stmt->fetch();
	$connexio->closeStmt();

	$cipher = "AES-128-CBC";
	$ivlen = openssl_cipher_iv_length($cipher);
	$sha2len = 32;
	$originalId = null;
	$tokenValid = false;

	if (strpos($encr, 'v2.') === 0) {
		$encoded = substr($encr, 3);
		$padding = strlen($encoded) % 4;
		if ($padding > 0)
			$encoded .= str_repeat('=', 4 - $padding);

		$raw = base64_decode(strtr($encoded, '-_', '+/'), true);
		if ($raw !== false && strlen($raw) > ($ivlen + $sha2len)) {
			$iv = substr($raw, 0, $ivlen);
			$hmac = substr($raw, $ivlen, $sha2len);
			$ciphertextRaw = substr($raw, $ivlen + $sha2len);
			$calcMac = hash_hmac('sha256', $iv.$ciphertextRaw, $keyEncr, true);

			if (hash_equals($hmac, $calcMac)) {
				$payloadRaw = openssl_decrypt($ciphertextRaw, $cipher, $keyEncr, OPENSSL_RAW_DATA, $iv);
				$payload = json_decode($payloadRaw, true);

				if (is_array($payload) && isset($payload['id']) && intval($payload['id']) > 0) {
					$urlToken = isset($payload['url']) ? $payload['url'] : '';
					if ($urlToken == '' || $urlTastet == '' || hash_equals($urlToken, $urlTastet)) {
						$originalId = intval($payload['id']);
						$tokenValid = true;
					}
				}
			}
		}
	}
	else {
		// Compatibilitat amb tokens antics ja emesos.
		$raw = base64_decode($encr, true);
		if ($raw !== false && strlen($raw) > ($ivlen + $sha2len)) {
			$iv = substr($raw, 0, $ivlen);
			$hmac = substr($raw, $ivlen, $sha2len);
			$ciphertextRaw = substr($raw, $ivlen + $sha2len);
			$calcMac = hash_hmac('sha256', $ciphertextRaw, $keyEncr, true);

			if (hash_equals($hmac, $calcMac)) {
				$legacyId = openssl_decrypt($ciphertextRaw, $cipher, $keyEncr, OPENSSL_RAW_DATA, $iv);
				if (is_numeric($legacyId) && intval($legacyId) > 0) {
					$originalId = intval($legacyId);
					$tokenValid = true;
				}
			}
		}
	}

	if ($tokenValid) {
		$pagina = new PaginaConfirmacioTastet($originalId);
		$mostrar = $pagina->mostrarPaginaConfirmacio();
	}
	else {
		$mostrar = missatgeError('1401');
	}

	$connexio->desconectarBD();
	echo $mostrar;
}
catch(Throwable $e) {
	if ($e->getCode() == 404)
		echo mostrarPagina404();
	else
		echo missatgeError($e->getCode());
}

?>