<?php

	include ('../ConnexioIntranet.php');
	include ('../ConnexioWeb.php');
	include ('../Text.php');
	include ('../Usuari.php');
	include ('../MailSMTPComvive.php');
	include ('../Intranet.php');
	include ('../Date.php');
	include ('../Template.php');
	include ('../inc/missatgesError.php');

	session_start();

		$intranet = unserialize($_SESSION['intranet']);

		$sql_certificats = "SELECT NOM_CURS, c.CURS, c.MES, i.Grup, c.ANY,
		DATAF, i.ID, NOM, COGNOMS, DNI, i.CORREU
		FROM inscripcions AS i INNER JOIN  curs AS c ON
		i.CURS=c.CURS AND i.ANY=c.ANY AND i.MES=c.MES
		WHERE (UPPER(CERTIFICAT) = 'DIGITAL' OR UPPER(CERTIFICAT) = 'ESTUDIANT')  AND
		(`OBS CERT` = '' OR `OBS CERT` IS NULL) AND (i.ANY >= 2019) AND
		i.correu_info_certificat!=1 ORDER BY DNI";

		$conWeb = new ConnexioWeb();
		$conWeb2 = new ConnexioWeb();
		$conWeb->connectarBD();
		$conWeb2->connectarBD();
		if ( $stmt=$conWeb->prepare( $sql_certificats ) ) {
			$stmt->execute();
			$stmt->bind_result($titol, $codiCurs, $mes, $aula, $any, $dataf, $id, $nomInsc, $cogInsc, $dni, $emailInsc);
			while ( $stmt->fetch() ) {
				$nomPDF = $dni."_".$any.$codiCurs.$mes.$aula.".pdf";
				$enllacPDF = "https://old.prisma.cat/certificats/pdf/".$nomPDF;
				//Si existeix el fitxer $nomPDF a /intranet/certificats/
				if ($intranet->url_exists($enllacPDF)) {

					$templates = new Template();
					$msg = $templates->getTemplate_Secretaria_EnviarCertificat_Alumne();

					$objDataf = new Date($dataf);
					$datafPronom = $objDataf->getPronomEl()."<strong>".$objDataf->getDataLlarga()."</strong>";

					$subject = "PrisMa | Certificat digital ".$titol." (".$dni.")";

					$names_template = array("[NOM_ALUMNE]", "[CORREU_ALUMNE]", "[DATAF]", "[TITOL]", "[URL_PDF]");
					$names_function   = array($nomInsc, $emailInsc, $datafPronom, $titol, $enllacPDF);
					$missatge = str_replace($names_template, $names_function, $msg);

					$authSecre = $intranet->getAuthSMTP_Secretaria();
					$usernameSecre = $authSecre[0];
					$passwordSecre = $authSecre[1];
					$nameUserSecre = $authSecre[2];

					$qui = "Pablo Martori";
					$depart = "Secretari";

					// Enviem un correu de confirmació
					$nomFromHead = $nameUserSecre;
					$correuFromHead = $usernameSecre;
					$nomReplyHead = $nomInsc." ".$cogInsc;
					$correuReplyHead = $emailInsc;

					$nomTo = "PrisMa Secretaria";
					$correuTo = "resguard.secretaria@prisma.cat";
					
					$mailTut = new MailSMTPComvive($usernameSecre, $passwordSecre, $nomFromHead, $correuFromHead,
													$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
													$subject, $missatge, $qui, $depart);

					$nomTo = $nomInsc." ".$cogInsc;
					$correuTo = $emailInsc;
					// $correuTo = "meriem.prisma.cat@gmail.com";
					$nomReplyHead = $nameUserSecre;
					$correuReplyHead = $usernameSecre;

					$mailTut = new MailSMTPComvive($usernameSecre, $passwordSecre, $nomFromHead, $correuFromHead,
													$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
													$subject, $missatge, $qui, $depart);


					// echo "<p>El certificat del curs ".$codiCurs." de l'alumne ".$dni." S'HA ENVIAT.</p>";
					$observacio_certificat = date("d")."/".date("m")."/".date("Y")." (D)";
					$sql_update_inscripcions = "UPDATE inscripcions SET correu_info_certificat=1, `OBS CERT` = ? WHERE ID=?";
					if ( $stmt2=$conWeb2->prepare( $sql_update_inscripcions ) ) {
						$stmt2->bind_param("sd", $observacio_certificat, $id);
						$stmt2->execute();
						echo "<p>El certificat del curs ".$codiCurs." de l'alumne ".$dni." S'HA ACTUALOITZAT.</p>";
					}
					$conWeb2->closeStmt();
				}
				else {
					echo "<p>El certificat del curs ".$codiCurs." de l'alumne ".$dni." no existeix.</p>";
				}
			}
			$conWeb->closeStmt();
		}



 ?>
