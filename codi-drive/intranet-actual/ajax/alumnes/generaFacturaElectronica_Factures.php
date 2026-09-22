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

	$empresa 		= $_POST['empresa'];
	$concepte1 		= $_POST['concepte1'];
	$concepte2 		= $_POST['concepte2'];
	$preu				= $_POST['preu'];
	$cursos			= $_POST['cursos'];
	$edicions		= $_POST['edicions'];
	$inscripcions	= $_POST['inscripcions'];
	$observacions	= $_POST['observacions'];

	echo $_SESSION['intranet']->generarFacturaElectronica_Alumnes($empresa, $concepte1,
	$concepte2, $preu, $cursos, $edicions, $inscripcions, $observacions);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
