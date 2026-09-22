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

	$any		= $_POST['any'];
	$mes		= $_POST['mes'];
	$curs		= $_POST['curs'];
	$usuari	= $_POST['usuari'];
	$fitxer 	= $_POST['fitxer'];
	$nom 		= $_POST['nom'];
	$cognoms = $_POST['cognoms'];
	$email 	= $_POST['email'];
	$poblacio= $_POST['poblacio'];

	echo $_SESSION['intranet']->pujar_AO($usuari, $any, $mes, $curs, $fitxer, $nom, $cognoms, $email, $poblacio);

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
