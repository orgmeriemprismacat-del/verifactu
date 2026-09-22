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
	$apagar			= $_GET['apagar'];
	$pagament		= $_GET['pagament'];
	$datapag		= $_GET['datapag'];
	$idpag			= $_GET['idpag'];
	$pagobs 		= $_GET['pagobs'];
	$fraccio 		= $_GET['fraccio'];
	$comfraccio = $_GET['comfraccio'];
	$factura 		= $_GET['factura'];
	$datarec 		= $_GET['datarec'];
	$rec				= $_GET['rec'];
	$recPag			= $_GET['recPag'];

	echo $_SESSION['intranet']->guardarDadesPagament_modalsresultatCerca(
		$idInsc, $apagar, $pagament, $datapag, $idpag, $pagobs, $fraccio,
		$comfraccio, $factura, $datarec, $rec);
	echo $_SESSION['intranet']->enviarNotificacioObsPagament_modalsresultatCerca(
		$idInsc, $apagar, $pagament, $pagobs, $recPag);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
