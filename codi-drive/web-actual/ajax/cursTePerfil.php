<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Curs.php");

$edicio = $_GET['edicio'];
$any = $_GET['any'];
$codi = $_GET['curs'];

try {
	$mostrar = 'false';

	$curs = new Curs($codi, '');
	if ($curs->obtenirPerfil(0)!=null)
		$mostrar='true';

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
