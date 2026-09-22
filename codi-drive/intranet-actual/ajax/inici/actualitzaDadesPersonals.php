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

	$idInsc 		= $_GET['idinsc'];
	$nom 			= $_GET['nom'];
	$cog 			= $_GET['cog'];
	$email 		= $_GET['email'];
	$tel 			= $_GET['tel'];
	$adreca 		= $_GET['adreca'];
	$cp 			= $_GET['cp'];
	$poblacio 	= $_GET['poblacio'];

	echo $_SESSION['intranet']->actualitzaDadesPersonals_pujadaAlumnes($idInsc,
	$nom, $cog, $email, $tel, $adreca, $cp, $poblacio);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
