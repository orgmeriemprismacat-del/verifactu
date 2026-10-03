<?php

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	exit;
}

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Numero.php");
include("../Date.php");
include("../Text.php");
include("../MailSMTPComvive.php");
include("../inc/SifGiftRedemptionClient.php");

try {
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
	$numAny = new Numero($_POST['any']);
	$textEdicio = new Text($_POST['edicio']);
	$textDates = new Text($_POST['dates']);
	$textConegut = new Text("Me l'han regalat");
	if ( $_POST['comentaris'] != '')
		$textComentaris = new Text($_POST['comentaris']);
	else
		$textComentaris = null;
	$textObservacions = new Text("CURS REGAL");
	$textMailing = new Text($_POST['mailing']);
	$textCodiRegal = new Text($_POST['codiRegal']);
	$textCodiCurs = new Text($_POST['codiCurs']);
	$textTitolCurs = new Text($_POST['titolCurs']);

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$textNom->arreglarParaulaBD('noms');
	$textCog->arreglarParaulaBD('noms');
	$textDocumentacio->arreglarParaulaBD('text');
	$textEmail->arreglarParaulaBD('email');
	$textAdreca->arreglarParaulaBD('text');
	$textCodiPostal->arreglarParaulaBD('text');
	$textPoblacio->arreglarParaulaBD('text');
	$textPerfil->arreglarParaulaBD('text');
	$textTitulacio->arreglarParaulaBD('text');
	$textEdicio->arreglarParaulaBD('text');
	$textDates->arreglarParaulaBD('text');
	$textConegut->arreglarParaulaBD('text');
	if ($textComentaris != null) $textComentaris->arreglarParaulaBD('text');
	$textObservacions->arreglarParaulaBD('text');
	$textMailing->arreglarParaulaBD('text');
	$textCodiRegal->arreglarParaulaBD('text');
	$textCodiCurs->arreglarParaulaBD('text');
	$textTitolCurs->arreglarParaulaBD('text');

	$dataInsc = date('d')."-".date('m')."-".date('Y')." ".date('H').":".date('i');

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
	$connexio->closeStmt();

	$cipher = "AES-128-CBC";
	$encryptEnrollmentId = static function($enrollmentId) use ($cipher, $keyEncr) {
		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertext_raw = openssl_encrypt(
			(string) $enrollmentId,
			$cipher,
			$keyEncr,
			OPENSSL_RAW_DATA,
			$iv
		);
		$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, true);
		return base64_encode($iv.$hmac.$ciphertext_raw);
	};

	/* ######################################################################### */
	$datesRealitzacio = $textDates->convertirMin();

	$cnsDatesCurs = "SELECT DATAI, DATAF, HORES, DATA_RESOL FROM curs WHERE CURS=? AND ANY=? AND MES=?";
	$stmt=$connexio->prepare($cnsDatesCurs);
	$stmt->bind_param("sds", $codiCurs, $any, $mes);
	$codiCurs = $textCodiCurs->convertirMaj();
	$any = $numAny->obtenirNumero();
	$mes = $textEdicio->obtenirText();
	$stmt->execute();
	$stmt->bind_result($datai, $dataf, $hores, $data_resol);
	$stmt->fetch();
	$connexio->closeStmt();

	/* ######################################################################### */
	// El replay no retorna aquí. La secció transaccional posterior reutilitza
	// `regal.USAT`/la mateixa ID_INSC i torna a entrar al SIF. Això permet
	// reconstruir o reutilitzar l'outbox idempotent si la resposta anterior es
	// va perdre després del CONSUME/reconciliació però abans de completar correus.
	$textCursReconegut = "<p>Aquest curs està reconegut pel Departament d'Educació
	de la Generalitat de Catalunya i té una durada lectiva de <strong>".$hores." hores</strong>.</p>";

	if ($data_resol==null or $data_resol=='') {
		$textCursReconegut = "<p>Aquest curs té una durada lectiva de <strong>";
		$textCursReconegut .= $hores." hores</strong>.<p>
		<p>PrisMa, com a entitat organitzadora, ha sol·licitat el reconeixement de
		les edicions del curs escolar 2020-2021 al Departament d'Educació. Tan bon
		punt surti la resolució t'avisarem a través del correu electrònic.";
	}

	if ($textPerfil->obtenirText()=='Consulta privada' ||
		 $textPerfil->obtenirText()=='No estic treballant' ||
		 $textPerfil->obtenirText()=='Altres') {
		$textCursReconegut .= "Els nostres cursos compten com Formació Permanent
		del Professorat sempre que es realitzin posteriorment a la data d’expedició
		del títol d’accés a la docència (Magisteri o CAP / Màster en Educació Secundària).";
	}

	/* ######################################################################### */
	$textEstudiant = '';
	if ( $titulacioEstudiant != null) {
		$textEstudiant = "<p>En el teu cas, ​atès que encara ​no disposes d'aquesta titulació
		finalitzada, rebràs un certificat de PrisMa que p​odràs fer constar​ com ​a ​
		currículum personal però ​que ​no ​te donarà punts en convocatòries oficials
		del Departament d'Educació. En el cas que tinguis uns estudis universitaris
		finalitzats, si us plau, contacta amb nosaltres perquè rectifiquem la sol·licitud.</p>";
	}

	/* ######################################################################### */
	$textHasRealitzatCurs='';
	//Buscar si el usuari XXX ha realitzat el curs xxx
	$cnsInsc = "SELECT ANY, MES FROM inscripcions WHERE CURS=? AND DNI=? AND GENERAT=1";
   $stmt=$connexio->prepare($cnsInsc);
   $stmt->bind_param("ss", $codiCurs, $documentacio);
	$documentacio = $textDocumentacio->convertirMaj();
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
	// IDPAG es reserva només si realment cal crear una nova inscripció.


	/* ######################################################################### */
	//Busquem la data d'inici del curs
	if ( $datai <= date('Y-m-d') ) { /*ha començat el curs*/
		$textIniciCurs = "<p>En un període de 24 hores laborals podràs accedir al
		curs amb les teves claus.</p>";
		if ( $tipusDescompte == 0 )
			$textIniciCurs = "<p>En un període de 24 hores laborals rebràs un correu
			electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
	}
	else {
		$textIniciCurs = "<p>Uns dies abans de l'inici del curs podràs accedir a
		l'apartat general de l'aula amb les teves claus.</p>";
		if ( $tipusDescompte == 0 )
			$textIniciCurs = "<p>Uns dies abans de l'inici del curs rebràs un correu
			electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
	}

	/* ######################################################################### */
	$textConsentimentMailing = '';
	if ( $mailing == 'Yes' ) {
		$textConsentimentMailing = "
			<p>Et recordem que has marcat la casella per rebre correus electrònics
			informatius dels nostres cursos i serveis. Tot i això, podràs donar-te
			de baixa de la nostra llista de correus en qualsevol moment.</p>";
	}

	/* ######################################################################### */
	$textAlertaConfirmacioInscripcio = '';

	// Cercar paramatre X per veure si el curs actual té una alerta d'inscripció
	$cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
	DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
	$stmtParam = $connexio->prepare($cnsParams);
	$stmtParam->bind_param("sss", $tipus, $valor, $orderBy);
	$tipus = 'alerta-confirmació-inscripcio';
	$valor = $codiCurs.'%';
	$orderBy='VALOR';
	$stmtParam->execute();
	$stmtParam->store_result();
	// Si té una alerta d'inscripció
	if ( $stmtParam->num_rows() > 0 ) {
		$stmtParam->bind_result($valorRes);
		$stmtParam->fetch();
		$arrValors = explode('|',$valorRes);
		$textAlertaConfirmacioInscripcio = "<div style='background-color:#e8ecf5;border:1px solid #d7deee;
		border-radius:2px;padding:5px 25px;margin-bottom:20px'>";
      $textAlertaConfirmacioInscripcio .= $arrValors[1];
      $textAlertaConfirmacioInscripcio .= "</div>";
	}
	else {
		$textAlertaConfirmacioInscripcio = '';
	}
	$connexio->closeStmt();

	/* ######################################################################### */
	$nom = $textNom->obtenirText();
	$cog = $textCog->obtenirText();
	$nomCognoms = $nom." ".$cog;
	$documentacio = $textDocumentacio->convertirMaj();
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

	$titolCurs = $textTitolCurs->obtenirText();
	$dates = $textDates->obtenirText();
	$conegut = $textConegut->obtenirText();
	$edicio = $textEdicio->obtenirText();
	if ($textComentaris != null)
		$comentaris = $textComentaris->obtenirText();
	$observacions = $textObservacions->obtenirText();
	$codiRegal = $textCodiRegal->obtenirText();

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
	$missatge = "<p>Benvolgut/da ".$nom.",</p>";
	$missatge .= "<p>Hem rebut la teva sol·licitud i ens plau comunicar-te que tens ";
	$missatge .= "plaça disponible en el curs en línia <strong>".$titolCurs."</strong> ";
	$missatge .= "que es realitza <strong>".$datesRealitzacio."</strong> amb les dades personals següents:</p>";
	$missatge .= "<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
		<p><strong>Nom:</strong> ".$nomCognoms."</p>
		<p><strong>NIF/NIE/passaport:</strong> ".$documentacio."</p>
		<p><strong>Correu electrònic:</strong> ".$email."</p>
		<p><strong>Telèfon de contacte:</strong> ".$telf."</p>
		<p><strong>Adreça:</strong> ".$adreca." - ".$codiPostal." ".$poblacio."</p>
	</div>";
	$missatge .= $textCursReconegut;
	$missatge .= $textEstudiant;
	$missatge .= $textHasRealitzatCurs;
	$missatge .= $textIniciCurs;
	$missatge .= $textAlertaConfirmacioInscripcio;
	$missatge .= $textConsentimentMailing;
	$missatge .= "<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";

	$msgInsc = "<p><strong>Nom:</strong> ".$nomCognoms."</p>";
	$msgInsc .= "<p><strong>Document:</strong> ".$documentacio."</p>";
	$msgInsc .= "<p><strong>Email:</strong> ".$email."</p>";
	$msgInsc .= "<p><strong>Telèfon:</strong> ".$telf."</p>";
	$msgInsc .= "<p><strong>Adreça:</strong> ".$adreca."</p>";
	$msgInsc .= "<p><strong>CP:</strong> ".$codiPostal."</p>";
	$msgInsc .= "<p><strong>Població:</strong> ".$poblacio."</p>";
	$msgInsc .= "<p><strong>Estic treballant a:</strong> ".$perfils."</p>";
	$msgInsc .= "<p><strong>Titulació / especialitat:</strong> ".$titulacions."</p>";
	$msgInsc .= "<p><strong>Curs:</strong> ".$titolCurs."</p>";
	$msgInsc .= "<p><strong>Data:</strong> ".$datesRealitzacio."</p>";
	$msgInsc .= "<p><strong>Codi regal:</strong> ".$codiRegal."</p>";
	if ($mailing=='Registred') $constMailing = 'Ja està subscrit';
	else if ($mailing=='Yes') $constMailing = 'Sí';
	else $constMailing = 'No';
	$msgInsc .= "<p><strong>Consentiment mailing:</strong> ".$constMailing."</p>";
	$msgInsc .= "<p><strong>Comentaris:</strong> ".$comentaris."</p>";

	$subjectMailInsc = "Inscripció regal ".$codiCurs." ".$edicio." - ".$documentacio;
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

	$subject = "Inscripció al curs regal ".$titolCurs;
	$subject2 = "Inscripció al curs regal ".$titolCurs." ".$dataInsc;

	/* ######################################################################### */
	// FACT_REL i USAT es llegeixen sota FOR UPDATE just abans de crear/reutilitzar la inscripció.

	/* ######################################################################### */
	$nomBD = $textNom->obtenirText();
	$cogBD = $textCog->obtenirText();
	$nomCognomsBD = $nomBD." ".$cogBD;
	$documentacioBD = $textDocumentacio->convertirMaj();
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

	$conegutBD = $textConegut->obtenirText();
	$edicioBD = $textEdicio->obtenirText();
	$codiCursBD = $textCodiCurs->convertirMaj();
	$codiRegalBD = $textCodiRegal->obtenirText();
	$titolCursBD = $textTitolCurs->obtenirText();

	$comentarisBD = '';
	if ($textComentaris!=null) $comentarisBD = $textComentaris->obtenirText();
	$observacionsBD = $textObservacions->obtenirText();

	if ($mailing=='Registred') $mailingBD = 'X';
	else if ($mailing=='Yes') $mailingBD = '1';
	else $mailingBD = '0';

	$usuariBD = '';
	$clean = preg_replace('~[^0-9]+~', '', $documentacioBD);
	preg_match_all('!\d+!', $clean, $numero);
	$usuariBD = implode(' ', $numero[0]);

	$currentDate = 'CURRENT_DATE';
	$perenne = 'X';
	$aPagar = 0;

	$legacyGiftTransaction = false;
	$idPagLockHeld = false;
	try {
		$connexio->connexio->begin_transaction();
		$legacyGiftTransaction = true;

		$cnsGiftLock = "SELECT FACT_REL, USAT FROM regal WHERE CODI=? FOR UPDATE";
		$stmt=$connexio->prepare($cnsGiftLock);
		$stmt->bind_param("s", $codiRegalBD);
		$stmt->execute();
		$stmt->bind_result($factRel, $giftUsedEnrollmentId);
		$giftExists = $stmt->fetch();
		$connexio->closeStmt();
		if (!$giftExists) {
			throw new Exception('No s\'ha trobat el regal', 404);
		}
		if ((int) $factRel <= 0) {
			throw new Exception('El regal no està disponible per al bescanvi', 409);
		}

		$idInserit = 0;
		$candidateId = 0;
		$candidateCourse = '';
		$candidateDni = '';
		$candidateAmount = null;
		$candidateFactRel = null;
		$candidateGiftCode = '';
		$candidateObservations = '';

		if ((int) $giftUsedEnrollmentId > 0) {
			$cnsExisting = "SELECT ID, CURS, DNI, A_PAGAR, FACTURA_RELACIONADA,
				pag_observacions, OBSERVACIONS
				FROM inscripcions WHERE ID=? FOR UPDATE";
			$stmt=$connexio->prepare($cnsExisting);
			$stmt->bind_param("d", $giftUsedEnrollmentId);
			$stmt->execute();
			$stmt->bind_result(
				$candidateId,
				$candidateCourse,
				$candidateDni,
				$candidateAmount,
				$candidateFactRel,
				$candidateGiftCode,
				$candidateObservations
			);
			$existingFound = $stmt->fetch();
			$connexio->closeStmt();
			if (!$existingFound) {
				throw new Exception('El regal apunta a una inscripció inexistent', 409);
			}
		}
		else {
			$cnsExisting = "SELECT ID, CURS, DNI, A_PAGAR, FACTURA_RELACIONADA,
				pag_observacions, OBSERVACIONS
				FROM inscripcions
				WHERE pag_observacions=?
				ORDER BY ID DESC LIMIT 2 FOR UPDATE";
			$stmt=$connexio->prepare($cnsExisting);
			$stmt->bind_param("s", $codiRegalBD);
			$stmt->execute();
			$stmt->store_result();
			$candidateCount = $stmt->num_rows();
			if ($candidateCount > 1) {
				$connexio->closeStmt();
				throw new Exception('Hi ha més d\'una inscripció candidata per al mateix regal', 409);
			}
			if ($candidateCount === 1) {
				$stmt->bind_result(
					$candidateId,
					$candidateCourse,
					$candidateDni,
					$candidateAmount,
					$candidateFactRel,
					$candidateGiftCode,
					$candidateObservations
				);
				$stmt->fetch();
			}
			$connexio->closeStmt();
		}

		if ((int) $candidateId > 0) {
			$normalizeIdentity = static function($value) {
				$value = strtoupper(trim((string) $value));
				$value = preg_replace('/[^A-Z0-9]/', '', $value);
				return is_string($value) ? $value : '';
			};
			if (strtoupper(trim((string) $candidateCourse)) !== strtoupper(trim((string) $codiCursBD))
				|| $normalizeIdentity($candidateDni) !== $normalizeIdentity($documentacioBD)
				|| number_format((float) $candidateAmount, 2, '.', '') !== '0.00'
				|| (string) $candidateFactRel !== (string) $factRel
				|| trim((string) $candidateGiftCode) !== trim((string) $codiRegalBD)
				|| stripos((string) $candidateObservations, 'CURS REGAL') === false
			) {
				throw new Exception('La inscripció existent del regal és contradictòria', 409);
			}
			$idInserit = (int) $candidateId;
		}
		else {
			$idPag = $connexio->reserveIdPag();
			$idPagLockHeld = true;

			$insertBD = "INSERT INTO inscripcions (ANY, MES, CURS, DATA_INSC, NOM, COGNOMS,
					 CORREU, DNI, ADRECA, Codi_Postal, POBLACIO, PERFIL, TITULACIO,
					 TELEFON, OBSERVACIONS, COMENTARIS, INSC_MAILING, IDPAG,
					 A_PAGAR, FACTURA_RELACIONADA,pag_observacions, USUARI, PERENNE, CONEGUT)
					 VALUES (?,?,?,CURRENT_TIME,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
			$stmt=$connexio->prepare($insertBD);
			$stmt->bind_param("dsssssssssssdsssdddsdss",
				$any, $edicioBD, $codiCursBD, $nomBD, $cogBD, $emailBD, $documentacioBD,
				$adrecaBD, $codiPostalBD, $poblacioBD,	$perfilsBD, $titulacionsBD, $telfBD,
				$observacionsBD, $comentarisBD, $mailingBD, $idPag, $aPagar, $factRel, $codiRegalBD,
				$usuariBD, $perenne, $conegutBD);
			$stmt->execute();
			$idInserit = (int) $connexio->lastInsertId();
			$connexio->closeStmt();
			$connexio->releaseIdPag();
			$idPagLockHeld = false;
		}

		if ((int) $idInserit <= 0) {
			throw new Exception('No s\'ha pogut materialitzar la inscripció regal', 409);
		}

		$connexio->connexio->commit();
		$legacyGiftTransaction = false;
	}
	catch(Throwable $writerException) {
		if ($idPagLockHeld) {
			$connexio->releaseIdPag();
		}
		if ($legacyGiftTransaction) {
			$connexio->connexio->rollback();
		}
		throw $writerException;
	}

	// Frontera servidor→SIF: només ID compromès + codi. Holder i preu es
	// resolen dins del SIF i el reintent reutilitza la mateixa ID_INSC.
	$sifGiftClient = new SifGiftRedemptionClient();
	$sifGiftRedemption = $sifGiftClient->redeemCommittedEnrollment([
		'enrollment_id' => (int) $idInserit,
		'gift_code' => $codiRegalBD,
	]);

	$notificationBundle = $sifGiftRedemption['notification_bundle'] ?? null;
	$notificationItems = is_array($notificationBundle)
		? ($notificationBundle['notifications'] ?? null)
		: null;
	if (!is_array($notificationItems) || count($notificationItems) !== 6) {
		throw new RuntimeException('El SIF no ha retornat els sis correus UC-018', 500);
	}

	$giftMailNotifications = [];
	foreach ($notificationItems as $notificationItem) {
		if (!is_array($notificationItem)) {
			throw new RuntimeException('Bundle de correus UC-018 invàlid', 500);
		}
		$messageCode = trim((string) ($notificationItem['message_code'] ?? ''));
		$uuidNotification = trim((string) ($notificationItem['uuid_notification'] ?? ''));
		if ($messageCode === '' || $uuidNotification === '') {
			throw new RuntimeException('Identitat de correu UC-018 incompleta', 500);
		}
		$giftMailNotifications[$messageCode] = $uuidNotification;
	}
	if (count($giftMailNotifications) !== 6) {
		throw new RuntimeException('Codis de correu UC-018 duplicats o incomplets', 500);
	}

	$giftMailIssues = [];
	$sendGovernedGiftMail = static function(
		string $messageCode,
		callable $factory
	) use ($sifGiftClient, $giftMailNotifications, &$giftMailIssues): void {
		$uuidNotification = $giftMailNotifications[$messageCode] ?? null;
		if (!is_string($uuidNotification) || trim($uuidNotification) === '') {
			$giftMailIssues[] = $messageCode.':MISSING_NOTIFICATION';
			return;
		}

		$claim = $sifGiftClient->claimNotificationBundle($uuidNotification);
		if (($claim['should_send'] ?? false) !== true) {
			if (strtoupper((string) ($claim['status'] ?? '')) !== 'SENT') {
				$giftMailIssues[] = $messageCode.':'.(string) ($claim['reason'] ?? 'NOT_SENDABLE');
			}
			return;
		}

		$uuidAttempt = trim((string) ($claim['uuid_delivery_attempt'] ?? ''));
		if ($uuidAttempt === '') {
			$giftMailIssues[] = $messageCode.':MISSING_ATTEMPT';
			return;
		}

		try {
			$mailer = $factory();
			$accepted = $mailer instanceof MailSMTPComvive && $mailer->enviat();
			$sifGiftClient->completeNotificationBundle(
				$uuidNotification,
				$uuidAttempt,
				$accepted
			);
			if (!$accepted) {
				$giftMailIssues[] = $messageCode.':SMTP_SEND_FAILED';
			}
		}
		catch(Throwable $mailException) {
			// El claim queda SENDING: és ambigu i no es reenvia automàticament.
			$giftMailIssues[] = $messageCode.':AMBIGUOUS_SENDING';
		}
	};

	$hashIdInserit = $encryptEnrollmentId($idInserit);

	$sendGovernedGiftMail(
		'GIFT_REDEEM_INTERNAL_DETAIL_PRIMARY',
		static function() use (
			$usernameInsc, $passwordInsc, $nomCognoms, $email,
			$subjectMailInsc, $msgInsc
		) {
			return new MailSMTPComvive(
				$usernameInsc,
				$passwordInsc,
				'Secretaria PrisMa',
				'inscripcions@prisma.cat',
				$nomCognoms,
				$email,
				'Secretaria PrisMa',
				'inscripcions@prisma.cat',
				$subjectMailInsc,
				$msgInsc
			);
		}
	);

	$sendGovernedGiftMail(
		'GIFT_REDEEM_RESGUARD_PRIMARY',
		static function() use (
			$username, $password, $nomCognoms, $email, $subject2, $missatge
		) {
			return new MailSMTPComvive(
				$username,
				$password,
				'Secretaria PrisMa',
				'secretaria@prisma.cat',
				$nomCognoms,
				$email,
				'PrisMa Secretaria',
				'resguard.secretaria@prisma.cat',
				$subject2,
				$missatge
			);
		}
	);

	$sendGovernedGiftMail(
		'GIFT_REDEEM_SECRETARY_CONFIRMATION',
		static function() use (
			$username, $password, $nomCognoms, $email, $subject, $missatge
		) {
			return new MailSMTPComvive(
				$username,
				$password,
				'Secretaria PrisMa',
				'secretaria@prisma.cat',
				$nomCognoms,
				$email,
				'Secretaria PrisMa',
				'inscripcions@prisma.cat',
				$subject,
				$missatge
			);
		}
	);

	$sendGovernedGiftMail(
		'GIFT_REDEEM_INTERNAL_DETAIL_GMAIL',
		static function() use (
			$username, $password, $nameUser, $nomCognoms, $email,
			$subjectMailInsc, $msgInsc
		) {
			return new MailSMTPComvive(
				$username,
				$password,
				$nameUser,
				$username,
				$nomCognoms,
				$email,
				'Inscripcions PrisMa',
				'inscripcions.prisma@gmail.com',
				$subjectMailInsc,
				$msgInsc
			);
		}
	);

	if ($mailingBD == '1') {
		$cnsMailing = "SELECT ID FROM mailing WHERE MAIL=?";
		$stmt=$connexio->prepare($cnsMailing);
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
	$connexio->closeStmt();

	$subject = "Inscripció al curs regal ".$titolCurs;
	$subject2 = "Inscripció al curs regal ".$titolCurs." ".$dataInsc;

	$sendGovernedGiftMail(
		'GIFT_REDEEM_RESGUARD_SECONDARY',
		static function() use (
			$username, $password, $nameUser, $nomCognoms, $email,
			$subject2, $missatge
		) {
			return new MailSMTPComvive(
				$username,
				$password,
				$nameUser,
				$username,
				$nomCognoms,
				$email,
				'PrisMa Secretaria',
				'resguard.secretaria@prisma.cat',
				$subject2,
				$missatge
			);
		}
	);

	$sendGovernedGiftMail(
		'GIFT_REDEEM_STUDENT_CONFIRMATION',
		static function() use (
			$username, $password, $nameUser, $nomCognoms, $email,
			$subject, $missatge
		) {
			return new MailSMTPComvive(
				$username,
				$password,
				$nameUser,
				$username,
				$nomCognoms,
				$email,
				$nomCognoms,
				$email,
				$subject,
				$missatge
			);
		}
	);

	if ($giftMailIssues !== []) {
		throw new RuntimeException(
			'Un o més correus UC-018 requereixen reconciliació abans de confirmar la resposta',
			500
		);
	}

	/* ######################################################################### */

	// regal.USAT ja ha estat reconciliat pel SIF amb compare-and-set.

	echo $hashIdInserit;

	$connexio->desconectarBD();
}
catch(Throwable $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
