<?php

include ('../../../ConnexioIntranet.php');
include ('../../../ConnexioWeb.php');
include ('../../../Date.php');
include ('../../../ConnexioMoodle.php');
include ('../../../ConnexioMoodleAntic.php');
include ('../../../Text.php');
include ('../../../Mail.php');
include ('../../../Usuari.php');
include ('../../../Intranet.php');
include ('../../../inc/missatgesError.php');
session_start();

try {

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$idTrobada		= $_GET['idTrobada'];

	echo $_SESSION['intranet']->avisInscritsTrobada($idTrobada);

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
