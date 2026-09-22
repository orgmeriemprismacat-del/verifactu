<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Numero.php');
include('../DescompteGrup.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

// session_start();
// echo session_id();
try {

	$dispositiu = $_GET['dispositiu'];

	$descompteGrup = new DescompteGrup($dispositiu);
	$mostrar = $descompteGrup->retornarPaginaInicialDescompte();

	// $_SESSION['descompteGrup'] = serialize($descompteGrup);

	// var_dump($_SESSION['descompteGrup']);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
