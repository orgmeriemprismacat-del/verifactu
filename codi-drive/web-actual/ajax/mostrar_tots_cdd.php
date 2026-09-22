<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../CursSubvencio.php");
include("../CursActualSubvencio.php");
include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

function mostrar_requadre($curs_escolar, $dispositiu){
	$titol = "<h2>Cursos de l'any escolar ".$curs_escolar." que compten per acreditar la CDD</h2>";
	$mostrar = "";
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsCDD="SELECT c.CURS FROM curs as c INNER JOIN informacio as i ON i.CODI_CURS = c.CURS
		WHERE (CDD != 0) AND i.ESTAT=1 AND c.curs_escolar = ? AND c.PUBLIC=1
		GROUP BY c.CURS
		ORDER BY i.TITOL";
	$stmCDD = $connexio->prepare($cnsCDD);
	$stmCDD->bind_param("s", $curs_escolar);
	$anterior = -1;
	$stmCDD->execute();
	$stmCDD->bind_result($codiCurs);
	while($stmCDD->fetch()) {
		$curs = new CursActual($codiCurs, $curs_escolar, $dispositiu);
		$mostrar .= $curs->mostrarCursCDD();
	}
	$connexio->closeStmt();
	$connexio->desconectarBD();

	if ( $mostrar != '' ) {
		$mostrar = $titol.$mostrar;
	}
	return $mostrar;
}

try {
	$dispositiu = $_GET['dispositiu'];

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

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
		$cnsParams = "SELECT VALOR FROM params WHERE TIPUS LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR DATAF >= CURRENT_TIME)";
		$stmtParam=$connexio->prepare($cnsParams);
      $stmtParam->bind_param("s", $parametre);
		$parametre = 'DATAF_MAX_CDD_ANY_ANTERIOR';
      $stmtParam->execute();
      $stmtParam->store_result();
		if ( $stmtParam->num_rows() > 0 ) {
			$stmtParam->bind_result($valor);
			$stmtParam->fetch();
			$date2 = new DateTime($valor);
			$date1 = new DateTime("now");
			$connexio->closeStmt();
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
			$date2 = $date1;
			$curs_escolar1 = $curs_escolar_seguent;
		}
	}
	$connexio->desconectarBD();

	$req1 = mostrar_requadre($curs_escolar1, $dispositiu);

	/* Mostrar el requadre pel curs_escolar1 i curs_escolar2 si existeix */
	if ( $curs_escolar2 != '' ) {
		$mostrar .= "<div class='container py-3 mb-4'>";
		$mostrar .= $req1;
		if ( $req1 != '') $mostrar .= "<div style='height:15px;'></div>";
		$mostrar .= mostrar_requadre($curs_escolar2, $dispositiu);
	}
	else {
		$mostrar .= "<div class='container py-3 mb-4'>";
		$mostrar .= $req1;
	}

	$mostrar .= "</div>";

	echo $mostrar;
}
catch(Exception $e) {
    echo missatgeError($e->getCode());
}

?>
