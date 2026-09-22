<?php
session_start();

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../ConnexioMoodle.php');
include ('../../ConnexioMoodleAntic.php');
include ('../../Text.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');

try {
	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$idInsc 			= $_POST['idInsc'];
	$motiuBaixa 	= $_POST['motiu'];

	$mostrar = 	$_SESSION['intranet']->updateSendMsg_Facturacio_Baixes_Segona_Setmana($idInsc, $motiuBaixa);

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
