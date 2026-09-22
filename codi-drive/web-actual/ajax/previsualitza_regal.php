<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../RegalCurs.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codiCurs = $_GET['codi'];
	$estil = $_GET['estil'];
	$codiRegal = $_GET['codiRegal'];
	$desti = $_GET['desti'];
	$origen = $_GET['origen'];
	$dedicatoria = $_GET['dedicatoria'];
	$dispositiu = $_GET['dispositiu'];

	$regal = new RegalCurs($dispositiu);
	$mostrar = $regal->mostrarPrevisualitzacio($codiCurs, $estil, $codiRegal, $origen, $desti, $dedicatoria);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
