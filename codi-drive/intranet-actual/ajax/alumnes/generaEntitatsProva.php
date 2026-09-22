<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');
session_start();

try {

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$rao 		= $_POST['rao'];
	$cif 		= $_POST['cif'];
	$adreca 	= $_POST['adreca'];
	$cp 		= $_POST['cp'];
	$poble 	= $_POST['poblacio'];
	$nomResp	= $_POST['nomResp'];
	$cogResp = $_POST['cognomResp'];
	$correu 	= $_POST['correuResp'];

	echo $_SESSION['intranet']->creaEmpresa_Alumnes($rao, $cif, $adreca, $cp, $poble, $nomResp, $cogResp, $correu);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
