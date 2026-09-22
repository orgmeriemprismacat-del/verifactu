<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../DescompteGrup.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
session_start();

try {
	$hores = $_GET['hores'];
	$dispositiu = $_GET['dispositiu'];

	$descompteGrup = unserialize($_SESSION['descompteGrup']);

	$descompteGrup = new DescompteGrup($dispositiu);
	$mostrar = $descompteGrup->mostraCursos($hores);

	$_SESSION['descompteGrup'] = serialize($descompteGrup);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
