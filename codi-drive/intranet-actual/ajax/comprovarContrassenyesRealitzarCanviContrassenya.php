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

		$passAct 		= $_GET['passAct'];
		$passNew 		= $_GET['passNew'];
		$passConfNew 	= $_GET['passConfNew'];

		echo $_SESSION['intranet']->comprovarContrassenyes_Perfil_MostrarPerfil($passAct, $passNew, $passConfNew);

		$_SESSION['usuari'] = serialize($_SESSION['usuari']);
		$_SESSION['intranet'] = serialize($_SESSION['intranet']);

		echo $mostrar;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());

		$_SESSION['usuari'] = serialize($_SESSION['usuari']);
		$_SESSION['intranet'] = serialize($_SESSION['intranet']);
	}

 ?>
