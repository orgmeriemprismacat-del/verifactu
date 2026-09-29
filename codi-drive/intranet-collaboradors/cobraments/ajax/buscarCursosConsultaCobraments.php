<?php
	require('../../../config.php');
	include ('../../ConnexioIntranetTutor.php');
	include ('../../ConnexioWeb.php');
	include ('../../Text.php');
	include ('../../IntranetTutor.php');
	include ('../inc/missatgesError.php');

	try {
		$any 			= $_GET['any'];
		$orderBy 	= $_GET['orderBy'];
		$asc 			= $_GET['asc'];
		$username = $_GET['mdlUsername'];

		$objIntranet = new IntranetTutor();

		$mostrar = 	$objIntranet->mostrarTable_Alumnes_ConsultaCobraments($any, $orderBy, $asc, $username);

		echo $mostrar;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());
	}

 ?>
