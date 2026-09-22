<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../BescanviaRegal.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codiRegal = $_GET['codiRegal'];
	$dispositiu = $_GET['dispositiu'];

	$bescanvia = new BescanviaRegal($dispositiu);
	$mostrar = $bescanvia->buscarCursRegalat($codiRegal);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
