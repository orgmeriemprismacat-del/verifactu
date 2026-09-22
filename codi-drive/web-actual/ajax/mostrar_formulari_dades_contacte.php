<?php
session_start();
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Numero.php');
include('../DescompteGrup.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codi = $_GET['codi'];
	$tipusInsc = $_GET['tipusInsc'];
	$dispositiu = $_GET['dispositiu'];
	$nomCentre = $_GET['nomCentre'];
	$nom = $_GET['nom'];
	$cognoms = $_GET['cognoms'];
	$dni = $_GET['dni'];
	$telefon = $_GET['telefon'];
	$email = $_GET['email'];
	$adreca = $_GET['adreca'];
	$cp = $_GET['cp'];
	$poblacio = $_GET['poblacio'];

	$descompteGrup = unserialize($_SESSION['descompteGrup']);

	// $descompteGrup = new DescompteGrup($dispositiu);
	$mostrar = $descompteGrup->mostraFormulariDadesContacte($codi, $tipusInsc, $nomCentre,
					$nom, $cognoms, $dni, $telefon, $email, $adreca, $cp, $poblacio);

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
