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
	$numPreuTaller = new Numero($_GET['preuTaller']);
	$textCodiTaller = new Text($_GET['codiTaller']);

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

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	/* ######################################################################### */
	$cnsInfo = "SELECT TITOL FROM informacio WHERE CODI_CURS=? AND ESTAT=1";
	if ( $stmt=$connexio->prepare($cnsInfo) ) {
		$stmt->bind_param("s", $codiTaller);
		$codiTaller = $textCodiTaller->obtenirText();
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

	$cnsInfoOrig = "SELECT HORES, ID_PREU, DATAI, ANY, MES, CIUTAT, LLOC2
						FROM info_taller WHERE CODI_CURS = ? AND ESTAT = 1 AND DATAI >= CURRENT_DATE";
	if ( $stmt = $connexio->prepare($cnsInfoOrig) ) {
		$stmt->bind_param("s", $codiTaller);
		$stmt->execute();
		$stmt->bind_result($hores, $idPreu, $dataI, $any, $mes, $ciutat, $lloc);
		$stmt->fetch();
		$connexio->closeStmt();
	}
	else {
		throw new Exception('',2912);
	}

	$objDateI = new Date($dataI);

	$textLlocTaller = "<p>El taller tindrà lloc <strong>a l'espai de ".$lloc." des de les 10 h fins a les 14 h</strong>.</p>";

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

	$urlIdPag = "https://www.prisma.cat/pagament/".$hashIdPag;

	$titolTaller = $textTitolCurs->obtenirText();
	$pagFrac = $textPagFrac->obtenirText();
	$preuTaller = $numPreuTaller->obtenirNumero();
	$mailing = $textMailing->obtenirText();

	$textManeresPagar ="
		<ul style='list-style: square; margin-bottom: 20px; margin-left: 30px; padding: 0;'>
			<li style='margin-top: 8px; line-height: 24px;'>
				TARGETA: clica a l'enllaç següent (cal que tinguis activat el codi
				de compra segura facilitat per la teva entitat bancària):
				<a style='color: #496BAA; text-decoration: none; font-weight: bold;'
				 href='$urlIdPag' title='Pagament del pack ".$titolTaller."'>".$urlIdPag."</a>.
			</li>
			<li style='margin-top: 8px; line-height: 24px;'>
				TRANSFERÈNCIA o INGRÉS BANCARI: indica clarament el concepte
				<strong>«".$textCodiTaller->obtenirText()." + el número
				del teu NIF/NIE/passaport»</strong>, en qualsevol dels comptes següents:
				<ul style='list-style: circle; margin-left: 30px; padding: 0;'>
					<li style='margin-top: 8px; line-height: 24px;'>La Caixa: ES30 2100 4279 21 2200080678 </li>
					<li style='margin-top: 8px; line-height: 24px;'>BBVA: ES04 0182 5117 00 0201534816</li>
				</ul>
			</li>
		</ul>
		<p>Un cop hagis realitzat el pagament és important que conservis el
		justificant bancari fins que t'arribi un correu electrònic que confirmi
		que l'hem rebut correctament.</p>";
	$textPagament = '';
	if ($pagFrac == 'Yes') {
		$textPagament = "
			<p>Perquè puguem fraccionar el pagament, t'agrairíem que ens comuniquis,
			contestant aquest mateix correu, com vols pagar els
			<strong>".$preuTaller." euros</strong> (quantitats i terminis) i que
			efectuís tots els ingressos escollint una de les opcions següents: </p>";
		$textPagament .= $textManeresPagar;

		$pagamentFraccionat="<p>Pagament fraccionat</p>";
	}
	else {
		$textPagament = "
			<p>Per tal de pagar els <strong> ".$preuTaller." euros</strong> de la matrícula,
			pots escollir una de les opcions següents:</p> ".$textManeresPagar;
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

	$objDataI = new Date($dataI);
	$datesRealitzacioCurs = $objDataI->getPronomEl()."".$objDataI->getDataLlarga();

	/* ######################################################################### */
	$missatge = "<p>Benvolgut/da ".$nom.",</p>";
	$missatge .= "<p>Hem rebut la teva sol·licitud i ens plau comunicar-te que ";
	$missatge .= "ja t'hem inscrit en el taller presencial <strong>".$titolTaller."</strong> ";

	$missatge .= "que es realitza <strong>".$datesRealitzacioCurs."</strong> amb les dades personals següents:</p>";

	$missatge .= "<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
		<p><strong>Nom:</strong> ".$nomCognoms."</p>
		<p><strong>NIF/NIE/passaport:</strong> ".$documentacio."</p>
		<p><strong>Correu electrònic:</strong> ".$email."</p>
		<p><strong>Telèfon de contacte:</strong> ".$telf."</p>
		<p><strong>Població:</strong> ".$adreca." - ".$codiPostal." ".$poblacio."</p>
	</div>";
	$missatge .= $textLlocTaller;
	$missatge .= $textPagament;
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
	$msgInsc .= "<p><strong>Taller:</strong> ".$titol."</p>";
	$msgInsc .= "<p><strong>Data:</strong> ".$datesRealitzacioCurs."</p>";
	$msgInsc .= "<p><strong>Com has conegut aquest curs?:</strong> ".$conegut."</p>";
	$msgInsc .= "<p><strong>Preu:</strong> ".$preuTaller." euros</p>";
	$msgInsc .= "<p><strong>IDPAG:</strong> ".$idPag."</p>";
	$msgInsc .= "<p><strong>URL pagament:</strong> ".$urlIdPag."</p>";
	if ($mailing=='Registred') $constMailing = 'Ja està subscrit';
	else if ($mailing=='Yes') $constMailing = 'Sí';
	else $constMailing = 'No';
	$msgInsc .= "<p><strong>Consentiment mailing:</strong> ".$constMailing."</p>";
	$msgInsc .= "<p><strong>Comentaris:</strong> ".$comentaris."</p>";
	$msgInsc .= $pagamentFraccionat;

	$subjectMailInsc = "Inscripció taller ".$codiTaller." - ".$documentacio;
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

	$subject = "Inscripció al taller ".$titolTaller;

	$nomFromHead = 'Secretaria PrisMa';
	$correuFromHead = 'inscripcions@prisma.cat';
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = 'Secretaria PrisMa';
	$correuTo = 'inscripcions@prisma.cat';

	$mailCopiaInsc = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);

	$nomFromHead = 'Secretaria PrisMa';
	$correuFromHead = 'secretaria@prisma.cat';
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";

	$subject2 = "Inscripció al taller ".$titolTaller." ".$dataInsc;

	$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject2, $missatge);

	$nomTo = 'Secretaria PrisMa';
	$correuTo = 'inscripcions@prisma.cat';

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
	$tipusInsc = 'T';

	$insertBD = "INSERT INTO inscripcions (ANY, MES, CURS, DATA_INSC, NOM, COGNOMS,
					 CORREU, DNI, ADRECA, Codi_Postal, POBLACIO, PERFIL, TITULACIO,
					 TELEFON, COMENTARIS, FRACCIONAT, INSC_MAILING,
					 A_PAGAR, USUARI, IDPAG, PERENNE, CONEGUT, TIPUS_INSC, OBSERVACIONS)
					 VALUES (?,?,?,CURRENT_TIME,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
	if ( $stmt=$connexio->prepare($insertBD) ) {
		$stmt->bind_param("dsssssssssssdsdsdddssss", $any, $mes, $codiTaller,
			$nomBD, $cogBD, $emailBD, $documentacioBD, $adrecaBD, $codiPostalBD, $poblacioBD,
			$perfilsBD, $titulacionsBD, $telfBD, $comentarisBD, $pagFraccBD, $mailingBD,
			$preuTaller, $usuariBD, $idPag, $perenne, $conegutBD, $tipusInsc, $observacions);
		$stmt->execute();
		$connexio->closeStmt();
	}
	else {
		throw new Exception('',2915);
	}

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

	$subject = "Inscripció al taller ".$titolTaller;
	$subject2 = "Inscripció al taller ".$titolTaller." ".$dataInsc;

	$nomFromHead = $nameUser;
	$correuFromHead = $username;
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";

	$mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject2, $missatge);

	$nomTo = $nomCognoms;
	$correuTo = $email;
	// $correuTo = "suport.informatic@prisma.cat";

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
