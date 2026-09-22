<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../RegalCurs.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codiCurs = $_GET['codi'];
	$desti = $_GET['desti'];
	$origen = $_GET['origen'];
	$dedicatoria = $_GET['dedicatoria'];
	$hores = $_GET['hores'];
	$preu = $_GET['preu'];
	$percentatge = $_GET['percentatge'];
	$regal = new RegalCurs('ordinador');

	$mostrar = $regal->mostrarFormulariAfortunat($codiCurs, $origen, $desti, $dedicatoria, $hores, $preu, $percentatge);
	// $mostrar = "formulari";
	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
