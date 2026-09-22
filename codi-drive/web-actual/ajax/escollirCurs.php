<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../BescanviaRegal.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codiCurs = $_GET['codiCurs'];
	$modalitat = $_GET['modalitat'];
	$dispositiu = $_GET['dispositiu'];

	$bescanvia = new BescanviaRegal($dispositiu);
	$mostrar = $bescanvia->mostrarCursos($modalitat,$codiCurs,true);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
