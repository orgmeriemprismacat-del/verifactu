<?php
	include("./ConnexioBBDD_PreparedStatment.php");
	include("./inc/apiRedsys.php");
	include("./Text.php");
	include("./Curs.php");
	include("./MailSMTPComvive.php");
	include("./MailSMTP.php");
	include("./Mail.php");

	$idPag = $_GET['idPag'];

	$order = $_GET['order'];

	$cursPag = $_GET['codiCurs'];
	$dniTitularPag = $_GET['dni'];
	$importPag = floatval($_GET['import']);
	$frac = intval($_GET['frac']);

	/* obtenir dades de facturació */
	$dadesFact = [];
	$dadesFact["dni"] = "";
	$dadesFact["email"] = "";
	$dadesFact["name"] = "";
	$dadesFact["cog"] = "";
	$dadesFact["direccio"] = "";
	$dadesFact["cp"] = "";
	$dadesFact["poblacio"] = "";

	include('inc/analitics.html');

	$nomMe = 'Meriem';
	$correuMe = "meriem.prisma.cat@gmail.com";
	$subjectMe = "pagament automatic ".$order;
	$missatge = "<p>DNI: ".$dniTitularPag."</p>
	<p>IMPORT: ".$importPag."</p>
	<p>FRAC: ".$frac."</p>
	<p>IDPAG: ".$idPag."</p>
	<p>ORDER: ".$order."</p>";
	$mailMe = new Mail();
	$mailMe->addHeaders($nomMe, $correuMe, $correuMe);
	$mailMe->addSubject($subjectMe);
	$mailMe->addTo($correuMe);
	$mailMe->addMissatgeTiquet("<p>Hola</p>", $missatge, '');
	$mailMe->sendMessage();

	/* enviem intent de pagament */

	try {
		// Se crea Objeto
		$miObj = new RedsysAPI;

		$version = $_POST["Ds_SignatureVersion"];
		$datos = $_POST["Ds_MerchantParameters"];
		$signatureRecibida = $_POST["Ds_Signature"];

		$decodec = $miObj->decodeMerchantParameters($datos);
		$kc = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; //Clave recuperada de CANALES
		$firma = $miObj->createMerchantSignatureNotif($kc,$datos);

	  $ordre = $miObj->getParameter('Ds_Order');
		$dateComanda = $miObj->getParameter('Ds_Date');
		$horaComanda = $miObj->getParameter('Ds_Hour');
		$preu = $miObj->getParameter('Ds_Amount');
	  $codiResposta = $miObj->getParameter("Ds_Response");

		if (intval($codiResposta)>=0 && intval($codiResposta)<=99) {
			$tipusError =  "Transacció autoritzada per a pagaments i preautoritzacions";

			require_once 'ConnexioBBDD_PreparedStatment.php';
			$connexio = new ConnexioBBDDSTMT();
			$connexio->connectarBD();

			/* #################### Enviem el missatge a Gestió ####################  */

			/* Busquem les dades de la inscripció a partar de l'id de pagament */
			$cnsInsc = "SELECT ID, ANY, MES, CURS, NOM, COGNOMS, DNI, CORREU, ADRECA, Codi_Postal,
			Poblacio, FACTURA_RELACIONADA, A_PAGAR, `INSC CURS`, PAGAMENT, FRACCIO
			FROM inscripcions WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1' OR `INSC CURS`='M')";
			if ( $stmtRegal=$connexio->prepare($cnsInsc) ) {
				$stmtRegal->bind_param("d", $idPag);
				$stmtRegal->execute();
				$stmtRegal->bind_result($idInsc, $any, $mes, $codiCurs, $nom, $cognoms, $dni,
				$email, $adreca, $cp, $poble, $factRel, $apagar, $inscrit, $importPagat, $fraccio);
				$stmtRegal->fetch();
				$connexio->closeStmt();
			}
			else {
				throw new Exception('',2000);

			}

			$cnsCurs = "SELECT NOM_CURS, DATAI, DATAF, HORES FROM curs WHERE ANY=? AND MES=? AND CURS=?";
			if ( $stmtRegal=$connexio->prepare($cnsCurs) ) {
				$stmtRegal->bind_param("dss", $any, $mes, $codiCurs);
				$stmtRegal->execute();
				$stmtRegal->bind_result($titol, $datai, $dataf, $hores);
				$stmtRegal->fetch();
				$connexio->closeStmt();
			}
			else {
				throw new Exception('',2001);
			}

			/* Calculem la data de pagament de la comanda */
			$textDataPag = new Text($dateComanda);
			$dataPagRevert = $textDataPag->replace('%2F','-');
			$vectDataPagRevert = explode('/', $dataPagRevert);
			$dataPag = $vectDataPagRevert[2]."-".$vectDataPagRevert[1]."-".$vectDataPagRevert[0];

			$horaComanda .= ":00";

			$dataPagCompleta = $dataPag." ".$horaComanda;
			$dataPagNewFormat = $dateComanda." ".$horaComanda;

			/* Preparem les dades del missatge que enviarem */
			$nomFrom = "Gestió PrisMa";
			$correuFrom = "gestio@prisma.cat";
			$correuReply = "gestio@prisma.cat";
			$to = "gestio@prisma.cat";
			// $to = "suport.informatic@prisma.cat";

			$subject = "Pagament TPV: ".$dni." | ".$codiCurs." | ".$order." | ".$dataPag;

			$missPreDiv = "<p>Dades del pagament:</p>";

			$missatge = "<p><strong>Nom Alumne:</strong> ".$nom." ".$cognoms."</p>
			<p><strong>DNI: </strong> ".$dni."</p>
			<p><strong>Correu electrònic: </strong> ".$email."</p>
			<p><strong>N. de comanda: </strong> ".$order."</p>
			<p><strong>Data i hora: </strong> ".$dataPagNewFormat."</p>
			<p><strong>Concepte: </strong> ".$dniTitularPag." | ".$titol."</p>
			<p><strong>Import: </strong> ".$importPag." €</p>
			<p><strong>Resultat: </strong> ".$codiResposta."</p>";

			$fracc = intval($frac);
			$pendentPagar = 0;
			$pagats = floatval($importPagat)+floatval($importPag);

			if ( floatval($apagar) > $pagats ) {
				$pendentPagar = floatval($apagar) - $pagats;
				$missatge .= "<p><strong>Pendent de pagar: </strong> ".$pendentPagar." de ".$apagar."</p>";
			}
			if ( floatval($apagar) > floatval($importPag) ) {
				$frac = 1;
			}

			/* Enviem el missatge */
			$mailGestio = new Mail();
			$mailGestio->addHeaders($nomFrom, $correuFrom, $correuReply);
			$mailGestio->addSubject($subject);
			$mailGestio->addTo($to);
			$mailGestio->addMissatgeTiquet($missPreDiv, $missatge, '');
			$mailGestio->sendMessage();

			/* Enviem el missatge */
			$mailSuport = new Mail();
			$mailSuport->addHeaders($nomFrom, $correuFrom, $correuReply);
			$mailSuport->addSubject($subject);
			$mailSuport->addTo("suport.informatic@prisma.cat");
			$mailSuport->addMissatgeTiquet($missPreDiv, $missatge, '');
			$mailSuport->sendMessage();

			/* ######################## Generem la factura ######################## */

			/* Calculem el número de la factura relacionada */
			if ( $factRel == null or $factRel == '' ) { //l'alumne no ha pagat res
				$cnsFactRel = "SELECT factura_relacionada FROM factures
					ORDER BY factura_relacionada DESC LIMIT 1";
				if ( $stmtFactRel=$connexio->prepare($cnsFactRel) ) {
					$stmtFactRel->execute();
					$stmtFactRel->bind_result($lastFactRel);
					$stmtFactRel->store_result();
					if ( $stmtFactRel->num_rows() > 0 ) {
						$stmtFactRel->fetch();
						$factura = intval($lastFactRel)+1;
					}
					else {
						$factura = 1;
					}
					$connexio->closeStmt();
				}
				else {
					throw new Exception('',2002);
				}
			}
			else { //l'alumne havia pagat alguna cosa
				$factura = $factRel;
			}

			/* Calculem l'any fiscal */
			$anyFiscal = date('Y');

			/* Calculem l'ordre de la factura */
			$cnsOrdre = "SELECT ordre FROM factures WHERE ANY=? AND TIPUS=?
			ORDER BY any DESC, ordre DESC LIMIT 1";
			if ( $stmtOrdre=$connexio->prepare($cnsOrdre) ) {
				$stmtOrdre->bind_param("ds", $anyFiscal, $tipusFact);
				$tipusFact = "A";
				$stmtOrdre->execute();
				$stmtOrdre->bind_result($lastOrdreFact);
				$stmtOrdre->store_result();
				if ( $stmtOrdre->num_rows() > 0 ) {
					$stmtOrdre->fetch();
					$ordreFact = intval($lastOrdreFact)+1;
				}
				else {
					$ordreFact = 1;
				}
				$connexio->closeStmt();
			}
			else {
				throw new Exception('',2003);
			}

			/* Calculem el número  de la factura */

			$numFact = "A".$anyFiscal."/".$ordreFact;

			/* Buscquem la data actual de la factura' */

			$dataActual = date('Y')."-".date('m')."-".date('d')." ".date('H').":".date('i').":".date('s');

			/* Calculem la rao de la factura */
			$rao = $nom." ".$cognoms;
			$cif = $dni;

			/* Calculem el concepte 1 i el concepte 2 de la factura */
			/* Obtinc el mes en lletres */
			$objMes = new Text($mes);
			$mesLletra = $objMes->obtenirMesLlarg();

			$textFactura = "";
			if ( $frac == 1 ) {
				$textFactura=". Pagament fraccionat";
			}

			/* Obtinc la data d'inici i la data de fi en lletres */
			$objDataI = new Text($datai);
			$dataIniciLlarga = $objDataI->convertirDataLlarga();
			$objDataF = new Text($dataf);
			$dataFiLlarga = $objDataF->convertirDataLlarga();

			if ( ctype_alpha($codiCurs) ) { //Si el codi curs no té numeros es tracta d'un curs
				$concepte1 = "Curs ".$titol;
				$concepte2 = "Convocatòria ".$mesLletra." ".$any.$textFactura;
			}
			else { //Si el codi curs té numeros es tracta d'una jornada
				$concepte1 = $titol;
				$concepte2 = "Convocatòria ".$dataIniciLlarga;
			}

			/* Calculem el valor de l'entitat i la forma de pagament de la factura */
			$entitat = "Asso";
			$formaPagament = "TPV";

			/* afegir la factura a FACTURES */
			$insertBD = "INSERT INTO factures (factura_relacionada, tipus, ANY, ORDRE, NUM, DATA,
				data_pagament, num_comanda, RAO, CIF, ADRECA, CP, POBLACIO, CONCEPTE1,
				CONCEPTE2, IMPORT, ENTITAT, FORMA_PAGAMENT, CURS, HORES)
				VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
			$stmtInsert=$connexio->prepare($insertBD);
			$stmtInsert->bind_param("dsddsssdsssssssdsssd",
				$factura, $tipusFact, $anyFiscal, $ordreFact, $numFact, $dataActual, $dataPagCompleta, $order,
				$rao, $cif, $adreca, $cp, $poble, $concepte1, $concepte2,
				$importPag, $entitat, $formaPagament, $codiCurs, $hores);
			$tipusFact = "A";

			$stmtInsert->execute();
			$idInserit = $connexio->lastInsertId();
			$stmtInsert->fetch();
			$connexio->closeStmt();

			/* actualitzar el registre d'inscripcions */
			if ($factRel==null or $factRel=='') //No ha pagat
			{
				//actualitzar el registre d'inscripcions amb la factura i el pagament utilitza
				$updateBD = "UPDATE inscripcions SET PAGAMENT=?, FACTURA_RELACIONADA=? WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1' OR `INSC CURS`='M')";
				if ( $stmtUpdate=$connexio->prepare($updateBD) ) {
					$stmtUpdate->bind_param("dds", $pagats, $factura, $idPag);
					$stmtUpdate->execute();
					$stmtUpdate->fetch();
					$connexio->closeStmt();
				}
				else {
					throw new Exception('',2004);
				}
			}
			else { // ja havia fet un pagament anterior
				//actualitzar el registre d'inscripcions amb la factura i el pagament utilitza
				$updateBD = "UPDATE inscripcions SET PAGAMENT=? WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1' OR `INSC CURS`='M')";
				if ( $stmtUpdate=$connexio->prepare($updateBD) ) {
					$stmtUpdate->bind_param("dd", $pagats, $idPag);
					$stmtUpdate->execute();
					$stmtUpdate->fetch();
					$connexio->closeStmt();
				}
				else {
					throw new Exception('',2005);
				}
			}

			if ( floatval($pagats) >= floatval($apagar) ) {
				$updateBD = "UPDATE inscripcions SET `DATA PAG`=? WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1' OR `INSC CURS`='M')";
				if ( $stmtUpdate=$connexio->prepare($updateBD) ) {
					$stmtUpdate->bind_param("sd", $dataPagCompleta, $idPag);
					$stmtUpdate->execute();
					$stmtUpdate->fetch();
				}
				else {
					throw new Exception('',2006);
				}
			}

			if ( $fracc==1 ) {
				if ( $fraccio!=null && $fraccio!='' )
					$fraccio.="\n";
				$fraccio .= "(".$importPag." € ".$dataPagNewFormat.")";
				$updFraccBD = "UPDATE inscripcions SET FRACCIO=? WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1' OR `INSC CURS`='M')";
				if ( $stmtUpdate=$connexio->prepare($updFraccBD) ) {
					$stmtUpdate->bind_param("sd", $fraccio, $idPag);
					$stmtUpdate->execute();
					$stmtUpdate->fetch();
				}
				else {
					throw new Exception('',2014);
				}
			}

			/* ##################### Enviem missatge a l'alumne #################### */

			/* Calculem la url de pagament */
			/* busco el username i el password d'autentificació de prisma */
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
				throw new Exception('',2007);
			}

			$cipher = "AES-128-CBC";

			$ivlen = openssl_cipher_iv_length($cipher);
			$iv = openssl_random_pseudo_bytes($ivlen);
			$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
			$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
			$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

			$urlPagament = "https://www.prisma.cat/pagament/".$hashIdPag;

			/* Calculem la diferència en dies entre la data d'avui i la data d'inici.
			Si la diferència es positiva vol dir que la data d'inici  és posterior a l'actual */
			$date1 = new DateTime("now");
			$date2 = new DateTime($datai);
			$diffInici = intval($date1->diff($date2)->days);
			$signe = $date1->diff($date2)->format('%R');
			if ($signe == "-") $diffInici = 0 - $diffInici;

			if ( $diffInici > 0 ) $cursHaComencat = false;
			else $cursHaComencat = true;

			/* Calculem la diferència en dies entre la data d'avui i la data de fi.
			Si la diferència es positiva vol dir que la data de fi és posterior a l'actual */
			$date1 = new DateTime("now");
			$date2 = new DateTime($dataf);
			$diffFi = intval($date1->diff($date2)->days);
			$signe = $date1->diff($date2)->format('%R');
			if ($signe == "-") $diffFi = 0 - $diffFi;

			if ( $diffFi >= 0 ) $cursHaAcabat = false;
			else $cursHaAcabat = true;

			$inscripcioOberta = false;

			if ( !$cursHaAcabat ) {
				$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
								 AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP) AND VALOR LIKE ?";
				if ( $stmt=$connexio->prepare($cnsParam) ) {
					$stmt->bind_param("ss", $tipusParam, $valorParam);
					$tipusParam='dies-inscriu-cursos';
					$valorParam=$hores.'%';
					$stmt->execute();
					$stmt->bind_result($valor);
					$stmt->fetch();
					$connexio->closeStmt();

					$diesOberts = explode('|',$valor)[1];

					if ( !$cursHaComencat || ( $cursHaComencat && $diffInici > 0-intval($diesOberts) && $diffInici <=0 ) )
						$inscripcioOberta = true;
				}
				else {
					throw new Exception('',2009);
				}
			}

			/* Calculem els texts del correu que enviarem a l'alumne */
			$textPendentPagar1="";
			$textPendentPagar2="";
			$textPagamentFraccionat="";
			$textDadesAccesCampus="";
			$textCursNoHaComencat="";

			if ( $frac == 1 )  {
				if ( $pendentPagar > 0 ) {
					$textPagamentFraccionat = "A data d'avui estan pagats <strong>".$pagats." €</strong> del total del curs que són <strong>".$apagar." €</strong>.";
					$textPendentPagar1 = "<p><strong>Pendent de pagar:</strong> ".$pendentPagar." € de ".$apagar."€</p>";
					$textPendentPagar2 = "<p>Et recordem l'enllaç per fer els pagaments posteriors: <a href='$urlPagament' title='Pagament del curs ".$titol."'>".$urlPagament."</a></p>";
				}
				else {
					$textPagamentFraccionat = "A data d'avui el curs està pagat en la seva totalitat.";
				}
			}

			if ( $cursHaComencat && $inscrit == '0' ) {
				$textCursNoHaComencat = "<p>En menys de 24 hores laborables podeu accedir al curs.</p>";
			}

			if ( !$cursHaComencat || ($cursHaComencat && $inscripcioOberta) ) {
				if (!$cursHaComencat)
					$textRebut = "rebreu";
				else
					$textRebut = "heu rebut";
				$textDadesAccesCampus = "<p>Els alumnes que:</p>
					<ul style='list-style: square; margin-bottom: 20px; margin-left: 30px; padding: 0;'>
						<li style='margin-top: 8px; line-height: 24px;'><strong>Feu per primera vegada
						un curs amb nosaltres</strong>, ".$textRebut." un correu electrònic amb el vostre
						nom d'usuari i contrasenya per accedir al campus virtual.</li>
						<li style='margin-top: 8px; line-height: 24px;'><strong>Ja havíeu fet algun
						curs amb nosaltres</strong>, heu d'utilitzar el nom i la contrasenya que
						ja teniu. En cas que no els recordeu, podeu clicar <a target='_blank'
						href='https://campus.prisma.cat/login/forgot_password.php'
						itle='Has oblidat la contrasenya?'>aquí</a> per	demanar-nos-els.</li>
					</ul>
					<p>Quan entris al campus amb el teu <strong>usuari</strong> i
					<strong>contrasenya</strong>, trobaràs
					el curs al bloc «Els meus cursos».</p>";
			}

			if ( !$cursHaComencat ) {
				$textDadesAccesCampus .= "<p>Et recordem que aquest espai no estarà
				plenament actiu fins a la data d'inici marcada en el calendari del curs,
				que serà el dia <strong>".$dataIniciLlarga."</strong>.</p>
				<p>Uns dies abans de l'inici del curs ja podràs entrar a l'aula per
				visualitzar el material de lectura.</p>";
			}

			/* busco el username i el password d'autentificació de prisma */
			$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
							 AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
			if ( $stmt=$connexio->prepare($cnsParam) ) {
				$stmt->bind_param("s", $tipusParam);
				$tipusParam = 'autentificacioPagament';
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
				throw new Exception('',2008);
			}

			$textPromoNovell = '';
			if ( $pendentPagar == 0 && $cursPag == 'JASOM') {

				//mirem si la titulació està validada
				$validPromo = 0;
				$cnsPromo = "SELECT ID FROM recent_titulat WHERE ID_INSC = ? AND VALIDAT = 1";
				if ( $stmtPromo=$connexio->prepare($cnsPromo) ) {
					$stmtPromo->bind_param("d", $idInsc);
					$stmtPromo->execute();
					$stmtPromo->store_result();
					if ( $stmtPromo->num_rows() > 0 ) $validPromo = 1;
					$connexio->closeStmt();
				}
				else {
					throw new Exception('', 2024120401 );
				}

				if ( $validPromo == 1 ) {
					//busquem el codi
					$cnsPromo = "SELECT CODI_DESCOMPTE FROM promocions WHERE CODI_DESCOMPTE LIKE '%MACABODETITULAR#%' AND DNI = ? ORDER BY CODI_DESCOMPTE DESC LIMIT 1";
					if ( $stmtPromo=$connexio->prepare($cnsPromo) ) {
						$stmtPromo->bind_param("s", $dni);
						$stmtPromo->execute();
						$stmtPromo->bind_result($lastCodePromo);
						$stmtPromo->fetch();
						$connexio->closeStmt();
					}
					else {
						throw new Exception('', 2024040901 );
					}

					//CREAR NUMERO ONLY
					// CREAR REGISTRE CODI DESCOMPTE
					// $codiPromoJul = "MACABODETITULAR#".(intval(explode('#', $lastCodePromo)[1]) + 1);
					$codiPromoJul = $lastCodePromo;

					$textDataPag = new Text($dateComanda);
					$dataPagRevert = $textDataPag->replace('%2F','-');
					$vectDataPagRevert = explode('/', $dataPagRevert);
					$dataPag = $vectDataPagRevert[0]."-".$vectDataPagRevert[1]."-".(intval($vectDataPagRevert[2])+1);

					$objDataPagament = new Text($dataPag);
					$dataDataPagament = $objDataPagament->convertirDataLlarga();

					$textPromoNovell .= "<p><strong>Atenció!</strong> Per facilitar-te l’entrada al món educatiu, t’oferim <strong>".$apagar." € de descompte</strong> en la matrícula de qualsevol dels nostres cursos! Per utilitzar el descompte, només cal que introdueixis el <strong>codi*</strong> <strong style='color: #496baa'>MACABODETITULAR#1103</strong> en el camp <strong>«Codi promocional»</strong> de la inscripció del curs a realitzar.</p>
					<p>*Codi personal i intransferible. Un sol ús. Vàlid fins al ".$dataDataPagament."</p>";
					$textPromoNovell .= "</div>";
				}
			}

			$missatge = "<p>Benvolgut/da ".$nom.",</p>

			<p>Hem rebut correctament el pagament de la matrícula del curs <strong style='color: #496baa'>".$titol."</strong> que es
			realitza del dia <strong>".$dataIniciLlarga."</strong> al dia <strong>".$dataFiLlarga."</strong>. ".$textPagamentFraccionat."</p>

			<div style='background-color: #e8ecf5; border: 1px solid #D7DEEE; border-radius: 2px; padding: 5px 25px; margin-bottom: 20px'>
			<p><strong>N. de comanda:</strong> ".$ordre."</p>
			<p><strong>Concepte:</strong> ".$dniTitularPag." | ".$titol."</p>
			<p><strong>Correu electrònic: </strong> ".$email."</p>
			<p><strong>Import:</strong> ".$importPag." €</p>
			<p><strong>Resultat:</strong> Acceptat</p>
			<p><strong>Data i hora:</strong> ".$dataPagNewFormat."</p>
			".$textPendentPagar1."
			</div>
			".$textPendentPagar2."

			".$textCursNoHaComencat.$textDadesAccesCampus."

			".$textPromoNovell."

			<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

			<p>Salutacions ben cordials,</p>";

			$subject = "Confirmació matrícula ".$titol." | ".$ordre." | ".$dataPag;

			/* ################## Enviar confirmació a PrisMa ################## */
			$nomFromHead = $nameUser;
			$correuFromHead = $username;
			$nomReplyHead = $nameUser;
			$correuReplyHead = $username;

			$nomTo = $nameUser;
			$correuTo = 'resguard.gestio@prisma.cat';
			// $correuTo = 'suport.informatic@prisma.cat';
			$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
												$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
												$subject, $missatge);

			/* ################## Enviar confirmació a l'alumne ################## */
			$nomTo = $nom;
			$correuTo = $email;

			//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
			$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
												$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
												$subject, $missatge);

			/* ##################            Morosos            ################## */
			if ( $inscrit == 'M' && $pendentPagar <= 0 ) {
				$missatge = "
				<div style='background-color: #e8ecf5; border: 1px solid #D7DEEE; border-radius: 2px; padding: 5px 25px; margin-bottom: 20px'>
					<p><strong>N. de comanda:</strong> ".$ordre."</p>
					<p><strong>Concepte:</strong> ".$dniTitularPag." | ".$titol."</p>
					<p><strong>Data inici curs:</strong> ".$dataIniciLlarga."</p>
					<p><strong>Data fi curs:</strong> ".$dataFiLlarga."</p>
					<p><strong>Nom: </strong> ".$nom." ".$cognoms."</p>
					<p><strong>Correu electrònic: </strong> ".$email."</p>
					<p><strong>Import:</strong> ".$importPag." €</p>
					<p><strong>Resultat:</strong> Acceptat</p>
					<p><strong>Data i hora pagament:</strong> ".$dataPagNewFormat."</p>
				</div>";

				$subject = "Pagament deutor ".$dniTitularPag." | ".$titol." | ".$ordre." | ".$dataPag;


				/* ################# Actualitzem inscripcicó ################# */
				$updFraccBD = "UPDATE inscripcions SET `INSC CURS`='1' WHERE IDPAG=? AND `INSC CURS`='M'";
				if ( $stmtUpdate=$connexio->prepare($updFraccBD) ) {
					$stmtUpdate->bind_param("d", $idPag);
					$stmtUpdate->execute();
				}
				else {
					throw new Exception('',xxx);
				}

				/* ################# Enviar missatge a secretaria ################# */
				$nomTo = "PrisMa Secreataria";
				$correuTo = "secretaria@prisma.cat";
				// $correuTo = "meriem.prisma.cat@gmail.com";

				//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
				$mailSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
													$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
													$subject, $missatge);
			}

			$connexio->desconectarBD();
		}
	   else {
	      if ($codiResposta == "0101") {
				$tipusError =  "Targeta caducada";
			}
			else if ($codiResposta == "0102") {
				$tipusError =  "Targeta en excepció transitòria o sota sospita de frau";
			}
			else if ($codiResposta == "0106") {
				$tipusError =  "Intents de PIN excedits";
			}
			else if ($codiResposta == "0125") {
				$tipusError =  "Targeta no efectiva";
			}
			else if ($codiResposta == "0129") {
				$tipusError =  "Codi de seguretat (CVV2/CVC2) incorrecte";
			}
			else if ($codiResposta == "0180") {
				$tipusError =  "Targeta aliena al servei";
			}
			else if ($codiResposta == "0184") {
				$tipusError =  "Error en la autenticación del titular";
				$tipusError =  "Error en l’autenticació del titular";
			}
			else if ($codiResposta == "0190") {
				$tipusError =  "Denegación del emisor sin especificar motivo";
				$tipusError =  "Denegació de l’emissor sense especificar el motiu";
			}
			else if ($codiResposta == "0191") {
				$tipusError =  "Fecha de caducidad errónea";
				$tipusError =  "Data de caducitat errònia";
			}
			else if ($codiResposta == "0195") {
				$tipusError =  "Requiere autenticación SCA";
				$tipusError =  "Es requereix autenticació SCA";
			}
			else if ($codiResposta == "0202") {
				$tipusError =  "Tarjeta en excepción transitoria o bajo sospecha de fraude con retirada de tarjeta";
				$tipusError =  "Targeta en excepció transitòria o sota sospita de frau amb retirada de targeta";
			}
			else if ($codiResposta == "0904") {
				$tipusError =  "Comerç no registrat al FUC";
			}
			else if ($codiResposta == "0909") {
				$tipusError =  "Error de sistema";
			}
			else if ($codiResposta == "0913") {
				$tipusError =  "Pedido repetido";
				$tipusError =  "Comanda repetida";
			}
			else if ($codiResposta == "0944") {
	         $tipusError =  "Sesión Incorrecta";
				$tipusError =  "Sessió Incorrecta";
			}
			else if ($codiResposta == "0950") {
				$tipusError =  "Operación de devolución no permitida";
				$tipusError =  "Operació de devolució no permesa";
			}
			else if ($codiResposta == "9912/0912") {
				$tipusError =  "Emisor no disponible";
				$tipusError =  "Emissor no disponible";
			}
			else if ($codiResposta == "9064") {
				$tipusError =  "Número de posiciones de la tarjeta incorrecto";
				$tipusError =  "Número de posicions de la targeta incorrecte";
			}
			else if ($codiResposta == "9078") {
				$tipusError =  "Tipo de operación no permitida para esa tarjeta";
				$tipusError =  "Tipus d’operació no permesa per a aquesta targeta";
			}
			else if ($codiResposta == "9093") {
				$tipusError =  "Tarjeta no existente";
				$tipusError =  "Targeta inexistent";
			}
			else if ($codiResposta == "9094") {
				$tipusError =  "Rechazo servidores internacionales";
				$tipusError =  "Rebuig de servidors internacionals";
			}
			else if ($codiResposta == "9104") {
				$tipusError =  "Comercio con \"titular seguro\" y titular sin clave de compra segura";
				$tipusError =  "Comerç amb «titular segur» i titular sense clau de compra segura";
			}
			else if ($codiResposta == "9218") {
				$tipusError =  "El comercio no permite op. seguras por entrada /operaciones";
				$tipusError =  "El comerç no permet operacions segures per les entrades «operacions»";
			}
			else if ($codiResposta == "9253") {
				$tipusError =  "Tarjeta no cumple el check-digit";
				$tipusError =  "La targeta no compleix el <em>check-digit</em>";
			}
			else if ($codiResposta == "9256") {
				$tipusError =  "El comercio no puede realizar preautorizaciones";
				$tipusError =  "El comerç no pot realitzar preautoritzacions";
			}
			else if ($codiResposta == "9257") {
				$tipusError =  "Esta tarjeta no permite operativa de preautorizaciones";
				$tipusError =  "Aquesta targeta no permet l’ús de preautorizacions";
			}
			else if ($codiResposta == "9261") {
				$tipusError =  "Operación detenida por superar el control de restricciones en la entrada al SIS";
				$tipusError =  "Operació aturada per superar el control de restriccions en l’entrada al SIS";
			}
			else if ($codiResposta == "9915") {
				$tipusError =  "A petición del usuario se ha cancelado el pago";
				$tipusError =  "Pagament cancel·lat a petició de l’usuari";
			}
			else if ($codiResposta == "9997") {
				$tipusError =  "Se está procesando otra transacción en SIS con la misma tarjeta";
				$tipusError =  "S’està processant una altra transacció SIS amb la mateixa targeta";
			}
			else if ($codiResposta == "9998") {
				$tipusError =  "Operación en proceso de solicitud de datos de tarjeta";
				$tipusError =  "Operació en procés de sol·licitud de dades de targeta";
			}

			require_once 'ConnexioBBDD_PreparedStatment.php';
			$connexio = new ConnexioBBDDSTMT();
			$connexio->connectarBD();

			/* #################### Enviem el missatge a Gestió ####################  */

			/* Busquem les dades de la inscripció a partar de l'id de pagament */
			$cnsInsc = "SELECT ANY, MES, CURS, NOM, COGNOMS, DNI, CORREU, ADRECA,
			Codi_Postal, Poblacio, FACTURA_RELACIONADA, A_PAGAR, `INSC CURS`, PAGAMENT,
			pag_observacions FROM inscripcions WHERE IDPAG=?";
			if ( $stmtInsc=$connexio->prepare($cnsInsc) ) {
				$stmtInsc->bind_param("d", $idPag);
				$stmtInsc->execute();
				$stmtInsc->bind_result($any, $mes, $codiCurs, $nom, $cognoms, $dni,
				$email, $adreca, $cp, $poble, $factRel, $apagar, $inscrit, $importPagat, $pagObs);
				$stmtInsc->fetch();
				$connexio->closeStmt();
			}
			else {
				throw new Exception('',2010);
			}

			$cnsCurs = "SELECT NOM_CURS, DATAI, DATAF, HORES FROM curs WHERE ANY=? AND MES=? AND CURS=?";
			if ( $stmtRegal=$connexio->prepare($cnsCurs) ) {
				$stmtRegal->bind_param("dss", $any, $mes, $codiCurs);
				$stmtRegal->execute();
				$stmtRegal->bind_result($titol, $datai, $dataf, $hores);
				$stmtRegal->fetch();
				$connexio->closeStmt();
			}
			else {
				throw new Exception('',2011);
			}

			/* Calculem la data de pagament de la comanda */
			$textDataPag = new Text($dateComanda);
			$dataPagRevert = $textDataPag->replace('%2F','-');
			$vectDataPagRevert = explode('/', $dataPagRevert);
			$dataPag = $vectDataPagRevert[2]."-".$vectDataPagRevert[1]."-".$vectDataPagRevert[0];

			$horaComanda .= ":00";

			$dataPagCompleta = $dataPag." ".$horaComanda;
			$dataPagNewFormat = $dateComanda." ".$horaComanda;

			$objMes = new Text($mes);
			$mesLletra = $objMes->obtenirMesLlarg();

			/* Preparem les dades del missatge que enviarem */
			$nomFrom = "Gestió PrisMa";
			$correuFrom = "gestio@prisma.cat";
			$correuReply = "gestio@prisma.cat";
			$to = "gestio@prisma.cat";
			// $to = "suport.informatic@prisma.cat";

			$subject = "Pagament TPV Denegat: ".$dni." | ".$codiCurs;

			$missPreDiv = "<p>Dades del pagament:</p>";

			$missatge = "<p><strong>Nom Alumne:</strong> ".$nom." ".$cognoms."</p>
			<p><strong>DNI: </strong> ".$dni."</p>
			<p><strong>Correu electrònic: </strong> ".$email."</p>
			<p><strong>Curs: </strong> ".$codiCurs."</p>
			<p><strong>Mes: </strong> ".$mesLletra."</p>
			<p><strong>N. de comanda: </strong> ".$order."</p>
			<p><strong>Data i hora: </strong> ".$dataPagNewFormat."</p>
			<p><strong>Concepte: </strong> ".$dniTitularPag." | ".$titol."</p>
			<p><strong>Import: </strong> ".$importPag." €</p>
			<p><strong>Resultat: </strong> ".$tipusError." (".$codiResposta.")</p>";

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
				throw new Exception('',2012);
			}

			$cipher = "AES-128-CBC";

			$ivlen = openssl_cipher_iv_length($cipher);
			$iv = openssl_random_pseudo_bytes($ivlen);
			$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
			$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
			$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

			$urlPagament = "https://www.prisma.cat/pagament/".$hashIdPag;

			/* Enviem el missatge */
			$mailGestio = new Mail();
			$mailGestio->addHeaders($nomFrom, $correuFrom, $correuReply);
			$mailGestio->addSubject($subject);
			$mailGestio->addTo($to);
			$mailGestio->addMissatgeTiquet($missPreDiv, $missatge, '');
			$mailGestio->sendMessage();

			/* ############### Actualitzem el registre d'inscripcions ############### */
			$pagamentObs = $pagObs." Pagament denegat: ".$dataPagNewFormat;
			$updPagObsBD = "UPDATE inscripcions SET pag_observacions=? WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1' OR `INSC CURS`='M')";
			if ( $stmtUpdate=$connexio->prepare($updPagObsBD) ) {
				$stmtUpdate->bind_param("sd", $pagamentObs, $idPag);
				$stmtUpdate->execute();
				$stmtUpdate->fetch();
				$connexio->closeStmt();
			}
			else {
				throw new Exception('',2015);
			}

			/* ############### Enviem missatge a l'alumne i a PrisMa ############### */

			/* busco el username i el password d'autentificació de prisma */
			$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
							 AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
			if ( $stmt=$connexio->prepare($cnsParam) ) {
				$stmt->bind_param("s", $tipusParam);
				$tipusParam = 'autentificacioPagament';
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
				throw new Exception('',2013);
			}

			$missatge = "<p>Benvolgut/da ".$nom.",</p>

			<p>Hem detectat que has intentat realitzar un pagament amb la targeta de crèdit, però aquest ha sigut <strong>denegat</strong></p>

			<div style='background-color: #e8ecf5; border: 1px solid #D7DEEE; border-radius: 2px; padding: 5px 25px; margin-bottom: 20px'>
			<p><strong>Curs:</strong> ".$titol."</p>
			<p><strong>Mes:</strong> ".$mesLletra."</p>
			<p><strong>Import:</strong> ".$importPag." €</p>
			<p><strong>Data i hora:</strong> ".$dataPagNewFormat."</p>
			<p><strong>N. de comanda:</strong> ".$ordre."</p>
			<p><strong>Resultat:</strong> Denegat ".$codiResposta."</p>
			</div>

			<p>Pots tornar a intentar-ho des amb la targeta o fer-lo amb transferència:</p>

			<ul style='list-style: square; margin-bottom: 20px; margin-left: 30px; padding: 0;'>
				<li style='margin-top: 8px; line-height: 24px;'>
					TARGETA: clica a l'enllaç següent (cal que tinguis activat el codi
					de compra segura facilitat per la teva entitat bancària):
					<a href='$urlPagament' title='Pagament del curs ".$titol."'>".$urlPagament."</a>.
				</li>
				<li style='margin-top: 8px; line-height: 24px;'>
					TRANSFERÈNCIA o INGRÉS BANCARI: indica clarament el concepte
					<strong>«".$codiCurs."-".$mes."+ el número
					del teu NIF/NIE/Passaport»</strong>, en qualsevol dels comptes següents:
					<ul style='list-style: circle; margin-left: 30px; padding: 0;'>
						<li style='margin-top: 8px; line-height: 24px;'>La Caixa: ES30 2100 4279 21 2200080678 </li>
						<li style='margin-top: 8px; line-height: 24px;'>BBVA: ES04 0182 5117 00 0201534816</li>
					</ul>
				</li>
			</ul>

			<p>Un cop hagis realitzat el pagament és important que conservis el justificant
			bancari fins que t'arribi un correu electrònic que confirmi que hem rebut
			correctament el teu pagament.</p>

			<p>Recorda que per realitzar el pagament amb targeta cal que des de la teva
			entitat bancària t'hagin activat el servei de <strong>pagament segur</strong>.</p>

				<p>Si fas el pagament per <strong>transferència</strong>, en dos dies laborals
			com a màxim t'enviarem un correu electrònic de confirmació del moviment bancari.</p>

			<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

			<p>Salutacions ben cordials,</p>";

			$subject = "Pagament denegat - ".$codiCurs." - ".$ordre;


			/* ################## Enviar confirmació a PrisMa ################## */
			$nomFromHead = $nameUser;
			$correuFromHead = $username;
			$nomReplyHead = $nameUser;
			$correuReplyHead = $username;
			$nomTo = $nameUser;
			$correuTo = 'resguard.gestio@prisma.cat';

			//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
			$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
												$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
												$subject, $missatge);

			/* ################## Enviar confirmació a l'alumne ################## */
			$nomTo = $nom;
			$correuTo = $email;

			//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
			$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
												$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
												$subject, $missatge);

			$connexio->desconectarBD();
	   }
	}
	catch(Exception $e) {
		$mailMe = new Mail();
		$mailMe->addHeaders($nomMe, $correuMe, $correuMe);
		$mailMe->addSubject("Error ".$ordre);
		$mailMe->addTo($correuMe);
		$mailMe->addMissatgeTiquet("<p>Hola</p>", "Error ".$e->getCode().$e->getMessage(), '');
		$mailMe->sendMessage();
	}
	echo $mostrar;

?>
