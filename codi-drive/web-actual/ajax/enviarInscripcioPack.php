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

header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
	header('Allow: POST');
	http_response_code(405);
	exit('Error: mètode no permès');
}

$allowedHosts = ['www.prisma.cat', 'prisma.cat'];
$fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
if ($fetchSite !== '' && !in_array($fetchSite, ['same-origin', 'same-site', 'none'], true)) {
	http_response_code(403);
	exit('Error: origen no permès');
}

foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $headerName) {
	$headerValue = trim((string) ($_SERVER[$headerName] ?? ''));
	if ($headerValue === '') {
		continue;
	}

	$host = strtolower((string) parse_url($headerValue, PHP_URL_HOST));
	if ($host === '' || !in_array($host, $allowedHosts, true)) {
		http_response_code(403);
		exit('Error: origen no permès');
	}
}

$request = $_POST;

$idPagReserved = false;
$packTransactionStarted = false;

try {
	$textNom = new Text($request['nom']);
	$textCog = new Text($request['cog']);
	$textDocumentacio = new Text($request['dni']);
	$numTelf = new Numero($request['telf']);
	$textEmail = new Text($request['email']);
	$textAdreca = new Text($request['adreca']);
	$textCodiPostal = new Text($request['codiPostal']);
	$textPoblacio = new Text($request['poblacio']);
	$textPerfil = new Text($request['perfil']);
	if ( $request['perfil'] == "Altres")
		$textPerfilAltres = new Text($request['perfilAltres']);
	else
		$textPerfilAltres = null;
	if ( $request['titulacio'] == "Altres") {
		$textTitulacio = new Text($request['titulacio']);
		$textTitulacioAltres = new Text($request['titulacioAltres']);
		$textTitulacioSecundaria = null;
		$textTitulacioEstudiant = null;
	}
	else if ( $request['titulacio'] == "Prof. Ed. Secundària") {
		$textTitulacio = new Text('Ed. Secundària');
		$textTitulacioAltres = null;
		$textTitulacioSecundaria = new Text($request['titulacioSecundaria']);
		$textTitulacioEstudiant = null;
	}
	else if ( $request['titulacio'] == "Encara no tinc cap titulació, sóc estudiant de") {
		$textTitulacio = new Text('Estudiant');
		$textTitulacioAltres = null;
		$textTitulacioSecundaria = null;
		$textTitulacioEstudiant = new Text($request['titulacioEstudiant']);
	}
	else {
		$textTitulacio = new Text($request['titulacio']);
		$textTitulacioAltres = null;
		$textTitulacioSecundaria = null;
		$textTitulacioEstudiant = null;
	}
	if ( $request['tbTitulacio'] != '')
		$textTbTitulacio = new Text($request['tbTitulacio']);
	else
		$textTbTitulacio = null;
	// UC-015: l'ecommerce de packs no permet fraccionament; no acceptar aquesta decisió del client.
	$textPagFrac = new Text('No');
	$textConegut = new Text($request['conegut']);
	if ( $request['comentaris'] != '')
		$textComentaris = new Text($request['comentaris']);
	else
		$textComentaris = null;
	$textMailing = new Text($request['mailing']);
	// Els imports rebuts del navegador no són autoritatius. Es recalculen des de BD.
	$textIdPack = new Text($request['idPack']);

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
	$cnsInfo = "SELECT TITOL, ID_PREU FROM info_pack WHERE ID_PACK=? AND ESTAT=1";
	if ( $stmt=$connexio->prepare($cnsInfo) ) {
		$stmt->bind_param("s", $idPack);
		$idPack = $textIdPack->obtenirText();
		$stmt->execute();
		$stmt->bind_result($titol, $idPreuPack);
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
		AND c.CURS NOT LIKE '%JOR%' ORDER BY c.DATAI, p.ID_CURS";
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
			$i++;
		}
		$datesRealitzacioCursos .= "</ul>";
		$connexio->closeStmt();
	}
	else {
		throw new Exception('',2912);
	}

	if (count($edicions) < 2) {
		throw new Exception('',2912);
	}

	/* ######################################################################### */
	/* Preus autoritatius del pack: mai confiar en preuPack/preuCursos del client. */
	$cnsPreuServidor = "SELECT IMPORT FROM preu
		WHERE ID=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)
		ORDER BY DATAI DESC LIMIT 1";
	if ( $stmtPreuServidor = $connexio->prepare($cnsPreuServidor) ) {
		$stmtPreuServidor->bind_param("d", $idPreuServidor);

		$idPreuServidor = $idPreuPack;
		$stmtPreuServidor->execute();
		$stmtPreuServidor->bind_result($preuPackServidor);
		if (!$stmtPreuServidor->fetch() || !is_numeric($preuPackServidor)) {
			$connexio->closeStmt();
			throw new Exception('',2906);
		}
		$preuPack = floatval($preuPackServidor);
		$connexio->closeStmt();

		$preuCursos = 0.0;
		foreach ($edicions as $edicioPreu) {
			if ( $stmtPreuServidor = $connexio->prepare($cnsPreuServidor) ) {
				$idPreuServidor = $edicioPreu->obtenirIdPreu()->obtenirNumero();
				$stmtPreuServidor->bind_param("d", $idPreuServidor);
				$stmtPreuServidor->execute();
				$stmtPreuServidor->bind_result($preuCursServidor);
				if (!$stmtPreuServidor->fetch() || !is_numeric($preuCursServidor)) {
					$connexio->closeStmt();
					throw new Exception('',2907);
				}
				$preuCursos += floatval($preuCursServidor);
				$connexio->closeStmt();
			}
			else {
				throw new Exception('',2916);
			}
		}
	}
	else {
		throw new Exception('',2916);
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
	$idPag = $connexio->reserveIdPag();
	$idPagReserved = true;

	$ivlen = openssl_cipher_iv_length($cipher);
	$iv = openssl_random_pseudo_bytes($ivlen);
	$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
	$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

	$urlIdPag = "https://www.prisma.cat/pagaments/".$hashIdPag;

	$titolPack = $textTitolCurs->obtenirText();
	$pagFrac = $textPagFrac->obtenirText();
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

	/* ######################################################################### */
	$msg = $templates->getTemplate_Dades_RequadreDadesPersonals(1);
	$names_template = array("[NOM_ALUMNE]", "[COG_ALUMNE]", "[DNI_ALUMNE]", "[EMAIL_ALUMNE]",
		"[TEL_ALUMNE]", "[ADRECA_ALUMNE]", "[CP_ALUMNE]", "[POBLACIO_ALUMNE]");
	$names_function   = array($nom, $cog, $documentacio, $email, $telf, $adreca, $codiPostal, $poblacio);
	$reqDadesAlumne = str_replace($names_template, $names_function, $msg);

	$msg = $templates->getTemplate_Inscripcions_EnviamentPack($pagFrac, $titolDocencia,
	$esAlumne, $datai, $mailing, $textTitulacioEstudiant);
	$names_template = array("[NOM_ALUMNE]", "[TITOL]", "[CURSOS_PACK]",
	"[DATAF_LLARGA]", "[PAY_ORIG_ALUMNE]", "[PAY_PACK_ALUMNE]",
	"[TEXT_DADES_ALUMNE]", "[TEXT_DESC_ALUMNE]", "[TEXT_MANERES_PAGAR]");
	$names_function   = array($nom, $titolPack, $datesRealitzacioCursos,
		$dataFiLlarga, $preuCursos, $preuPack,
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

	// UC-015: l'ecommerce de packs no crea fraccionament. Les excepcions es gestionen per intranet.
	$pagFraccBD = 0;

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
	$connexio->beginTransaction();
	$packTransactionStarted = true;

	if ( $stmt2 = $connexio2->prepare($cnsPreu) ) {
		$stmt2->bind_param("d", $idPreuEd);

		if ( $stmt=$connexio->prepare($insertBD) ) {
			$stmt->bind_param("dsssssssssssdsdsdddssss", $anyEd, $mesEd, $codiCursEd,
				$nomBD, $cogBD, $emailBD, $documentacioBD, $adrecaBD, $codiPostalBD, $poblacioBD,
				$perfilsBD, $titulacionsBD, $telfBD, $comentarisBD, $pagFraccBD, $mailingBD,
				$preuCurs, $usuariBD, $idPag, $perenne, $conegutBD, $tipusInsc, $observacions);

			$aux = round((float) $preuPack, 2);
			$tipusInsc = 'P';
			for ( $i=0; $i<count($edicions); $i++ ) {
				$edicio = $edicions[$i];

				/* Omplo les dades necessaries obtenides de l'edicio*/
				$anyEd = $edicio->obtenirAny()->obtenirNumero();
				$mesEd = $edicio->obtenirMes()->obtenirText();
				$idPreuEd = $edicio->obtenirIdPreu()->obtenirNumero();

				$codiCursEd = $edicio->obtenirCodiCurs()->obtenirText();

				/* Busco el preu original del curs */
				if (!$stmt2->execute()) {
					throw new Exception('',2916);
				}
				$stmt2->bind_result($preuCursOriginal);
				if (!$stmt2->fetch() || !is_numeric($preuCursOriginal)) {
					throw new Exception('',2916);
				}

				$preuCursOriginal = round((float) $preuCursOriginal, 2);
				$preuCurs = round(min($aux, $preuCursOriginal), 2);
				$aux = round(max(0, $aux - $preuCurs), 2);
				$descompteCurs = round(max(0, $preuCursOriginal - $preuCurs), 2);
				$descomptePct = $preuCursOriginal > 0
					? round(($descompteCurs / $preuCursOriginal) * 100, 2)
					: 0.0;

				/* Snapshot comercial mínim per no reconstruir ordre/imports després del cobrament. */
				$observacions = sprintf(
					'PACK|%s PACK_ORDINAL|%d PACK_BASE|%.2f PACK_DISCOUNT|%.2f PACK_DISCOUNT_PCT|%.2f PACK_TOTAL|%.2f',
					$idPack,
					$i + 1,
					$preuCursOriginal,
					$descompteCurs,
					$descomptePct,
					$preuCurs
				);

				/* Executo el insert */
				if (!$stmt->execute()) {
					throw new Exception('',2915);
				}
			}

			$idInserit = $connexio->lastInsertId();
			$connexio->closeStmt();
			$connexio->commitTransaction();
			$packTransactionStarted = false;
			$connexio->releaseIdPag();
			$idPagReserved = false;
		}
		else {
			throw new Exception('',2915);
		}
	}
	else {
		throw new Exception('',2916);
	}

	$connexio2->desconectarBD();

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
	if ($packTransactionStarted && isset($connexio)) {
		$connexio->rollbackTransaction();
		$packTransactionStarted = false;
	}

	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
finally {
	if ($packTransactionStarted && isset($connexio)) {
		$connexio->rollbackTransaction();
	}
	if ($idPagReserved && isset($connexio)) {
		$connexio->releaseIdPag();
	}
}

?>
