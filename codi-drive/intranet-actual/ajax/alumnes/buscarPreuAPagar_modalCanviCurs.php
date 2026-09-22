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

	$id 	= $_GET['id'];
	$any 	= $_GET['any'];
	$mes 	= $_GET['mes'];
	$curs = $_GET['curs'];
	$apagar = $_GET['apagar'];
	$tipusDesc = $_GET['tipusDesc'];
	$validDesc = $_GET['validDesc'];

	echo $_SESSION['intranet']->buscarPreuAPagar_modalCanviCurs($id, $any, $mes, $curs, $apagar, $tipusDesc, $validDesc);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
