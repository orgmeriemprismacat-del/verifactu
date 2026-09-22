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

	$descompteGrup = new DescompteAmic($dispositiu);
	$mostrar = $descompteGrup->page1();

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
