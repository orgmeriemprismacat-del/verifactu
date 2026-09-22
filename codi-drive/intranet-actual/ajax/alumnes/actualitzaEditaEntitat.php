<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../ConnexioMoodle.php');
include ('../../ConnexioMoodleAntic.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');
session_start();

try {

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$id 		= $_GET['idEntitat'];
	$rao 		= $_GET['rao'];
	$cif 		= $_GET['cif'];
	$adreca 	= $_GET['adreca'];
	$cp 		= $_GET['cp'];
	$poblacio = $_GET['poblacio'];
	$nomResp	= $_GET['nomResp'];
	$cogResp = $_GET['cognomResp'];
	$correu 	= $_GET['correuResp'];

	echo $_SESSION['intranet']->actualitzaEditaEntitat_Alumnes($id, $rao, $cif,
	$adreca, $cp, $poblacio, $nomResp, $cogResp, $correu);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	// $_SESSION['usuari'] = null;
	// $_SESSION['intranet'] = null;
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
