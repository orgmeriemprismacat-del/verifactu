<?php
	session_start();
	include ('../ConnexioIntranet.php');
	include ('../ConnexioWeb.php');
	include ('../Text.php');
	include ('../Usuari.php');
	include ('../Intranet.php');
	include ('../inc/missatgesError.php');

	try {
		$usuari = $_GET['username'];
		$password = $_GET['password'];

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

			$connexioIntra = new ConnexioIntranet();
			$connexioIntra->connectarBD();

			$cnsPass = "SELECT IMG_PERFIL, NOM, COGNOMS, DEPARTAMENT, PASSWORD, ROL_PROVA FROM usuaris WHERE USUARI LIKE ?";
			$stmtIntra=$connexioIntra->prepare($cnsPass);
			$stmtIntra->bind_param("s", $usuari);
			$stmtIntra->execute();
			$stmtIntra->store_result();
			if ($stmtIntra->num_rows() > 0) {
				$stmtIntra->bind_result($urlImg, $nom, $cognoms, $departament, $hashPassUser, $esUnUserProva);
				$stmtIntra->fetch();
				$connexioIntra->closeStmt();
				// echo $hashPassUser."<br />";

				$c = base64_decode($hashPassUser);
			   $cipher="AES-128-CBC";
			   $ivlen = openssl_cipher_iv_length($cipher);
			   $iv = substr($c, 0, $ivlen);
			   $hmac = substr($c, $ivlen, $sha2len=32);
			   $ciphertext_raw = substr($c, $ivlen+$sha2len);
			   $originalPass = openssl_decrypt($ciphertext_raw, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);

				echo $usuari;

				if ( $originalPass == $password ) {
					$mostrar = "success";

					$objUsuari = new Usuari();
					$objUsuari->setUsuari($usuari);
					$objUsuari->setHashPass($hashPassUser);
					$objUsuari->setNom($nom);
					$objUsuari->setCognoms($cognoms);
					$objUsuari->setDepartament($departament);

					if ( $esUnUserProva == 1 )
						$objIntranet = new IntranetProva( serialize($objUsuari) );
					else
						$objIntranet = new Intranet( serialize($objUsuari) );

					$_SESSION['usuari'] = serialize($objUsuari);
					$_SESSION['intranet'] = serialize($objIntranet);
					$_SESSION['googleClient'] = '';
				}
				else {
					$connexioIntra->desconectarBD();
					$mostrar = "warning|La constrasenya introduïda no és correcta.";
				}
			}
			else {
				$connexioIntra->closeStmt();
				$connexioIntra->desconectarBD();
				$mostrar = "warning|L'usuari introduït no existeix.";
			}
			$connexio->desconectarBD();

			echo $mostrar;
		}
		else {
			$connexio->closeStmt();
			$connexio->desconectarBD();
			throw new Exception("",1001);
		}
	}
	catch(Exception $e) {
	   echo missatgeError($e->getCode());
	}
?>
