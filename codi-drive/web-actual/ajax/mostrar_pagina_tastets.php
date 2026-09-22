<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Imatge.php');
include('../Video.php');
include('../Tastet.php');
include('../Tastets.php');
include('../Mail.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
session_start();

try {

	$dispositiu = $_GET['dispositiu'];

	$tastets = new Tastets($dispositiu);
	$_SESSION['tastets'] = serialize($tastets);

	$mostrar = $tastets->retornarPaginaTastets();

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
