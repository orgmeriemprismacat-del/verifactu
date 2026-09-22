<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Imatge.php');
include('../Video.php');
include('../Tutor.php');
include('../Trobada.php');
include('../Trobades.php');
include('../Mail.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
session_start();

try {

	$urlAct = $_GET['url'];
	$dispositiu = $_GET['dispositiu'];

	$idUrlAct = buscarPagina($urlAct);

   $trobada = new Trobada($idUrlAct, $dispositiu);

	$_SESSION['trobada'] = serialize($trobada);

	$mostrar = $trobada->retornarPaginaUnaTrobada();

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
