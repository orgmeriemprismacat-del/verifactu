<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Numero.php");
include("../Date.php");
include("../Text.php");
include("../Mail.php");
include("../EdicioPack.php");
include("../Curs.php");
include("../Template.php");
include("../MailSMTPComvive.php");
include("../MailSMTP.php");

try {
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
	$textPagFrac = new Text($_GET['pagFrac']);
	$textConegut = new Text($_GET['conegut']);
	if ( $_GET['comentaris'] != '')
		$textComentaris = new Text($_GET['comentaris']);
	else
		$textComentaris = null;
	$textMailing = new Text($_GET['mailing']);
	$numPreuCursos = new Numero($_GET['preuCursos']);
	$numPreuPack = new Numero($_GET['preuPack']);
	$textIdPack = new Text($_GET['idPack']);

	$textNom->arreglarParaulaBD('noms');
	$textCog->arreglarParaulaBD('noms');
	$textDocumentacio->arreglarParaulaBD('text_maj');
	$textEmail->arreglarParaulaBD('email');
	$textAdreca->arreglarParaulaBD('text');
	$textCodiPostal->arreglarParaulaBD('text_maj');
	$textPoblacio->arreglarParaulaBD('noms');
	$textPerfil->arreglarParaulaBD('text_no_mod');
	$textTitulacio->arreglarParaulaBD('text_no_mod');
	$textPagFrac->arreglarParaulaBD('text');
	$textConegut->arreglarParaulaBD('text_no_mod');
	if ($textComentaris != null) $textComentaris->arreglarParaulaBD('text');
	$textMailing->arreglarParaulaBD('text');

	$dataInsc = date('d')."-".date('m')."-".date('Y')." ".date('H').":".date('i');

	$templates = new Template();

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	/* ######################################################################### */
	$cnsInfo = "SELECT TITOL FROM info_pack WHERE ID_PACK=? AND ESTAT=1";
	if ( $stmt=$connexio->prepare($cnsInfo) ) {
		$stmt->bind_param("s", $idPack);
		$idPack = $textIdPack->obtenirText();
		$stmt->execute();
		$stmt->bind_result($titol);
		$stmt->fetch();
		$connexio->closeStmt();
	}
	else {
		throw new Exception('',2910);
	}

	$textTitolCurs = new Text($titol);
	$textTitolCurs->arreglarParaulaBD('text_no_mod');

	/* ######################################################################### */
	//consulta per buscar la key de prisma $key
	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	if ( $stmt=$connexio->prepare($cnsParam) ) {
		$stmt->bind_param("s", $tipusParam);
		$tipusParam = 'keyEncriptar';
		$stmt->execute();
		$stmt->bind_result($keyEncr);
		$stmt->fetch();
		$connexio->closeStmt();
	}
	else {
		throw new Exception('',2911);
	}

	$cipher = "AES-128-CBC";

	/* ######################################################################### */

	$edicions = [];

	$cnsInfoOrig = "SELECT DATAI, DATAF, CURS_ESCOLAR, FISS, GTAF, DATA_RESOL, ANY, MES,
		c.CURS, p.ID_CURS, HORES, i.ID, i.ID_AMIGABLE, i.TITOL, c.ESTAT
		FROM packs AS p INNER JOIN info_pack AS ip ON p.ID_PACK = ip.ID_PACK INNER JOIN curs AS c
		ON p.ID_CURS = c.ID_CURS INNER JOIN informacio AS i ON c.CURS = i.CODI_CURS
		WHERE ip.ID_PACK = ? AND p.PUBLIC=1 AND c.PUBLIC=1 AND c.CURS!='PROVA' AND
		c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND i.ESTAT = 1
		AND c.CURS NOT LIKE '%JOR%' ORDER BY c.DATAI";
	if ( $stmt = $connexio->prepare($cnsInfoOrig) ) {
		$stmt->bind_param("d", $idPack);
		$stmt->execute();
		$stmt->bind_result($dataiEd, $datafEd, $cursEscEd, $fissEd, $gtafEd,
			$dataResEd, $anyEd, $mesEd, $cursEd, $idCursEd, $horesEd, $idInfoEd, $idUrl, $idTitol, $estatEd);
		$i = 0; $dispositiu = "ordinador";
		$datesRealitzacioCursos = "<ul style='list-style: square; margin-bottom: 0px; margin-left: 30px; padding: 0;'>";
		while ( $stmt->fetch() ) {
			$edicio = new EdicioPack($cursEd, $idUrl, $dispositiu, $anyEd, $mesEd, $horesEd, $dataiEd, $datafEd, $cursEscEd, $gtafEd, $dataResEd, $fissEd, $estatEd);
			$edicio->setInfo();
			$datesRealitzacioCursos .= "<li style='margin-top: 8px; line-height: 24px;'>".$edicio->mostrarEdicioInscripcioPack()."</li>";
			$edicions[$i] = $edicio;
			$titols[$i] = $idTitol;
			$i++;
		}
		$datesRealitzacioCursos .= "</ul>";
		$connexio->closeStmt();
	}
	else {
		throw new Exception('',2912);
	}

	$datai = $edicions[0]->obtenirDataInici()->obtenirText();
	$dataf = $edicions[count($edicions)-1]->obtenirDataFi()->obtenirText();

	$objDataF = new Text($dataf);
	$dataFiLlarga = $objDataF->convertirDataLlarga();

	/* ######################################################################### */
	$titolDocencia = 0;
	if ($textPerfil->obtenirText()=='Consulta privada' ||
		 $textPerfil->obtenirText()=='No estic treballant' ||
		 $textPerfil->obtenirText()=='Altres') {
		$titolDocencia = 1;
	}

	/* ######################################################################### */
	$esAlumne = 0;

	//Buscar si el usuari XXX ha realitzat el curs xxx
	$cnsInsc = "SELECT ID FROM inscripcions WHERE DNI=? AND
      ((A_PAGAR>0 AND PAGAMENT>0) OR
      (A_PAGAR=0 AND OBSERVACIONS LIKE '%CURS REGAL%') OR
      (A_PAGAR=0 AND TIPUS_INSC LIKE '%R%')) AND
      UPPER(`INSC CURS`)!='D'";
   if ( $stmt=$connexio->prepare($cnsInsc) ) {
	   $stmt->bind_param("s", $documentacio);
		$documentacio = $textDocumentacio->obtenirText();
	   $stmt->execute();
	   $stmt->store_result();
		if ($stmt->num_rows() > 0) {
			$esAlumne = 1;
		}
	   $connexio->closeStmt();
	}
	else {
		throw new Exception('',2913);
	}

	/* ######################################################################### */
	$cnsIdPag = "SELECT IDPAG FROM inscripcions ORDER BY IDPAG DESC LIMIT 1";
	if ( $stmt=$connexio->prepare($cnsIdPag) ) {
		$stmt->execute();
		$stmt->bind_result($idPag);
		$stmt->fetch();
		$connexio->closeStmt();
		$idPag = $idPag+1;
	}
	else {
		throw new Exception('',2914);
	}

	$ivlen = openssl_cipher_iv_length($cipher);
	$iv = openssl_random_pseudo_bytes($ivlen);
	$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
	$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

	$urlIdPag = "https://www.prisma.cat/pagaments/".$hashIdPag;

	$titolPack = $textTitolCurs->obtenirText();
	$pagFrac = $textPagFrac->obtenirText();
	$preuPack = $numPreuPack->obtenirNumero();
	$preuCursos = $numPreuCursos->obtenirNumero();
	$mailing = $textMailing->obtenirText();

	$msg = $templates->getTemplate_Inscripcions_Pagaments_MissatgeTextManeresPagar2();
	$names_template = array("[URL_PAGAMENT]", "[TITOL]", "[CODI]", "[TYPE]");
	$names_function   = array($urlIdPag, $titolPack, "P".$textIdPack->obtenirText(), "pack");
	$textManeresPagar = str_replace($names_template, $names_function, $msg);

	$textPagament = '';

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

	$conegut = $textConegut->obtenirText();
	if ($textComentaris != null)
		$comentaris = $textComentaris->obtenirText();

	/* ######################################################################### */
	$datai1 = $edicions[0]->obtenirDataInici()->obtenirText();
	$dataf1 = $edicions[0]->obtenirDataFi()->obtenirText();

	$datai2 = $edicions[1]->obtenirDataInici()->obtenirText();
	$dataf2 = $edicions[1]->obtenirDataFi()->obtenirText();

	$objDataI1 = new Date($datai1);
	$objDataF1 = new Date($dataf1);
	$objDataI2 = new Date($datai2);
	$objDataF2 = new Date($dataf2);

	//primer curs
	if ( $objDataI1->getAny() != $objDataF1->getAny() ) {
		//De l'1 de desembre de 2021 al 15 de febrer de 2022
		//De l'1 de desembre de 2021 a l'11 de febrer de 2022
		//Del 2 de desembre de 2021 al 15 de febrer de 2022
		//Del 2 de desembre de 2021 a l'11 de febrer de 2022
		$textDates1 = $objDataI1->getPronomDel()."".$objDataI1->getDataLlarga()."
		".$objDataF1->getPronomAl()."".$objDataF1->getDataLlarga()."";
	}
	else {
		if ( $objDataI1->getMes() != $objDataF1->getMes() ) {
			//Del 4 d'abril a l'11 de maig de 2022
			$textDates1 = $objDataI1->getPronomDel()."".intval($objDataI1->getDia())."
			".$objDataI1->getNomMesArticle()."
			".$objDataF1->getPronomAl()."".$objDataF1->getDataLlarga()."";
		}
		else {
			//Del 4 al 31 de juliol de 2022
			$textDates1 = $objDataI1->getPronomDel()."".intval($objDataI1->getDia())."
			".$objDataF1->getPronomAl()."".$objDataF1->getDataLlarga()."";
		}
	}

	//segon curs
	if ( $objDataI2->getAny() != $objDataF2->getAny() ) {
		//De l'1 de desembre de 2021 al 15 de febrer de 2022
		//De l'1 de desembre de 2021 a l'11 de febrer de 2022
		//Del 2 de desembre de 2021 al 15 de febrer de 2022
		//Del 2 de desembre de 2021 a l'11 de febrer de 2022
		$textDates2 = $objDataI2->getPronomDel()."".$objDataI2->getDataLlarga()."
		".$objDataF2->getPronomAl()."".$objDataF2->getDataLlarga()."";
	}
	else {
		if ( $objDataI2->getMes() != $objDataF2->getMes() ) {
			//Del 4 d'abril a l'11 de maig de 2022
			$textDates2 = $objDataI2->getPronomDel()."".intval($objDataI2->getDia())."
			".$objDataI2->getNomMesArticle()."
			".$objDataF2->getPronomAl()."".$objDataF2->getDataLlarga()."";
		}
		else {
			//Del 4 al 31 de juliol de 2022
			$textDates2 = $objDataI2->getPronomDel()."".intval($objDataI2->getDia())."
			".$objDataF2->getPronomAl()."".$objDataF2->getDataLlarga()."";
		}
	}

	$datesRealitzacioCurs1 = $textDates1;
	$datesRealitzacioCurs2 = $textDates2;

	/* ######################################################################### */
	$msg = $templates->getTemplate_Dades_RequadreDadesPersonals(1);
	$names_template = array("[NOM_ALUMNE]", "[COG_ALUMNE]", "[DNI_ALUMNE]", "[EMAIL_ALUMNE]",
		"[TEL_ALUMNE]", "[ADRECA_ALUMNE]", "[CP_ALUMNE]", "[POBLACIO_ALUMNE]");
	$names_function   = array($nom, $cog, $documentacio, $email, $telf, $adreca, $codiPostal, $poblacio);
	$reqDadesAlumne = str_replace($names_template, $names_function, $msg);

	$msg = $templates->getTemplate_Inscripcions_EnviamentPack($pagFrac, $titolDocencia,
	$esAlumne, $datai, $mailing, $textTitulacioEstudiant);
	$names_template = array("[NOM_ALUMNE]", "[TITOL]", "[TITOL1]", "[DATAI_DATAF1]",
	"[TITOL2]", "[DATAI_DATAF2]", "[DATAF_LLARGA]", "[PAY_ORIG_ALUMNE]", "[PAY_PACK_ALUMNE]",
	"[TEXT_DADES_ALUMNE]", "[TEXT_DESC_ALUMNE]", "[TEXT_MANERES_PAGAR]");
	$names_function   = array($nom, $titolPack, $titols[0], $datesRealitzacioCurs1,
		$titols[1], $datesRealitzacioCurs2, $dataFiLlarga, $preuCursos, $preuPack,
		$reqDadesAlumne, '', $textManeresPagar);
	$missatge = str_replace($names_template, $names_function, $msg);

	$msg = $templates->getTemplate_Inscripcions_EnviamentPackShort($mailing, $pagFrac);
	$names_template = array("[NOM_ALUMNE]", "[COG_ALUMNE]", "[DNI_ALUMNE]",
		"[EMAIL_ALUMNE]",	"[TEL_ALUMNE]", "[ADRECA_ALUMNE]", "[CP_ALUMNE]",
		"[POBLACIO_ALUMNE]", "[PERFIL_ALUMNE]", "[TITULACIO_ALUMNE]", "[TITOL]",
		"[DATAI_DATAF]", "[CONEGUT_ALUMNE]", "[PAY_PACK_ALUMNE]","[COMENTARIS_ALUMNE]",
		"[IDPAG_PAY]", "[URL_PAGAMENT]");
	$names_function   = array($nom, $cog, $documentacio, $email, $telf, $adreca,
	$codiPostal, $poblacio, $perfils, $titulacions, $titolPack, $datesRealitzacioCursos,
	$conegut, $preuPack, $comentaris, $idPag, $urlIdPag);
	$msgInsc = str_replace($names_template, $names_function, $msg);

	$subjectMailInsc = "Inscripció pack P".$idPack." - ".$documentacio;
	if ($comentaris != '') $subjectMailInsc .= " + O";

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

	$subject = "Inscripció al pack ".$titolPack;

	$nomFromHead = 'Secretaria PrisMa';
	$correuFromHead = 'inscripcions@prisma.cat';
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = 'Secretaria PrisMa';
	$correuTo = 'inscripcions@prisma.cat';
	// $correuTo = 'meriem.prisma.cat@gmail.com';

	$mailCopiaInsc = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);

	$nomFromHead = 'Secretaria PrisMa';
	$correuFromHead = 'secretaria@prisma.cat';
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";
	// $correuTo = 'meriem.prisma.cat@gmail.com';

	$subject2 = "Inscripció al pack ".$titolPack." ".$dataInsc;

	$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject2, $missatge);

	$nomTo = 'Secretaria PrisMa';
	$correuTo = 'inscripcions@prisma.cat';
	// $correuTo = 'meriem.prisma.cat@gmail.com';

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

	$conegutBD = $textConegut->obtenirText();;

	$comentarisBD = '';
	if ($textComentaris!=null) $comentarisBD = $textComentaris->obtenirText();

	$pagFraccBD = 0;
	if ($pagFrac == 'Yes') $pagFraccBD = 1;

	if ($mailing=='Registred') $mailingBD = 'X';
	else if ($mailing=='Yes') $mailingBD = '1';
	else $mailingBD = '0';

	$usuariBD = '';
	$clean = preg_replace('~[^0-9]+~', '', $documentacioBD);
	preg_match_all('!\d+!', $clean, $numero);
	$usuariBD = implode(' ', $numero[0]);

	$perenne = 'X';

	$connexio2 = new ConnexioBBDDSTMT();
	$connexio2->connectarBD();

	$cnsPreu = "SELECT IMPORT FROM preu WHERE ID=? AND DATAI<=CURRENT_TIME AND
					(CURRENT_TIME<=DATAF OR DATAF IS NULL)";

	$insertBD = "INSERT INTO inscripcions (ANY, MES, CURS, DATA_INSC, NOM, COGNOMS,
					 CORREU, DNI, ADRECA, Codi_Postal, POBLACIO, PERFIL, TITULACIO,
					 TELEFON, COMENTARIS, FRACCIONAT, INSC_MAILING,
					 A_PAGAR, USUARI, IDPAG, PERENNE, CONEGUT, TIPUS_INSC, OBSERVACIONS)
					 VALUES (?,?,?,CURRENT_TIME,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
	if ( $stmt2 = $connexio2->prepare($cnsPreu) ) {
		$stmt2->bind_param("d", $idPreuEd);

		if ( $stmt=$connexio->prepare($insertBD) ) {
			$stmt->bind_param("dsssssssssssdsdsdddssss", $anyEd, $mesEd, $codiCursEd,
				$nomBD, $cogBD, $emailBD, $documentacioBD, $adrecaBD, $codiPostalBD, $poblacioBD,
				$perfilsBD, $titulacionsBD, $telfBD, $comentarisBD, $pagFraccBD, $mailingBD,
				$preuCurs, $usuariBD, $idPag, $perenne, $conegutBD, $tipusInsc, $observacions);

			$aux = $preuPack;
			$tipusInsc = 'P';
			$observacions = 'PACK|'.$idPack;
			for ( $i=0; $i<count($edicions); $i++ ) {
				$edicio = $edicions[$i];

				/* Omplo les dades necessaries obtenides de l'edicio*/
				$anyEd = $edicio->obtenirAny()->obtenirNumero();
				$mesEd = $edicio->obtenirMes()->obtenirText();
				$idPreuEd = $edicio->obtenirIdPreu()->obtenirNumero();

				$codiCursEd = $edicio->obtenirCodiCurs()->obtenirText();

				/* Busco el preu original del curs */
				$stmt2->execute();
				$stmt2->bind_result($preuCursOriginal);
				$stmt2->fetch();

				$preuCurs = $aux;
				if ( $aux >= $preuCursOriginal ) {
					$preuCurs = $preuCursOriginal;
					$aux -= $preuCursOriginal;
				}

				/* Executo el insert */
				$stmt->execute();
			}
			$connexio->closeStmt();
		}
		else {
			throw new Exception('',2915);
		}
	}
	else {
		throw new Exception('',2916);
	}

	$connexio2->desconectarBD();

	$idInserit = $connexio->lastInsertId();

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

	$nomTo = "Inscripcions PrisMa";
	$correuTo = "inscripcions.prisma@gmail.com";
	// $correuTo = 'meriem.prisma.cat@gmail.com';

	$mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);

	if ($mailingBD == '1') {
		$cnsMailing = "SELECT ID FROM mailing WHERE MAIL=?";
			if ( $stmt=$connexio->prepare($cnsMailing) ) {
			$stmt->bind_param("s", $emailBD);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() <= 0 ) {
				$connexio->closeStmt();

				$insertMailing = "INSERT INTO mailing (mail, nom, usuari) VALUES (?, ?, ?)";
				$stmt=$connexio->prepare($insertMailing);
				$stmt->bind_param("ssd", $emailBD, $nomBD, $usuariBD);
				$stmt->execute();
				$stmt->fetch();
			}
			$connexio->closeStmt();
		}
      else {
         throw new Exception('',2917);
      }
	}

	$cnsPoble = "SELECT ID FROM poblacions WHERE CP=? AND POBLE=?";
	if ( $stmt=$connexio->prepare($cnsPoble) ) {
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
	}
	else {
		throw new Exception('',2918);
	}

	/* ######################################################################### */

	//buscar el username i el password d'autentificació de prisma
	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	if ( $stmt=$connexio->prepare($cnsParam) ) {
		$stmt->bind_param("s", $tipusParam);
		$tipusParam = 'autentificacioInscripcio';
		$stmt->execute();
		$stmt->bind_result($valor);
		$stmt->fetch();
		$autentificacioInscripcio = explode('|',$valor);
		$username = $autentificacioInscripcio[0];
		$password = $autentificacioInscripcio[1];
		$nameUser = $autentificacioInscripcio[2];
		$connexio->closeStmt();
	}
	else {
		throw new Exception('',2919);
	}

	$subject = "Inscripció al pack ".$titolPack;
	$subject2 = "Inscripció al pack ".$titolPack." ".$dataInsc;

	$nomFromHead = $nameUser;
	$correuFromHead = $username;
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";
	// $correuTo = 'meriem.prisma.cat@gmail.com';

	$mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject2, $missatge);

	$nomTo = $nomCognoms;
	$correuTo = $email;
	// $correuTo = 'meriem.prisma.cat@gmail.com';

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
