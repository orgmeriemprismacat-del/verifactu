<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Numero.php");
include("../PagamentRegal.php");

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
   $original_id = openssl_decrypt($ciphertext_raw, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
   $calcmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
   if (hash_equals($hmac, $calcmac)) {
		$cnsInsc = "SELECT CODI FROM regal WHERE USAT=?";
		$stmt=$connexio->prepare($cnsInsc);
		$stmt->bind_param("s", $original_id);
		$stmt->execute();
		$stmt->store_result();
		if ( $stmt->num_rows() > 0 ) {
			$stmt->bind_result($codiRegal);
			$stmt->fetch();
			$connexio->closeStmt();
			$cnsInsc = "SELECT CORREU FROM inscripcions WHERE ID=?";
			$stmt=$connexio->prepare($cnsInsc);
			$stmt->bind_param("s", $original_id);
			$stmt->execute();
			$stmt->store_result();
			$stmt->bind_result($email);
			$stmt->fetch();
			$mostrar .= "
			<h1 class='mb-4'>Confirmació de la inscripció</h1>
			<div class='info-banner mb-4 pt-3'>
				<picture>
					<source type='image/webp' class='w-100 border-radius-2 banner-img'
						data-srcset='https://www.prisma.cat/img/portades/bescanvia-curs.webp'
						alt='Bescanvia la targeta regal!' 
						srcset='https://www.prisma.cat/img/portades/bescanvia-curs.webp'>
					<source type='image/jpeg' class='w-100 border-radius-2 banner-img'
						data-srcset='https://www.prisma.cat/img/portades/bescanvia-curs.jpg'
						alt='Bescanvia la targeta regal!'
						srcset='https://www.prisma.cat/img/portades/bescanvia-curs.jpg'>
					<img role='img' class='w-100 border-radius-2 banner-img lazyloaded'
					 	data-src='https://www.prisma.cat/img/portades/bescanvia-curs.jpg'
						alt='Bescanvia la targeta regal!'
						src='https://www.prisma.cat/img/portades/bescanvia-curs.jpg'>
				</picture>
			</div>
			<p>
				Acabes d'utilitzar el codi regal <span class='font-weight-bold'>".$codiRegal."</span>.
			</p>
			<p>
				Consulta la <span class='font-weight-bold'>safata d'entrada o el
				correu brossa (<em>spam</em>)</span> de l'adreça
				<span class='email font-weight-bold'>".$email."</span> per
				comprovar que has rebut el missatge de confirmació que se t'ha enviat.
			</p>
			<p>
				Si la inscripció no s'ha realitzat correctament, contacta amb nosaltres al
			 	telèfon <span class='font-weight-bold'>972 21 75 65</span> o
				a través del
				<a class='font-weight-bold' href='https://www.prisma.cat/contacte'
				title='Contacta amb PrisMa'>formulari de contacte</a>.
			</p>
			<p>
				Gràcies per confiar en PrisMa.
			</p>";


		}
		$connexio->closeStmt();
   }
	else {
		$mostrar = missatgeError('1701');
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
