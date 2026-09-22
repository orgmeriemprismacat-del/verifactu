<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Numero.php");
include("../Text.php");
include("../Date.php");
include("../Template.php");
include("../Mail.php");
include("../MailSMTP.php");
include("../MailSMTPComvive.php");
include("../MailSMTPFile.php");

try {
	$tipusCurs = $_GET['tipusCurs'];
	$textNom = new Text($_GET['nom']);
	$textCog = new Text($_GET['cog']);
	$textDocumentacio = new Text($_GET['dni']);
	$numTelf = new Numero($_GET['telf']);
	$textEmail = new Text($_GET['email']);
	$textAdreca = new Text($_GET['adreca']);
	$textCodiPostal = new Text($_GET['codiPostal']);
	$textPoblacio = new Text($_GET['poblacio']);
	$textPerfil = new Text($_GET['perfil']);
	if ( $_GET['perfil'] == "Altres")
		$textPerfilAltres = new Text($_GET['perfilAltres']);
	else
		$textPerfilAltres = null;

	if ( $_GET['perfilCentre'] != '' )
		$textPerfilCentre = new Text($_GET['perfilCentre']);
	else
		$textPerfilCentre = '';

	if ( $_GET['titulacio'] == "Altres") {
		$textTitulacio = new Text($_GET['titulacio']);
		$textTitulacioAltres = new Text($_GET['titulacioAltres']);
		$textTitulacioSecundaria = null;
		$textTitulacioEstudiant = null;
	}
	else if ( $_GET['titulacio'] == "Prof. Ed. Secundària") {
		$textTitulacio = new Text('Ed. Secundària');
		$textTitulacioAltres = null;
		$textTitulacioSecundaria = new Text($_GET['titulacioSecundaria']);
		$textTitulacioEstudiant = null;
	}
	else if ( $_GET['titulacio'] == "Encara no tinc cap titulació, sóc estudiant de") {
		$textTitulacio = new Text('Estudiant');
		$textTitulacioAltres = null;
		$textTitulacioSecundaria = null;
		$textTitulacioEstudiant = new Text($_GET['titulacioEstudiant']);
	}
	else {
		$textTitulacio = new Text($_GET['titulacio']);
		$textTitulacioAltres = null;
		$textTitulacioSecundaria = null;
		$textTitulacioEstudiant = null;
	}
	if ( $_GET['tbTitulacio'] != '')
		$textTbTitulacio = new Text($_GET['tbTitulacio']);
	else
		$textTbTitulacio = null;
	$numAny = new Numero($_GET['any']);
	$textEdicio = new Text($_GET['edicio']);
	$textPagFrac = new Text($_GET['pagFrac']);
	// $textDates = new Text($_GET['dates']);
	$textConegut = new Text($_GET['conegut']);
	if ( $_GET['comentaris'] != '')
		$textComentaris = new Text($_GET['comentaris']);
	else
		$textComentaris = null;
	$textMailing = new Text($_GET['mailing']);
	// Calcular
	// $numPreuCar = new Numero($_GET['preuCar']);
	$textCodiCurs = new Text($_GET['codiCurs']);
	$textTitolCurs = new Text($_GET['titolCurs']);

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$textNom->arreglarParaulaBD('noms');
	$textCog->arreglarParaulaBD('noms');
	$textDocumentacio->arreglarParaulaBD('text_maj');
	$textEmail->arreglarParaulaBD('email');
	$textAdreca->arreglarParaulaBD('text');
	$textCodiPostal->arreglarParaulaBD('text_maj');
	$textPoblacio->arreglarParaulaBD('noms');
	$textPerfil->arreglarParaulaBD('text_no_mod');
	$textTitulacio->arreglarParaulaBD('text_no_mod');
	$textEdicio->arreglarParaulaBD('text_no_mod');
	$textPagFrac->arreglarParaulaBD('text');
	$textConegut->arreglarParaulaBD('text_no_mod');
	if ($textComentaris != null) $textComentaris->arreglarParaulaBD('text');
	$textMailing->arreglarParaulaBD('text');
	$textCodiCurs->arreglarParaulaBD('text_maj');
	$textTitolCurs->arreglarParaulaBD('text_no_mod');

	$dataInsc = date('d')."-".date('m')."-".date('Y')." ".date('H').":".date('i');
	$templates = new Template();

	/* ######################################################################### */
	$cnsINFO = "SELECT TITOL FROM informacio WHERE CODI_CURS=? AND ESTAT=1";
	$stmt=$connexio->prepare($cnsINFO);
	$stmt->bind_param("s", $codiCurs);
	$codiCurs = $textCodiCurs->obtenirText();
	$stmt->execute();
	$stmt->bind_result($titol);
	$stmt->fetch();
	$connexio->closeStmt();

	$textTitolCurs = new Text($titol);
	$textTitolCurs->arreglarParaulaBD('text_no_mod');

	/* ######################################################################### */
	//consulta per buscar la key de prisma $key
	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	$stmt=$connexio->prepare($cnsParam);
	$stmt->bind_param("s", $tipusParam);
	$tipusParam = 'keyEncriptar';
	$stmt->execute();
	$stmt->bind_result($keyEncr);
	$stmt->fetch();
	$tipusParam = 'anticipi-preu-usoc';
	$stmt->execute();
	$stmt->bind_result($anticipiPreu);
	$stmt->fetch();
	$connexio->closeStmt();

	$cipher = "AES-128-CBC";

	/* ######################################################################### */
	$cnsDatesCurs = "SELECT DATAI, DATAF, HORES, DATA_RESOL, ID_PREU FROM curs WHERE CURS=? AND ANY=? AND MES=?";
	$stmt=$connexio->prepare($cnsDatesCurs);
	$stmt->bind_param("sds", $codiCurs, $any, $mes);
	$codiCurs = $textCodiCurs->obtenirText();
	$any = $numAny->obtenirNumero();
	$mes = $textEdicio->obtenirText();
	$stmt->execute();
	$stmt->bind_result($datai, $dataf, $hores, $data_resol, $idPreu);
	$stmt->fetch();
	$connexio->closeStmt();

	/* ######################################################################### */
	// $textCursReconegut = "<p>Aquest curs està reconegut pel Departament d'Educació
	// de la Generalitat de Catalunya i té una durada lectiva de <strong>".$hores." hores</strong>.</p>";
	//
	// if ($data_resol==null or $data_resol=='') {
	// 	$textCursReconegut = "<p>Aquest curs té una durada lectiva de <strong>";
	// 	$textCursReconegut .= $hores." hores</strong>.<p>
	// 	<p>PrisMa, com a entitat organitzadora, ha sol·licitat el reconeixement de
	// 	les edicions del curs escolar 2023-2024 al Departament d'Educació. Tan bon
	// 	punt surti la resolució t'avisarem a través del correu electrònic.";
	// }
	//
	// if ($textPerfil->obtenirText()=='Consulta privada' ||
	// 	 $textPerfil->obtenirText()=='No estic treballant' ||
	// 	 $textPerfil->obtenirText()=='Altres') {
	// 	$textCursReconegut .= "Els nostres cursos compten com Formació Permanent
	// 	del Professorat sempre que es realitzin posteriorment a la data d’expedició
	// 	del títol d’accés a la docència (Magisteri o CAP / Màster en Educació Secundària).";
	// }

	/* ######################################################################### */
	$titolDocencia = 0;
	if ($textPerfil->obtenirText()=='Consulta privada' ||
		 $textPerfil->obtenirText()=='No estic treballant' ||
		 $textPerfil->obtenirText()=='Altres') {
		$titolDocencia = 1;
	}
	// $titolDocencia, $dataResol
	// $hores --- [HORES]

	// /* ######################################################################### */
	// $textEstudiant = '';
	// if ( $titulacioEstudiant != null) {
	// 	$textEstudiant = "<p>En el teu cas, ​atès que encara ​no disposes d'aquesta titulació
	// 	finalitzada, rebràs un certificat de PrisMa que p​odràs fer constar​ com ​a ​
	// 	currículum personal però ​que ​no ​te donarà punts en convocatòries oficials
	// 	del Departament d'Educació. En el cas que tinguis uns estudis universitaris
	// 	finalitzats, si us plau, contacta amb nosaltres perquè rectifiquem la sol·licitud.</p>";
	// }
	// $textTitulacioEstudiant

	/* ######################################################################### */
	$textHasRealitzatCurs='';
	//Buscar si el usuari XXX ha realitzat el curs xxx
	$cnsInsc = "SELECT ANY, MES FROM inscripcions WHERE CURS=? AND DNI=? AND GENERAT=1";
   $stmt=$connexio->prepare($cnsInsc);
   $stmt->bind_param("ss", $codiCurs, $documentacio);
	 $documentacio = $textDocumentacio->obtenirText();
   $stmt->execute();
   $stmt->store_result();
	if ($stmt->num_rows() > 0) {
		$stmt->bind_result($anyReal, $mesReal);
		$stmt->fetch();
	   $connexio->closeStmt();

		$cnsTitol = "SELECT NOM_CURS FROM curs WHERE CURS=? AND ANY=? AND MES=?";
	   $stmt=$connexio->prepare($cnsTitol);
	   $stmt->bind_param("sds", $codiCurs, $anyReal, $mesReal);
	   $stmt->execute();
		$stmt->bind_result($titolReal);
		$stmt->fetch();
		$connexio->closeStmt();

		$objMes = new Text($mesReal);
		$textMesReal = $objMes->obtenirMesLlarg();

		$textHasRealitzatCurs = "<p>Ja has realitzat el curs <strong>".$titolReal."</strong> ";
		$textHasRealitzatCurs .= "en l'edició <strong>".$textMesReal."</strong> ";
		$textHasRealitzatCurs .= "de l'any <strong>".$anyReal."</strong>.</p>";
	}
   $connexio->closeStmt();

	/* ######################################################################### */
	$textAlumne='';

	$cnsIdPag = "SELECT IDPAG FROM inscripcions ORDER BY IDPAG DESC LIMIT 1";
	$stmt=$connexio->prepare($cnsIdPag);
	$stmt->execute();
	$stmt->bind_result($idPag);
	$stmt->fetch();
	$connexio->closeStmt();
	$idPag = $idPag+1;

	$ivlen = openssl_cipher_iv_length($cipher);
	$iv = openssl_random_pseudo_bytes($ivlen);
	$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
	$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

	$urlIdPag = "https://www.prisma.cat/pagament/".$hashIdPag;

	$edicio = $textEdicio->obtenirText();
	$titolCurs = $textTitolCurs->obtenirText();
	$pagFrac = $textPagFrac->obtenirText();
	$mailing = $textMailing->obtenirText();

	/* ######################################################################### */
	//Busquem la data d'inici del curs
	// if ( $datai <= date('Y-m-d') ) { /*ha començat el curs*/
	// 	$textIniciCurs = "<p>En un període de 24 hores laborals podràs accedir al
	// 	curs amb les teves claus.</p>";
	// 	if ( $tipusDescompte == 0 )
	// 		$textIniciCurs = "<p>En un període de 24 hores laborals rebràs un correu
	// 		electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
	// }
	// else {
	// 	$textIniciCurs = "<p>Uns dies abans de l'inici del curs podràs accedir a
	// 	l'apartat general de l'aula amb les teves claus.</p>";
	// 	if ( $tipusDescompte == 0 )
	// 		$textIniciCurs = "<p>Uns dies abans de l'inici del curs rebràs un correu
	// 		electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
	// }

	// $datai, $tipusDescompte

	/* ######################################################################### */
	// $textConsentimentMailing = '';
	// if ( $mailing == 'Yes' ) {
	// 	$textConsentimentMailing = "
	// 		<p>Et recordem que has marcat la casella per rebre correus electrònics
	// 		informatius dels nostres cursos i serveis. Tot i això, podràs donar-te
	// 		de baixa de la nostra llista de correus en qualsevol moment.</p>";
	// }

	// $mailing

	/* ######################################################################### */
	$nom = $textNom->obtenirText();
	$cog = $textCog->obtenirText();
	$nomCognoms = $nom." ".$cog;
	$documentacio = $textDocumentacio->obtenirText();
	$email = $textEmail->obtenirText();
	$telf = $numTelf->obtenirNumero();
	$adreca = $textAdreca->obtenirText();
	$codiPostal = $textCodiPostal->obtenirText();
	$poblacio = $textPoblacio->obtenirText();
	$perfils = $textPerfil->obtenirText();
	if ($textPerfilAltres!=null)  {
		$textPerfilAltres->arreglarParaulaBD('text');
		$perfils .= ': '.$textPerfilAltres->obtenirText();
	}
	if ( $tipusCurs == 'S' && $textPerfilCentre != '' ) {
		$textPerfilCentre->arreglarParaulaBD('text');
		$perfils .= ", Centre: ".$textPerfilCentre->obtenirText();
	}

	$titulacions = $textTitulacio->obtenirText();
	if ($textTitulacioAltres!=null)  {
		$textTitulacioAltres->arreglarParaulaBD('text');
		$titulacions .= ', '.$textTitulacioAltres->obtenirText();
	}
	else if ($textTitulacioSecundaria!=null)  {
		$textTitulacioSecundaria->arreglarParaulaBD('text');
		$titulacions .= ', '.$textTitulacioSecundaria->obtenirText();
	}
	else if ($textTitulacioEstudiant!=null)  {
		$textTitulacioEstudiant->arreglarParaulaBD('text');
		$titulacions .= ', '.$textTitulacioEstudiant->obtenirText();
	}

	if ($textTbTitulacio!=null)  {
		$textTbTitulacio->arreglarParaulaBD('text');
		$titulacions .= ', També tinc la titulació de: '.$textTbTitulacio->obtenirText();
	}

	// $codiCurs = $textCodiCurs->obtenirText();
	$titolCurs = $textTitolCurs->obtenirText();
	$conegut = $textConegut->obtenirText();
	$edicio = $textEdicio->obtenirText();
	if ($textComentaris != null)
		$comentaris = $textComentaris->obtenirText();



	/* ######################################################################### */

	$objDataI = new Date($datai);
	$objDataF = new Date($dataf);

	if ( $objDataI->getAny() != $objDataF->getAny() ) {
		//De l'1 de desembre de 2021 al 15 de febrer de 2022
		//De l'1 de desembre de 2021 a l'11 de febrer de 2022
		//Del 2 de desembre de 2021 al 15 de febrer de 2022
		//Del 2 de desembre de 2021 a l'11 de febrer de 2022
		$textDates = $objDataI->getPronomDel()."".$objDataI->getDataLlarga()."
		".$objDataF->getPronomAl()."".$objDataF->getDataLlarga()."";
	}
	else {
		if ( $objDataI->getMes() != $objDataF->getMes() ) {
			//Del 4 d'abril a l'11 de maig de 2022
			$textDates = $objDataI->getPronomDel()."".intval($objDataI->getDia())."
			".$objDataI->getNomMesArticle()."
			".$objDataF->getPronomAl()."".$objDataF->getDataLlarga()."";
		}
		else {
			//Del 4 al 31 de juliol de 2022
			$textDates = $objDataI->getPronomDel()."".intval($objDataI->getDia())."
			".$objDataF->getPronomAl()."".$objDataF->getDataLlarga()."";
		}
	}
	$datesRealitzacio = $textDates;

	/* ######################################################################### */
	$tipusDescompte = 4;

	$msg = $templates->getTemplate_Dades_RequadreDadesPersonalsCurs(2);
	$names_template = array("[NOM_ALUMNE]", "[COG_ALUMNE]", "[DNI_ALUMNE]",
	"[EMAIL_ALUMNE]",	"[TEL_ALUMNE]", "[TITOL]", "[DATAI_DATAF]", "[PAY_DESC_ALUMNE]");
	$names_function   = array($nom, $cog, $documentacio, $email, $telf,
	$titolCurs, $datesRealitzacio, $anticipiPreu);
	$reqDadesPersonalsCurs = str_replace($names_template, $names_function, $msg);

	$msg = $templates->getTemplate_Inscripcions_EnviamentUSOC();
	$names_template = array("[NOM_ALUMNE]", "[TEXT_DADES_ALUMNE]");
	$names_function   = array($nom, $reqDadesPersonalsCurs);
	$missatge = str_replace($names_template, $names_function, $msg);

	$msg = $templates->getTemplate_Inscripcions_EnviamentUSOCShort();
	$names_template = array("[NOM_ALUMNE]", "[COG_ALUMNE]", "[DNI_ALUMNE]",
		"[EMAIL_ALUMNE]",	"[TEL_ALUMNE]", "[ADRECA_ALUMNE]", "[CP_ALUMNE]",
		"[POBLACIO_ALUMNE]", "[PERFIL_ALUMNE]", "[TITULACIO_ALUMNE]", "[TITOL]",
		"[DATAI_DATAF]", "[CONEGUT_ALUMNE]", "[PAY_DESC_ALUMNE]","[COMENTARIS_ALUMNE]",
		"[IDPAG_PAY]", "[URL_PAGAMENT]");
	$names_function   = array($nom, $cog, $documentacio, $email, $telf, $adreca,
		$codiPostal, $poblacio, $perfils, $titulacions, $titolCurs, $datesRealitzacio,
		$conegut, $anticipiPreu, $comentaris, $idPag, $urlIdPag);
	$msgInsc = str_replace($names_template, $names_function, $msg);

	$subjectMailInsc = "Inscripció ".$codiCurs." ".$edicio." - ".$documentacio;
	if ($comentaris != '' || $tipusDescompte==4 )
		$subjectMailInsc .= " + O";
	/* ######################################################################### */

	//buscar el username i el password d'autentificació de prisma
	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	$stmt=$connexio->prepare($cnsParam);
	$stmt->bind_param("s", $tipusParam);

	$tipusParam = 'autentificacioInscripcioCopiaInscripcions';
	$stmt->execute();
	$stmt->bind_result($valor);
	$stmt->fetch();
	$autentificacioInscripcio = explode('|',$valor);
	$usernameInsc = $autentificacioInscripcio[0];
	$passwordInsc = $autentificacioInscripcio[1];
	$nameUserInsc = $autentificacioInscripcio[2];

	$tipusParam = 'autentificacioInscripcio';
	$stmt->execute();
	$stmt->bind_result($valor);
	$stmt->fetch();
	$autentificacioInscripcio = explode('|',$valor);
	$username = $autentificacioInscripcio[0];
	$password = $autentificacioInscripcio[1];
	$nameUser = $autentificacioInscripcio[2];

	$connexio->closeStmt();

	/* ######################################################################### */

	$subject = "Inscripció al curs ".$titolCurs;

	$nomFromHead = 'Secretaria PrisMa';
	$correuFromHead = 'inscripcions@prisma.cat';
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = 'Secretaria PrisMa';
	$correuTo = 'inscripcions@prisma.cat';
	$correuTo = 'meriem.prisma.cat@gmail.com';

	$mailCopiaInsc = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);

	$nomFromHead = 'Secretaria PrisMa';
	$correuFromHead = 'secretaria@prisma.cat';
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";
	$correuTo = 'meriem.prisma.cat@gmail.com';

	$subject2 = "Inscripció al curs ".$titolCurs." ".$dataInsc;

	$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject2, $missatge);

	$nomTo = 'Secretaria PrisMa';
	$correuTo = 'inscripcions@prisma.cat';
	$correuTo = 'meriem.prisma.cat@gmail.com';

	$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject, $missatge);

	/* ######################################################################### */
	$nomBD = $textNom->obtenirText();
	$cogBD = $textCog->obtenirText();
	$nomCognomsBD = $nomBD." ".$cogBD;
	$documentacioBD = $textDocumentacio->obtenirText();
	$emailBD = $textEmail->obtenirText();
	$telfBD = $numTelf->obtenirNumero();
	$adrecaBD = $textAdreca->obtenirText();
	$codiPostalBD = $textCodiPostal->obtenirText();
	$poblacioBD = $textPoblacio->obtenirText();
	$perfilsBD = $textPerfil->obtenirText();
	if ($textPerfilAltres!=null)  {
		$textPerfilAltres->arreglarParaulaBD('text');
		$perfilsBD .= ': '.$textPerfilAltres->obtenirText();
	}
	if ( $tipusCurs == 'S' && $textPerfilCentre != '' ) {
		$textPerfilCentre->arreglarParaulaBD('text');
		$perfilsBD .= ", Centre: ".$textPerfilCentre->obtenirText();
	}

	$titulacionsBD = $textTitulacio->obtenirText();
	if ($textTitulacioAltres!=null)  {
		$textTitulacioAltres->arreglarParaulaBD('text');
		$titulacionsBD .= ': '.$textTitulacioAltres->obtenirText();
	}
	else if ($textTitulacioSecundaria!=null)  {
		$textTitulacioSecundaria->arreglarParaulaBD('text');
		$titulacionsBD .= ', '.$textTitulacioSecundaria->obtenirText();
	}
	else if ($textTitulacioEstudiant!=null)  {
		$textTitulacioEstudiant->arreglarParaulaBD('text');
		$titulacionsBD .= ', '.$textTitulacioEstudiant->obtenirText();
	}

	if ($textTbTitulacio!=null)  {
		$textTbTitulacio->arreglarParaulaBD('text');
		$titulacionsBD .= ', També tinc la titulació de: '.$textTbTitulacio->obtenirText();
	}

	$conegutBD = $textConegut->obtenirText();
	$edicioBD = $textEdicio->obtenirText();
	$codiCursBD = $textCodiCurs->obtenirText();
	$titolCursBD = $textTitolCurs->obtenirText();
	$pagFracBD = $textPagFrac->obtenirText();

	$comentarisBD = '';
	if ($textComentaris!=null) $comentarisBD = $textComentaris->obtenirText();

	$pagFraccBD = 0;

	$mailingBD = '0';

	$usuariBD = '';
	$clean = preg_replace('~[^0-9]+~', '', $documentacioBD);
	preg_match_all('!\d+!', $clean, $numero);
	$usuariBD = implode(' ', $numero[0]);

	$currentDate = 'CURRENT_DATE';
	$carnetJove = '';
	if ($tipusDescompte == 2) $carnetJove = 'Carnet Jove';
	else if ($tipusDescompte==4) $carnetJove = 'Carnet USOC';
	else if ($tipusDescompte==5) $carnetJove = 'Carnet de discapacitat';
	else if ($tipusDescompte==6) $carnetJove = 'Carnet de familia nombrosa';
	else if ($tipusDescompte==7) $carnetJove = 'Carnet de familia monoparental';
	$perenne = 'X';

	$tipusDesc=$tipusDescompte;
	$validDesc = 0;

	$preuDescompte = $anticipiPreu;

	$insertBD = "INSERT INTO inscripcions (ANY, MES, CURS, DATA_INSC, NOM, COGNOMS,
					 CORREU, DNI, ADRECA, Codi_Postal, POBLACIO, PERFIL, TITULACIO,
					 TELEFON, OBSERVACIONS, COMENTARIS, FRACCIONAT, INSC_MAILING,
					 A_PAGAR, USUARI, IDPAG, PERENNE, CONEGUT, TIPUS_DESC, VALID_DESC)
					 VALUES (?,?,?,CURRENT_TIME,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
	$stmt=$connexio->prepare($insertBD);
	$stmt->bind_param("dsssssssssssdssdsdddssdd", $any, $edicioBD, $codiCursBD, $nomBD,
		$cogBD, $emailBD, $documentacioBD, $adrecaBD, $codiPostalBD, $poblacioBD,
		$perfilsBD, $titulacionsBD, $telfBD, $carnetJove, $comentarisBD, $pagFraccBD,
		$mailingBD, $preuDescompte, $usuariBD, $idPag, $perenne, $conegutBD,
		$tipusDesc, $validDesc);
	$stmt->execute();
	$idInserit = $connexio->lastInsertId();
	$stmt->fetch();
	$connexio->closeStmt();

	$ivlen = openssl_cipher_iv_length($cipher);
	$iv = openssl_random_pseudo_bytes($ivlen);
	$ciphertext_raw = openssl_encrypt($idInserit, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
	$hashIdInserit = base64_encode( $iv.$hmac.$ciphertext_raw );

	echo $hashIdInserit;

	$nomFromHead = $nameUser;
	$correuFromHead = $username;
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = "PrisMa Secretaria";
	$correuTo = "inscripcions.prisma@gmail.com";
	$correuTo = 'meriem.prisma.cat@gmail.com';

	$mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);

	$cnsPoble = "SELECT ID FROM poblacions WHERE CP=? AND POBLE=?";
	$stmt=$connexio->prepare($cnsPoble);
	$stmt->bind_param("ds", $codiPostalBD, $poblacioBD);
	$stmt->execute();
	$stmt->store_result();
	if ( $stmt->num_rows() <= 0 ) {
		$connexio->closeStmt();

		$insertMailing = "INSERT INTO poblacions_validar (CP, POBLE) VALUES (?,?)";
		$stmt=$connexio->prepare($insertMailing);
		$stmt->bind_param("ds", $codiPostalBD, $poblacioBD);
		$stmt->execute();
		$stmt->fetch();
	}
	$connexio->closeStmt();

	/* ######################################################################### */

	//buscar el username i el password d'autentificació de prisma
	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	$stmt=$connexio->prepare($cnsParam);
	$stmt->bind_param("s", $tipusParam);
	$tipusParam = 'autentificacioInscripcio';
	$stmt->execute();
	$stmt->bind_result($valor);
	$stmt->fetch();
	$autentificacioInscripcio = explode('|',$valor);
	$username = $autentificacioInscripcio[0];
	$password = $autentificacioInscripcio[1];
	$nameUser = $autentificacioInscripcio[2];

	$nomFromHead = $nameUser;
	$correuFromHead = $username;
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;
	//
	if ( $tipusDescompte == 4 ) {
		$tipusParam = 'dadesMailFEUSOC';
		$stmt->execute();
		$stmt->bind_result($valor);
		$stmt->fetch();
		$dadesMailFEUSOC = explode('|',$valor);

		$mailFeusoc = $dadesMailFEUSOC[0];
		$nomSrFeusoc = $dadesMailFEUSOC[1];
		$nomCompletSrFeusoc = $dadesMailFEUSOC[2];

		$msg = $templates->getTemplate_Dades_RequadreDadesPersonals(0);
		$names_template = array("[NOM_ALUMNE]", "[COG_ALUMNE]", "[DNI_ALUMNE]",
		"[EMAIL_ALUMNE]",	"[TEL_ALUMNE]");
		$names_function   = array($nom, $cog, $documentacio, $email, $telf);
		$reqDadesPersonals = str_replace($names_template, $names_function, $msg);

		$msg = $templates->getTemplate_Inscripcions_USOC_EnviamentInscripcioAmbDescompte();
		$names_template = array("[NOM_FEUSOC]", "[TITOL]", "[DATAI_DATAF]", "[TEXT_DADES_ALUMNECURS]");
		$names_function   = array($nomSrFeusoc, $titolCurs, $datesRealitzacio, $reqDadesPersonals);
		$msgFeusoc = str_replace($names_template, $names_function, $msg);

		$subjectFeusoc = "PrisMa. Inscripció al curs ".$titolCurs." ".$dataInsc;

		$nomTo = $nomCompletSrFeusoc;
		$correuTo = $mailFeusoc;
		$nomReplyHeadFeu = "Secretaria PrisMa";
		$correuReplyHeadFeu = "secretaria@prisma.cat";
		$correuTo = 'meriem.prisma.cat@gmail.com';
		$mailFeu = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
											$nomReplyHeadFeu, $correuReplyHeadFeu, $nomTo, $correuTo,
											$subjectFeusoc, $msgFeusoc);
	}

	$connexio->closeStmt();


	$subject = "Inscripció al curs ".$titolCurs;
	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";
	$subject2 = "Inscripció al curs ".$titolCurs." ".$dataInsc;
	$correuTo = 'meriem.prisma.cat@gmail.com';

	$mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject2, $missatge);

	$nomTo = $nomCognoms;
	$correuTo = $email;
	$correuTo = 'meriem.prisma.cat@gmail.com';

	$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject, $missatge);

	$connexio->desconectarBD();
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
