<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');
session_start();

try {
	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$any 			= $_GET['any'];
	$mes 			= $_GET['mes'];
	$codiCurs 	= $_GET['codiCurs'];
	$idCurs 		= $_GET['idCurs'];
	$aula 		= $_GET['aula'];
	$idCuho 		= $_GET['idCuho'];

	$mostrar = 	$_SESSION['intranet']->mostrarModal_PreviIniciCursos_AssignarTutors($codiCurs, $idCurs, $aula, $idCuho, $any, $mes);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

	echo $mostrar;
}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
