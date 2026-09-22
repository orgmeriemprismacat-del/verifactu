<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Curs.php");
include("../CursActual.php");
include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

function mostrar_requadre($curs_escolar, $dispositiu){
	$mostrar = "<h2>Cursos de l'any escolar ".$curs_escolar." que compten per acreditar perfil</h2>";
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsPerfils="SELECT i.CODI_CURS, p.ID_PERFIL, pl.NOM, pl.NHORES, pl.NCURT
		FROM (perfils AS p INNER JOIN informacio AS i ON p.CODI = i.CODI_CURS)
		INNER JOIN perfils_list AS pl ON pl.ID = p.ID_PERFIL
		WHERE CURS_ESCOLAR=? AND i.ESTAT=1 AND i.TIPUS_CURS = 'N' GROUP BY p.ID_PERFIL, p.CODI, i.TITOL
		ORDER BY p.ID_PERFIL DESC, i.TITOL";
	$stmPerfils = $connexio->prepare($cnsPerfils);
	$stmPerfils->bind_param("s", $curs_escolar);
	$anterior = -1;
	$stmPerfils->execute();
	$stmPerfils->bind_result($codiCurs, $idPerf, $nomPerf, $horesPerf, $nomPerfilCurt);
	while($stmPerfils->fetch()) {
		if ($anterior != $idPerf) {
			$textClassPerfil = new Text($nomPerfilCurt);
         $textClassPerfil->obtenirNomCurt();
         $classPerfil = $textClassPerfil->obtenirText();

			$textExperiencia = "";
			if ( $idPerf == 7 ) $textExperiencia = " i experiència";

			$mostrar .= "<h3 class='titol-perfil font-weight-normal mt-2'><div class='cnt-icona-titol'>";
			$mostrar .= "<i class='fas fa-square titol float-left'></i></div>";
			$mostrar .= "<strong class=".$classPerfil.">".$nomPerf."</strong> ";
			$mostrar .= "(cal acreditar ".$horesPerf." hores de formació permanent".$textExperiencia.")</h3>";
		}
		$curs = new CursActual($codiCurs, $curs_escolar, $dispositiu);
		$mostrar .= $curs->mostrarCursPerfil();
		// $mostrar .= $curs->crearTastet();
		$anterior = $idPerf;
	}
	$connexio->closeStmt();
	$connexio->desconectarBD();

	return $mostrar;
}

try {
	$dispositiu = $_GET['dispositiu'];

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();
	$connexio2 = new ConnexioBBDDSTMT();
	$connexio2->connectarBD();

	setlocale(LC_ALL,"es_ES");
	$num_mes_actual = date("m");
	$num_any_actual = date("Y");

	$any_anterior = date("Y") - 1;
	$any_seguent = date("Y") + 1;

	$curs_escolar_actual = $any_anterior."/".date("Y");
	$curs_escolar_seguent = date("Y")."/".$any_seguent;

	$curs_escolar1 = '';
	$curs_escolar2 = '';

	if ($num_mes_actual>=1 && $num_mes_actual<6)
		$curs_escolar1 = $curs_escolar_actual;
	else {
		/* Si exiteix algun curs amb perfil del curs_escolar_seguent */
		$cnsPerfil = "SELECT ID FROM perfils WHERE CURS_ESCOLAR LIKE ?";
		$stmtPerfil=$connexio->prepare($cnsPerfil);
      $stmtPerfil->bind_param("s", $curs_escolar_seguent);
      $stmtPerfil->execute();
      $stmtPerfil->store_result();
		if ( $stmtPerfil->num_rows() > 0 ) {

			$cnsParams = "SELECT VALOR FROM params WHERE TIPUS LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR DATAF >= CURRENT_TIME)";
			$stmtParam=$connexio2->prepare($cnsParams);
	      $stmtParam->bind_param("s", $parametre);
			$parametre = 'DATAF_MAX_PERFILS_ANY_ANTERIOR';
	      $stmtParam->execute();
	      $stmtParam->store_result();
			if ( $stmtParam->num_rows() > 0 ) {
				$stmtParam->bind_result($valor);
				$stmtParam->fetch();
				$date2 = new DateTime($valor);
			}
			else {
				$date2 = $date1;
			}
			$connexio2->closeStmt();
			$date1 = new DateTime("now");
			$diff = $date1->diff($date2);

			if ( $num_mes_actual>=6 && ($num_mes_actual<=8 || $diff->invert == 0) ) {
				$curs_escolar1 = $curs_escolar_seguent;
				$curs_escolar2 = $curs_escolar_actual;
			}
			else {
				$curs_escolar1 = $curs_escolar_seguent;
			}
		}
		else {
			$curs_escolar1 = $curs_escolar_actual;
		}
		$connexio->closeStmt();
	}
	$connexio2->desconectarBD();
	$connexio->desconectarBD();

	/* Mostrar el requadre pel curs_escolar1 i curs_escolar2 si existeix */
	$mostrar .= "<div class='container py-3 mb-4'>";
	$mostrar .= mostrar_requadre($curs_escolar1, $dispositiu);
	if ($curs_escolar2 != '') {
		$mostrar .= "<div style='height:15px;'></div>";
		$mostrar .= mostrar_requadre($curs_escolar2, $dispositiu);
	}
	$mostrar .= "</div>";



	echo $mostrar;
}
catch(Exception $e) {
    echo missatgeError($e->getCode());
}

?>
