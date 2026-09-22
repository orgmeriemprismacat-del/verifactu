<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../inc/missatgesError.php');
session_start();

try {

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$idCurs 		= $_POST['idCurs'];
	$nomCurs 	= $_POST['nomCurs'];
	$dataI 		= $_POST['dataI'];
	$dataF 		= $_POST['dataF'];
	$hores 		= $_POST['hores'];
	$cursEsc 	= $_POST['cursEsc'];
	$codiGtaf 	= $_POST['codiGtaf'];
	$codiFiss 	= $_POST['codiFiss'];
	$dataRes 	= $_POST['dataRes'];
	$dataQual 	= $_POST['dataQual'];
	$dataBloq 	= $_POST['dataBloq'];
	$valGtaf 	= $_POST['valGtaf'];
	$valFiss 	= $_POST['valFiss'];
	$obs 			= $_POST['obs'];

	echo $_SESSION['intranet']->desarCanvisDadesEdicio($idCurs, $nomCurs, $dataI,
	$dataF, $hores, $cursEsc, $codiGtaf, $codiFiss, $dataRes, $dataQual, $dataBloq, $valGtaf, $valFiss, $obs);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
