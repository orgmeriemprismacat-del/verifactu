<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Numero.php");
include("../Text.php");
include("../Mail.php");
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
	$numAny = new Numero($_GET['any']);
	$textEdicio = new Text($_GET['edicio']);
	$textPagFrac = new Text($_GET['pagFrac']);
	$textDates = new Text($_GET['dates']);
	$textConegut = new Text($_GET['conegut']);
	if ( $_GET['comentaris'] != '')
		$textComentaris = new Text($_GET['comentaris']);
	else
		$textComentaris = null;
	$textMailing = new Text($_GET['mailing']);
	$numTipusDescompte = new Numero($_GET['tipusDescompte']);
	$numPreuCar = new Numero($_GET['preuCar']);
	$numPreuDescompte = new Numero($_GET['preuDescompte']);
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
	$textDates->arreglarParaulaBD('text_min');
	$textConegut->arreglarParaulaBD('text_no_mod');
	if ($textComentaris != null) $textComentaris->arreglarParaulaBD('text');
	$textMailing->arreglarParaulaBD('text');
	$textCodiCurs->arreglarParaulaBD('text_maj');
	$textTitolCurs->arreglarParaulaBD('text_no_mod');

	$tipusDescompte = $numTipusDescompte->obtenirNumero();

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
	$datesRealitzacio = $textDates->obtenirText();

	$cnsDatesCurs = "SELECT DATAI, DATAF, HORES, DATA_RESOL FROM curs WHERE CURS=? AND ANY=? AND MES=?";
	$stmt=$connexio->prepare($cnsDatesCurs);
	$stmt->bind_param("sds", $codiCurs, $any, $mes);
	$codiCurs = $textCodiCurs->obtenirText();
	$any = $numAny->obtenirNumero();
	$mes = $textEdicio->obtenirText();
	$stmt->execute();
	$stmt->bind_result($datai, $dataf, $hores, $data_resol);
	$stmt->fetch();
	$connexio->closeStmt();

	/* ######################################################################### */
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
	if ($tipusDescompte == 0) $textAlumne='';
	else if ($tipusDescompte == 1) $textAlumne='(per ser alumne/a de PrisMa)';
	else if ($tipusDescompte == 2) $textAlumne="(per ser titular d'un Carnet Jove)";
	else if ($tipusDescompte == 3) $textAlumne='(per ser alumne/a de PrisMa)';

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
	$preuDescompte = $numPreuDescompte->obtenirNumero();
	$mailing = $textMailing->obtenirText();

	$textManeresPagar ="
		<ul style='list-style: square; margin-bottom: 20px; margin-left: 30px; padding: 0;'>
			<li style='margin-top: 8px; line-height: 24px;'>
				TARGETA: clica a l'enllaç següent (cal que tinguis activat el codi
				de compra segura facilitat per la teva entitat bancària):
				<a href='$urlIdPag' title='Pagament del curs ".$titolCurs."'>".$urlIdPag."</a>.
			</li>
			<li style='margin-top: 8px; line-height: 24px;'>
				TRANSFERÈNCIA o INGRÉS BANCARI: indica clarament el concepte
				<strong>«".$textCodiCurs->obtenirText()."-".$edicio."+ el número
				del teu NIF/NIE/Passaport»</strong>, en qualsevol dels comptes següents:
				<ul style='list-style: circle; margin-left: 30px; padding: 0;'>
					<li style='margin-top: 8px; line-height: 24px;'>La Caixa: ES30 2100 4279 21 2200080678 </li>
					<li style='margin-top: 8px; line-height: 24px;'>BBVA: ES04 0182 5117 00 0201534816</li>
				</ul>
			</li>
		</ul>
		<p>Un cop hagis realitzat el pagament és important que conservis el
		justificant bancari fins que t'arribi un correu electrònic que confirmi
		que hem rebut correctament el teu pagament.</p>";
	if ( $tipusDescompte == 0 ) {
		$textManeresPagar.="
			<p style='text-decoration:underline'>Nota: si esàs inscrit en un altre
			curs <em>on-line</em> de PrisMa però et falta efectuar el pagament o
			b&eacute; t'has trobat amb alguna incidència en l'import, contacta amb
			nosaltres per recalcular el preu.</p>";
	}
	$textPagament = '';
	if ($pagFrac == 'Yes') {
		$textPagament = "
			<p>Perquè puguem fraccionar el pagament, t'agrairíem que ens comuniquis,
			contestant aquest mateix correu, com vols pagar els
			<strong>".$preuDescompte." euros</strong> (quantitats i terminis) i que
			efectuís tots els ingressos escollint una de les opcions següents: </p>";
		$textPagament .= $textManeresPagar;
		$textPagament .= "
			<p>T'informem que cal efectuar un primer pagament a l'inici del curs i
			que després de la finalització d'aquest encara disposaràs d'una setmana
			per acabar de pagar els ".$preuDescompte." euros.</p>";

		$pagamentFraccionat="<p>Pagament fraccionat</p>";
	}
	else {
		$textPagament = "
			<p>Per tal de formalitzar la matr&iacute;cula, pots realitzar el pagament
			de <strong>".$preuDescompte." euros</strong> ".$textAlumne." escollint
			una de les opcions següents:</p> ".$textManeresPagar;
	}

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

	// $codiCurs = $textCodiCurs->obtenirText();
	$titolCurs = $textTitolCurs->obtenirText();
	$dates = $textDates->obtenirText();
	$conegut = $textConegut->obtenirText();
	$preuDescompte = $numPreuDescompte->obtenirNumero();
	$edicio = $textEdicio->obtenirText();
	if ($textComentaris != null)
		$comentaris = $textComentaris->obtenirText();

	/* ######################################################################### */
	$missatge = "<p>Benvolgut/da ".$nom.",</p>";
	$missatge .= "<p>Hem rebut la teva sol·licitud i ens plau comunicar-te que tens ";
	$missatge .= "plaça disponible en el curs en línia <strong>".$titolCurs."</strong> ";
	$missatge .= "que es realitza <strong>".$datesRealitzacio."</strong>.</p>";
	$missatge .= $textCursReconegut;
	$missatge .= $textEstudiant;
	$missatge .= $textHasRealitzatCurs;
	$missatge .= $textPagament;
	$missatge .= $textIniciCurs;
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
	$msgInsc .= "<p><strong>Com has conegut aquest curs?:</strong> ".$conegut."</p>";
	$msgInsc .= "<p><strong>Preu:</strong> ".$preuDescompte." euros</p>";
	$msgInsc .= "<p><strong>IDPAG:</strong> ".$idPag."</p>";
	$msgInsc .= "<p><strong>URL pagament:</strong> ".$urlIdPag."</p>";
	if ($mailing=='Registred') $constMailing = 'Ja està subscrit';
	else if ($mailing=='Yes') $constMailing = 'Sí';
	else $constMailing = 'No';
	$msgInsc .= "<p><strong>Consentiment mailing:</strong> ".$constMailing."</p>";
	$msgInsc .= "<p><strong>Comentaris:</strong> ".$comentaris."</p>";
	if ($tipusDescompte==2) $msgInsc .= '<p>Carnet Jove</p>';
	$msgInsc .= $pagamentFraccionat;

	$subjectMailInsc = "Inscripció ".$codiCurs." ".$edicio." - ".$documentacio;
	if ($comentaris != '') $subjectMailInsc .= " + O";

	$mailInscripcions = new Mail();
	$mailInscripcions->addHeaders('Secretaria PrisMa', 'inscripcions@prisma.cat', $email);
	$mailInscripcions->addSubject($subjectMailInsc);
	// $mailInscripcions->addTo('inscripcions@prisma.cat');
	$mailInscripcions->addTo('suport.informatic@prisma.cat');
	$mailInscripcions->addMissatge($msgInsc);
	$mailInscripcions->sendMessage();

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

	$subject = "Inscripció al curs ".$titolCurs;

	$nomFromHead = 'PrisMa Secretaria';
	$correuFromHead = 'secretaria@prisma.cat';
	$nomReplyHead = 'PrisMa Secretaria';
	$correuReplyHead = 'secretaria@prisma.cat';
	$nomTo = $nomCognoms;
	$nomTo = 'PrisMa Inscripcions';
	// $correuTo = 'inscripcions@prisma.cat';
	$correuTo = 'suport.informatic@prisma.cat';

	$mailAlumne = new MailSMTP($username, $password, $nomFromHead, $correuFromHead,
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

	$conegutBD = $textConegut->obtenirText();
	$edicioBD = $textEdicio->obtenirText();
	$codiCursBD = $textCodiCurs->obtenirText();
	$titolCursBD = $textTitolCurs->obtenirText();
	$pagFracBD = $textPagFrac->obtenirText();

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

	$currentDate = 'CURRENT_DATE';
	$carnetJove = '';
	if ($tipusDescompte == 2) $carnetJove = 'Carnet Jove';
	$perenne = 'X';

	$insertBD = "INSERT INTO inscripcions (ANY, MES, CURS, DATA_INSC, NOM, COGNOMS,
					 CORREU, DNI, ADRECA, Codi_Postal, POBLACIO, PERFIL, TITULACIO,
					 TELEFON, OBSERVACIONS, COMENTARIS, FRACCIONAT, INSC_MAILING,
					 A_PAGAR, USUARI, IDPAG, PERENNE, CONEGUT)
					 VALUES (?,?,?,CURRENT_TIME,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
	$stmt=$connexio->prepare($insertBD);
	$stmt->bind_param("dsssssssssssdssdsdddss", $any, $edicioBD, $codiCursBD, $nomBD, $cogBD, $emailBD, $documentacioBD, $adrecaBD, $codiPostalBD, $poblacioBD,	$perfilsBD, $titulacionsBD, $telfBD, $carnetJove, $comentarisBD, $pagFraccBD, $mailingBD, $preuDescompte, $usuariBD, $idPag, $perenne, $conegutBD);
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

	$mailInscripcions2 = new Mail();
	$mailInscripcions2->addHeaders('Secretaria PrisMa', 'secretaria@prisma.cat', $email);
	$mailInscripcions2->addSubject($subjectMailInsc);
	// $mailInscripcions2->addTo('inscripcions.prisma@gmail.com');
	$mailInscripcions2->addTo('suport.informatic@gmail.com');
	$mailInscripcions2->addMissatge($msgInsc);
	$mailInscripcions2->sendMessage();

	if ($mailingBD == '1') {
		$cnsMailing = "SELECT ID FROM mailing WHERE MAIL=?";
		$stmt=$connexio->prepare($cnsMailing);
		$stmt->bind_param("s", $emailBD);
		$stmt->execute();
		$stmt->store_result();
		if ( $stmt->num_rows() <= 0 ) {
			$connexio->closeStmt();

			$insertMailing = "INSERT INTO mailing (mail) VALUES (?)";
			$stmt=$connexio->prepare($insertMailing);
			$stmt->bind_param("s", $emailBD);
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

	$subject = "Inscripció al curs ".$titolCurs;

	$nomFromHead = 'PrisMa Secretaria';
	$correuFromHead = 'secretaria@prisma.cat';
	$nomReplyHead = 'PrisMa Secretaria';
	$correuReplyHead = 'secretaria@prisma.cat';
	$nomTo = $nomCognoms;
	$correuTo = $email;

	$mailAlumne = new MailSMTP($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject, $missatge);

	// $nomTo = 'PrisMa Inscripcions';
	// // $correuTo = 'suport.informatic@prisma.cat';
	// $correuTo = 'inscripcions@prisma.cat';
	// $mailAlumne = new MailSMTP($username, $password, $nomFromHead, $correuFromHead,
	// 									$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
	// 									$subject, $missatge);

	$connexio->desconectarBD();
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
