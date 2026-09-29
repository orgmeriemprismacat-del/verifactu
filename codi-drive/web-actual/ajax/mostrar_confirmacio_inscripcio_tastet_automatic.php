<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Numero.php");
include("../PaginaConfirmacioTastet.php");
include("../Uc108ConfirmationToken.php");

try {
	if (!isset($_GET['keyEncr']) || trim($_GET['keyEncr']) === '')
		throw new Exception('',1401);

	$encr = trim($_GET['keyEncr']);

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
	$mostrar = '';

	$c = base64_decode($encr, true);
	if ($c === false)
		throw new Exception('',1401);
   $cipher="AES-128-CBC";
   $ivlen = openssl_cipher_iv_length($cipher);
   $iv = substr($c, 0, $ivlen);
   $hmac = substr($c, $ivlen, $sha2len=32);
   $ciphertext_raw = substr($c, $ivlen+$sha2len);
   $original_id = openssl_decrypt($ciphertext_raw, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
   if (strlen($c) <= ($ivlen + $sha2len))
      throw new Exception('',1401);

   // Tokens nous: HMAC(IV + ciphertext). Es manté lectura de tokens antics
   // HMAC(ciphertext) durant la transició perquè els enllaços ja emesos funcionin.
   $calcmac = hash_hmac('sha256', $iv.$ciphertext_raw, $keyEncr, $as_binary=true);
   $legacyCalcmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);

   if (hash_equals($hmac, $calcmac) || hash_equals($hmac, $legacyCalcmac)) {
		if ($original_id === false || !ctype_digit((string)$original_id))
			throw new Exception('',1401);

		$pagamentInscripcio= new PaginaConfirmacioTastet($original_id);
		$mostrar = $pagamentInscripcio->mostrarPaginaConfirmacio();
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