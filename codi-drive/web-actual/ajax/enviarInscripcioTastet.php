<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Numero.php");
include("../Text.php");
include("../Date.php");
include("../Mail.php");
include("../MailSMTP.php");
include("../MailSMTPComvive.php");
include("../MailSMTPFile.php");
include("../Uc108Validation.php");
include("../Uc108ConfirmationToken.php");

try {
	// Compatibilitat temporal amb clients antics GET; el flux actual usa POST
	// per evitar dades personals a la URL.
	$request = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;

	$textNom = new Text($request['nom']);
	$textCog = new Text($request['cog']);
	$textDocumentacio = new Text($request['dni']);
	$textEmail = new Text($request['email']);
	$textPoblacio = new Text($request['poblacio']);
	$textConegut = new Text($request['conegut']);
	if ( isset($request['comentaris']) && $request['comentaris'] != '')
		$textComentaris = new Text($request['comentaris']);
	else
		$textComentaris = null;
	$textMailing = new Text($request['mailing']);
	$textCodiCurs = new Text($request['codiCurs']);

	$urlOrigen = isset($request['url']) ? trim($request['url']) : '';
	$idUrlTastet = null;
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		if ($urlOrigen === '')
			throw new Exception('',404);

		$partsUrl = explode('/', rtrim($urlOrigen, '/'));
		$slugTastet = $partsUrl[count($partsUrl)-1];
		$idUrlTastet = buscarPagina('/tastets/'.$slugTastet);

		if ($idUrlTastet == null || $idUrlTastet == '')
			throw new Exception('',404);
	}

	$textEmailConf = null;
	if (isset($input['email_conf']) && trim($input['email_conf']) != '')
		$textEmailConf = new Text($input['email_conf']);

	if ($esPost && $textEmailConf === null) {
		echo "Error: cal confirmar el correu electrònic.";
		return;
	}

	$urlTastet = isset($input['urlTastet']) ? trim($input['urlTastet']) : '';
	$codiCursLegacy = isset($input['codiCurs']) ? trim($input['codiCurs']) : '';
	$tipusDoc = isset($input['tipus_doc']) ? trim($input['tipus_doc']) : '';

	if ($esPost && $tipusDoc !== 'NIF/NIE' && $tipusDoc !== 'Altres') {
		echo "Error: cal indicar el tipus de document identificatiu.";
		return;
	}

	$textNom->arreglarParaulaBD('noms');
	$textCog->arreglarParaulaBD('noms');
	$textDocumentacio->arreglarParaulaBD('text_maj');
	$textEmail->arreglarParaulaBD('email');
	$textPoblacio->arreglarParaulaBD('noms');
	$textConegut->arreglarParaulaBD('text_no_mod');
	if ($textComentaris != null) $textComentaris->arreglarParaulaBD('text');
	if ($textEmailConf != null) $textEmailConf->arreglarParaulaBD('email');

	$nomValidat = $textNom->obtenirText();
	$cognomsValidats = $textCog->obtenirText();
	$documentValidat = $textDocumentacio->obtenirText();
	$emailValidat = $textEmail->obtenirText();
	$poblacioValidada = $textPoblacio->obtenirText();
	$conegutValidat = $textConegut->obtenirText();

	if ($nomValidat == '' || $cognomsValidats == '' || $documentValidat == '' ||
		$emailValidat == '' || $poblacioValidada == '' || $conegutValidat == '') {
		echo "Error: falten camps obligatoris.";
		return;
	}

	if (!Uc108Validation::validarEmail($emailValidat)) {
		echo "Error: el correu electrònic no és vàlid.";
		return;
	}

	if ($textEmailConf != null && strcasecmp($textEmailConf->obtenirText(), $emailValidat) !== 0) {
		echo "Error: els correus electrònics no coincideixen.";
		return;
	}

	if ($tipusDoc == 'NIF/NIE') {
		if (!Uc108Validation::validarNifNie($documentValidat)) {
			echo "Error: el NIF/NIE no és vàlid.";
			return;
		}
		$documentValidat = Uc108Validation::normalitzarNifNie($documentValidat);
	}

	if ($tipusDoc != '' && $tipusDoc != 'NIF/NIE' && mb_strlen(trim($documentValidat)) < 3) {
		echo "Error: el document identificatiu no és vàlid.";
		return;
	}

	if (preg_match('/\d/u', $poblacioValidada)) {
		echo "Error: la població no és vàlida.";
		return;
	}

	$dataInsc = date('d')."-".date('m')."-".date('Y')." ".date('H').":".date('i');

	/* ######################################################################### */
	$codiCurs = $textCodiCurs->obtenirText();
	if ($idUrlTastet !== null) {
		$cnsINFO = "SELECT TITOL FROM reptes WHERE CODI_CURS=? AND ID_URL=? AND ESTAT=1";
		$stmt=$connexio->prepare($cnsINFO);
		$stmt->bind_param("sd", $codiCurs, $idUrlTastet);
	}
	else {
		// Compatibilitat temporal amb clients GET antics.
		$cnsINFO = "SELECT TITOL FROM reptes WHERE CODI_CURS=? AND ESTAT=1";
		$stmt=$connexio->prepare($cnsINFO);
		$stmt->bind_param("s", $codiCurs);
	}
	$stmt->execute();
	$stmt->store_result();
	if ($stmt->num_rows() != 1) {
		$connexio->closeStmt();
		$connexio->desconectarBD();
		throw new Exception('',404);
	}
	$stmt->bind_result($titol);
	$stmt->fetch();
	$connexio->closeStmt();

	$textTitolCurs = new Text($titol);
	$textTitolCurs->arreglarParaulaBD('text_no_mod');

	// El precheck del navegador no és autoritatiu: repetir al servidor abans de qualsevol efecte.
	$documentacioCheck = ($tipusDoc == 'NIF/NIE')
		? $documentValidat
		: $textDocumentacio->obtenirText();

	if ($tipusDoc == 'NIF/NIE') {
		$cnsDuplicat = "SELECT ID FROM inscripcions_reptes
			WHERE CURS=?
			AND REPLACE(REPLACE(REPLACE(UPPER(DNI),' ',''),'.',''),'-','')=?
			AND INSC_CURS=1
			LIMIT 1";
	}
	else {
		$cnsDuplicat = "SELECT ID FROM inscripcions_reptes
			WHERE CURS=? AND DNI=? AND INSC_CURS=1
			LIMIT 1";
	}
	$stmt = $connexio->prepare($cnsDuplicat);
	$stmt->bind_param("ss", $codiCurs, $documentacioCheck);
	$stmt->execute();
	$stmt->store_result();

	if ($stmt->num_rows() > 0) {
		$connexio->closeStmt();
		$connexio->desconectarBD();
		echo "DUPLICATE";
		return;
	}
	$connexio->closeStmt();

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

	/* ######################################################################### */
	//Busquem la data d'inici del curs
	$textIniciCurs = "<p><strong>En un període de 24/48 hores laborals podràs accedir al tastet amb les teves claus.</strong> En cas que no disposis de claus, rebràs un correu electrònic amb les teves dades d'accés al campus virtual de PrisMa.</p>";

	/* ######################################################################### */
	$textConsentimentMailing = "
			<p>Amb aquesta inscripció gratuïta et donarem d'alta al nostre butlletí electrònic. Si no vols continuar rebent aquests missatges, te'n podràs donar de baixa en qualsevol moment.</p>";

	/* ######################################################################### */
	$nom = $textNom->obtenirText();
	$cog = $textCog->obtenirText();
	$nomCognoms = $nom." ".$cog;
	$documentacio = ($tipusDoc == 'NIF/NIE') ? $documentValidat : $textDocumentacio->obtenirText();
	$email = $textEmail->obtenirText();
	$poblacio = $textPoblacio->obtenirText();
	$titolCurs = $textTitolCurs->obtenirText();
	$conegut = $textConegut->obtenirText();
	$comentaris = '';
	if ($textComentaris != null)
		$comentaris = $textComentaris->obtenirText();

	$nomHtml = htmlspecialchars($nom, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$nomCognomsHtml = htmlspecialchars($nomCognoms, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$documentacioHtml = htmlspecialchars($documentacio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$emailHtml = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$poblacioHtml = htmlspecialchars($poblacio, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$titolCursHtml = htmlspecialchars($titolCurs, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$conegutHtml = htmlspecialchars($conegut, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$comentarisHtml = htmlspecialchars($comentaris, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$nomCognomsHeader = preg_replace('/[\r\n]+/', ' ', $nomCognoms);

	/* ######################################################################### */

	$textPagament = "<p>
		Ja saps que fer aquest tastet és completament <strong>gratuït</strong>.
	</p>";

	/* ######################################################################### */
	$textDuradaAcces = "<p>
		I tingues en compte que, un cop t’hàgim donat d’alta al tastet, <strong>només hi tindràs accés durant una setmana</strong>.
		Però no et preocupis: completar-lo no et portarà més de dues o tres hores!
	</p>";

	/* ######################################################################### */
	$missatge = "<p>Benvolgut/da ".$nomHtml.",</p>";
	$missatge .= "<p>Et comuniquem que ja hem rebut la teva sol·licitud d'inscripció per al tastet en línia ";
	$missatge .= "<strong style='color: #496baa'>".$titolCursHtml."</strong> ";
	$missatge .= " amb les dades personals següents:</p>";
	$missatge .= "<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
		<p><strong>Nom:</strong> ".$nomCognomsHtml."</p>
		<p><strong>NIF/NIE/passaport:</strong> ".$documentacioHtml."</p>
		<p><strong>Correu electrònic:</strong> ".$emailHtml."</p>
		<p><strong>Població:</strong> ".$poblacioHtml."</p>
	</div>";
	$missatge .= $textIniciCurs;
	$missatge .= $textPagament;
	$missatge .= $textDuradaAcces;
	$missatge .= $textConsentimentMailing;
	$missatge .= "<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";

	$msgInsc = "<p><strong>Nom:</strong> ".$nomCognomsHtml."</p>";
	$msgInsc .= "<p><strong>Document:</strong> ".$documentacioHtml."</p>";
	$msgInsc .= "<p><strong>Email:</strong> ".$emailHtml."</p>";
	$msgInsc .= "<p><strong>Població:</strong> ".$poblacioHtml."</p>";
	$msgInsc .= "<p><strong>Tastet:</strong> ".$titolCursHtml."</p>";
	$msgInsc .= "<p><strong>Com has conegut aquest curs?:</strong> ".$conegutHtml."</p>";
	$msgInsc .= "<p><strong>Preu:</strong> Gratuït</p>";
	$msgInsc .= "<p><strong>Comentaris:</strong> ".$comentarisHtml."</p>";

	$subjectMailInsc = "Inscripció tastet ".$codiCurs;
	if ($comentaris != '' )
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

	$subject = "Inscripció al tastet ".$titolCurs;

	$nomFromHead = 'Secretaria PrisMa';
	$correuFromHead = 'inscripcions@prisma.cat';
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = 'Secretaria PrisMa';
	$correuTo = 'inscripcions@prisma.cat';
	// $correuTo = 'meriem.prisma.cat@gmail.com';


	$nomFromHead = 'Secretaria PrisMa';
	$correuFromHead = 'secretaria@prisma.cat';
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";
	// $correuTo = 'meriem.prisma.cat@gmail.com';

	$subject2 = "Inscripció al tastet ".$titolCurs." ".$dataInsc;


	$nomTo = 'Secretaria PrisMa';
	$correuTo = 'inscripcions@prisma.cat';
	// $correuTo = 'meriem.prisma.cat@gmail.com';


	/* ######################################################################### */
	// Primer persistim la petició. Les notificacions s'executen després de tenir un ID real.
	$nomBD = $textNom->obtenirText();
	$cogBD = $textCog->obtenirText();
	$nomCognomsBD = $nomBD." ".$cogBD;
	$documentacioBD = $documentacio;
	$emailBD = $textEmail->obtenirText();
	$poblacioBD = $textPoblacio->obtenirText();
	$conegutBD = $textConegut->obtenirText();
	$codiCursBD = $codiCurs;
	$titolCursBD = $textTitolCurs->obtenirText();

	$comentarisBD = '';
	if ($textComentaris!=null) $comentarisBD = $textComentaris->obtenirText();

	$mailingBD = '1';
	
	$usuariBD = '';
	$clean = preg_replace('~[^0-9]+~', '', $documentacioBD);
	preg_match_all('!\d+!', $clean, $numero);
	$usuariBD = implode(' ', $numero[0]);


	$insertBD = "INSERT INTO inscripcions_reptes (CURS, DATA_INSC, NOM, COGNOMS,
					 CORREU, DNI, POBLACIO, COMENTARIS, USUARI_MDL, CONEGUT)
					 VALUES (?,CURRENT_TIME,?,?,?,?,?,?,?,?)";
	$stmt=$connexio->prepare($insertBD);
	$stmt->bind_param("sssssssds", $codiCursBD, $nomBD,
		$cogBD, $emailBD, $documentacioBD, $poblacioBD,
		$comentarisBD, $usuariBD, $conegutBD);
	$stmt->execute();
	$idInserit = $connexio->lastInsertId();
	$stmt->fetch();
	$connexio->closeStmt();

	/*
	 * Les notificacions internes es fan només després que la sol·licitud
	 * existeixi a inscripcions_reptes. Així no es genera un correu d'una
	 * alta que després no hagi pogut persistir.
	 */
	$mailCopiaInsc = new MailSMTPComvive(
		$usernameInsc, $passwordInsc,
		'Secretaria PrisMa', 'inscripcions@prisma.cat',
		$nomCognoms, $email,
		'Secretaria PrisMa', 'inscripcions@prisma.cat',
		$subjectMailInsc, $msgInsc
	);

	$mailCopiaResguard = new MailSMTPComvive(
		$username, $password,
		'Secretaria PrisMa', 'secretaria@prisma.cat',
		$nomCognoms, $email,
		'PrisMa Secretaria', 'resguard.secretaria@prisma.cat',
		$subject2, $missatge
	);

	$mailCopiaSecretaria = new MailSMTPComvive(
		$username, $password,
		'Secretaria PrisMa', 'secretaria@prisma.cat',
		$nomCognoms, $email,
		'Secretaria PrisMa', 'inscripcions@prisma.cat',
		$subject, $missatge
	);

	$ivlen = openssl_cipher_iv_length($cipher);
	$iv = openssl_random_pseudo_bytes($ivlen);
	$ciphertext_raw = openssl_encrypt($idInserit, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
	// Els tokens nous autentiquen IV + ciphertext per evitar manipulació del primer bloc.
	$hmac = hash_hmac('sha256', $iv.$ciphertext_raw, $keyEncr, $as_binary=true);
	$hashIdInserit = base64_encode( $iv.$hmac.$ciphertext_raw );

	echo $hashIdInserit;

	$nomFromHead = $nameUser;
	$correuFromHead = $username;
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = "PrisMa Secretaria";
	$correuTo = "inscripcions.prisma@gmail.com";
	// $correuTo = 'meriem.prisma.cat@gmail.com';

	$mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);

	if ($mailingBD == '1') {
		$cnsMailing = "SELECT ID FROM mailing WHERE MAIL=?";
		$stmt=$connexio->prepare($cnsMailing);
		$stmt->bind_param("s", $emailBD);
		$stmt->execute();
		$stmt->store_result();
		if ( $stmt->num_rows() <= 0 ) {
			$connexio->closeStmt();

	$errorsSMTP = [];
	try {
		// Alta comercial obligatòria associada al tastet. Un email existent no es duplica.
		if ($mailingBD == '1') {
			$cnsMailing = "SELECT ID FROM mailing WHERE MAIL=?";
			$stmt = $connexio->prepare($cnsMailing);
			$stmt->bind_param("s", $emailBD);
			$stmt->execute();
			$stmt->store_result();

			if ( $stmt->num_rows() <= 0 ) {
				$connexio->closeStmt();
				$insertMailing = "INSERT INTO mailing (mail, nom, usuari) VALUES (?, ?, ?)";
				$stmt = $connexio->prepare($insertMailing);
				$stmt->bind_param("ssd", $emailBD, $nomBD, $usuariBD);
				$stmt->execute();
				$stmt->fetch();
			}
			$connexio->closeStmt();
		}

	/*
	 * Els blocs antics de codi postal i promocions s'han retirat d'aquest handler:
	 * el formulari actual de tastets no envia CP ni promoció i les variables
	 * $codiPostalBD / $promocioAplicada no tenien cap origen en aquest flux.
	 */

	if (count($errorsSMTP) > 0)
		error_log('UC-108: SMTP no lliurat en '.implode(',', $errorsSMTP).'. Peticio '.$idInserit);

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
