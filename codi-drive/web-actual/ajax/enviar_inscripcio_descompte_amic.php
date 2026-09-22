<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Numero.php');
include('../DescompteAmic.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
session_start();

try {

	$dniPagador = $_GET['dniPagador'];
	$perfil1 = $_GET['perfil1'];
	$perfil2 = $_GET['perfil2'];
	$titulacio1 = $_GET['titulacio1'];
	$titulacio2 = $_GET['titulacio2'];
	$comentaris1 = $_GET['comentaris1'];
	$comentaris2 = $_GET['comentaris2'];
	$conegut1 = $_GET['conegut1'];
	$conegut2 = $_GET['conegut2'];

	$descompte = unserialize($_SESSION['descompteAmic']);
	$mostrar = $descompte->enviaDades($dniPagador, $perfil1, $perfil2,
	$titulacio1, $titulacio2, $comentaris1, $comentaris2, $conegut1, $conegut2);
	$_SESSION['descompteAmic'] = serialize($descompte);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
