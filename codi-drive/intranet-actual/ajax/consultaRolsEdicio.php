<?php
session_start();

	include ('../ConnexioIntranet.php');
	include ('../ConnexioWeb.php');
	include ('../Text.php');
	include ('../Usuari.php');
	include ('../Intranet.php');
	include ('../Date.php');
	include ('../inc/missatgesError.php');


	try {
		$_SESSION['usuari'] 		= unserialize($_SESSION['usuari']);
		$_SESSION['intranet'] 	= unserialize($_SESSION['intranet']);

		$page 		= $_GET['url'];

		echo $_SESSION['intranet']->consultaRolsEdiicio($page);

		$_SESSION['usuari'] = serialize($_SESSION['usuari']);
		$_SESSION['intranet'] = serialize($_SESSION['intranet']);

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());

		$_SESSION['usuari'] = serialize($_SESSION['usuari']);
		$_SESSION['intranet'] = serialize($_SESSION['intranet']);
	}

 ?>
