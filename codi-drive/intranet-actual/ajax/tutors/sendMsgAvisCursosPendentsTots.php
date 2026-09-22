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

	$dniTutor 		= $_GET['dniTutor'];
	$any 				= $_GET['any'];
	$mes		 		= $_GET['mes'];
	$curs 			= $_GET['curs'];
	$dataMaxim 		= $_GET['dataMaxim'];
	$hourDataMaxim = $_GET['hourDataMaxim'];

	echo $_SESSION['intranet']->sendMsgTutorAvisCursosPendentsTots( $dniTutor, $any, $mes, $curs, $dataMaxim, $hourDataMaxim );

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
