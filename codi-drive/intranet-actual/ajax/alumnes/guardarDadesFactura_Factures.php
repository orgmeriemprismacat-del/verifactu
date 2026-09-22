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

	$id 			= $_GET['id'];
	$factura		= $_GET['factura'];
	$rao			= $_GET['rao'];
	$cif			= $_GET['cif'];
	$cp			= $_GET['cp'];
	$poblacio 	= $_GET['poblacio'];
	$adreca 		= $_GET['adreca'];
	$concepte1 	= $_GET['concepte1'];
	$concepte2	= $_GET['concepte2'];
	$obs 			= $_GET['obs'];

	echo $_SESSION['intranet']->guardarDadesFactura_Factures(
		$id, $factura, $rao, $cif, $cp, $poblacio, $adreca,
		$concepte1, $concepte2, $obs);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
