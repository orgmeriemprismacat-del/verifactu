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
	$hores 		= $_GET['hores'];
	$curs 		= $_GET['curs'];
	$aula 		= $_GET['aula'];

	$mostrar = 	$_SESSION['intranet']->mostrarTable_Alumnes_EnviarMsgCursSuperat_Curs_aula($any, $mes, $curs, $aula);

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
