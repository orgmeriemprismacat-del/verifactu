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

	$idInsc 		= $_GET['idInsc'];
	$nom 			= $_GET['nom'];
	$cog 			= $_GET['cog'];
	$dni 			= $_GET['dni'];
	$email 		= $_GET['email'];
	$tel 			= $_GET['tel'];
	$adreca 		= $_GET['adreca'];
	$cp 			= $_GET['cp'];
	$poblacio 	= $_GET['poblacio'];
	$perfil 		= $_GET['perfil'];
	$titulacio 	= $_GET['titulacio'];

	echo $_SESSION['intranet']->guardarDadesPersonals_resultatCerca($idInsc, $nom,
	$cog, $email, $dni, $adreca, $cp, $poblacio, $perfil, $titulacio, $tel);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
