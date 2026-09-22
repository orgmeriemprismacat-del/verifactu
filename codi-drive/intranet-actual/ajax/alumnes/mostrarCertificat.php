<?php
session_start();

require_once '../../lib/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

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

	$idInsc		= $_GET['idInsc'];
	$tipus		= $_GET['tipus'];
	$download	= $_GET['download'];

	if ( $_GET['download'] == "false" )
		$download = false;
	else if ( $_GET['download'] == "true" )
		$download = true;

	echo $_SESSION['intranet']->generaCertificat($idInsc, $tipus, $download);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
