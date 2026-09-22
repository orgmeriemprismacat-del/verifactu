<?php

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
	$textNovell = new Text($_GET['novell']);
	$novell = 0;
	if ( $_GET['novell'] == 'yes' ) $novell = 1;
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
	$promocioATrobadaplicada = $_GET['promocioATrobadaplicada'];
	$promocioAplicada = $_GET['promocioAplicada'];
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

	$dataInsc = date('d')."-".date('m')."-".date('Y')." ".date('H').":".date('i');

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
		les edicions del curs escolar 2024-2025 al Departament d'Educació. Tan bon
		punt surti la resolució t'avisarem a través del correu electrònic.";
	}

	if ($textPerfil->obtenirText()=='Consulta privada' ||
		 $textPerfil->obtenirText()=='No estic treballant' ||
		 $textPerfil->obtenirText()=='Altres') {
		$textCursReconegut .= "Els nostres cursos compten com a formació permanent del professorat sempre que es realitzin posteriorment a la data d’expedició del títol d’accés a la docència (Magisteri o CAP / Màster en Educació Secundària).";
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
	else if ($tipusDescompte == 1) $textAlumne=' (per ser alumne/a de PrisMa)';
	else if ($tipusDescompte == 2) $textAlumne=" (per ser titular d'un Carnet Jove)";
	else if ($tipusDescompte == 3) $textAlumne=' (per ser alumne/a de PrisMa)';

	if ( $promocioATrobadaplicada != '' ) {
		$textAlumne = ' (per haver utilitzat el codi de descompte '.explode('|', $promocioATrobadaplicada)[0].")";
	}
	if ( $promocioAplicada != '' ) {
		$textAlumne = ' (per haver utilitzat el codi promocional '.explode('|', $promocioAplicada)[0].")";
	}

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
	if ( $tipusDescompte == 0 ) {
		$textManeresPagar.="
			<p style='text-decoration:underline'>Nota: si estàs inscrit en un altre
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
			<p>T'informem que cal efectuar un primer pagament abans de l'inici del curs i
			que després de la finalització d'aquest encara disposaràs d'uns dies
			per acabar de pagar els ".$preuDescompte." euros.</p>";

		$pagamentFraccionat="<p>Pagament fraccionat</p>";
	}
	else {
		$textPagament = "
			<p>Per tal de pagar els <strong>".$preuDescompte." euros</strong> de la matrícula".$textAlumne.",
			pots escollir una de les opcions següents:</p> ".$textManeresPagar;
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
	$dates = $textDates->obtenirText();
	$conegut = $textConegut->obtenirText();
	$preuDescompte = $numPreuDescompte->obtenirNumero();
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
	$missatge = "<p>Benvolgut/da ".$nom.",</p>";
	$missatge .= "<p>Hem rebut la teva sol·licitud i ens plau comunicar-te que ";
	$missatge .= "ja t'hem inscrit en el curs en línia <strong style='color: #496baa'>".$titolCurs."</strong> ";
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
	if ( $tipusCurs != 'S' ) $missatge .= $textPagament;
	else {
		$missatge .= "<p><strong>Curs subvencionat per a docents de centres públics i de centres privats sostinguts amb fons públics, en el marc del Pla de Recuperació, Transformació i Resiliència (PRTR), finançat per la Unió Europea - Next Generation EU.</strong></p>";
	}
	$missatge .= $textIniciCurs;
	$missatge .= $textConsentimentMailing;
	$missatge .= "<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";

	if ( $tipusDescompte == 4 || $tipusDescompte == 5 || $tipusDescompte == 6 || $tipusDescompte == 7 || $tipusDescompte == 8 ) {
		// CAS USOC
		if ( $tipusDescompte == 4 ) {
			$descompteTextCas = "descompte afiliat USOC";
			$textIntroCasDesc = "<p>Hem rebut la teva sol·licitud amb les dades següents i ens hem posat en contacte amb la FEUSOC (la Federació d’Educació de la Unió Sindical Obrera de Catalunya) perquè ens confirmin la teva afiliació al sindicat:</p>";
		}
		else {
			$textIntroCasDesc ="<p>Hem rebut la teva sol·licitud amb les dades següents i
			des de PrisMa validarem els documents que ens has enviat per aplicar els
			descomptes per ser titular d’un ";
			if ( $tipusDescompte == 5 ) {
				$descompteTextCas = "descompte carnet de discapacitat";
				$textIntroCasDesc .= "carnet de discapacitat";
			}
			else if ( $tipusDescompte == 6 ) {
				$descompteTextCas = "descompte carnet de familia nombrosa";
				$textIntroCasDesc .= "carnet de familia nombrosa";
			}
			else if ( $tipusDescompte == 7 ) {
				$descompteTextCas = "descompte carnet de familia monoparental";
				$textIntroCasDesc .= "carnet de familia monoparental";
			}
			else if ( $tipusDescompte == 8 ) {
				$descompteTextCas = "descompte víctima de violència de gènere";
				$textIntroCasDesc .= "víctima de violència de gènere";
			}

			if ( $novell ) $textIntroCasDesc .=  " i per ser docent novell";
			$textIntroCasDesc .= ".</p>";
		}

		$missatge = "<p>Benvolgut/da ".$nom.",</p>";
		$missatge .= $textIntroCasDesc;
		$missatge .= "<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Nom:</strong> ".$nomCognoms."</p>
			<p><strong>NIF/NIE/passaport:</strong> ".$documentacio."</p>
			<p><strong>Correu electrònic:</strong> ".$email."</p>
			<p><strong>Telèfon de contacte:</strong> ".$telf."</p>
			<p><strong>Curs en línia:</strong> ".$titolCurs."</p>
			<p><strong>Dates:</strong> ".$datesRealitzacio."</p>
			<p><strong>Preu:</strong> <span class='text-decoration:line-through; color: #a7a7a7'>".$preuCar."</span> ".$preuDescompte." euros (".$descompteTextCas.")</p>
		</div>";
		$missatge .= "<p>Un cop confirmat, acabarem el procés de reserva de la plaça i t’enviarem les dades per fer el pagament amb el descompte.</p>";
		if ( $novell ) $missatge .= "<p>I tan bon punt hàgim validat el títol de docent i rebut el pagament, t’enviarem un codi per descomptar l’import pagat en el pròxim curs de PrisMa a què et matriculis.";
		$missatge .= "<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";
	}

	if ( $tipusDescompte == 4 ) {
		$msgFeusoc = "<p>
			Ens ha arribat aquesta sol·licitud per fer el curs en línia <strong style='color: #496baa'>".$titolCurs."</strong>
			 que es realitza <strong>".$datesRealitzacio."</strong>  amb les dades personals següents:
		</p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Nom:</strong> ".$nomCognoms."</p>
			<p><strong>NIF/NIE/passaport:</strong> ".$documentacio."</p>
			<p><strong>Correu electrònic:</strong> ".$email."</p>
			<p><strong>Telèfon de contacte:</strong> ".$telf."</p>
		</div>
		<p>Confirma’ns si us plau, si aquesta persona és afiliada per tal de poder aplicar-li el descompte i continuar el procés d’inscripció.</p>
		<p>Atentament,</p>";
	}

	if ( $novell && ( $tipusDescompte == 0 || $tipusDescompte == 1 || $tipusDescompte == 2 || $tipusDescompte == 3 ) ) {

		$missatge = "<p>Benvolgut/da ".$nom.",</p>";
		$missatge .= "<p>Hem rebut la teva sol·licitud amb les dades següents:</p>";
		$missatge .= "<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Nom:</strong> ".$nomCognoms."</p>
			<p><strong>NIF/NIE/passaport:</strong> ".$documentacio."</p>
			<p><strong>Correu electrònic:</strong> ".$email."</p>
			<p><strong>Telèfon de contacte:</strong> ".$telf."</p>
			<p><strong>Curs en línia:</strong> ".$titolCurs."</p>
			<p><strong>Dates:</strong> ".$datesRealitzacio."</p>
			<p><strong>Preu:</strong> ".$preuDescompte." euros</p>
		</div>";
		$missatge .= "<p>Tan bon punt hàgim pogut validar el títol, acabarem el procés de reserva de la plaça i t’enviarem les dades per fer el pagament.</p>";
		$missatge .= "<p>I un cop confirmat el títol i rebut el pagament rebràs un codi de descompte per valor de ".$preuDescompte." euros per al pròxim curs de PrisMa a què et matriculis.</p>";
		$missatge .= "<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";
	}

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
	if ( $tipusCurs == 'S' ) $msgInsc .= "<p><strong>Preu:</strong> Subvencionat</p>";
	if ( $codiCurs == 'JASOM' ) $msgInsc .= "<p><strong>Novell:</strong> ".$textNovell->obtenirText()."</p>";
	else $msgInsc .= "<p><strong>Preu:</strong> ".$preuDescompte." euros</p>";
	if ( $promocioATrobadaplicada != '' )  {
		$msgInsc .= "<p><strong>Codi descompte del ".explode('|', $promocioATrobadaplicada)[1]."% per una trobada:</strong> ".explode('|', $promocioATrobadaplicada)[0]."</p>";
	}
	if ( $promocioAplicada != '' )  {
		if ( explode('|', $promocioAplicada)[2] == 0 )
			$msgInsc .= "<p><strong>Codi promocional d'un ".explode('|', $promocioAplicada)[1]."%:</strong> ".explode('|', $promocioAplicada)[0]."</p>";
		else if ( explode('|', $promocioAplicada)[2] == 1 )
			$msgInsc .= "<p><strong>Codi promocional de ".explode('|', $promocioAplicada)[1]."€ de descompte:</strong> ".explode('|', $promocioAplicada)[0]."</p>";
		else
			$msgInsc .= "<p><strong>Codi promocional amb un preu especial de ".explode('|', $promocioAplicada)[1]."€:</strong> ".explode('|', $promocioAplicada)[0]."</p>";
	}
	if ( $tipusCurs != 'S' ) $msgInsc .= "<p><strong>IDPAG:</strong> ".$idPag."</p>";
	if ( $tipusCurs != 'S' ) $msgInsc .= "<p><strong>URL pagament:</strong> ".$urlIdPag."</p>";
	if ($mailing=='Registred') $constMailing = 'Ja està subscrit';
	else if ($mailing=='Yes') $constMailing = 'Sí';
	else $constMailing = 'No';
	$msgInsc .= "<p><strong>Consentiment mailing:</strong> ".$constMailing."</p>";
	$msgInsc .= "<p><strong>Comentaris:</strong> ".$comentaris."</p>";
	if ($tipusDescompte==2) $msgInsc .= '<p>Carnet Jove</p>';
	else if ($tipusDescompte==4) $msgInsc .= '<p>Carnet USOC</p>';
	else if ($tipusDescompte==5) $msgInsc .= '<p>Carnet de discapacitat</p>';
	else if ($tipusDescompte==6) $msgInsc .= '<p>Carnet de familia nombrosa</p>';
	else if ($tipusDescompte==7) $msgInsc .= '<p>Carnet de familia monoparental</p>';
	if ( $tipusCurs != 'S' ) $msgInsc .= $pagamentFraccionat;

	$subjectMailInsc = "Inscripció ".$codiCurs." ".$edicio." - ".$documentacio;
	if ($comentaris != '' || $tipusDescompte==4 || $tipusDescompte == 5 || $tipusDescompte == 6 || $tipusDescompte == 7  || $tipusDescompte == 8 )
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

	$subject2 = "Inscripció al curs ".$titolCurs." ".$dataInsc;

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
	else if ($tipusDescompte==4) $carnetJove = 'Carnet USOC';
	else if ($tipusDescompte==5) $carnetJove = 'Carnet de discapacitat';
	else if ($tipusDescompte==6) $carnetJove = 'Carnet de familia nombrosa';
	else if ($tipusDescompte==7) $carnetJove = 'Carnet de familia monoparental';
	$perenne = 'X';
	if ( $promocioAplicada != '' ) {
		$codiDescomptePromo = explode('|', $promocioAplicada)[0];
		if ( explode('|', $promocioAplicada)[2] == 0 ) {
			$carnetJove .= 'Promoció '.$codiDescomptePromo." del ".explode('|', $promocioAplicada)[1]."%";
		}
		else if ( explode('|', $promocioAplicada)[2] == 1 ){
			$carnetJove .= 'Promoció '.$codiDescomptePromo." de ".explode('|', $promocioAplicada)[2]."€";
		}
		else {
			$carnetJove .= 'Promoció '.$codiDescomptePromo." amb un preu especial de ".explode('|', $promocioAplicada)[2]."€";
		}
	}

	$tipusDesc=$tipusDescompte;
	$validDesc=1;
	if ( $tipusDescompte == 4 || $tipusDescompte == 5 || $tipusDescompte == 6 || $tipusDescompte == 7 || $tipusDescompte == 8 )
		$validDesc = 0;

	if ( $tipusCurs == 'S' ) $preuDescompte = 0;

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

	if ( $codiCursBD == 'JASOM' ) {
		$insertBD2 = "INSERT INTO recent_titulat (ID_INSC) VALUES (?)";
		$stmt2=$connexio->prepare($insertBD2);
		$stmt2->bind_param("d", $idInserit);
		$stmt2->execute();
		$stmt2->fetch();
		$connexio->closeStmt();
	}

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

	if ( $promocioAplicada != '' ) {
		$updPromo = "UPDATE promocions SET USED = 1 WHERE CODI_DESCOMPTE = ?";
		$stmt=$connexio->prepare($updPromo);
		$stmt->bind_param("s", $codiDescomptePromo);
		$codiDescomptePromo = explode('|', $promocioAplicada)[0];
		$stmt->execute();
		$connexio->closeStmt();
	}

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

		$msgFeusoc = "<p>Hola ".$nomSrFeusoc.",</p>".$msgFeusoc;
		$subjectFeusoc = "PrisMa. Inscripció al curs ".$titolCurs." ".$dataInsc;

		$nomTo = $nomCompletSrFeusoc;
		$correuTo = $mailFeusoc;
		$nomReplyHeadFeu = "Secretaria PrisMa";
		$correuReplyHeadFeu = "secretaria@prisma.cat";
		// $correuTo = 'meriem.prisma.cat@gmail.com';
		$mailFeu = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
											$nomReplyHeadFeu, $correuReplyHeadFeu, $nomTo, $correuTo,
											$subjectFeusoc, $msgFeusoc);
	}

	$connexio->closeStmt();


	$subject = "Inscripció al curs ".$titolCurs;
	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";
	$subject2 = "Inscripció al curs ".$titolCurs." ".$dataInsc;
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
