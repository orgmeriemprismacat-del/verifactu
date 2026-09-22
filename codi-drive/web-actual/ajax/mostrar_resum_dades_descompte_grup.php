<?php
session_start();
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Numero.php');
include('../Edicio.php');
include('../DescompteGrup.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codi = $_GET['codi'];
	$tipusInsc = $_GET['tipusInsc'];
	$numAlumnes = $_GET['numAlumnes'];
	$dates = $_GET['dates'];
	$conegut = $_GET['conegut'];
	$any = $_GET['any'];
	$edicio = $_GET['edicio'];

	$descompteGrup = unserialize($_SESSION['descompteGrup']);
	$mostrar = $descompteGrup->mostraResumDades($codi, $numAlumnes, $tipusInsc, $dates, $any, $edicio, $conegut);

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
