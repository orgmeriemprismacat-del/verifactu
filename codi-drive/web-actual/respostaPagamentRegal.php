<!DOCTYPE HTML PUBLIC "-/W3C/DTD HTML 4.01/EN" "http:/www.w3.org/TR/html4/strict.dtd">
<html lang="ca" prefix="og: http:/ogp.me/ns# fb: http:/ogp.me/ns/fb# video: http:/ogp.me/ns/video#">
<head>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  if ( !localStorage.getItem("acceptCookies") ) {
    console.log('consent default');
    gtag('consent', 'default', {
      'ad_storage': 'denied',
      'ad_user_data': 'denied',
      'ad_personalization': 'denied',
      'analytics_storage': 'denied'
    });
  }
	else {
		console.log('consent');
		gtag('consent', 'default', {
      'ad_storage': 'granted',
      'ad_user_data': 'granted',
      'ad_personalization': 'granted',
      'analytics_storage': 'granted'
    });
	}
</script>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-DD77YFRVS0"></script>
<script>
  gtag('js', new Date());
  gtag('config', 'G-DD77YFRVS0');

  function consentGrantedAdStorage() {
    console.log('consentGrantedAdStorage');
    gtag('consent', 'update', {
      'ad_storage': 'granted'
    });
  }
  function consentGrantedAdUserData() {
    console.log('consentGrantedAdUserData');
    gtag('consent', 'update', {
      'ad_user_data': 'granted'
    });
  }
  function consentGrantedAdPersonalization() {
    console.log('consentGrantedAdPersonalization');
    gtag('consent', 'update', {
      'ad_personalization': 'granted'
    });
  }
  function consentGrantedAnalyticsStorage() {
    console.log('consentGrantedAnalyticsStorage');
    gtag('consent', 'update', {
      'analytics_storage': 'granted'
    });
  }
</script>
   <meta charset="utf-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <?php include("./inc/buscarMetaTags.php");flush();?>
   <style>.url-no-mostrar{display:none}</style>
   <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/estructura.min.css?ver=5.0' />
   <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
   <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.0' />
   <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/footer.min.css?ver=5.0' />
   <script></script>
</head>
<body class="index">
   <header></header>
   <?php  ?>
	<div id="contingut" class="prisma-container container separacio-peu" role="main">
		<?php
			include("./ConnexioBBDD_PreparedStatment.php");
			include("./inc/apiRedsys.php");
			include("./Text.php");
			include("./Curs.php");
			include("./MailSMTP.php");

			// Se crea Objeto
			$miObj = new RedsysAPI;

			if (!empty( $_POST ) ) {
				$version = $_POST["Ds_SignatureVersion"];
				$datos = $_POST["Ds_MerchantParameters"];
				$signatureRecibida = $_POST["Ds_Signature"];
			}
			else{
				if (!empty( $_GET ) ) {
					$version = $_GET["Ds_SignatureVersion"];
					$datos = $_GET["Ds_MerchantParameters"];
					$signatureRecibida = $_GET["Ds_Signature"];

					$decodec = $miObj->decodeMerchantParameters($datos);
					$kc = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; //Clave recuperada de CANALES
					$firma = $miObj->createMerchantSignatureNotif($kc,$datos);

               $ordre = $miObj->getParameter('Ds_Order');
					// echo "Ds_Order".$ordre."<br />";
					$dateComanda = $miObj->getParameter('Ds_Date');
					// echo "Ds_Date".$dateComanda."<br />";
					$hora = $miObj->getParameter('Ds_Hour');
					// echo "Ds_Hour".$hora."<br />";
					$preu = $miObj->getParameter('Ds_Amount');
					// echo "Ds_Amount".$preu."<br />";
               $codiResposta = $miObj->getParameter("Ds_Response");
					// echo "Ds_Response".$codiResposta."<br />";

					// if ($firma === $signatureRecibida){
                  $missatgePantallaLlarg = "<p class='mb-4'>Hi ha hagut un error a l'hora de registrar el pagament.</p>
   					<p>Contacta amb el banc indicant l'<strong>error ".$codiResposta."</strong> per solucionar l'error. </p>";

   					if (intval($codiResposta)>=0 && intval($codiResposta)<=99) {
   						// $tipusError =  "Transacción autorizada para pagos y preautorizaciones";
   						$tipusError =  "Transacció autoritzada per a pagaments i preautoritzacions";

   						require_once 'ConnexioBBDD_PreparedStatment.php';
   						$connexio = new ConnexioBBDDSTMT();
   						$connexio->connectarBD();

   						// echo "<span style='color: #5690c2; font-weight: bold;'>
   						// 		ORDRE DE PAGAMENT [CONSULTA]: </span>".$ordre."<br />";
   						//Consulta idpag en el pagament d'un curs i el codiregal en el pagament d'un regal
   						$cnsOrdre = "SELECT CODI, IMPORT FROM intentsPagament WHERE ORDRE=? LIMIT 1";
   						$stmtOrdre=$connexio->prepare($cnsOrdre);
   						$stmtOrdre->bind_param("d", $ordre);
   						$stmtOrdre->execute();
   						$stmtOrdre->bind_result($codiRegal, $importPag);
   						$stmtOrdre->fetch();
   						$connexio->closeStmt();
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		ORDRE DE PAGAMENT [RESULTAT]: </span>".$ordre."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		CODI DE PAGAMENT [RESULTAT]: </span>".$codiRegal."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		IMPORT DE PAGAMENT [RESULTAT]: </span>".$importPag."<br />";
                     //
   						// echo "<span style='color: #5690c2; font-weight: bold;'>
   						// 		ORDRE DE PAGAMENT [CONSULTA]: </span>".$dateComanda."<br />";
   						$textDataPag = new Text($dateComanda);
   						$dataPagRevert = $textDataPag->replace('%2F','-');
   						$vectDataPagRevert = explode('-', $dataPagRevert);
   						$dataPag = $vectDataPagRevert[2]."-".$vectDataPagRevert[1]."-".$vectDataPagRevert[0];

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		DATA PAGAMENT [CALCULAT]: </span>".$dataPag."<br />";
                     $tipusFact = "A";
   						$anyFiscal = date('Y');

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		ANY ACTUAL [CALCULAT]: </span>".$anyFiscal."<br />";

   						$dataActual = date('Y')."-".date('m')."-".date('d')." ".date('H').":".date('i').":".date('s');

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		DATA ACTUAL [CALCULAT]: </span>".$dataActual."<br />";

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

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		ORDRE FACTURA [CALCULAT]: </span>".$ordreFact."<br />";

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

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		FACTURA [CALCULAT]: </span>".$factura."<br />";


   						$cnsRegal = "SELECT NOM_CURS, CCURS, NOMC, NIFC, MAILC, ADRECAC,
   							POBLEC, CPC, FACT_REL FROM regal WHERE CODI=?";
   						$stmtRegal=$connexio->prepare($cnsRegal);
   						$stmtRegal->bind_param("s", $codiRegal);
   						$stmtRegal->execute();
   						$stmtRegal->bind_result($nomCurs, $codiCurs, $nomC, $nifC,
   							$mailC, $adrecaC, $pobleC, $cpC, $factRel);
   						$stmtRegal->fetch();
   						$connexio->closeStmt();

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. NOM CURS [CALCULAT]: </span>".$nomCurs."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. CODI CURS [CALCULAT]: </span>".$codiCurs."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. NOM COMPRADOR [CALCULAT]: </span>".$nomC."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. NIF COMPRADOR [CALCULAT]: </span>".$nifC."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. EMAIL COMPRADOR [CALCULAT]: </span>".$mailC."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. ADRECA COMPRADOR [CALCULAT]: </span>".$adrecaC."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. POBLE COMPRADOR [CALCULAT]: </span>".$pobleC."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. CPC COMPRADOR [CALCULAT]: </span>".$cpC."<br />";

   						$concepte1 = "Curs regal ".$nomCurs;
   						$concepte2 = "Codi ".$codiRegal;

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		CONCEPTE 1 [CALCULAT]: </span>".$concepte1."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		CONCEPTE 2 [CALCULAT]: </span>".$concepte2."<br />";

   						require_once 'Curs.php';

                     if (intval($codiCurs)==0) {
                        $cursR = new Curs($codiCurs);

      						$hores = $cursR->obtenirHores()->obtenirNumero();
                     }
                     else {
                        $hores = intval($codiCurs);
                     }


   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. HORES [CALCULAT]: </span>".$hores."<br />";

   						$numFact = "A".$anyFiscal."/".$ordreFact;

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		REGAL. NUM_FACT [CALCULAT]: </span>".$numFact."<br />";

   						$rao = $nomC;
   						$cif = $nifC;

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		FACTURA. RAO [CALCULAT]: </span>".$rao."<br />";
   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		FACTURA. CIF [CALCULAT]: </span>".$cif."<br />";

   						$entitat = "Asso";

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		FACTURA. ENTITAT [CALCULAT]: </span>".$entitat."<br />";

   						$formaPagament = "TPV";

   						// echo "<span style='color: #c25698; font-weight: bold;'>
   						// 		FACTURA. FORMA DE PAGAMENT [CALCULAT]: </span>".$formaPagament."<br />";

   						if ($factRel==0) //No ha pagat
   						{
   							//afegir la factura a FACTURES
   							$insertBD = "INSERT INTO factures (factura_relacionada, TIPUS, ANY, ORDRE, NUM, DATA,
   												data_pagament, NUM_COMANDA, RAO, CIF, ADRECA, CP, POBLACIO, CONCEPTE1,
   												CONCEPTE2, IMPORT, ENTITAT, FORMA_PAGAMENT, CURS, HORES)
   											 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
   							$stmtInsert=$connexio->prepare($insertBD);
   							$stmtInsert->bind_param("dsddsssdsssssssdsssd",
   								$factura, $tipusFact, $anyFiscal, $ordreFact, $numFact, $dataActual, $dataPag,
   								$ordre, $rao, $cif, $adrecaC, $cpC, $pobleC, $concepte1, $concepte2,
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
   							$tipusParam = 'autentificacioInscripcio';
   							$stmt->execute();
   							$stmt->bind_result($valor);
   							$stmt->fetch();
   							$autentificacioInscripcio = explode('|',$valor);
   							$username = $autentificacioInscripcio[0];
   							$password = $autentificacioInscripcio[1];
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

   							$nomFromHead = 'PrisMa Gestió';
   							$correuFromHead = 'gestio@prisma.cat';
   							$nomReplyHead = 'PrisMa Gestió';
   							$correuReplyHead = 'gestio@prisma.cat';
   							$nomTo = "PrisMa Gestió";
   							$correuTo = 'gestio@prisma.cat';
   							// $correuTo = 'suport.informatic@prisma.cat';

   							//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
   							$mailAlumne = new MailSMTP($username, $password, $nomFromHead, $correuFromHead,
   																$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
   																$subject, $missatge);

   							$nomFromHead = 'PrisMa Gestió';
   							$correuFromHead = 'gestio@prisma.cat';
   							$nomReplyHead = 'PrisMa Gestió';
   							$correuReplyHead = 'gestio@prisma.cat';
   							$nomTo = $nomC;
   							$correuTo = $mailC;

   							//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
   							$mailAlumne = new MailSMTP($username, $password, $nomFromHead, $correuFromHead,
   																$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
   																$subject, $missatge);

   							$missatgePantallaLlarg = "<p class='mb-4'>El pagament s'ha registrat correctament.
   							Consulta la safata d'entrada o el correu brossa (<em>spam</em>)
   							de l'adreça <span class='font-weight-bold email'>";
   							$missatgePantallaLlarg.= $mailC."</span>
   							per comprovar que has rebut el missatge de confirmació del pagament.</p>";
   						}
   						$connexio->desconectarBD();

                     $mostrar = "<div class='container' id='notfound'>
         					<div class='col-md-12'>
         						<div class='page-error-content text-center'>
         							<h1>".$tipusError."</h1>
         							".$missatgePantallaLlarg."
         						</div>
         					</div>
         				</div>";
   					}
                  else {
                     $missatgePantallaLlarg = "<p class='mb-4'>Hi ha hagut un error a l'hora de fer el pagament.</p>
      					<p>Contacta amb el banc indicant l'<strong>error ".$codiResposta."</strong> per solucionar l'error. </p>";

      					if ($codiResposta == "0101") {
      						// $tipusError =  "Tarjeta caducada";
      						$tipusError =  "Targeta caducada";
      					}
      					else if ($codiResposta == "0102") {
      						$tipusError =  "Tarjeta en excepción transitoria o bajo sospecha de fraude";
      						$tipusError =  "Targeta en excepció transitòria o sota sospita de frau";
      					}
      					else if ($codiResposta == "0106") {
      						$tipusError =  "Intentos de PIN excedidos";
      						$tipusError =  "Intents de PIN excedits";
      					}
      					else if ($codiResposta == "0125") {
      						$tipusError =  "Tarjeta no efectiva";
      						$tipusError =  "Targeta no efectiva";
      					}
      					else if ($codiResposta == "0129") {
      						$tipusError =  "Código de seguridad (CVV2/CVC2) incorrecto";
      						$tipusError =  "Codi de seguretat (CVV2/CVC2) incorrecte";
      					}
      					else if ($codiResposta == "0180") {
      						$tipusError =  "Tarjeta ajena al servicio";
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

                     $cnsOrdre = "SELECT CODI, IMPORT FROM intentsPagament WHERE ORDRE=? LIMIT 1";
   						$stmtOrdre=$connexio->prepare($cnsOrdre);
   						$stmtOrdre->bind_param("d", $ordre);
   						$stmtOrdre->execute();
   						$stmtOrdre->bind_result($codiRegal, $importPag);
   						$stmtOrdre->fetch();
   						$connexio->closeStmt();

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
                     $tipusParam = 'autentificacioInscripcio';
                     $stmt->execute();
                     $stmt->bind_result($valor);
                     $stmt->fetch();
                     $autentificacioInscripcio = explode('|',$valor);
                     $username = $autentificacioInscripcio[0];
                     $password = $autentificacioInscripcio[1];
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

                     $nomFromHead = 'PrisMa Gestió';
                     $correuFromHead = 'gestio@prisma.cat';
                     $nomReplyHead = 'PrisMa Gestió';
                     $correuReplyHead = 'gestio@prisma.cat';
                     $nomTo = "PrisMa Gestió";
                     $correuTo = 'gestio@prisma.cat';
                     // $correuTo = 'suport.informatic@prisma.cat';

                     //enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
                     $mailAlumne = new MailSMTP($username, $password, $nomFromHead, $correuFromHead,
                                                $nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
                                                $subject, $missatge);

                     $nomFromHead = 'PrisMa Gestió';
                     $correuFromHead = 'gestio@prisma.cat';
                     $nomReplyHead = 'PrisMa Gestió';
                     $correuReplyHead = 'gestio@prisma.cat';
                     $nomTo = $nomC;
                     $correuTo = $mailC;

                     //enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
                     $mailAlumne = new MailSMTP($username, $password, $nomFromHead, $correuFromHead,
                                                $nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
                                                $subject, $missatge);

                     $mostrar = "<div class='container' id='notfound'>
         					<div class='col-md-12'>
         						<div class='page-error-content text-center'>
                              <img src='https://www.prisma.cat/img/error-pagament.png' alt='Error en el pagament amb targeta'/>
         							<h1>".$tipusError."</h1>
         							".$missatgePantallaLlarg."
         						</div>
         					</div>
         				</div>";
                  }
					// }
					// else {
               //    $tipusError =  "Firma no autoritzada.";
   				// 	$missatgePantallaCurt = "Error 1602.";
   				// 	$codiResposta = "1602";
   				// 	$missatgePantallaLlarg = "<p class='mb-4'>Hi ha hagut un error al realitzar el pagament.</p>
   				// 	<p>Torna-ho a intentar en uns minuts. </p>";
               //
               //    $mostrar = "<div class='container' id='notfound'>
      			// 		<div class='col-md-12'>
      			// 			<div class='page-error-content text-center'>
               //             <img src='https://www.prisma.cat/img/error-pagament.png' alt='Error en el pagament amb targeta'/>
               //             <h1>".$tipusError."</h1>
      			// 				".$missatgePantallaLlarg."
      			// 			</div>
      			// 		</div>
      			// 	</div>";
					// }
   				echo $mostrar;
				}
				else{
					die("No se recibió respuesta");
				}
			}
		?>
	</div>
	<footer class="prisma-footer"></footer>
   <script async>
	   var headerCSS = "";
	   if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
	   	headerCSS += "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.0' />";
	   }
	   else {
		   headerCSS += "<link rel='stylesheet' media='screen and (min-width: 769px)' href='https://www.prisma.cat/css1619773569/header_wind.min.css?ver=5.0' />";
		   headerCSS += "<link rel='stylesheet' media='screen and (max-width: 768px)' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.0' />";
	   }
	   $('head').append(headerCSS);
   </script>
   <script async src="https://www.prisma.cat/js1619773569/mostrarRespostaPagamentRegal.min.js"></script>
   <script async src="https://www.prisma.cat/js1619773569/obrirTancar.min.js"></script>
   <script async src="https://www.prisma.catjs1619773569/lazysizes.min.js"></script>
   <script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>
