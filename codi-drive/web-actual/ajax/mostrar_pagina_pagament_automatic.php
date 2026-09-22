<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Numero.php");
include("../PagamentTallerAutomatic.php");
include("../PagamentCursAutomatic.php");

try {
	$encr = substr(explode("?", $_SERVER["REQUEST_URI"])[1], "8", "-16");

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	$stmt=$connexio->prepare($cnsParam);
	$stmt->bind_param("s", $tipusParam);
	$tipusParam = 'keyEncriptar';
	$stmt->execute();
	$stmt->bind_result($keyEncr);
	$stmt->fetch();
	$connexio->closeStmt();

	$cipher = "AES-128-CBC";
	$mostrar = '';

	$c = base64_decode($encr);
   $cipher="AES-128-CBC";
   $ivlen = openssl_cipher_iv_length($cipher);
   $iv = substr($c, 0, $ivlen);
   $hmac = substr($c, $ivlen, $sha2len=32);
   $ciphertext_raw = substr($c, $ivlen+$sha2len);
   $original_idInsc = openssl_decrypt($ciphertext_raw, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
   $calcmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
   if (hash_equals($hmac, $calcmac)) {
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

		$cnsInsc = "SELECT TIPUS_INSC FROM inscripcions WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1')";
		$stmt=$connexio->prepare($cnsInsc);
		$stmt->bind_param("d", $original_idInsc);
		$stmt->execute();
		$stmt->bind_result($tipusInsc);
		$stmt->fetch();
      $connexio->closeStmt();
      $connexio->desconectarBD();

		if ( $tipusInsc == 'T' ) {
			$pagamentInscripcio = new PagamentTallerAutomatic($original_idInsc);
			$mostrar = $pagamentInscripcio->mostrar();
		}
		else {
			$pagamentInscripcio = new PagamentCursAutomatic($original_idInsc);
			$mostrar = $pagamentInscripcio->mostrar();
		}
   }
	else {
		$mostrar = missatgeError('1501');
	}

	$connexio->desconectarBD();

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
