<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Numero.php");
include("../PagamentCurs.php");
include("../Edicio.php");
include("../PagamentGrupAutomatic.php");

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
      if (!ctype_digit((string) $original_idInsc) || (int) $original_idInsc <= 0) {
         throw new Exception('',1501);
      }

      $cnsTipusPagament = "SELECT COUNT(*)
         FROM inscripcions
         WHERE IDPAG=? AND TIPUS_INSC='P'
           AND (`INSC CURS`='0' OR `INSC CURS`='1' OR `INSC CURS`='M')";
      if (!$stmtTipusPagament = $connexio->prepare($cnsTipusPagament)) {
         throw new Exception('',1501);
      }
      $stmtTipusPagament->bind_param("d", $original_idInsc);
      $stmtTipusPagament->execute();
      $stmtTipusPagament->bind_result($numPack);
      $stmtTipusPagament->fetch();
      $connexio->closeStmt();

      if ((int) $numPack > 0) {
         $pagamentInscripcio = new PagamentGrupAutomatic($original_idInsc);
      }
      else {
         $pagamentInscripcio = new PagamentCurs($original_idInsc);
      }
      $mostrar = $pagamentInscripcio->mostrar();
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
