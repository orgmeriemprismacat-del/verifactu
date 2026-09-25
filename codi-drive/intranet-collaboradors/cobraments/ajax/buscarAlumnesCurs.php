<?php
	require('../../../config.php');
	include ('../../ConnexioIntranetTutor.php');
	include ('../../ConnexioWeb.php');
	include ('../../Text.php');
	include ('../../IntranetTutor.php');
	include ('../inc/missatgesError.php');

	try {
		$any 			= $_GET['any'];
		$mes 			= $_GET['mes'];
		$username = $_GET['username'];

		$objIntranet = new IntranetTutor();

		$mostrar = 	$objIntranet->mostrarTable_Alumnes_ConsultaAlumnes($any, $mes, $username);

		echo $mostrar;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());
	}

 ?>
