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
	$codiCurs1 = $_GET['codiCurs1'];
	$any1 = $_GET['any1'];
	$mes1 = $_GET['mes1'];
	$codiCurs2 = $_GET['codiCurs2'];
	$any2 = $_GET['any2'];
	$mes2 = $_GET['mes2'];

	$descompte = unserialize($_SESSION['descompteAmic']);
	$mostrar = $descompte->page6( $codiCurs1, $any1, $mes1, $codiCurs2, $any2, $mes2 );
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
