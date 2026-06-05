<?php
	include("./ConnexioBBDD_PreparedStatment.php");
	include("./inc/apiRedsys.php");
	include("./Text.php");
	include("./Curs.php");
	include("./MailSMTPComvive.php");
	include("./MailSMTP.php");
	include("./Mail.php");

	$order = $_GET['order'];
	$cursPag = $_GET['codiCurs'];
	$codiRegal = $_GET['codiRegal'];
	$dniTitularPag = $_GET['dni'];
	$nomTitularPag = $_GET['nom'];
	$importPag = floatval($_GET['import']);

	include('inc/analitics.html');

	$nomMe = 'Meriem';
	$correuMe = "meriem.prisma.cat@gmail.com";
	$subjectMe = "pagament automatic ".$order;
	$missatge = "<p>DNI: ".$dniTitularPag."</p>
	<p>IMPORT: ".$importPag."</p>
	<p>CODI REGAL: ".$codiRegal."</p>
	<p>ORDER: ".$order."</p>";
	$mailMe = new Mail();
	$mailMe->addHeaders($nomMe, $correuMe, $correuMe);
	$mailMe->addSubject($subjectMe);
	$mailMe->addTo($correuMe);
	$mailMe->addMissatgeTiquet("<p>Hola</p>", $missatge, '');
	$mailMe->sendMessage();

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

			// Busquem les dades de la inscripció del regal a partir del codi del regal
			$cnsRegal = "SELECT NOM_CURS, CCURS, NOMC, NIFC, MAILC, ADRECAC,
				POBLEC, CPC, FACT_REL FROM regal WHERE CODI=?";
			$stmtRegal=$connexio->prepare($cnsRegal);
			$stmtRegal->bind_param("s", $codiRegal);
			$stmtRegal->execute();
			$stmtRegal->bind_result($nomCurs, $codiCurs, $nomC, $nifC,
				$mailC, $adrecaC, $pobleC, $cpC, $factRel);
			$stmtRegal->fetch();
			$connexio->closeStmt();

			// Calculem la data de pagament de la comanda
			$textDataPag = new Text($dateComanda);
			$dataPagRevert = $textDataPag->replace('%2F','-');
			$vectDataPagRevert = explode('-', $dataPagRevert);
			$dataPag = $vectDataPagRevert[2]."-".$vectDataPagRevert[1]."-".$vectDataPagRevert[0];

			$horaComanda .= ":00";

			$dataPagCompleta = $dataPag." ".$horaComanda;
			$dataPagNewFormat = $dateComanda." ".$horaComanda;

			// Calculem el número de la factura relacionada
			$cnsFactRel = "SELECT factura_relacionada FROM factures
				ORDER BY factura_relacionada DESC LIMIT 1";
			$stmtFactRel=$connexio->prepare($cnsFactRel);
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

			// Calculem l'any fiscal
			$anyFiscal = date('Y');

			$tipusFact = "A";

			// Calculem l'ordre de la factura
			$cnsOrdre = "SELECT ordre FROM factures WHERE ANY = ? AND TIPUS = ?
			ORDER BY any DESC, ordre DESC LIMIT 1";
			$stmtOrdre=$connexio->prepare($cnsOrdre);
			$stmtOrdre->bind_param("ds", $anyFiscal, $tipusFact);
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

			// Calculem el número  de la factura
			$numFact = "A".$anyFiscal."/".$ordreFact;

			// Buscquem la data actual de la factura'
			$dataActual = date('Y')."-".date('m')."-".date('d')." ".date('H').":".date('i').":".date('s');

			// Calculem la rao de la factura
			$rao = $nomC;
			$cif = $nifC;

			// Calculem el concepte 1 i el concepte 2 de la factura
			$concepte1 = "Curs regal ".$nomCurs;
			$concepte2 = "Codi ".$codiRegal;

			// Calculem el valor de l'entitat i la forma de pagament de la factura
			$entitat = "Asso";
			$formaPagament = "TPV";

			require_once 'Curs.php';

			if (intval($codiCurs)==0) {
				$cursR = new Curs($codiCurs);
				$hores = $cursR->obtenirHores()->obtenirNumero();
			}
			else {
				$hores = intval($codiCurs);
			}

			if ($factRel==0) { //No ha pagat
				//afegir la factura a FACTURES
				$insertBD = "INSERT INTO factures (factura_relacionada, TIPUS, ANY, ORDRE, NUM, DATA,
									data_pagament, NUM_COMANDA, RAO, CIF, ADRECA, CP, POBLACIO, CONCEPTE1,
									CONCEPTE2, IMPORT, ENTITAT, FORMA_PAGAMENT, CURS, HORES)
								 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
				$stmtInsert=$connexio->prepare($insertBD);
				$stmtInsert->bind_param("dsddsssdsssssssdsssd",
					$factura, $tipusFact, $anyFiscal, $ordreFact, $numFact, $dataActual, $dataActual,
					$order, $rao, $cif, $adrecaC, $cpC, $pobleC, $concepte1, $concepte2,
					$importPag, $entitat, $formaPagament, $codiCurs, $hores);
				$stmtInsert->execute();
				$idInserit = $connexio->lastInsertId();
				$stmtInsert->fetch();
				$connexio->closeStmt();

				//actualitzar el registre del regal amb la factura utilitzada
				$updateBD = "UPDATE regal SET FACT_REL=? WHERE CODI=?";
				$stmtUpdate=$connexio->prepare($updateBD);
				$stmtUpdate->bind_param("ds", $factura, $codiRegal);
				$stmtUpdate->execute();
				$stmtUpdate->fetch();
				$connexio->closeStmt();

				//buscar el username i el password d'autentificació de prisma
				$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND
								DATAI<=CURRENT_TIMESTAMP AND
								(DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
				$stmt=$connexio->prepare($cnsParam);
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

				$subject = "Confirmació de pagament del val regal ".$codiRegal;

				$missatge = "<p>Benvolgut/da ".$nomC.",</p>
				<p>
					Hem rebut correctament el teu pagament per al val regal
					amb el codi <strong>".$codiRegal."</strong>.
				</p>
				<p>
					En <a title='Targeta regal. Versió digital'
						 href=\"https://www.prisma.cat/targetes-regal/".$codiRegal."_targeta_regal_versio_digital.pdf\">
						 aquest enllaç</a> trobaràs la targeta regal en format PDF.
				</p>
				<p>Et recordem que el codi té una validesa d’un any des
				de la compra de la targeta regal.</p>
				<p>Gràcies per haver escollit regalar un dels nostres cursos!</p>";

				$nomFromHead = $nameUser;
				$correuFromHead = $username;
				$nomReplyHead = $nameUser;
				$correuReplyHead = $username;

				$nomTo = $nameUser;
				$correuTo = 'resguard.gestio@prisma.cat';
				// $correuTo = "merimari051094@gmail.com";

				//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
				$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
													$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
													$subject, $missatge);

				$nomTo = $nomC;
				$correuTo = $mailC;
				// $correuTo = "merimari051094@gmail.com";

				//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
				$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
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

			require_once 'ConnexioBBDD_PreparedStatment.php';
			$connexio = new ConnexioBBDDSTMT();
			$connexio->connectarBD();

			$cnsRegal = "SELECT NOM_CURS, CCURS, NOMC, NIFC, MAILC, ADRECAC,
				POBLEC, CPC, FACT_REL FROM regal WHERE CODI=?";
			$stmtRegal=$connexio->prepare($cnsRegal);
			$stmtRegal->bind_param("s", $codiRegal);
			$stmtRegal->execute();
			$stmtRegal->bind_result($nomCurs, $codiCurs, $nomC, $nifC,
				$mailC, $adrecaC, $pobleC, $cpC, $factRel);
			$stmtRegal->fetch();
			$connexio->closeStmt();

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

			$ivlen = openssl_cipher_iv_length($cipher);
			$iv = openssl_random_pseudo_bytes($ivlen);
			$ciphertext_raw = openssl_encrypt($codiRegal, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
			$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
			$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );


			//buscar el username i el password d'autentificació de prisma
			$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND
							DATAI<=CURRENT_TIMESTAMP AND
							(DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
			$stmt=$connexio->prepare($cnsParam);
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

			$connexio->desconectarBD();

			$subject = "Error en el pagament del val regal ".$codiRegal;

			$missatge = "<p>Benvolgut/da ".$nomC.",</p>
			<p>
				Hem detectat que el teu pagament amb targeta de crèdit del curs <strong>".$nomCurs."</strong>
				no s’ha completat amb èxit.
			</p>
			<p>
				Pots <a title='Efectuar pagament amb targeta'
					href='https://www.prisma.cat/regal/pagament/".$hashIdPag."'
				>tornar a intentar-ho</a> o bé fer-lo amb transferència bancària;
				quan el rebem correctament, en un màxim de dos dies laborables t'enviarem
				un correu electrònic de confirmació del moviment bancari.
			</p>
			<p>Recorda que per realitzar el pagament cal que des de la teva entitat
			bancària t'hagin activat el servei de pagament segur.</p>";

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

			$nomTo = $nomC;
			$correuTo = $mailC;

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
