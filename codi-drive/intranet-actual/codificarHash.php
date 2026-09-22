<?php

$element = 'Fw7qBcgtW5DZXDdHOjdm';

require_once 'ConnexioWeb.php';
$connexio = new ConnexioWeb();
$connexio->connectarBD();
$cnsParam = "SELECT VALOR FROM params WHERE TIPUS LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR DATAF>=CURRENT_TIME)";
$stmt=$connexio->prepare($cnsParam);
$stmt->bind_param("s", $valor);
$valor = 'keyEncriptar';
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows() > 0) {
	$stmt->bind_result($keyEncr);
	$stmt->fetch();

	$cipher = "AES-128-CBC";
	$ivlen = openssl_cipher_iv_length($cipher);
	$iv = openssl_random_pseudo_bytes($ivlen);
	$ciphertext_raw = openssl_encrypt($element, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
	$hashElement1 = base64_encode( $iv.$hmac.$ciphertext_raw );

	echo $hashElement1;

	$c = base64_decode($hashElement1);
   $cipher="AES-128-CBC";
   $ivlen = openssl_cipher_iv_length($cipher);
   $iv = substr($c, 0, $ivlen);
   $hmac = substr($c, $ivlen, $sha2len=32);
   $ciphertext_raw = substr($c, $ivlen+$sha2len);
   $original_id = openssl_decrypt($ciphertext_raw, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
	$hashElement2 = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);

	if ($original_id == $element) {
		$mostrar = "success";
	}
	else {
		$mostrar = "warning|La constrasenya introduïda no és correcta.";
	}

}
else
	throw new Exception("La clau d'encriptació no existeix",0000);

$connexio->closeStmt();
$connexio->desconectarBD();

?>
