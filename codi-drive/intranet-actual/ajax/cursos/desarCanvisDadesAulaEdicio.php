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

	$idCurs 			= $_POST['idCurs'];
	$idAula 			= $_POST['idAula'];
	$aula		 		= $_POST['aula'];
	$dataRevisio	= $_POST['dataRevisio'];
	$dataInforme	= $_POST['dataInforme'];
	$obs 				= $_POST['obs'];

	echo $_SESSION['intranet']->desarCanvisDadesAulaEdicio($idCurs, $aula, $dataRevisio,
	$dataInforme, $obs, $idAula);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
