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

	$nomPost 	= $_POST['nomPost'];
	$tipusPost 	= $_POST['tipusPost'];
	$descPost 	= $_POST['descPost'];
	$any 			= $_POST['any'];
	$mes 			= $_POST['mes'];
	$dia 			= $_POST['dia'];

	$mostrar = 	$_SESSION['intranet']->insertPublicacio_Xarxes_Mensual(
		$nomPost, $tipusPost, $descPost, $any, $mes, $dia);

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
