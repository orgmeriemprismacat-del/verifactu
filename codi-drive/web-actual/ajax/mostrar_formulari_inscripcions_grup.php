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
	$nomCentreContacte = $_GET['nomCentre'];
	$nomPersonaContacte = $_GET['nom'];
	$cognomsPersonaContacte = $_GET['cognoms'];
	$dniPersonaContacte = $_GET['dni'];
	$telefonPersonaContacte = $_GET['telefon'];
	$emailPersonaContacte = $_GET['email'];
	$adrecaPersonaContacte = $_GET['adreca'];
	$cpPersonaContacte = $_GET['cp'];
	$poblacioPersonaContacte = $_GET['poble'];

	$dadesContacte = [
		$nomCentreContacte,
		$nomPersonaContacte,
		$cognomsPersonaContacte,
		$dniPersonaContacte,
		$telefonPersonaContacte,
		$emailPersonaContacte,
		$adrecaPersonaContacte,
		$cpPersonaContacte,
		$poblacioPersonaContacte
	];

	// echo var_dump($_SESSION['descompteGrup']);

	// $descompteGrup = unserialize($_SESSION['descompteGrup']);

	$descompteGrup = new DescompteGrup('ordinador');
	$mostrar = $descompteGrup->mostraFormulariInscripcionsGrup($codi, $tipusInsc, $dadesContacte);

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
