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

	$any 			= $_POST['any'];
	$mes 			= $_POST['mes'];
	$codiCurs 	= $_POST['codiCurs'];
	$aula 		= $_POST['aula'];

	$mostrar = 	$_SESSION['intranet']->updCursos_Tutors_ConfirmacioTutoritzacio($any, $mes, $codiCurs, $aula);

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
