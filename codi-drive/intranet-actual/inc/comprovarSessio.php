<?php
session_start();
	include ('ConnexioIntranet.php');
	include ('ConnexioWeb.php');
	include ('Text.php');
	include ('Usuari.php');
	include ('inc/missatgesError.php');
	$configOk = false;

	try {
		if (isset($_SESSION['usuari'])) {
			$_SESSION['usuari'] = unserialize($_SESSION['usuari']);

			$usuari = $_SESSION['usuari']->getUsuari()->get();
			$password = $_SESSION['usuari']->getHashPass()->get();

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
				$connexio->closeStmt();
				$connexio->desconectarBD();

				$connexioIntra = new ConnexioIntranet();
				$connexioIntra->connectarBD();

				$cnsPass = "SELECT PASSWORD, ROLS, MENU_EXT FROM usuaris WHERE USUARI LIKE ?";
				$stmtIntra=$connexioIntra->prepare($cnsPass);
				$stmtIntra->bind_param("s", $usuari);
				$stmtIntra->execute();
				$stmtIntra->store_result();
				if ($stmtIntra->num_rows() > 0) {
					$stmtIntra->bind_result($hashPassUser, $rols, $menuExt);
					$stmtIntra->fetch();

					$c = base64_decode($hashPassUser);
				   $cipher="AES-128-CBC";
				   $ivlen = openssl_cipher_iv_length($cipher);
				   $iv = substr($c, 0, $ivlen);
				   $hmac = substr($c, $ivlen, $sha2len=32);
				   $ciphertext_raw = substr($c, $ivlen+$sha2len);
				   $originalPass = openssl_decrypt($ciphertext_raw, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);

					$c = base64_decode($password);
				   $cipher="AES-128-CBC";
				   $ivlen = openssl_cipher_iv_length($cipher);
				   $iv = substr($c, 0, $ivlen);
				   $hmac = substr($c, $ivlen, $sha2len=32);
				   $ciphertext_raw = substr($c, $ivlen+$sha2len);
				   $savePass = openssl_decrypt($ciphertext_raw, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);

					if ( $originalPass == $savePass ) {
						$configOk = true;
						$_SESSION['usuari']-> setRols($rols);
						$_SESSION['usuari']-> setMenuExt($menuExt);
						$_SESSION['usuari'] = serialize($_SESSION['usuari']);
					}
				}
				$connexioIntra->closeStmt();
				$connexioIntra->desconectarBD();
			}
			else {
				throw new Exception("",2001);
			}
		}

	}
	catch(Exception $e) {
	   echo missatgeError($e->getCode());
	}
?>
