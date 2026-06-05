<?php
	include("./ConnexioBBDD_PreparedStatment.php");
	include("./inc/apiRedsys.php");
	include("./Text.php");
	include("./Curs.php");
	include("./MailSMTPComvive.php");
	include("./MailSMTP.php");
	include("./Mail.php");

	$dniTitularPag = $_GET['dni'];
	$importPag = floatval($_GET['import']);
	$idPag = $_GET['idPag'];
	$numComanda = $_GET['order'];
	$tipusInsc = $_GET['tipusInsc'];

	include('inc/analitics.html');

	$nomMe = 'Meriem';
	$correuMe = "meriem.prisma.cat@gmail.com";
	$subjectMe = "pagament automatic ".$numComanda;
	$missatge = "<p>DNI: ".$dniTitularPag."</p>
	<p>IMPORT: ".$importPag."</p>
	<p>IDPAG: ".$idPag."</p>
	<p>ORDER: ".$numComanda."</p>";
	$mailMe = new Mail();
	$mailMe->addHeaders($nomMe, $correuMe, $correuMe);
	$mailMe->addSubject($subjectMe);
	$mailMe->addTo($correuMe);
	$mailMe->addMissatgeTiquet("<p>Hola</p>", $missatge, '');
	$mailMe->sendMessage();

	function obtenirMsgInfoBanc($codiResposta) {
		if (intval($codiResposta)>=0 && intval($codiResposta)<=99) {
			$tipusError =  "Transacció autoritzada per a pagaments i preautoritzacions";
		}
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

		return $tipusError;
	}

	try {
		// Es crea l'objecte RedsysAPI
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

			$tipusError = obtenirMsgInfoBanc($codiResposta);

			require_once 'ConnexioBBDD_PreparedStatment.php';

			$connexio = new ConnexioBBDDSTMT();
			$connexio->connectarBD();

			/* ##################    Consultem les dades de   ##################
			   ##################    la persona responsable   ################## */

			if ( $tipusInsc == 'G' ) {
				$cnsResp = "SELECT r.NOM, r.COGNOMS, r.DNI, r.CORREU, r.ADRECA, r.Codi_Postal,
				r.Poblacio, i.FACTURA_RELACIONADA, SUM(i.A_PAGAR) as total, SUM(i.PAGAMENT) as pagat
				FROM inscripcions as i INNER JOIN respGrups as r ON i.IDPAG = r.IDPAG
				WHERE TIPUS_INSC = 'G' AND (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS`='M') AND
				r.IDPAG = ? GROUP BY r.IDPAG";
				if ( $stmt=$connexio->prepare($cnsResp) ) {
					$stmt->bind_param("d", $idPag);
					$stmt->execute();
					$stmt->bind_result($nomResp, $cognomsResp, $dniResp, $emailResp,
					$adrecaResp, $cpResp, $pobleResp, $factRel, $apagar, $importPagat);
					$stmt->fetch();
					$connexio->closeStmt();
				}
				else {
					throw new Exception('',2502);
				}

				$cnsGroup = "SELECT i.ANY, i.MES, i.CURS, c.NOM_CURS, c.HORES, c.DATAI, c.DATAF
				FROM inscripcions as i INNER JOIN curs as c
				ON i.ANY = c.ANY and i.MES = c.MES and i.CURS = c.CURS
				WHERE IDPAG = ? AND (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS`='M')
				AND GTAF IS NOT NULL AND GTAF!='' GROUP BY i.CURS, i.MES, i.ANY, c.HORES";
				if ( $stmt=$connexio->prepare($cnsGroup) ) {
					$stmt->bind_param("d", $idPag);
					$stmt->execute();
					$stmt->bind_result($anyEdicio, $mesEdicio, $codiCurs, $titol, $horesEd,
					$datai, $dataf);
					$stmt->fetch();
					$connexio->closeStmt();
				}
				else {
					throw new Exception('',2505);
				}

				/* Obtinc la data d'inici i la data de fi en lletres */
				$objDataI = new Text($datai);
				$dataIniciLlarga = $objDataI->convertirDataLlarga();
				$objDataF = new Text($dataf);
				$dataFiLlarga = $objDataF->convertirDataLlarga();
			}

			/* ********* Calculem la data de pagament de la comanda ********* */
			$textDataPag = new Text($dateComanda);
			$dataPagRevert = $textDataPag->replace('%2F','-');
			$vectDataPagRevert = explode('/', $dataPagRevert);
			$dataPag = $vectDataPagRevert[2]."-".$vectDataPagRevert[1]."-".$vectDataPagRevert[0];

			$horaComanda .= ":00";

			$dataPagCompleta = $dataPag." ".$horaComanda;
			$dataPagNewFormat = $dateComanda." ".$horaComanda;

			/* ################## Enviem el missatge a Gestió ##################  */

			if ( $tipusInsc == 'G' ) {
				$missPreDiv = "<p>Dades del pagament de la persona resposable del grup:</p>";

				$msgGrup = "
					<p><strong>Nom: </strong> ".$nomResp." ".$cognomsResp."</p>
					<p><strong>DNI: </strong> ".$dniResp."</p>
					<p><strong>Correu electrònic: </strong> ".$emailResp."</p>
					<p><strong>N. de comanda: </strong> ".$numComanda."</p>
					<p><strong>Data i hora: </strong> ".$dataPagNewFormat."</p>
					<p><strong>Concepte: </strong> ".$dniTitularPag." | ".$titol."</p>
					<p><strong>Import: </strong> ".$importPag." €</p>
					<p><strong>Resultat: </strong> ".$codiResposta."</p>";

				$pendentPagar = 0;
				$totalPagat = floatval($importPagat)+floatval($importPag);

				if ( floatval($apagar) > $totalPagat ) {
					$pendentPagar = floatval($apagar) - $totalPagat;
					$missatge .= "<p><strong>Pendent de pagar: </strong> ".$pendentPagar." de ".$apagar."</p>";
				}
				if ( floatval($apagar) > floatval($importPag) ) {
					$frac = 1;
				}

				$nomFrom = "Gestió PrisMa";
				$correuFrom = "gestio@prisma.cat";
				$correuReply = "gestio@prisma.cat";
				$to = "gestio@prisma.cat";
				// $to = "merimari051094@gmail.com";

				$subject = "Pagament TPV: ".$dniTitularPag." | ".$codiCurs." | ".$numComanda." | ".$dataPag;

				/* Enviem el missatge a Gestió */
				$mailGestio = new Mail();
				$mailGestio->addHeaders($nomFrom, $correuFrom, $correuReply);
				$mailGestio->addSubject($subject);
				$mailGestio->addTo($to);
				$mailGestio->addMissatgeTiquet($missPreDiv, $msgGrup, '');
				$mailGestio->sendMessage();
			}

			/* ##################     Generem la factura     #################### */

			$tipusFact = "A";

			/* *********            Calculem l'any fiscal            ********* */
			$anyFiscal = date('Y');

			/* *********       Calculem l'ordre de la factura       ********* */
			$cnsOrdre = "SELECT ordre FROM factures WHERE ANY = ? AND TIPUS = ?
			ORDER BY any DESC, ordre DESC LIMIT 1";
			if ( $stmtOrdre=$connexio->prepare($cnsOrdre) ) {
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
			}
			else {
				throw new Exception('',2504);
			}

			/* ********* Calculem el número de la factura ********* */
			$numFact = $tipusFact.$anyFiscal."/".$ordreFact;

			/* ********* Calculem la data actual de la factura ********* */
			$dataActual = date('Y')."-".date('m')."-".date('d')." ".date('H').":".date('i').":".date('s');

			/* ********* Calculem el valor de l'entitat de la factura ********* */
			$entitat = "Asso";
			/* ********* Calculem la forma de pagament de la factura ********* */
			$formaPagament = "TPV";

			if ( $tipusInsc == 'G' ) {
				/* Obtinc el mes en lletres */
				$objMes = new Text($mesEdicio);
				$mesEdicioEscrit = $objMes->obtenirMesLlarg();

				/* ********* Calculem el número de la factura relacionada ********* */
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
						throw new Exception('',2503);
					}
				}
				else { //l'alumne havia pagat alguna cosa
					$factura = $factRel;
				}

				/* ********* Calculem la rao de la factura ********* */
				$rao = $nomResp." ".$cognomsResp;
				$cif = $dniTitularPag;

				/* ********* Calculem el concepte 1 i el concepte 2 de la factura ********* */
				$concepte1 = "Curs ".$titol;
				$concepte2 = "Convocatòria ".$mesEdicioEscrit." ".$anyEdicio;

				$cp = $cpResp;
				$adreca = $adrecaResp;
				$poble = $pobleResp;
				$hores = $horesEd;
			}

			$insertBD = "INSERT INTO factures (FACTURA_RELACIONADA, TIPUS, ANY, ORDRE, NUM, DATA,
				DATA_PAGAMENT, NUM_COMANDA, RAO, CIF, ADRECA, CP, POBLACIO, CONCEPTE1,
				CONCEPTE2, IMPORT, ENTITAT, FORMA_PAGAMENT, CURS, HORES)
				VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
			if ( $stmtInsert=$connexio->prepare($insertBD) ) {
				$stmtInsert->bind_param("dsddsssdsssssssdsssd",
					$factura, $tipusFact, $anyFiscal, $ordreFact, $numFact, $dataActual,
					$dataPagCompleta, $numComanda, $rao, $cif, $adreca, $cp, $poble,
					$concepte1, $concepte2,	$importPag, $entitat, $formaPagament, $codiCurs, $hores);
				$stmtInsert->execute();
				$idInserit = $connexio->lastInsertId();
				$stmtInsert->fetch();
				$connexio->closeStmt();
			}
			else {
				throw new Exception ('', 2501);
			}

			/* ##################  Actualitzem inscripcions  #################### */

			/* Busco tots els registres que tenen IDPAG $idpag i estiguin inscrits.
			Mentre hi hagi registre i $auxPag > 0,
					si auxPag >= apagar-pagat d'aquest registre,
						fer un update del registre i posar que pagat APAGAR i la factura relacionada $factura i la DATA PAGAMENT actual
						AUXPAG -= (apagar-pagat)
					else
						fer un update del registre i posar que pagat PAGAT+AUXPAG i la factura relacionada $factura
						auxPag -= (auxpag)
			*/
			$connexio2 = new ConnexioBBDDSTMT();
			$connexio2->connectarBD();
			if ( $tipusInsc == 'G' ) {
				$auxPagat = floatval($importPag);
				$exMoros = 0;
				$searchMembresGrup = "SELECT ID, A_PAGAR, PAGAMENT, `INSC CURS` FROM inscripcions
				WHERE TIPUS_INSC = 'G' AND (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS`='M') AND
				IDPAG = ?";
				$updDateInscr = "UPDATE inscripcions SET `DATA PAG`=? WHERE ID=?";
				$updPayInscr = "UPDATE inscripcions SET PAGAMENT=?, FACTURA_RELACIONADA=? WHERE ID=?";
				if ( $stmt=$connexio->prepare($searchMembresGrup) ) {
					$stmt->bind_param("d", $idPag);
					$stmt->execute();
					$stmt->bind_result($id, $aPagarMembre, $pagamentMembre, $inscrit); $i=0;
					while ( $stmt->fetch() && $auxPagat > 0 ) {
						if ( !$exMoros && $inscrit == 'M' ) $exMoros = 1;
						if ( $auxPagat < ($aPagarMembre - $pagamentMembre) ) {
							$pagat = floatval($auxPagat);
							$auxPagat -= floatval($auxPagat);
						}
						else {
							$pagat = floatval($aPagarMembre);
							$auxPagat -= floatval($aPagarMembre - $pagamentMembre);
						}
						if ( $stmtUpdate=$connexio2->prepare($updPayInscr) ) {
							$stmtUpdate->bind_param("ddd", $pagat, $factura, $id);
							$stmtUpdate->execute();
							$stmtUpdate->fetch();
							$connexio2->closeStmt();
						}
						else {
							throw new Exception('',2506);
						}

						$pagat2 = (real) number_format($pagat,2);
						$aPagarMembre2 = (real) number_format($aPagarMembre,2);

						if ( floatval($pagat2) >= floatval($aPagarMembre2) ) {
							if ( $stmtUpdate=$connexio2->prepare($updDateInscr) ) {
								$stmtUpdate->bind_param("sd", $dataPagCompleta, $id);
								$stmtUpdate->execute();
								$stmtUpdate->fetch();
								$connexio2->closeStmt();
							}
							else {
								throw new Exception('',2505);
							}
						}
						$i++;
					}
					$connexio->closeStmt();
				}
				else {
					throw new Exception ('', 2507);
				}
			}
			$connexio2->desconectarBD();

			/* ##################  Enviem missatge a l'alumne  ################## */
			if ( $tipusInsc == 'G' ) {

				$subject = "Confirmació matrícula ".$titol." | ".$numComanda." | ".$dataPag;

				$missatge = "<p>Benvolgut/da ".$nomResp.",</p>

				<p>Hem rebut correctament el pagament de la matrícula del curs <strong style='color: #496baa'>".$titol."</strong> que es
				realitza del dia <strong>".$dataIniciLlarga."</strong> al dia <strong>".$dataFiLlarga."</strong>.</p>

				<div style='background-color: #e8ecf5; border: 1px solid #D7DEEE; border-radius: 2px; padding: 5px 25px; margin-bottom: 20px'>
					<p><strong>N. de comanda:</strong> ".$numComanda."</p>
					<p><strong>Concepte:</strong> ".$dniTitularPag." | ".$titol."</p>
					<p><strong>Correu electrònic: </strong> ".$emailResp."</p>
					<p><strong>Import:</strong> ".$importPag." €</p>
					<p><strong>Resultat:</strong> Acceptat</p>
					<p><strong>Data i hora:</strong> ".$dataPagNewFormat."</p>
				</div>

				<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

				<p>Salutacions ben cordials,</p>";

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
					throw new Exception('',2512);
				}

				$nomFromHead = $nameUser;
				$correuFromHead = $username;
				$nomReplyHead = $nameUser;
				$correuReplyHead = $username;

				$nomTo = $nameUser;
				$correuTo = 'resguard.gestio@prisma.cat';
				// $correuTo = 'merimari051094@gmail.com';
				$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
													$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
													$subject, $missatge);

				/* ################## Enviar confirmació a l'alumne ################## */
				$nomTo = $nomResp;
				$correuTo = $emailResp;

				//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
				$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
													$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
													$subject, $missatge);

				/* ##################            Morosos            ################## */
				if ( $exMoros && floatval($apagar) <= $totalPagat ) {
					$missatge = "
					<div style='background-color: #e8ecf5; border: 1px solid #D7DEEE; border-radius: 2px; padding: 5px 25px; margin-bottom: 20px'>
						<p><strong>N. de comanda:</strong> ".$ordre."</p>
						<p><strong>Concepte:</strong> ".$dniTitularPag." | ".$titol."</p>
						<p><strong>Data inici curs:</strong> ".$dataIniciLlarga."</p>
						<p><strong>Data fi curs:</strong> ".$dataFiLlarga."</p>
						<p><strong>Nom responsable: </strong> ".$nomResp."</p>
						<p><strong>Correu electrònic: </strong> ".$emailResp."</p>
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

			}

			$connexio->desconectarBD();
		}
		else {
			$tipusError =  obtenirMsgInfoBanc($codiResposta);

			require_once 'ConnexioBBDD_PreparedStatment.php';
			$connexio = new ConnexioBBDDSTMT();
			$connexio->connectarBD();

			/* ##################    Consultem les dades de   ##################
			   ##################    la persona responsable   ################## */

			if ( $tipusInsc == 'G' ) {
				$cnsResp = "SELECT r.NOM, r.COGNOMS, r.DNI, r.CORREU, r.ADRECA, r.Codi_Postal,
				r.Poblacio, i.FACTURA_RELACIONADA, SUM(i.A_PAGAR) as total, SUM(i.PAGAMENT) as pagat
				FROM inscripcions as i INNER JOIN respGrups as r ON i.IDPAG = r.IDPAG
				WHERE TIPUS_INSC = 'G' AND (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS`='M') AND
				r.IDPAG = ? GROUP BY r.IDPAG";
				if ( $stmt=$connexio->prepare($cnsResp) ) {
					$stmt->bind_param("d", $idPag);
					$stmt->execute();
					$stmt->bind_result($nomResp, $cognomsResp, $dniResp, $emailResp,
					$adrecaResp, $cpResp, $pobleResp, $factRel, $apagar, $importPagat);
					$stmt->fetch();
					$connexio->closeStmt();
				}
				else {
					throw new Exception('',2508);
				}

				$cnsGroup = "SELECT i.ANY, i.MES, i.CURS, c.NOM_CURS, c.HORES, c.DATAI, c.DATAF
				FROM inscripcions as i INNER JOIN curs as c
				ON i.ANY = c.ANY and i.MES = c.MES and i.CURS = c.CURS
				WHERE IDPAG = ? AND (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS`='M')
				AND GTAF IS NOT NULL AND GTAF!='' GROUP BY i.CURS, i.MES, i.ANY, c.HORES";
				if ( $stmt=$connexio->prepare($cnsGroup) ) {
					$stmt->bind_param("d", $idPag);
					$stmt->execute();
					$stmt->bind_result($anyEdicio, $mesEdicio, $codiCurs, $titol, $horesEd,
					$datai, $dataf);
					$stmt->fetch();
					$connexio->closeStmt();
				}
				else {
					throw new Exception('',2509);
				}
			}

			/* ********* Calculem la data de pagament de la comanda ********* */
			$textDataPag = new Text($dateComanda);
			$dataPagRevert = $textDataPag->replace('%2F','-');
			$vectDataPagRevert = explode('/', $dataPagRevert);
			$dataPag = $vectDataPagRevert[2]."-".$vectDataPagRevert[1]."-".$vectDataPagRevert[0];

			$horaComanda .= ":00";

			$dataPagCompleta = $dataPag." ".$horaComanda;
			$dataPagNewFormat = $dateComanda." ".$horaComanda;

			$objMes = new Text($mesEdicio);
			$mesEdicioEscrit = $objMes->obtenirMesLlarg();

			/* ################## Enviem el missatge a Gestió ##################  */

			if ( $tipusInsc == 'G' ) {
				$missPreDiv = "<p>Dades del pagament de la persona resposable del grup:</p>";

				$msgGrup = "<p><strong>Nom: </strong> ".$nomResp." ".$cognomsResp."</p>
					<p><strong>DNI: </strong> ".$dniResp."</p>
					<p><strong>Correu electrònic: </strong> ".$emailResp."</p>
					<p><strong>Curs: </strong> ".$codiCurs."</p>
					<p><strong>Mes: </strong> ".$mesEdicioEscrit."</p>
					<p><strong>N. de comanda: </strong> ".$numComanda."</p>
					<p><strong>Data i hora: </strong> ".$dataPagNewFormat."</p>
					<p><strong>Concepte: </strong> ".$dniTitularPag." | ".$titol."</p>
					<p><strong>Import: </strong> ".$importPag." €</p>
					<p><strong>Resultat: </strong> ".$tipusError."(".$codiResposta.")</p>";

				$pendentPagar = 0;
				$totalPagat = floatval($importPagat)+floatval($importPag);

				if ( floatval($apagar) > $totalPagat ) {
					$pendentPagar = floatval($apagar) - $totalPagat;
					$missatge .= "<p><strong>Pendent de pagar: </strong> ".$pendentPagar." de ".$apagar."</p>";
				}
				if ( floatval($apagar) > floatval($importPag) ) {
					$frac = 1;
				}

				$nomFrom = "Gestió PrisMa";
				$correuFrom = "gestio@prisma.cat";
				$correuReply = "gestio@prisma.cat";
				$to = "gestio@prisma.cat";
				// $to = "merimari051094@gmail.com";

				$subject = "Pagament TPV Denegat: ".$dniTitularPag." | ".$codiCurs;

				/* Enviem el missatge a Gestió */
				$mailGestio = new Mail();
				$mailGestio->addHeaders($nomFrom, $correuFrom, $correuReply);
				$mailGestio->addSubject($subject);
				$mailGestio->addTo($to);
				$mailGestio->addMissatgeTiquet($missPreDiv, $msgGrup, '');
				$mailGestio->sendMessage();

				/* ############### Actualitzem el registre d'inscripcions ############### */

				$connexio2 = new ConnexioBBDDSTMT();
				$connexio2->connectarBD();
				if ( $tipusInsc == 'G' ) {
					$auxPagat = $importPag;

					$searchMembresGrup = "SELECT pag_observacions FROM inscripcions
					WHERE TIPUS_INSC = 'G' AND (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS`='M') AND
					IDPAG = ?";
					$updPagObsBD = "UPDATE inscripcions SET pag_observacions=? WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1' OR `INSC CURS`='M')";
					if ( $stmt=$connexio->prepare($searchMembresGrup) ) {
						$stmt->bind_param("d", $idPag);
						$stmt->execute();
						$stmt->bind_result($pagObs);
						while ( $stmt->fetch() ) {
							$pagamentObs = $pagObs." Pagament denegat: ".$dataPagNewFormat;

							//update amb data de pagament
							if ( $stmtUpdate=$connexio2->prepare($updPagObsBD) ) {
								$stmtUpdate->bind_param("sd", $pagObs, $idPag);
								$stmtUpdate->execute();
								$stmtUpdate->fetch();
								$connexio2->closeStmt();
							}
							else {
								throw new Exception('',2510);
							}

						}
						$connexio->closeStmt();
					}
					else {
						throw new Exception ('', 2511);
					}
				}
				$connexio2->desconectarBD();

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
					throw new Exception('',2512);
				}

				$cipher = "AES-128-CBC";

				$ivlen = openssl_cipher_iv_length($cipher);
				$iv = openssl_random_pseudo_bytes($ivlen);
				$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
				$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
				$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

				$urlPagament = "https://www.prisma.cat/pagaments/".$hashIdPag;

				$msgGrupAlumne = "<p>Benvolgut/da ".$nomResp.",</p>

				<p>Hem detectat que has intentat realitzar un pagament amb la targeta de crèdit, però aquest ha sigut <strong>denegat</strong></p>

				<div style='background-color: #e8ecf5; border: 1px solid #D7DEEE; border-radius: 2px; padding: 5px 25px; margin-bottom: 20px'>
				<p><strong>Curs:</strong> ".$titol."</p>
				<p><strong>Mes:</strong> ".$mesEdicioEscrit."</p>
				<p><strong>Import:</strong> ".$importPag." €</p>
				<p><strong>Data i hora:</strong> ".$dataPagNewFormat."</p>
				<p><strong>N. de comanda:</strong> ".$numComanda."</p>
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

				$subject = "Pagament denegat - ".$codiCurs." - ".$numComanda;

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
													$subject, $msgGrupAlumne);

				/* ################## Enviar confirmació a l'alumne ################## */
				$nomTo = $nom;
				$correuTo = $email;

				//enviar un email a la persona que ha fet la compra i a gestio@prisma.cat
				$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
													$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
													$subject, $msgGrupAlumne);
			}


			$connexio->desconectarBD();
		}

		// $nomMe = 'Meriem';
		// $correuMe = "merimari051094@gmail.com";
		// $subjectMe = "2 - pagament automatic per grup ".$numComanda;
		// $msgProva = "<p>codiResposta: ".$codiResposta."</p>
		// <p>tipusError: ".$tipusError."</p>
		// <p>preu: ".$preu."</p>
		// <p>ORDER: ".$ordre."</p>";
		// $mailMe = new Mail();
		// $mailMe->addHeaders($nomMe, $correuMe, $correuMe);
		// $mailMe->addSubject($subjectMe);
		// $mailMe->addTo($correuMe);
		// $mailMe->addMissatgeTiquet("<p>Hola</p>", $msgProva, '');
		// $mailMe->sendMessage();
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
