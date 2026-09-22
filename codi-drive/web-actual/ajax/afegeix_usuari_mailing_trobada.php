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
	$_SESSION['trobada'] = unserialize($_SESSION['trobada']);

	$email 			= $_GET['email'];

	$mostrar = $_SESSION['trobada']->addMailingTrobada($email);

	$_SESSION['trobada'] = serialize( $_SESSION['trobada'] );

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
