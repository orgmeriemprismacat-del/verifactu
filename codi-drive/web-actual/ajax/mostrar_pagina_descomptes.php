<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Numero.php');
include('../Imatge.php');
include('../PaginaDescomptes.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
session_start();

try {

	$dispositiu = $_GET['dispositiu'];

	$pagina = new PaginaDescomptes($dispositiu);
	$mostrar = $pagina->mostrarPagina();

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
