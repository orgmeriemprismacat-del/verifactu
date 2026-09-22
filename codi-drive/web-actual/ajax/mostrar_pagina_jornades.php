<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Imatge.php');
include('../Video.php');
include('../Tutor.php');
include('../Jornada.php');
include('../Jornades.php');
include('../Mail.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
session_start();

try {

	$dispositiu = $_GET['dispositiu'];

	$jornades = new Jornades($dispositiu);
	$_SESSION['jornades'] = serialize($jornades);

	// $mostrar = $jornades->vistaPaginaJornades();

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
	else if ($e->getCode()==302)
      echo mostrarPagina302();
   else
      echo missatgeError($e->getCode());
}

?>
