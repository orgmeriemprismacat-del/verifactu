<?php

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	header('Allow: POST');
	http_response_code(405);
	exit('Error: mètode no permès.');
}

require_once __DIR__ . '/../inc/PublicWebMutationAuthorization.php';
try {
	PublicWebMutationAuthorization::assertSameOriginAjax();
} catch (Throwable $exception) {
	$code = (int) $exception->getCode();
	http_response_code($code >= 400 && $code <= 599 ? $code : 403);
	exit('Error: petició no autoritzada.');
}


$packIdPagReserved = false;
$packRequestLockHeld = false;
$packInsertTransactionStarted = false;
$connexio = null;

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
	$packRequestId = trim((string) ($_POST['requestId'] ?? ''));
	if (!preg_match('/^[A-Za-z0-9-]{16,80}$/D', $packRequestId)) {
		throw new RuntimeException('Error: identificador de petició no vàlid.', 422);
	}

	$textNom = new Text($_POST['nom']);
	$textCog = new Text($_POST['cog']);
	$textDocumentacio = new Text($_POST['dni']);
	$numTelf = new Numero($_POST['telf']);
	$textEmail = new Text($_POST['email']);
	$textAdreca = new Text($_POST['adreca']);
	$textCodiPostal = new Text($_POST['codiPostal']);
	$textPoblacio = new Text($_POST['poblacio']);
	$textPerfil = new Text($_POST['perfil']);
	if ( $_POST['perfil'] == "Altres")
		$textPerfilAltres = new Text($_POST['perfilAltres']);
	else
		$textPerfilAltres = null;
	if ( $_POST['titulacio'] == "Altres") {
		$textTitulacio = new Text($_POST['titulacio']);
		$textTitulacioAltres = new Text($_POST['titulacioAltres']);
		$textTitulacioSecundaria = null;
		$textTitulacioEstudiant = null;
	}
	else if ( $_POST['titulacio'] == "Prof. Ed. Secundària") {
		$textTitulacio = new Text('Ed. Secundària');
		$textTitulacioAltres = null;
		$textTitulacioSecundaria = new Text($_POST['titulacioSecundaria']);
		$textTitulacioEstudiant = null;
	}
	else if ( $_POST['titulacio'] == "Encara no tinc cap titulació, sóc estudiant de") {
		$textTitulacio = new Text('Estudiant');
		$textTitulacioAltres = null;
		$textTitulacioSecundaria = null;
		$textTitulacioEstudiant = new Text($_POST['titulacioEstudiant']);
	}
	else {
		$textTitulacio = new Text($_POST['titulacio']);
		$textTitulacioAltres = null;
		$textTitulacioSecundaria = null;
		$textTitulacioEstudiant = null;
	}
	if ( $_POST['tbTitulacio'] != '')
		$textTbTitulacio = new Text($_POST['tbTitulacio']);
	else
		$textTbTitulacio = null;
	$textConegut = new Text($_POST['conegut']);
	if ( $_POST['comentaris'] != '')
		$textComentaris = new Text($_POST['comentaris']);
	else
		$textComentaris = null;
	$textMailing = new Text($_POST['mailing']);
	// Els imports rebuts del navegador no són autoritatius. Es recalculen des de BD.
	$textIdPack = new Text($_POST['idPack']);

	$textNom->arreglarParaulaBD('noms');
	$textCog->arreglarParaulaBD('noms');
	$textDocumentacio->arreglarParaulaBD('text_maj');
	$textEmail->arreglarParaulaBD('email');
	$textAdreca->arreglarParaulaBD('text');
	$textCodiPostal->arreglarParaulaBD('text_maj');
	$textPoblacio->arreglarParaulaBD('noms');
	$textPerfil->arreglarParaulaBD('text_no_mod');
	$textTitulacio->arreglarParaulaBD('text_no_mod');
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
	$encodePackConfirmationId = static function (int $id) use ($cipher, $keyEncr): string {
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertextRaw = openssl_encrypt(
			$id,
			$cipher,
			$keyEncr,
			OPENSSL_RAW_DATA,
			$iv
		);
		if ($ciphertextRaw === false) {
			throw new RuntimeException('Error: no s’ha pogut generar la confirmació.', 500);
		}
		$hmac = hash_hmac('sha256', $ciphertextRaw, $keyEncr, true);
		return base64_encode($iv.$hmac.$ciphertextRaw);
	};

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
			$titols[$i] = $idTitol;
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

	$packRequestFingerprintJson = json_encode([
		'pack_id' => (string) $textIdPack->obtenirText(),
		'nom' => (string) $textNom->obtenirText(),
		'cog' => (string) $textCog->obtenirText(),
		'dni' => (string) $textDocumentacio->obtenirText(),
		'telf' => (string) $numTelf->obtenirNumero(),
		'email' => (string) $textEmail->obtenirText(),
		'adreca' => (string) $textAdreca->obtenirText(),
		'codi_postal' => (string) $textCodiPostal->obtenirText(),
		'poblacio' => (string) $textPoblacio->obtenirText(),
		'perfil' => (string) $textPerfil->obtenirText(),
		'perfil_altres' => $textPerfilAltres !== null ? (string) $textPerfilAltres->obtenirText() : '',
		'titulacio' => (string) $textTitulacio->obtenirText(),
		'titulacio_altres' => $textTitulacioAltres !== null ? (string) $textTitulacioAltres->obtenirText() : '',
		'titulacio_secundaria' => $textTitulacioSecundaria !== null ? (string) $textTitulacioSecundaria->obtenirText() : '',
		'titulacio_estudiant' => $textTitulacioEstudiant !== null ? (string) $textTitulacioEstudiant->obtenirText() : '',
		'tb_titulacio' => $textTbTitulacio !== null ? (string) $textTbTitulacio->obtenirText() : '',
		'conegut' => (string) $textConegut->obtenirText(),
		'comentaris' => $textComentaris !== null ? (string) $textComentaris->obtenirText() : '',
		'mailing' => (string) $textMailing->obtenirText(),
	], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	if ($packRequestFingerprintJson === false) {
		throw new RuntimeException('Error: no s’ha pogut preparar la petició.', 500);
	}
	$packRequestFingerprint = hash('sha256', $packRequestFingerprintJson);
	$packRequestLockName = 'prisma_pack_request_' . hash('sha256', $packRequestId);

	$stmtPackRequestLock = $connexio->connexio->prepare('SELECT GET_LOCK(?, 10)');
	if (!$stmtPackRequestLock) {
		throw new RuntimeException('Error: no s’ha pogut bloquejar la petició.', 503);
	}
	$stmtPackRequestLock->bind_param('s', $packRequestLockName);
	$stmtPackRequestLock->execute();
	$stmtPackRequestLock->bind_result($packRequestLocked);
	$stmtPackRequestLock->fetch();
	$stmtPackRequestLock->close();
	if ((int) $packRequestLocked !== 1) {
		throw new RuntimeException('Error: la petició està sent processada. Torna-ho a provar.', 409);
	}
	$packRequestLockHeld = true;

	$packRequestNeedle = 'PACK_REQUEST|' . $packRequestId . ' ';
	$stmtExistingRequest = $connexio->connexio->prepare(
		"SELECT ID, IDPAG, OBSERVACIONS
		 FROM inscripcions
		 WHERE TIPUS_INSC='P' AND LOCATE(?, OBSERVACIONS) > 0
		 ORDER BY ID"
	);
	if (!$stmtExistingRequest) {
		throw new RuntimeException('Error: no s’ha pogut verificar la petició.', 500);
	}
	$stmtExistingRequest->bind_param('s', $packRequestNeedle);
	$stmtExistingRequest->execute();
	$stmtExistingRequest->bind_result($existingRequestId, $existingRequestIdPag, $existingRequestObservations);
	$existingRequestRows = [];
	while ($stmtExistingRequest->fetch()) {
		$existingRequestRows[] = [
			'id' => (int) $existingRequestId,
			'idpag' => (int) $existingRequestIdPag,
			'observations' => (string) $existingRequestObservations,
		];
	}
	$stmtExistingRequest->close();

	if ($existingRequestRows !== []) {
		$expectedIdPag = $existingRequestRows[0]['idpag'];
		$lastExistingId = 0;
		foreach ($existingRequestRows as $existingRow) {
			if ($existingRow['idpag'] !== $expectedIdPag) {
				throw new RuntimeException('Error: conflicte d’idempotència del pack.', 409);
			}
			if (!preg_match('/(?:^|\\s)PACK_REQUEST_HASH\\|([a-f0-9]{64})(?:\\s|$)/i', $existingRow['observations'], $hashMatch)
				|| !hash_equals($packRequestFingerprint, strtolower($hashMatch[1]))) {
				throw new RuntimeException('Error: la mateixa petició conté dades diferents.', 409);
			}
			if (!preg_match('/(?:^|\\s)PACK\\|([^\\s]+)(?:\\s|$)/', $existingRow['observations'], $packMatch)
				|| (string) $packMatch[1] !== (string) $textIdPack->obtenirText()) {
				throw new RuntimeException('Error: conflicte de pack en una petició repetida.', 409);
			}
			$lastExistingId = max($lastExistingId, $existingRow['id']);
		}

		if (count($existingRequestRows) !== count($edicions) || $lastExistingId <= 0) {
			throw new RuntimeException('Error: la petició repetida té una alta incompleta.', 409);
		}

		$stmtReleaseRequest = $connexio->connexio->prepare('SELECT RELEASE_LOCK(?)');
		if ($stmtReleaseRequest) {
			$stmtReleaseRequest->bind_param('s', $packRequestLockName);
			$stmtReleaseRequest->execute();
			$stmtReleaseRequest->close();
		}
		$packRequestLockHeld = false;

		echo $encodePackConfirmationId($lastExistingId);
		$connexio->desconectarBD();
		exit;
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
	$packIdPagReserved = true;

	$ivlen = openssl_cipher_iv_length($cipher);
	$iv = openssl_random_pseudo_bytes($ivlen);
	$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
	$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

	$urlIdPag = "https://www.prisma.cat/pagaments/".$hashIdPag;

	$titolPack = $textTitolCurs->obtenirText();
	$pagFrac = 'No'; // UC-015 ecommerce PACK: no fraccionament autoritzat des del client.
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
	if ( $stmt2 = $connexio2->prepare($cnsPreu) ) {
		$stmt2->bind_param("d", $idPreuEd);

		if (!$connexio->connexio->begin_transaction()) {
			throw new RuntimeException('Error: no s’ha pogut iniciar la transacció del pack.', 500);
		}
		$packInsertTransactionStarted = true;

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
					throw new RuntimeException('Error: no s’ha pogut consultar el preu del component del pack.', 500);
				}
				$stmt2->bind_result($preuCursOriginal);
				if (!$stmt2->fetch() || !is_numeric($preuCursOriginal)) {
					throw new RuntimeException('Error: preu del component del pack no vàlid.', 409);
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
					'PACK|%s PACK_ORDINAL|%d PACK_BASE|%.2f PACK_DISCOUNT|%.2f PACK_DISCOUNT_PCT|%.2f PACK_TOTAL|%.2f PACK_REQUEST|%s PACK_REQUEST_HASH|%s',
					$idPack,
					$i + 1,
					$preuCursOriginal,
					$descompteCurs,
					$descomptePct,
					$preuCurs,
					$packRequestId,
					$packRequestFingerprint
				);

				/* Executo el insert */
				if (!$stmt->execute()) {
					throw new RuntimeException('Error: no s’ha pogut crear totes les inscripcions del pack.', 500);
				}
			}
			$connexio->closeStmt();

			if (!$connexio->connexio->commit()) {
				throw new RuntimeException('Error: no s’ha pogut confirmar l’alta del pack.', 500);
			}
			$packInsertTransactionStarted = false;

			$connexio->releaseIdPag();
			$packIdPagReserved = false;

			$stmtReleaseRequest = $connexio->connexio->prepare('SELECT RELEASE_LOCK(?)');
			if ($stmtReleaseRequest) {
				$stmtReleaseRequest->bind_param('s', $packRequestLockName);
				$stmtReleaseRequest->execute();
				$stmtReleaseRequest->close();
			}
			$packRequestLockHeld = false;
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

	$hashIdInserit = $encodePackConfirmationId((int) $idInserit);

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
catch(Throwable $e) {
	if ($packInsertTransactionStarted && is_object($connexio) && isset($connexio->connexio)) {
		$connexio->connexio->rollback();
		$packInsertTransactionStarted = false;
	}
	if ($packIdPagReserved && is_object($connexio)) {
		$connexio->releaseIdPag();
		$packIdPagReserved = false;
	}
	if ($packRequestLockHeld && is_object($connexio) && isset($connexio->connexio)) {
		$stmtReleaseRequest = $connexio->connexio->prepare('SELECT RELEASE_LOCK(?)');
		if ($stmtReleaseRequest) {
			$stmtReleaseRequest->bind_param('s', $packRequestLockName);
			$stmtReleaseRequest->execute();
			$stmtReleaseRequest->close();
		}
		$packRequestLockHeld = false;
	}

	$code = (int) $e->getCode();
	if ($code >= 400 && $code <= 599) {
		http_response_code($code);
		echo $e->getMessage() !== '' ? $e->getMessage() : 'Error: no s’ha pogut completar l’alta del pack.';
	}
	else if ($code === 404) {
		echo mostrarPagina404();
	}
	else {
		echo missatgeError($code);
	}
}

?>
