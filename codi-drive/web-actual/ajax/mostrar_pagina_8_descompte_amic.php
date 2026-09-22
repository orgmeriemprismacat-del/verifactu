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

	$dispositiu = $_GET['dispositiu'];
	$nom = $_GET['nom'];
	$cognoms = $_GET['cognoms'];
	$dni = $_GET['dni'];
	$telefon = $_GET['telefon'];
	$email = $_GET['email'];
	$adreca = $_GET['adreca'];
	$cp = $_GET['cp'];
	$poblacio = $_GET['poblacio'];

	$descompte = unserialize($_SESSION['descompteAmic']);
	$mostrar = $descompte->page8($nom, $cognoms, $dni,
   $telefon, $email, $adreca, $cp, $poblacio);

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
