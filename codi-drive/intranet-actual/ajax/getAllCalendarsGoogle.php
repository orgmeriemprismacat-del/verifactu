<?php
session_start();

include ('../ConnexioIntranet.php');
include ('../ConnexioWeb.php');
include ('../Text.php');
include ('../Usuari.php');
include ('../inc/missatgesError.php');

try {
	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] 	= unserialize($_SESSION['intranet']);

	echo $_SESSION['intranet']->getAllCalendarsGoogle();

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
}

?>
