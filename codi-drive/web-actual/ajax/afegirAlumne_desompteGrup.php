<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Numero.php');
include('../DescompteGrup.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
session_start();

try {
	$nom = $_GET['nom'];
	$cog = $_GET['cog'];
	$dni = $_GET['dni'];
	$tel = $_GET['tel'];
	$email = $_GET['email'];
	$adreca = $_GET['adreca'];
	$cp = $_GET['cp'];
	$poble = $_GET['poble'];
	$perfil = $_GET['perfil'];
	$titulacio = $_GET['titulacio'];
	$comentaris = $_GET['comentaris'];

	$descompteGrup = unserialize($_SESSION['descompteGrup']);

	$mostrar = $descompteGrup->afegirDadesAlumne($nom, $cog, $dni, $tel,
	$email, $adreca, $cp, $poble, $perfil, $titulacio, $comentaris);

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
