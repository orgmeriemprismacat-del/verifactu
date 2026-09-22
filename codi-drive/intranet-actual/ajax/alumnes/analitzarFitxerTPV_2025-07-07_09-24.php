<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');

try {

	$file = $_FILES['fitxer-tpv'];
	$type = $_FILES['fitxer-tpv']['type'];
	$mida = $_FILES['fitxer-tpv']['size'];
	$fileTmpName = $_FILES['fitxer-tpv']['tmp_name'];

	$resposta = new stdClass();
	$fecha = new DateTime();
	$tmp = $fecha->getTimestamp();

	$resposta->msg = "";
	$resposta->type = $type;
	$resposta->mida = $mida;
	$resposta->fileTmpName = $fileTmpName;
	$resposta->state = 1;
	$resposta->tmp = $tmp;

	$cntErrors = 10;

	for ( $i = 0; $i < $cntErrors; $i++ ) {
		$idsIgnorat[$i] = [];
	}

	$msgErrorsIgnorats[0] = "la transacció no ha estat autoritzada";
	$msgErrorsIgnorats[1] = "el número de la comanda no és vàlida";
	$msgErrorsIgnorats[2] = "l'import no és vàlid";
	$msgErrorsIgnorats[3] = "l'import en euros no és vàlid";
	$msgErrorsIgnorats[4] = "l'import retornat no és vàlid";
	$msgErrorsIgnorats[5] = "la transacció no ha acabat";
	$msgErrorsIgnorats[6] = "la transacció està denegada";
	$msgErrorsIgnorats[7] = "la transacció s'ha cancel·lat";
	$msgErrorsIgnorats[8] = "la data de la transacció no és vàlida";
	$msgErrorsIgnorats[9] = "el titular no és un DNI";

	if( $resposta->state == 1 ){
		$path_file = "../../fitxers/fitxer-tpv-".$tmp.".csv";
		$path_file = $fileTmpName;
		if ( file_exists($path_file) ) {
			if (!$handle = fopen($path_file, "r")) {
				 echo "Cannot open file imported";
				 exit;
			}
			else {
				$content = fgetcsv( $handle, filesize($path_file), ',', "\"", "\\" );
				// echo "CONTENT1.<BR />";
				// print_r($content);
				// $contentCSV = explode(';', str_replace("El Dibuix Creatiu a l'Aula: Espai, Forma i Tra", "El Dibuix Creatiu a l'Aula: Espai. Forma i Tra", $content[0]));
				$contentCSV = explode(';', $content[0]);
				// echo "CONTENTCSV1.<BR />";
				// print_r($contentCSV);

				$conWeb = new ConnexioWeb();
				$conWeb->connectarBD();

				// echo count($contentCSV)-1;
				if ( count($contentCSV)-1 == 12 ) {
					$i=2; $cntIgn=0;
					$registresPagamentsNoExisteixen = $registresPagamentsExisteixen = '';
					while ( ! feof( $handle ) ) {
						$content = fgetcsv( $handle, filesize($path_file), ',', "\"", "\\" );
						// echo "CONTENT2.<BR />";
						// print_r($content);
						if ( $content != '')  {
							// echo "CONTENTCSV2.<BR />";
							$contentCSV = explode(';', $content[0]);
							// print_r($contentCSV);

							/* Defineixo els camps llegits */
							$datePayTPVDDMMYYYY = $contentCSV[0];
							// $transaccio = utf8_encode($contentCSV[3]);
							$transaccio = mb_convert_encoding($contentCSV[3], "UTF-8");
							$numComanda = intval($contentCSV[4]);
							// $accioTransaccio = utf8_encode($contentCSV[5]);
							$accioTransaccio = mb_convert_encoding($contentCSV[5], "UTF-8");
							$importCSV = floatval($contentCSV[6]);
							$importEuros = floatval($contentCSV[8]);
							// $cif = utf8_encode($contentCSV[9]);
							$cif = mb_convert_encoding($contentCSV[9], "UTF-8");
							// $conepte = utf8_encode($contentCSV[10]);
							$conepte = mb_convert_encoding($contentCSV[10], "UTF-8");
							if ( count($contentCSV)<12 ) $importRetornat = 0;
							else $importRetornat = floatval($contentCSV[11]);


							// echo "<br />";
							// for ($j = 0; $j<count($contentCSV); $j++) {
							// 	echo "[".$i."][".$j."]".mb_convert_encoding($contentCSV[$j], "UTF-8")."\n";
							// }
							// echo "<br />";

							$ignore = (
								($transaccio != "Autorización" && $transaccio != "Devolución") || $numComanda == 0 ||
								$importCSV == 0 || $importEuros < 0 || $importRetornat < 0 ||
								strripos($accioTransaccio,'Autorizada') === false ||
								count(explode('/', $datePayTPVDDMMYYYY)) != 3 ||
								intval($cif) == 0
							);

							if ( !$ignore ) {
								if ( strripos($accioTransaccio,'Autorizada') !== false ) {
									$objDate = new Date( $datePayTPVDDMMYYYY );
									$datePayTPVYYYYMMDD = "%".$objDate->getDataFomatYYYYMMDD_HHMMSS()."%";

									if ( $transaccio == "Devolución" ) {
										$tipus = 'R';
										$import = 0 - $importRetornat;
									}
									else {
										$tipus = 'A';
										$import = $importEuros;
									}

									$cnsFacturaAmbComanda = "SELECT ID FROM factures WHERE data_pagament LIKE ? AND
									num_comanda = ? AND import = ? AND TIPUS = ?";
									$cnsFacturaSenseComanda = "SELECT ID FROM factures WHERE data_pagament LIKE ? AND
									(num_comanda IS NULL OR num_comanda = '') AND import = ? AND TIPUS = ? AND cif = ?";

									if ( $stmtFact=$conWeb->prepare( $cnsFacturaAmbComanda ) ) {
										$stmtFact->bind_param("sdds", $datePayTPVYYYYMMDD, $numComanda, $import, $tipus);
										$stmtFact->execute();
										$stmtFact->store_result();
										$existeix = $stmtFact->num_rows() > 0;
										$conWeb->closeStmt();
									}
									if ( !$existeix ) {
										if ( $stmtFact=$conWeb->prepare( $cnsFacturaSenseComanda ) ) {
											$stmtFact->bind_param("sdss", $datePayTPVYYYYMMDD, $import, $tipus, $cif);
											$stmtFact->execute();
											$stmtFact->store_result();
											$existeix = $stmtFact->num_rows() > 0;
											$conWeb->closeStmt();
										}
									}

									if ( !$existeix && $cif != '77922662L') {
										if ( $import > 0 ) $idConsulta = 'cnsAlumne-'.$cif;
										else $idConsulta = 'cnsFactura-'.$cif;

										// echo $cnsFactura." ".$datePayTPVYYYYMMDD." ".$numComanda." ".$import." ".$tipus." ".$cif."\n";
										$registresPagamentsNoExisteixen .= "<li>El pagament del dia <strong>".$datePayTPVDDMMYYYY."</strong>
										amb import <strong>".$import." €</strong> amb titular
										<strong id='".$idConsulta."' class='consultaPagament pointer color-prisma search'>".$cif."</strong> NO té factura. El concepte de la transacció és: <strong>".$conepte."</strong></li>";

									}
								}
							}
							else {
								// echo "ignore ".$i;
								if ( $transaccio != "Autorización" && $transaccio != "Devolución") $typeError = 0;
								if ( $numComanda == 0 ) $typeError = 1;
								if ( $importCSV == 0 ) $typeError = 2;
								if ( $importEuros < 0  ) $typeError = 3;
								if ( $importRetornat < 0 ) $typeError = 4;
								if (
									strripos($accioTransaccio,'Sin Finalizar') !== false ||
									strripos($accioTransaccio,'Denegada') !== false ||
									strripos($accioTransaccio,'Cancelada') !== false
								) {
									if ( strripos($accioTransaccio,'Sin Finalizar') !== false ) $typeError = 5;
									if ( strripos($accioTransaccio,'Denegada') !== false ) $typeError = 6;
									if ( strripos($accioTransaccio,'Cancelada') !== false ) $typeError =7;
								}
								if ( count(explode('/', $datePayTPVDDMMYYYY)) != 3 ) $typeError = 8;
								if ( intval($cif) == 0 )  $typeError = 9;

								$idsIgnorat[$typeError][] = $i;
							}
							$i++;
						}
					}
				}
				else {
					$resposta->state = 0;
					$resposta->msg = "L'arxiu no disposa del format correcte";

				}
				$conWeb->desconectarBD();
			}

		}

		fclose($handle);
	}

	if ( $resposta->state == 1 ) {
		$resposta->registresPagErrors = "";
		if ( $registresPagamentsNoExisteixen == '' ) {
			$resposta->msg = "<p>Tots els registres estan registrats correctament</p>";
		}
		else {
			$resposta->state = 2;
			$resposta->msg = "<p>Hi ha alguns registres que no s'han registrat correctament. </p>";
			$resposta->registresPagErrors = "<p>Cal que registirs els pagaments següents:</p>
			<ul>
			".$registresPagamentsNoExisteixen."</ul>";
		}

		$textIgnorats = "";
		for ($i = 0; $i<count($idsIgnorat); $i++){
			// echo "[".$i."]".count($idsIgnorat[$i])."\n";
			if ( count($idsIgnorat[$i]) > 0 ) {
				$textIgnorats .= "<p>Els registres <strong>";
				for ( $j = 0; $j < count($idsIgnorat[$i]); $j++ ) {
					if ( $j > 0 && $j == count($idsIgnorat[$i])-1 ) $textIgnorats .= " i ";
					else if ( $j > 0 && $j <count ($idsIgnorat[$i])-1  ) $textIgnorats .= ", ";
					$textIgnorats .= $idsIgnorat[$i][$j];
				}
				$textIgnorats .= "</strong> s'han ignorat degut a que ".$msgErrorsIgnorats[$i].".</p>";
			}
		}

		$resposta->msg .= $textIgnorats;
	}
	else {
		$resposta->state = 0;
		if ( $resposta->msg = "" )
			$resposta->msg = "<p>La informació no s'ha analitzat degut a que només s'admeten fitxers .CSV</p>";
	}
	echo json_encode($resposta);

	$csv_file = "analisis-fitxer.txt";
	$path_file = "../../fitxers/".$csv_file;

	$csv = date("d/m/Y  H:i")."_";

	//Si el fitxer no existeix, generem el fitxer
	if (!$handle = fopen($path_file, "a+")) {
		 echo "Cannot open file analisis-fitxer";
		 exit;
	}
	//Omplim el fitxer
	// if (fwrite($handle, utf8_decode($csv)) === FALSE) {
	if (fwrite($handle, mb_convert_encoding($csv, "ISO-8859-1")) === FALSE) {
		 echo "Cannot write to file";
		 exit;
	}
	fclose($handle);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
}

?>
