<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../RegalCurs.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$hores = $_GET['hores'];
	$dispositiu = $_GET['dispositiu'];

	$regal = new RegalCurs($dispositiu);
	$mostrar = $regal->mostraCursos($hores);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
