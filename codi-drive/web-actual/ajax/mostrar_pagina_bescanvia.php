<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../BescanviaRegal.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$dispositiu = $_GET['dispositiu'];
	$regal = new BescanviaRegal($dispositiu);
	$mostrar = $regal->mostrarPaginaBescanviaRegal();
	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
