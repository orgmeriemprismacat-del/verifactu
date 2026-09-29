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

function normalitzarNifNieUc108($document) {
	return strtoupper(preg_replace('/[\s\.\-]+/', '', trim($document)));
}

function validarNifNieUc108($document) {
	$document = normalitzarNifNieUc108($document);
	$letters = 'TRWAGMYFPDXBNJZSQVHLCKE';

	if (preg_match('/^[0-9]{8}[A-Z]$/', $document)) {
		$numero = intval(substr($document, 0, 8));
		return $letters[$numero % 23] === substr($document, -1);
	}

	if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $document)) {
		$prefix = ['X' => '0', 'Y' => '1', 'Z' => '2'];
		$numero = intval($prefix[$document[0]].substr($document, 1, 7));
		return $letters[$numero % 23] === substr($document, -1);
	}

	return false;
}

try {
	$esPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
	$input = $esPost ? $_POST : $_GET;

	$textNom = new Text(isset($input['nom']) ? $input['nom'] : '');
	$textCog = new Text(isset($input['cog']) ? $input['cog'] : '');
	$textDocumentacio = new Text(isset($input['dni']) ? $input['dni'] : '');
	$textEmail = new Text(isset($input['email']) ? $input['email'] : '');
	$textPoblacio = new Text(isset($input['poblacio']) ? $input['poblacio'] : '');
	$textConegut = new Text(isset($input['conegut']) ? $input['conegut'] : '');

	if (isset($input['comentaris']) && $input['comentaris'] != '')
		$textComentaris = new Text($input['comentaris']);
	else
		$textComentaris = null;

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

	if (filter_var($emailValidat, FILTER_VALIDATE_EMAIL) === false) {
		echo "Error: el correu electrònic no és vàlid.";
		return;
	}

	if ($textEmailConf != null && strcasecmp($textEmailConf->obtenirText(), $emailValidat) !== 0) {
		echo "Error: els correus electrònics no coincideixen.";
		return;
	}

	if ($tipusDoc == 'NIF/NIE') {
		if (!validarNifNieUc108($documentValidat)) {
			echo "Error: el NIF/NIE no és vàlid.";
			return;
		}
		$documentValidat = normalitzarNifNieUc108($documentValidat);
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

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$codiCurs = '';
	$titol = '';

	if ($urlTastet != '') {
		$idUrlTastet = buscarPagina($urlTastet);
		if ($idUrlTastet != null && $idUrlTastet != '') {
			$cnsINFO = "SELECT CODI_CURS, TITOL FROM reptes WHERE ID_URL=? AND ESTAT=1";
			$stmt = $connexio->prepare($cnsINFO);
			$stmt->bind_param("d", $idUrlTastet);
			$stmt->execute();
			$stmt->store_result();
			if ($stmt->num_rows() > 0) {
				$stmt->bind_result($codiCurs, $titol);
				$stmt->fetch();
			}
			$connexio->closeStmt();
		}
	}
	else if ($codiCursLegacy != '') {
		// Compatibilitat temporal amb clients JS antics.
		$cnsINFO = "SELECT CODI_CURS, TITOL FROM reptes WHERE CODI_CURS=? AND ESTAT=1";
		$stmt = $connexio->prepare($cnsINFO);
		$stmt->bind_param("s", $codiCursLegacy);
		$stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows() > 0) {
			$stmt->bind_result($codiCurs, $titol);
			$stmt->fetch();
		}
		$connexio->closeStmt();
	}

	if ($codiCurs == '' || $titol == '') {
		$connexio->desconectarBD();
		echo "Error: el tastet no està disponible actualment.";
		return;
	}

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
			<p>Et recordem que amb aquesta inscripció has acceptat rebre correus electrònics informatius dels nostres cursos i serveis. Tot i això, podràs donar-te de baixa de la nostra llista de correus en qualsevol moment.</p>";

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
	$documentacioSubject = preg_replace('/[\r\n]+/', ' ', $documentacio);
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

	$subjectMailInsc = "Inscripció tastet ".$codiCurs." - ".$documentacioSubject;
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

	$ivlen = openssl_cipher_iv_length($cipher);
	$iv = openssl_random_pseudo_bytes($ivlen);
	$payloadToken = json_encode([
		'id' => intval($idInserit),
		'url' => $urlTastet,
		'iat' => time()
	]);
	$ciphertext_raw = openssl_encrypt($payloadToken, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
	$hmac = hash_hmac('sha256', $iv.$ciphertext_raw, $keyEncr, $as_binary=true);
	$tokenRaw = $iv.$hmac.$ciphertext_raw;
	$hashIdInserit = 'v2.'.rtrim(strtr(base64_encode($tokenRaw), '+/', '-_'), '=');

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

		$subject = "Inscripció al tastet ".$titolCurs;
		$subject2 = "Inscripció al tastet ".$titolCurs." ".$dataInsc;

		$mailCopiaInsc = new MailSMTPComvive(
			$usernameInsc, $passwordInsc,
			'Secretaria PrisMa', 'inscripcions@prisma.cat',
			$nomCognomsHeader, $email,
			'Secretaria PrisMa', 'inscripcions@prisma.cat',
			$subjectMailInsc, $msgInsc
		);
		if (!$mailCopiaInsc->enviat()) $errorsSMTP[] = 'copia-inscripcions';

		$mailCopiaSecreResguard = new MailSMTPComvive(
			$username, $password,
			'Secretaria PrisMa', 'secretaria@prisma.cat',
			$nomCognomsHeader, $email,
			'PrisMa Secretaria', 'resguard.secretaria@prisma.cat',
			$subject2, $missatge
		);
		if (!$mailCopiaSecreResguard->enviat()) $errorsSMTP[] = 'resguard-secretaria-1';

		$mailCopiaSecre = new MailSMTPComvive(
			$username, $password,
			'Secretaria PrisMa', 'secretaria@prisma.cat',
			$nomCognomsHeader, $email,
			'Secretaria PrisMa', 'inscripcions@prisma.cat',
			$subject, $missatge
		);
		if (!$mailCopiaSecre->enviat()) $errorsSMTP[] = 'secretaria';

		$mailCopiaGmail = new MailSMTPComvive(
			$username, $password,
			$nameUser, $username,
			$nomCognomsHeader, $email,
			'PrisMa Secretaria', 'inscripcions.prisma@gmail.com',
			$subjectMailInsc, $msgInsc
		);
		if (!$mailCopiaGmail->enviat()) $errorsSMTP[] = 'copia-gmail';

		$mailCopiaResguard2 = new MailSMTPComvive(
			$username, $password,
			$nameUser, $username,
			$nomCognomsHeader, $email,
			'PrisMa Secretaria', 'resguard.secretaria@prisma.cat',
			$subject2, $missatge
		);
		if (!$mailCopiaResguard2->enviat()) $errorsSMTP[] = 'resguard-secretaria-2';

		$mailAlumne = new MailSMTPComvive(
			$username, $password,
			$nameUser, $username,
			$nomCognomsHeader, $email,
			$nomCognomsHeader, $email,
			$subject, $missatge
		);
		if (!$mailAlumne->enviat()) $errorsSMTP[] = 'participant';
	}
	catch(Throwable $sideEffectError) {
		error_log('UC-108: error posterior a la persistencia de la peticio. Codi '.$sideEffectError->getCode());
	}

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
