<?php
session_start();

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');

try {

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$dni 			= $_GET['dni'];
	$codiRegal 	= $_GET['codiRegal'];
	$numeroFact 	= $_GET['numFact'];
	$tipusInsc 	= $_GET['tipusInsc'];

	echo $_SESSION['intranet']->mostrarPagaments($dni, $codiRegal, $numeroFact, $tipusInsc);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
