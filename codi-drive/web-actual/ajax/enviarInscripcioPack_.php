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

	$cnsInfoOrig = "SELECT DATAI, DATAF, CURS_ESCOLAR, FISS, GTAF, DATA_RESOL,
		ANY, MES, c.CURS, p.ID_CURS, HORES, i.ID, i.ID_AMIGABLE, c.ESTAT
		FROM packs AS p INNER JOIN info_pack AS ip ON p.ID_PACK = ip.ID_PACK INNER
		JOIN curs AS c ON p.ID_CURS = c.ID_CURS INNER JOIN informacio AS i ON
		c.CURS = i.CODI_CURS INNER JOIN aula AS a ON c.ID_AULA=a.ID_AULA INNER JOIN
		rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO INNER JOIN honoraris AS h ON r.ID_HONO=h.ID
		WHERE ip.ID_PACK = ? AND p.PUBLIC=1 AND c.PUBLIC=1 AND c.CURS!='PROVA' AND
		c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1 AND i.ESTAT = 1
		AND c.CURS NOT LIKE '%JOR%' AND (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC') OR
		(a.ID_CUHO!=17 AND h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor' AND
		ORDRE_TUTOR is not NULL)) ORDER BY c.DATAI";
	if ( $stmt = $connexio->prepare($cnsInfoOrig) ) {
		$stmt->bind_param("d", $idPack);
		$stmt->execute();
		$stmt->bind_result($dataiEd, $datafEd, $cursEscEd, $fissEd, $gtafEd,
			$dataResEd, $anyEd, $mesEd, $cursEd, $idCursEd, $horesEd, $idInfoEd, $idUrl, $estatEd);
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

	$datai = $edicions[0]->obtenirDataInici()->obtenirText();
	$dataf = $edicions[count($edicions)-1]->obtenirDataFi()->obtenirText();

	$objDataF = new Text($dataf);
	$dataFiLlarga = $objDataF->convertirDataLlarga();

	/* ######################################################################### */
	$textCursReconegut = "<p>Aquests cursos estan reconeguts pel Departament d'Educació
	de la Generalitat de Catalunya.</p>";

	// if ($data_resol==null or $data_resol=='') {
	// 	$textCursReconegut = "<p>Aquest curs té una durada lectiva de <strong>";
	// 	$textCursReconegut .= $hores." hores</strong>.<p>
	// 	<p>PrisMa, com a entitat organitzadora, ha sol·licitat el reconeixement de
	// 	les edicions del curs escolar 2020-2021 al Departament d'Educació. Tan bon
	// 	punt surti la resolució t'avisarem a través del correu electrònic.";
	// }

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

	$textManeresPagar ="
		<ul style='list-style: square; margin-bottom: 20px; margin-left: 30px; padding: 0;'>
			<li style='margin-top: 8px; line-height: 24px;'>
				TARGETA: clica a l'enllaç següent (cal que tinguis activat el codi
				de compra segura facilitat per la teva entitat bancària):
				<a style='color: #496BAA; text-decoration: none; font-weight: bold;'
				 href='$urlIdPag' title='Pagament del pack ".$titolPack."'>".$urlIdPag."</a>.
			</li>
			<li style='margin-top: 8px; line-height: 24px;'>
				TRANSFERÈNCIA o INGRÉS BANCARI: indica clarament el concepte
				<strong>«P".$textIdPack->obtenirText()." + el número
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
			<strong>".$preuPack." euros</strong> (quantitats i terminis) i que
			efectuís tots els ingressos escollint una de les opcions següents: </p>";
		$textPagament .= $textManeresPagar;
		$textPagament .= "
			<p>T'informem que cal efectuar un primer pagament a l'inici del curs i
			que després de la finalització de l’últim dels cursos del <em>pack</em> (".$dataFiLlarga.") encara
			disposaràs d'una setmana per acabar de pagar els <strong>".$preuPack." euros</strong>.</p>";

		$pagamentFraccionat="<p>Pagament fraccionat</p>";
	}
	else {
		$textPagament = "
			<p>Per tal de pagar els <span style='text-decoration: line-through; color: #A7A7A7;'>
			".$preuCursos." euros</span><strong> ".$preuPack." euros</strong> de la matrícula".$textAlumne.",
			pots escollir una de les opcions següents:</p> ".$textManeresPagar;
	}

	/* ######################################################################### */
	//Busquem la data d'inici del primer curs

	if ( $datai <= date('Y-m-d') ) { /*ha començat el curs*/
		$textIniciCurs = "<p>En un període de 24 hores laborals podràs accedir al primer
		curs amb les teves claus.</p>";
		if ( !$esAlumne0 )
			$textIniciCurs = "<p>En un període de 24 hores laborals rebràs un correu
			electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
	}
	else {
		$textIniciCurs = "<p>Uns dies abans de l'inici del curs podràs accedir al primer
		l'apartat general de l'aula amb les teves claus.</p>";
		if ( !$esAlumne0 )
			$textIniciCurs = "<p>Uns dies abans de l'inici del primer curs rebràs un correu
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
	//
	// $titolCurs = $textTitolCurs->obtenirText();
	$conegut = $textConegut->obtenirText();
	// $preuDescompte = $numPreuDescompte->obtenirNumero();
	// $edicio = $textEdicio->obtenirText();
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
	$datesRealitzacioCursos = $textDates;

	/* ######################################################################### */
	$missatge = "<p>Benvolgut/da ".$nom.",</p>";
	$missatge .= "<p>Hem rebut la teva sol·licitud i ens plau comunicar-te que ";
	$missatge .= "ja t'hem inscrit en el <em>pack</em> <strong>".$titolCurs."</strong> ";
	$missatge .= "que inclou els cursos següents <strong>".$datesRealitzacioCursos."</strong> amb les dades personals següents:</p>";
	$missatge .= "<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
		<p><strong>Nom:</strong> ".$nomCognoms."</p>
		<p><strong>NIF/NIE/passaport:</strong> ".$documentacio."</p>
		<p><strong>Correu electrònic:</strong> ".$email."</p>
		<p><strong>Telèfon de contacte:</strong> ".$telf."</p>
		<p><strong>Adreça:</strong> ".$adreca." - ".$codiPostal." ".$poblacio."</p>
	</div>";
	$missatge .= $textCursReconegut;
	$missatge .= $textEstudiant;
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
	$msgInsc .= "<p><strong>Pack:</strong> ".$titolPack."</p>";
	$msgInsc .= "<p><strong>Dates:</strong></p>".$datesRealitzacioCursos."";
	$msgInsc .= "<p><strong>Com has conegut aquest curs?:</strong> ".$conegut."</p>";
	$msgInsc .= "<p><strong>Preu:</strong> ".$preuPack." euros</p>";
	$msgInsc .= "<p><strong>IDPAG:</strong> ".$idPag."</p>";
	$msgInsc .= "<p><strong>URL pagament:</strong> ".$urlIdPag."</p>";
	if ($mailing=='Registred') $constMailing = 'Ja està subscrit';
	else if ($mailing=='Yes') $constMailing = 'Sí';
	else $constMailing = 'No';
	$msgInsc .= "<p><strong>Consentiment mailing:</strong> ".$constMailing."</p>";
	$msgInsc .= "<p><strong>Comentaris:</strong> ".$comentaris."</p>";
	$msgInsc .= $pagamentFraccionat;

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

	$mailCopiaInsc = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);

	$nomFromHead = 'Secretaria PrisMa';
	$correuFromHead = 'secretaria@prisma.cat';
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";

	$subject2 = "Inscripció al pack ".$titolPack." ".$dataInsc;

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

	$mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subject2, $missatge);

	$nomTo = $nomCognoms;
	$correuTo = $email;
	// $correuTo = "suport.informatic@prisma.cat";

	$mailAlumne = new MailSMTP($username, $password, $nomFromHead, $correuFromHead,
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
