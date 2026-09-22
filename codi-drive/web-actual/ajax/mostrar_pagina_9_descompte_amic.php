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

	$nom1 = $_GET['nom1'];
	$cognoms1 = $_GET['cognoms1'];
	$dni1 = $_GET['dni1'];
	$telefon1 = $_GET['telefon1'];
	$email1 = $_GET['email1'];
	$adreca1 = $_GET['adreca1'];
	$cp1 = $_GET['cp1'];
	$poblacio1 = $_GET['poblacio1'];

	$nom2 = $_GET['nom2'];
	$cognoms2 = $_GET['cognoms2'];
	$dni2 = $_GET['dni2'];
	$telefon2 = $_GET['telefon2'];
	$email2 = $_GET['email2'];
	$adreca2 = $_GET['adreca2'];
	$cp2 = $_GET['cp2'];
	$poblacio2 = $_GET['poblacio2'];

	$descompte = unserialize($_SESSION['descompteAmic']);
	$mostrar = $descompte->page9($nom1, $cognoms1, $dni1, $telefon1, $email1, $adreca1, $cp1, $poblacio1,
	$nom2, $cognoms2, $dni2, $telefon2, $email2, $adreca2, $cp2, $poblacio2);

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
