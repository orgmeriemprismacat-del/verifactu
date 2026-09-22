<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');

session_start();

try {

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$file = $_FILES['fitxer-add-edicions'];
	$type = $_FILES['fitxer-add-edicions']['type'];
	$mida = $_FILES['fitxer-add-edicions']['size'];
	$fileTmpName = $_FILES['fitxer-add-edicions']['tmp_name'];
	$orderby = $_POST['orderby'];
	$asc = $_POST['asc'];

	$resposta = new stdClass();
	$fecha = new DateTime();
	$tmp = $fecha->getTimestamp();

	$resposta->msg = "";
	$resposta->type = $type;
	$resposta->mida = $mida;
	$resposta->fileTmpName = $fileTmpName;
	$resposta->state = 1;
	$resposta->tmp = $tmp;

	$numColumnesCSVCorrect = 18;

	$buscarLastIdAula = "SELECT ID_AULA FROM aula ORDER BY ID_AULA DESC LIMIT 1";
	$buscarCursByTitol = "SELECT CODI_CURS, ESTAT FROM informacio WHERE TITOL = ? ORDER BY ESTAT DESC LIMIT 1";
	$buscarCursByTitolCurs = "SELECT CURS, ID_PREU FROM curs WHERE NOM_CURS = ? AND HORES = ? ORDER BY ID_CURS DESC LIMIT 1";
	$buscarIdPreuCursByHoresCurs = "SELECT ID_PREU FROM curs WHERE HORES = ? AND CURS_ESCOLAR = ? GROUP BY ID_PREU ORDER BY COUNT(ID_CURS)";
	$buscarCursByCursEscGtaf = "SELECT ID_CURS, CURS, PUBLIC FROM curs WHERE CURS_ESCOLAR = ? AND GTAF = ?";

	if( $type == 'application/vnd.ms-excel') {
		$path_file = "../../fitxers/fitxer-add-edicions-".$tmp.".csv";
		if(move_uploaded_file($fileTmpName, $path_file) ){
		   $resposta->state = 1;
		} else {
		   $resposta->state = 0;
		   $resposta->msg = "L'arxiu no s'ha pogut desar a servidor. Intenta-ho de nou més tard.";
		}
	}
	if( $resposta->state == 1 ){
		// echo "2";
		$path_file = "../../fitxers/fitxer-add-edicions-".$tmp.".csv";
		$path_file = $fileTmpName;
		if ( file_exists($path_file) ) {
			if (!$handle = fopen($path_file, "r")) {
				 echo "Cannot open file imported";
				 exit;
			}
			else {
				$content = fgetcsv( $handle, filesize($path_file), ',', "\"", "\\" );
				// $content = fgetcsv( $handle, filesize($path_file) );
				// $content = fgetcsv( $handle, filesize($path_file) );
				// $content = fgetcsv( $handle, filesize($path_file) );
				$contentCSV = explode(';', $content[0]);

				$table = "";

				$conWeb = new ConnexioWeb();
		      $conWeb->connectarBD();
		      $conWeb2 = new ConnexioWeb();
		      $conWeb2->connectarBD();
		      $conWeb3 = new ConnexioWeb();
		      $conWeb3->connectarBD();

				if ( count($contentCSV) > $numColumnesCSVCorrect ) {
					if ( $stmt=$conWeb->prepare( $buscarLastIdAula ) ) {
						$stmt->execute();
						$stmt->bind_result($idAulaCercat);
						$stmt->fetch();
						$conWeb->closeStmt();
					}
					else {
						throw new Exception('',9001);
					}

					$cnt = 0; $ignore = 0; $cntAula=1;
					while ( ! feof( $handle ) ) {
						/* Provar aquesta opció*/
						$contentCSV = fgetcsv( $handle, filesize($path_file), ";", "\"", "\\" );
						// echo "CONTENT2.<BR />";
						// print_r($contentCSV);

						// echo $contentCSV[2]."<br />";
						if ( $contentCSV != '' ) {
							if ( !$ignore && count($contentCSV) <= $numColumnesCSVCorrect ) $ignore = 1;
							else $ignore = 0;

							if ( !$ignore ) {
								/* 2.1. Obtinc el curs escolar, el codi gtaf, el nom del curs, el número d'hores, la data d'inici i la data de fi del CSV */
						      $cursEscolar = mb_convert_encoding($contentCSV[0], "UTF-8");
						      $codiGtaf = mb_convert_encoding($contentCSV[1], "UTF-8");
						      // $nomCurs = utf8_encode($contentCSV[3]);
						      $nomCurs = ($contentCSV[2]);
						      $numHores = intval($contentCSV[14]);
						      $dataI = $contentCSV[15];
						      $dataF = $contentCSV[16];
						      $codiStatus = $contentCSV[30];

								// echo "STATUS:".$codiStatus."<br />";
								// echo "NOM_CURS:".$nomCurs."<br />";
								// echo "DATAI:".$dataI."<br />";
								// echo "DATAF:".$dataF."<br />";
								// echo "CURS_ESCOLAR:".$cursEscolar."<br />";
								// echo "CODI_GTAF:".$codiGtaf."<br />";

								/* 2.2 Reemplaço el - per / en el curs escolar */
						      $cursEscolar  = str_replace('-', '/', $cursEscolar);

								/* 2.3 Reemplaço el ’ per ' i les '' per '  en el nom del curs  */
								$arr = array('％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-',
								'：' => ':', '。' => '.', '、' => ',', '，' => '.', '、' => '.',
								'；' => ',', '？' => '?', '！' => '!', '…' => '-', '‖' => '|',
								'”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"',
								'　' => ' ', '“' => "'", '”' => "'", '‘' => "'", '’' => "'",  '´' => "'");
								$nomCurs  = strtr($nomCurs, $arr);
							   $nomCurs  = str_replace("’", "'", $nomCurs);
							   $nomCurs  = str_replace("", "'", $nomCurs);
							   $nomCurs  = mb_convert_encoding($nomCurs, "UTF-8");
							   $nomCurs  = str_replace("''", "'", $nomCurs);
							   $nomCurs  = str_replace("", "'", $nomCurs);

								/* 2.6. Arreglem el format de les dates que obtenim del CSV ja que no és el ideal.
								Les dates obtenides del CSV tenen el format DD/MM/YY.
								Canviem el format per a què sigui el que necessitem nosaltres: YYYY-MM-DD.
								També obtenim l'any i el mes de la data d'inici. */

								$vectDataI = explode('/',$dataI);
								$vectDataF = explode('/',$dataF);

								$dataICorrect = "".$vectDataI[2]."-".$vectDataI[1]."-".$vectDataI[0];
								$dataFCorrect = "".$vectDataF[2]."-".$vectDataF[1]."-".$vectDataF[0];

								$dateDataI = new DateTime( $dataICorrect ); //2020-01-10
								$dateDataF = new DateTime( $dataFCorrect );

								$dataI = $dateDataI->format('Y-m-d');
								$dataF = $dateDataF->format('Y-m-d');

								$any = $dateDataI->format('Y');
								$mes = $dateDataI->format('m');

								// echo $dataI."<br />";
								// echo $dataF."<br />";
								// echo $any."<br />";
								// echo $mes."<br />";

								/* 2.5. Per defecte, aula = 'A* i public = 1 */
								$aula = 'A';
								$public = 1;

								$codiCurs = '';
								$idPreu = 0;

								if ( $stmt=$conWeb->prepare( $buscarCursByCursEscGtaf ) ) {
									$stmt->bind_param("ss", $cursEscolar, $codiGtaf);
									// echo $buscarCursByCursEscGtaf."<br />";
									// echo $cursEscolar."<br />";
									// echo $codiGtaf."<br />";
									$stmt->execute();
									$stmt->store_result();
									$existeix = $stmt->num_rows();
									$stmt->bind_result($idCurs, $codiCurs, $public);
									$stmt->fetch();
									$conWeb->closeStmt();

									$mostrar = "OK";
								}
								else {
									throw new Exception('', 9000);
								}

								if ( $existeix <= 0 ) {
								   /* 2.4. Si el cnt > 0 (no és la primera vegada que accedeix en el bucle), idAula = idAula anterior incrementat en 1
								   Altrament, idAula = idAula cercat de la BD incrmeentat en 1
								   */
								   $idAula = $idAulaCercat+$cntAula;

									// echo $idAula."<br />";

								   /* 2.7 Necessito obtenir el CODI_CURS, el ID_PREU i l'ESTAT  del curs que té com a nom $nomCurs i de $numHores hores */

									/*
							        Primer de tot, per saber el curs que estem pujant, hem de buscar el codi del curs a taula informacio de la BD i comprovar si està actiu.
							        Per fer-ho, busquem el CODI_CURS i ESTAT  de la taula informació on TITOL = $nomCurs ordenat per ESTAT descendentment i limitat per 1.
							      */
							      if ( $stmt=$conWeb->prepare( $buscarCursByTitol ) ) {
										// echo $buscarCursByTitol."<br />";
										// echo $nomCurs."<br />";
										$stmt->bind_param("s", $nomCurs);
										$stmt->execute();
										$stmt->store_result();
										if ($stmt->num_rows() > 0) {
											//Si existeix algun registre:
											$stmt->bind_result($codiCurs, $public);
											$stmt->fetch();
											// echo $codiCurs."<br />";
											// echo $public."<br />";

											//Ens falta trobar el ID_PREU. Per trobar-l'ho, busquem a la taula curs on NOM_CURS = $nomCurs i HORES = $numHores.
											if ( $stmt2=$conWeb2->prepare( $buscarCursByTitolCurs ) ) {
												$stmt2->bind_param("sd", $nomCurs, $numHores);
												$stmt2->execute();
												$stmt2->store_result();
												if ($stmt2->num_rows() > 0) {
													// Si existeix algun registre: Obtinc el ID_PREU.
													$stmt2->bind_result($codiCurs, $idPreu);
													$stmt2->fetch();
												}
												else {
													/* Si no existeix algun registre, pot ser degut a que s'han canviat
													el nº de hores o que es tracta d'un curs nou. Per tant,
													buscarem el ID_PREU d'un curs del mateix curs_escolar amb unes hores iguals a la del curs.
													Per fer-ho, busquem un registre on HORES = $numHores i CURS_ESCOLAR = $cursEscolar
													ORDENAT per num edicions descendentment i limitat per 1.*/
													if ( $stmt3=$conWeb3->prepare( $buscarIdPreuCursByHoresCurs ) ) {
														$stmt3->bind_param("ds", $numHores, $cEsc);
														$cEsc = $cursEscolar;
														$stmt3->execute();
														$stmt3->store_result();
														if ($stmt3->num_rows() > 0) {
															// Si existeix algun registre: Obtinc el ID_PREU.
															$stmt3->bind_result($idPreu);
															$stmt3->fetch();
														}
														else {
															$anyCEsc = explode('/',$cursEscolar)[0];
															$anyCEscAnt = intval($anyCEsc)-1;
															$cursEscolarAnt = $anyCEscAnt."/".$anyCEsc;

															$cEsc = $cursEscolarAnt;

															$stmt3->execute();
															$stmt3->store_result();
															if ($stmt3->num_rows() > 0) {
																// Si existeix algun registre: Obtinc el ID_PREU.
																$stmt3->bind_result($idPreu);
																$stmt3->fetch();
															}
														}
														$conWeb3->closeStmt();
													}
													else {
														throw new Exception('',9003);
													}
												}
												$conWeb2->closeStmt();
											}
											else {
												throw new Exception('',9002);
											}
										}
										else {
											//Si no existeix cap registre:
											if ( $stmt2=$conWeb2->prepare( $buscarCursByTitolCurs ) ) {
												$stmt2->bind_param("sd", $nomCurs, $numHores);
												$stmt2->execute();
												$stmt2->store_result();
												if ($stmt2->num_rows() > 0) {
													// Si existeix algun registre: Obtinc el ID_PREU.
													$stmt2->bind_result($codiCurs, $idPreu);
													$stmt2->fetch();
												}
												$conWeb2->closeStmt();
												$public = 0;
											}
											else {
												throw new Exception('',9004);
											}
										}
										$conWeb->closeStmt();
									}
									else {
										throw new Exception('',9005);
									}

								  /* Després de buscar els codi del curs i el id preu de totes les maneres possibles,
								  si no l'hem trobat, es tracta d'un error ja que no disposo de cap més manera per cercar el curs */

								  $typeError = 0;
								  if ( $codiCurs == '' ) {
										$msg = "No es pot associar un codi curs";
										$typeError = 1;
										// $msg .= "No s'ha pogut afegir el curs amb codi GTAF ".$codiGtaf." del curs escolar ".$cursEscolar."<br>";
								  }
								  else if ( $idPreu == 0 ) {
										$msg = "No es pot associar un id preu en el curs";
										$typeError = 2;
										// $msg .= "No s'ha pogut afegir el curs amb codi GTAF ".$codiGtaf." del curs escolar ".$cursEscolar."<br>";
								  }

								  /* A partir de l'any, el codi del curs i del mes, obtinc el id curs. */
								  $idCurs = $any.$codiCurs.$mes;

								  $cntAula++;
							  }
							  else {
								  $typeError = 3;$idAula=0;
							  }

							  /* Omplo els resultats obtinguts a l'array de resultats */
							  $resultats[$cnt]["typeError"] = $typeError;
							  $resultats[$cnt]["cursEscolar"] = $cursEscolar;
							  $resultats[$cnt]["codiGtaf"] = $codiGtaf;
							  $resultats[$cnt]["nomCurs"] = $nomCurs;
							  $resultats[$cnt]["dataI"] = $dataI;
							  $resultats[$cnt]["dataF"] = $dataF;
							  $resultats[$cnt]["aula"] = $aula;
							  $resultats[$cnt]["idAula"] = $idAula;
							  $resultats[$cnt]["any"] = $any;
							  $resultats[$cnt]["mes"] = $mes;
							  $resultats[$cnt]["idCurs"] = $idCurs;
							  $resultats[$cnt]["codiCurs"] = $codiCurs;
							  $resultats[$cnt]["idPreu"] = $idPreu;
							  $resultats[$cnt]["public"] = $public;
							  $resultats[$cnt]["hores"] = $numHores;
							  $resultats[$cnt]["status"] = $codiStatus;

							  // if ( $public == 1 ) {
						  		// 	$colorLabelPublic = "lightGreen";
						  		// 	$nomLabelPublic = "<span class='material-icons mr-1'>visibility</span>SÍ";
						  		// }
						  		// else {
						  		// 	$colorLabelPublic = "lightOrange";
						  		// 	$nomLabelPublic = "<span class='material-icons mr-1'>visibility_off</span>NO";
						  		// }
							  //
							  // $labelVisible = $_SESSION['intranet']->obtenirLabel('', $colorLabelPublic, $nomLabelPublic);
							  //
							  // if ( $typeError == 1 )  {
								//   $respostaError = "Error (1)";
								//   $classResposta = 'lightRed';
							  // }
							  // else if ( $typeError == 2 )  {
								//   $respostaError = "Error (2)";
								//   $classResposta = 'lightRed';
							  // }
							  // else if ( $typeError == 3 )  {
								//   $respostaError = "Existeix";
								//   $classResposta = 'lightGray';
							  // }
							  // else {
								//   $respostaError = "Preparat";
								//   $classResposta = 'lightGreen';
							  // }
							  //
							  // $labelResposta = $_SESSION['intranet']->obtenirLabel('', $classResposta, $respostaError);
							  //
							  // if ( $typeError == 0 ) {
								//   $labelButton = "<div class='d-flex flex-column flex-sm-row justify-content-center align-items-center w-100'>
								// 	  <button id='confirma-pujada-edicions-".$cnt."' class='marcat pujada boto-blau px-4 d-flex'>PUJAR</button>
								//   </div>";
							  // }
							  // else {
								//   $labelButton = $_SESSION['intranet']->obtenirLabel('', $classResposta, 'No es pot pujar');
							  // }
							  //
							  // $resposta->table .= "<tr><td>".$labelResposta."</td><td>".$cursEscolar."</td>";
							  // $resposta->table .= "<td>".$codiGtaf."</td><td>".$codiCurs."</td><td>".$nomCurs."</td>";
							  // $resposta->table .= "<td>".$labelVisible."</td><td>".$labelButton."</td></tr>";
						  	}
				  		}
						$cnt++;
					}
					// $resposta->table .= "</tbody></table>";

				}
				else {
					$resposta->state = 0;
					$resposta->msg = "L'arxiu no disposa del format correcte";
				}
				$conWeb3->desconectarBD();
		      $conWeb2->desconectarBD();
		      $conWeb->desconectarBD();
			}
			fclose($handle);
		}
	}

	if ($orderby != 'creacio' && $orderby!='cursEscolar' && $orderby!='gtaf'  && $orderby!='codiCurs' && $orderby!='nomCurs' && $orderby!='visible' && $orderby!='pujar')
		$orderby = 'creacio';
	if ($asc!=1 and $asc!=0)
		$asc = 1;

	function cmpCreacio($a, $b) {
		if ($a["typeError"] == $b["typeError"]) {
			if ($a["nomCurs"] == $b["nomCurs"]) {
				if ($a["cursEscolar"] == $b["cursEscolar"]) {
					if ($a["codiGtaf"] == $b["codiGtaf"]) {
						return 0;
					}
					return ($a["codiGtaf"] < $b["codiGtaf"]) ? -1 : 1;
				}
				return ($a["cursEscolar"] < $b["cursEscolar"]) ? -1 : 1;
			}
			return ($a["nomCurs"] < $b["nomCurs"]) ? -1 : 1;
		}
		return ($a["typeError"] < $b["typeError"]) ? -1 : 1;
	}

	function cmpCursEscolar($a, $b) {
		if ($a["cursEscolar"] == $b["cursEscolar"]) {
			if ($a["typeError"] == $b["typeError"]) {
				if ($a["nomCurs"] == $b["nomCurs"]) {
					if ($a["codiGtaf"] == $b["codiGtaf"]) {
						return 0;
					}
					return ($a["codiGtaf"] < $b["codiGtaf"]) ? -1 : 1;
				}
				return ($a["nomCurs"] < $b["nomCurs"]) ? -1 : 1;
			}
			return ($a["typeError"] < $b["typeError"]) ? -1 : 1;
		}
		return ($a["cursEscolar"] < $b["cursEscolar"]) ? -1 : 1;
	}

	function cmpCodiGtaf($a, $b) {
		if ($a["codiGtaf"] == $b["codiGtaf"]) {
			if ($a["cursEscolar"] == $b["cursEscolar"]) {
				if ($a["typeError"] == $b["typeError"]) {
					if ($a["nomCurs"] == $b["nomCurs"]) {
						return 0;
					}
					return ($a["nomCurs"] < $b["nomCurs"]) ? -1 : 1;
				}
				return ($a["typeError"] < $b["typeError"]) ? -1 : 1;
			}
			return ($a["cursEscolar"] < $b["cursEscolar"]) ? -1 : 1;
		}
		return ($a["codiGtaf"] < $b["codiGtaf"]) ? -1 : 1;
	}

	function cmpCodiCurs($a, $b) {
		if ($a["codiCurs"] == $b["codiCurs"]) {
			if ($a["typeError"] == $b["typeError"]) {
				if ($a["cursEscolar"] == $b["cursEscolar"]) {
					if ($a["codiGtaf"] == $b["codiGtaf"]) {
						return 0;
					}
					return ($a["codiGtaf"] < $b["codiGtaf"]) ? -1 : 1;
				}
				return ($a["cursEscolar"] < $b["cursEscolar"]) ? -1 : 1;
			}
			return ($a["typeError"] < $b["typeError"]) ? -1 : 1;
		}
		return ($a["codiCurs"] < $b["codiCurs"]) ? -1 : 1;
	}

	function cmpNomCurs($a, $b) {
		if ($a["nomCurs"] == $b["nomCurs"]) {
			if ($a["typeError"] == $b["typeError"]) {
				if ($a["cursEscolar"] == $b["cursEscolar"]) {
					if ($a["codiGtaf"] == $b["codiGtaf"]) {
						return 0;
					}
					return ($a["codiGtaf"] < $b["codiGtaf"]) ? -1 : 1;
				}
				return ($a["cursEscolar"] < $b["cursEscolar"]) ? -1 : 1;
			}
			return ($a["typeError"] < $b["typeError"]) ? -1 : 1;
		}
		return ($a["nomCurs"] < $b["nomCurs"]) ? -1 : 1;
	}

// echo "Resultats<br />";
// print_r($resultats);
	if ($orderby == 'creacio') usort($resultats, "cmpCreacio");
	else if ($orderby == 'cursEscolar') usort($resultats, "cmpCursEscolar");
	else if ($orderby == 'gtaf') usort($resultats, "cmpCodiGtaf");
	else if ($orderby == 'codiCurs') usort($resultats, "cmpCodiCurs");
	else if ($orderby == 'nomCurs') usort($resultats, "cmpNomCurs");

	$resposta->resultats = $resultats;

	if ( count($resultats) > 0 ) {
		$classCreacio = '';
		$classCursEsc = '';
		$classGtaf = '';
		$classCodiCurs = '';
		$classNomCurs = '';

		if ($asc == 1) { //s'ordena ascendentment
			if ($orderby == 'creacio') $classCreacio = ' asc';
			else if ($orderby == 'cursEscolar') $classCursEsc = ' asc';
			else if ($orderby == 'gtaf') $classGtaf = ' asc';
			else if ($orderby == 'codiCurs') $classCodiCurs = ' asc';
			else if ($orderby == 'nomCurs') $classNomCurs = ' asc';
		}
		else { //s'ordena desscendentment
			if ($orderby == 'creacio') $classCreacio = ' desc';
			else if ($orderby == 'cursEscolar') $classCursEsc = ' desc';
			else if ($orderby == 'gtaf') $classGtaf = ' desc';
			else if ($orderby == 'codiCurs') $classCodiCurs = ' desc';
			else if ($orderby == 'nomCurs') $classNomCurs = ' desc';
		}

		$resposta->table = "<table class='table table-striped table-hover table-order text-center mt-2'>";
		$resposta->table .= "<thead><tr>";
		$resposta->table .= "<th id='th-creacio' class='sorting".$classCreacio."'><span style='display: flex;justify-content: center;align-items: center;'>CREACIÓ DEL CURS<span style='display: flex;align-items: center;justify-content: center;'><i class='fa-solid fa-arrow-up-a-z ml-1'></i><i class='fa-solid fa-arrow-down-a-z ml-1'></i></span></span></th>";
		$resposta->table .= "<th id='th-cursEscolar' class='sorting".$classCursEsc."'>CURS ESCOLAR</th>";
		$resposta->table .= "<th id='th-gtaf' class='sorting".$classGtaf."'><span style='display: flex;justify-content: center;align-items: center;'>CODI GTAF<span style='display: flex;align-items: center;justify-content: center;'><i class='fa-solid fa-arrow-up-a-z ml-1'></i><i class='fa-solid fa-arrow-down-a-z ml-1'></i></span></span></th>";
		$resposta->table .= "<th id='th-codiCurs' class='sorting".$classCodiCurs."'>CODI CURS</th>";
		$resposta->table .= "<th id='th-nomCurs' class='sorting".$classNomCurs."'><span style='display: flex;justify-content: center;align-items: center;'>NOM DEL CURS<span style='display: flex;align-items: center;justify-content: center;'><i class='fa-solid fa-arrow-up-a-z ml-1'></i><i class='fa-solid fa-arrow-down-a-z ml-1'></i></span></span></th>";
		$resposta->table .= "<th>VISIBLE (WEB)</th><th>PENJA EL CURS</th></tr>";
		$resposta->table .= "</thead><tbody>";

		if ($asc == 1) {
			$inici = 0;
			$fi = count($resultats);
			for ($i = $inici; $i<$fi; $i++) {

				$cursEscolar = $resultats[$i]["cursEscolar"];
				$codiGtaf = $resultats[$i]["codiGtaf"];
				$codiCurs = $resultats[$i]["codiCurs"];
				$nomCurs = $resultats[$i]["nomCurs"];
				$codiStatus = $resultats[$i]["status"];

				$public = $resultats[$i]["public"];

				if ( $public == 1 ) {
					 $colorLabelPublic = "lightGreen";
					 $nomLabelPublic = "<span class='material-icons mr-1'>visibility</span>SÍ";
				 }
				 else {
					 $colorLabelPublic = "lightOrange";
					 $nomLabelPublic = "<span class='material-icons mr-1'>visibility_off</span>NO";
				 }

				$labelVisible = $_SESSION['intranet']->obtenirLabel('', $colorLabelPublic, $nomLabelPublic);

				$typeError = $resultats[$i]["typeError"];

				if ( $typeError == 1 )  {
					$respostaError = "Error (1)";
					$classResposta = 'lightRed';
				}
				else if ( $typeError == 2 )  {
					$respostaError = "Error (2)";
					$classResposta = 'lightRed';
				}
				else if ( $typeError == 3 )  {
					$respostaError = "Existeix";
					$classResposta = 'lightGray';
				}
				else {
					$respostaError = "Preparat";
					$classResposta = 'lightGreen';
				}

				$labelResposta = $_SESSION['intranet']->obtenirLabel('', $classResposta, $respostaError);

				if ( $typeError == 0 ) {
					$marcat = "marcat";
					if ( $codiStatus == 'P' or $codiStatus == 'A' )
						$marcat = "no_marcat";

					$labelButton = "<div class='d-flex flex-column flex-sm-row justify-content-center align-items-center w-100'>
					   <button id='confirma-pujada-edicions-".$i."' class='".$marcat." pujada boto-blau px-4 d-flex'>PUJAR</button>
					</div>";
				}
				else {
					$labelButton = $_SESSION['intranet']->obtenirLabel('', $classResposta, 'No es pot');
				}

				$resposta->table .= "<tr><td>".$labelResposta."</td><td>".$cursEscolar."</td>";
				$resposta->table .= "<td>".$codiGtaf."</td><td>".$codiCurs."</td><td>".$nomCurs."</td>";
				$resposta->table .= "<td>".$labelVisible."</td><td>".$labelButton."</td></tr>";
			}
		}
		else {
			$inici = count($resultats);
			$fi = 0;
			for ($i = $inici-1; $i>=$fi; $i--) {
				$cursEscolar = $resultats[$i]["cursEscolar"];
				$codiGtaf = $resultats[$i]["codiGtaf"];
				$codiCurs = $resultats[$i]["codiCurs"];
				$nomCurs = $resultats[$i]["nomCurs"];
				$codiStatus = $resultats[$i]["status"];

				$public = $resultats[$i]["public"];

				if ( $public == 1 ) {
					 $colorLabelPublic = "lightGreen";
					 $nomLabelPublic = "<span class='material-icons mr-1'>visibility</span>SÍ";
				 }
				 else {
					 $colorLabelPublic = "lightOrange";
					 $nomLabelPublic = "<span class='material-icons mr-1'>visibility_off</span>NO";
				 }

				$labelVisible = $_SESSION['intranet']->obtenirLabel('', $colorLabelPublic, $nomLabelPublic);

				$typeError = $resultats[$i]["typeError"];

				if ( $typeError == 1 )  {
					$respostaError = "Error (1)";
					$classResposta = 'lightRed';
				}
				else if ( $typeError == 2 )  {
					$respostaError = "Error (2)";
					$classResposta = 'lightRed';
				}
				else if ( $typeError == 3 )  {
					$respostaError = "Existeix";
					$classResposta = 'lightGray';
				}
				else {
					$respostaError = "Preparat";
					$classResposta = 'lightGreen';
				}

				$labelResposta = $_SESSION['intranet']->obtenirLabel('', $classResposta, $respostaError);

				if ( $typeError == 0 ) {
					$marcat = "marcat";
					if ( $codiStatus == 'P' or $codiStatus == 'A' )
						$marcat = "no_marcat";

					$labelButton = "<div class='d-flex flex-column flex-sm-row justify-content-center align-items-center w-100'>
						<button id='confirma-pujada-edicions-".$i."' class='".$marcat." pujada boto-blau px-4 d-flex'>PUJAR</button>
					</div>";
				}
				else {
					$labelButton = $_SESSION['intranet']->obtenirLabel('', $classResposta, 'No es pot');
				}

				$resposta->table .= "<tr><td>".$labelResposta."</td><td>".$cursEscolar."</td>";
				$resposta->table .= "<td>".$codiGtaf."</td><td>".$codiCurs."</td><td>".$nomCurs."</td>";
				$resposta->table .= "<td>".$labelVisible."</td><td>".$labelButton."</td></tr>";
			}
		}
		$resposta->table .= "</tbody></table>";
	}

	$resposta->llegendaErrors[0]["label"] = $_SESSION['intranet']->obtenirLabel('', 'lightGray', "Existeix");
	$resposta->llegendaErrors[0]["explicacio"] = "El curs ja està creat";
	$resposta->llegendaErrors[1]["label"] = $_SESSION['intranet']->obtenirLabel('', 'lightGreen', "Preparat");
	$resposta->llegendaErrors[1]["explicacio"] = "Disposem de totes les dades per crear el curs";
	$resposta->llegendaErrors[2]["label"] = $_SESSION['intranet']->obtenirLabel('', 'lightRed', "Error (1)");
	$resposta->llegendaErrors[2]["explicacio"] = "No es pot crear el curs ja que no s'ha pogut associar un codi curs";
	$resposta->llegendaErrors[3]["label"] = $_SESSION['intranet']->obtenirLabel('', 'lightRed', "Error (2)");
	$resposta->llegendaErrors[3]["explicacio"] = "No es pot crear el curs ja que no s'ha pogut associar un id preu";

	echo json_encode($resposta);

	//Si el fitxer no existeix, generem el fitxer
	if (!$handle = fopen($path_file, "a+")) {
		 echo "Cannot open file analisis-fitxer";
		 exit;
	}
	fclose($handle);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
