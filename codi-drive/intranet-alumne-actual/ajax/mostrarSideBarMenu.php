<?php
require('../../config.php');

include ('../ConnexioIntranet.php');
include ('../ConnexioWeb.php');
include ('../Text.php');
include ('../Usuari.php');
include ('../inc/missatgesError.php');
session_start();

try {
	$objUsuari = unserialize($_SESSION['objUsuariMdl']);

	$path = "https://campus.prisma.cat";
	$rols = $objUsuari->getRols();
	$nameuser = $objUsuari->getUsuari()->get();

	$urlAct = $_GET['url'];
	$nItem = 0;

	/* Busquem els registres de la taula apartats de la BD intranet de nivell 1.
	Per cada registre de nivell 1 que existeix
	 	es comprovara si és visible per el rol actual.
			si és visible, busquem tots els registres de nivell 2 que existeixi.
				Per cada registre de nivell 2 que existeixi,
					si és visible per el rol actual, busquem tots els registres de nivell 3 que existeixi
						...
	*/
	$connexioIntra = new ConnexioIntranet();
	$connexioIntra->connectarBD();

	$mostrar = '';

	$cnsAP = "SELECT ID, ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR
				 FROM apartats WHERE NIVELL = ? AND ID_NIVELL_PARE = ? ORDER BY ORDRE";
	$stmtIntra=$connexioIntra->prepare($cnsAP);
	$stmtIntra->bind_param("dd", $nivell, $idAp);
	$nivell = 1;
	$idAp = 0;
	$stmtIntra->execute();
	$stmtIntra->bind_result($id, $icona, $nom, $nivell, $url, $nivellPare, $rolsVis);
	while ($stmtIntra->fetch()) {
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
			$connexioIntra2 = new ConnexioIntranet();
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
						$connexioIntra3 = new ConnexioIntranet();
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
			$connexioIntra2->closeStmt();
			$connexioIntra2->desconectarBD();
		}
	}
	$connexioIntra->closeStmt();
	$connexioIntra->desconectarBD();
}
catch(Exception $e) {
	echo missatgeError($e->getCode());
}

echo $mostrar;

 ?>
