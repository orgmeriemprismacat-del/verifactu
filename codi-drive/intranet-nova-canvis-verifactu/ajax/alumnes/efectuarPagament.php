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

	$idTipus		= $_GET['id'];
	$tipus 		= $_GET['tipus'];

	$pagament 	= $_GET['pagament'];
	$dataPag 	= $_GET['dataPag'];
	$banc 		= $_GET['banc'];
	$obs 			= $_GET['obs'];
	$numFact 		= $_GET['numFact'];
	$efact 		= $_GET['efact'];

	echo $_SESSION['intranet']->efectuarPagament($idTipus, $tipus, $pagament,
	$dataPag, $banc, $obs, $numFact, $efact);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
