<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../RegalCurs.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codiCurs = $_GET['codi'];

	$regal = new RegalCurs('ordinador');

	$mostrar = $regal->obtenirPreuHoresNomCursRegal($codiCurs);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
