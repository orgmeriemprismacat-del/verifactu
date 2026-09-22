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

	$cursEscolar = $_POST['cursEscolar'];
	$codiGtaf 	= $_POST['codiGtaf'];
	$nomCurs 	= $_POST['nomCurs'];
	$dataI 		= $_POST['dataI'];
	$dataF 		= $_POST['dataF'];
	$aula 		= $_POST['aula'];
	$idAula 		= $_POST['idAula'];
	$any 			= $_POST['any'];
	$mes 			= $_POST['mes'];
	$idCurs 		= $_POST['idCurs'];
	$codiCurs 	= $_POST['codiCurs'];
	$idPreu 		= $_POST['idPreu'];
	$public 		= $_POST['public'];
	$hores 		= $_POST['hores'];
	$dataRes 		= $_POST['dataRes'];

	$mostrar = 	$_SESSION['intranet']->insertCurs($idCurs, $any, $mes, $codiCurs,
	$cursEscolar, $codiGtaf, $nomCurs, $dataI, $dataF, $aula, $idAula, $idPreu, $public, $hores, $dataRes);

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
