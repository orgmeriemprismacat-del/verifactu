<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../Imatge.php');
include('../Video.php');
include('../Tutor.php');
include('../Trobada.php');
include('../Trobades.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
session_start();

try {
	$_SESSION['trobades'] = unserialize($_SESSION['trobades']);

	$email 			= $_GET['email'];
	$mailingCursos = $_GET['mailingCursos'];

	$mostrar = $_SESSION['trobades']->addMailingAllTrobades($email);

	$_SESSION['trobades'] = serialize( $_SESSION['trobades'] );

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
