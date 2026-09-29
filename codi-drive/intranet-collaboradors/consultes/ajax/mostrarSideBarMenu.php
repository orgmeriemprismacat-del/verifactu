<?php
require('../../../config.php');
include ('../../ConnexioIntranetTutor.php');
include ('../../ConnexioWeb.php');
include ('../../IntranetTutor.php');
include ('../../Text.php');
include ('../inc/missatgesError.php');

try {
	$path = "https://campus.prisma.cat";
	$rols = "4"; //Buscar si es tutor/a
	$nameuser = $USER->username;

	$urlAct = $_GET['url'];
	$nItem = 0;

	$mes_actual = intval(date('m'));
	$any_actual = intval(date('Y'));

	/* Busquem els registres de la taula apartats de la BD intranet de nivell 1.
	Per cada registre de nivell 1 que existeix
	 	es comprovara si és visible per el rol actual.
			si és visible, busquem tots els registres de nivell 2 que existeixi.
				Per cada registre de nivell 2 que existeixi,
					si és visible per el rol actual, busquem tots els registres de nivell 3 que existeixi
						...
	*/
	$mostraEstatLaboral = 0;

	$connexioWeb = new ConnexioWeb();
	$connexioWeb->connectarBD();
	$connexioIntra = new ConnexioIntranetTutor();
	$connexioIntra->connectarBD();

	$mostrar = '';

	$cnsAP = "SELECT ID, ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR
				 FROM apartats_tutor WHERE NIVELL = ? AND ID_NIVELL_PARE = ? ORDER BY ORDRE";
	if ( $stmtIntra = $connexioIntra->prepare($cnsAP) ) {
		$stmtIntra->bind_param("dd", $nivell, $idAp);
		$nivell = 1;
		$idAp = 0;
		$stmtIntra->execute();
		$stmtIntra->bind_result($id, $icona, $nom, $nivell, $url, $nivellPare, $rolsVis);
		while ( $stmtIntra->fetch() ) {
			$arrRolsVis = explode("|", $rolsVis);
			$i=0; $trobat=false;
			while ($i<count($rols) && !$trobat) {
				$j = 0;
				while ($j<count($arrRolsVis) && !$trobat) {
					if ( $rols[$i] == $arrRolsVis[$j] ) $trobat=true;
					$j++;
				}
				$i++;
			}
			if ($trobat) {
				$connexioIntra2 = new ConnexioIntranetTutor();
				$connexioIntra2->connectarBD();

				$stmtIntra2=$connexioIntra2->prepare($cnsAP);
				$stmtIntra2->bind_param("dd", $nivell, $id);
				$nivell = 2;
				$stmtIntra2->execute();
				$stmtIntra2->store_result();
				if ($stmtIntra2->num_rows() > 0) {
					$mostrar .= "<li class='nav-item";
					if ($urlAct==$url) $mostrar .= " active";
					$mostrar .= "' data-nitem='".$nItem."'>
						<a data-toggle='collapse' class='nav-link collapsed' href='#item".$id."' aria-expanded='false'>
							".$icona."
							<p>".$nom."</p>
							<b class='caret'></b>
						</a>
						<div class='collapse' id='item".$id."' style=''>
						<ul class='nav'>";
					$nItem++;
					$stmtIntra2->bind_result($id, $icona, $nom, $nivell, $url, $nivellPare, $rolsVis);
					while ($stmtIntra2->fetch()) {
						$arrRolsVis = explode("|", $rolsVis);
						$i=0; $trobat=false;
						while ($i<count($rols) && !$trobat) {
							$j = 0;
							while ($j<count($arrRolsVis) && !$trobat) {
								if ( $rols[$i] == $arrRolsVis[$j] ) $trobat=true;
								$j++;
							}
							$i++;
						}
						if ($trobat) {
							$connexioIntra3 = new ConnexioIntranetTutor();
							$connexioIntra3->connectarBD();

							$stmtIntra3=$connexioIntra3->prepare($cnsAP);
							$stmtIntra3->bind_param("dd", $nivell, $id);
							$nivell = 3;
							$stmtIntra3->execute();
							$stmtIntra3->store_result();
							if ($stmtIntra3->num_rows() > 0) {
								$mostrar .= "<li class='nav-item";
								if ($urlAct==$url) $mostrar .= " active";

								if ( $menuExt == 1 ) {
									$icona = "<span class='material-icons ml-1 mr-2'>remove</span>";
								}
								$mostrar .= "' data-nitem='".$nItem."'>
									<a data-toggle='collapse' class='nav-link collapsed' href='#item".$id."' aria-expanded='false'>
									".$icona."
									<p>".$nom."</p>
									<b class='caret'></b>
									</a>
									<div class='collapse' id='item".$id."' style=''>
									<ul class='nav'>";
								$nItem++;
								$stmtIntra3->bind_result($id, $icona, $nom, $nivell, $url, $nivellPare, $rolsVis);
								while ($stmtIntra3->fetch()) {
									$arrRolsVis = explode("|", $rolsVis);
									$i=0; $trobat=false;
									while ($i<count($rols) && !$trobat) {
										$j = 0;
										while ($j<count($arrRolsVis) && !$trobat) {
											if ( $rols[$i] == $arrRolsVis[$j] ) $trobat=true;
											$j++;
										}
										$i++;
									}
									if ($trobat) {
										$mostrar .= "<li class='nav-item";
										if ($urlAct==$url) $mostrar .= " active";
										if ( $menuExt == 1 ) {
											$icona = "<span class='material-icons ml-1 mr-2'>remove</span>";
										}
										$mostrar .= "' data-nitem='".$nItem."'>
											<a class='nav-link' href='".$path.$url."'>
												".$icona."
												<span class='sidebar-normal'>".$nom."</span>
											</a>
										</li>";
										$nItem++;
									}
								}
								$mostrar .= "</ul>
									</div>
								</li>";
							}
							else {
								$mostrar .= "<li class='nav-item";
								if ($urlAct==$url) $mostrar .= " active";
								if ( $menuExt == 1 ) {
									$icona = "<span class='material-icons ml-1 mr-2'>remove</span>";
								}
								$mostrar .= "' data-nitem='".$nItem."'>
									<a class='nav-link' href='".$path.$url."'>
										".$icona."
										<span class='sidebar-normal'>".$nom."</span>
									</a>
								</li>";
								$nItem++;
							}
							$connexioIntra3->closeStmt();
							$connexioIntra3->desconectarBD();
						}
					}
					$mostrar .= "</ul>
						</div>
					</li>";
				}
				else {
					$mostraEstatLaboral = 1;
					if ( $nom == "Estat laboral" ) {

						if ( $nameuser == "36557520" || $nameuser == "45171998" || $nameuser == "77618906" ) $mostraEstatLaboral = 0;
						else {
							$cnsJub = "SELECT e.ID FROM personal AS p INNER JOIN estat AS e ON p.DNI = e.DNI
							WHERE p.DNI LIKE ? AND SITUACIO = ?";
							$stmtWeb=$connexioWeb->prepare($cnsJub);
							$dniTutor = "%".$nameuser."%";
							$situacio = 'Estic jubilat/da.';
							$stmtWeb->bind_param("ss", $dniTutor, $situacio);
							$stmtWeb->execute();
							$stmtWeb->store_result();
							if ($stmtWeb->num_rows() > 0) { // no hi ha cap registre de jubilaci�
								$mostraEstatLaboral = 0;
							}
							$connexioWeb->closeStmt();

							if ( $mostraEstatLaboral ) {
								$cnsPortaCurs = "SELECT MES FROM cursos WHERE DNI_TUTOR LIKE ? AND ANY = ?";
								$stmtWeb=$connexioWeb->prepare($cnsPortaCurs);
								$dniTutor = "%".$nameuser."%";
								$anyAct = $any_actual;
								$stmtWeb->bind_param("sd", $dniTutor, $anyAct);
								$stmtWeb->execute();
								$stmtWeb->store_result();
								if ($stmtWeb->num_rows() > 0) { // no hi ha cap registre de jubilaci�
									$stmtWeb->bind_result($mesPortaCurs);
									$portaCursTrobat = 0;
									while ( $stmtWeb->fetch() && !$portaCursTrobat) {
										$numMesPortaCurs = intval($mesPortaCurs);
										if ($mes_actual <= 6) // (mesos 1-6)
										{
											if ( $numMesPortaCurs >= 1 && $numMesPortaCurs <= 6 ) $portaCursTrobat = 1;
										}
										else {
											if ( $numMesPortaCurs >= 7 && $numMesPortaCurs <= 12 ) $portaCursTrobat = 1;
										}
									}
								}
								$connexioWeb->closeStmt();

								if ( $portaCursTrobat ) {
									$cnsAssigCursSemAct = "SELECT DISTINCT e.ID FROM personal AS p INNER JOIN estat AS e
									ON p.DNI = e.DNI INNER JOIN cursos AS c ON c.DNI_TUTOR = p.DNI
									WHERE (DATA BETWEEN ? AND ?) AND ((CAST(MES AS SIGNED) >= ?) AND (CAST(MES AS SIGNED) <= ?))
									AND ANY=? AND DNI_TUTOR LIKE ? ORDER BY DATA";
									$stmtWeb=$connexioWeb->prepare($cnsAssigCursSemAct);
									$stmtWeb->bind_param("ssddds", $dataIniciSem, $dataFiSem, $mesSigned1, $mesSigned2, $anyAct, $dniTutor);
									$anyAct = $any_actual;
									$dniTutor = "%".$nameuser."%";
									if ($mes_actual <= 6) // (mesos 1-6)
									{
										$dataIniciSem = $anyAct."-01-01";
										$dataFiSem = $anyAct."-06-30";
										$mesSigned1 = 1;
										$mesSigned2 = 6;
									}
									else {
										$dataIniciSem = $anyAct."-07-01";
										$dataFiSem = $anyAct."-12-31";
										$mesSigned1 = 7;
										$mesSigned2 = 12;
									}
									$stmtWeb->execute();
									$stmtWeb->store_result();
									if ($stmtWeb->num_rows() <= 0) {
										//si t� pendent enviar certificat estat laboral
										$mostraEstatLaboral = 1;
									}
									else {
										$mostraEstatLaboral = 0;
									}
									$connexioWeb->closeStmt();
								}
								else {
									$mostraEstatLaboral = 0;
								}
							}

						}
					}

					if ( $nom != "Estat laboral" || ( $nom == "Estat laboral" && $mostraEstatLaboral ) ) {
						$mostrar .= "<li class='nav-item";
						if ($urlAct==$url) $mostrar .= " active";
						$mostrar .= "' data-nitem='".$nItem."'>
							<a class='nav-link' href='".$path.$url."'>
								".$icona."
								<p>".$nom."</p>
							</a>
						</li>";
						$nItem++;
					}
				}
				$connexioIntra2->closeStmt();
				$connexioIntra2->desconectarBD();
			}
		}
		$connexioIntra->closeStmt();
	}
	else {
		throw new Exception('', 9014);
	}

	$connexioIntra->desconectarBD();
	$connexioWeb->desconectarBD();
}
catch(Exception $e) {
	echo missatgeError($e->getCode());
}

echo $mostrar;

 ?>
