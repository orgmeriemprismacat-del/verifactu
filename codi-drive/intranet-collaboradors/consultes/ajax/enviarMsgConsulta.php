<?php
	require('../../../config.php');
	include ('../../ConnexioIntranetTutor.php');
	include ('../../ConnexioWeb.php');
	include ('../../Text.php');
	include ('../../IntranetTutor.php');
	include ('../../IntranetTutorProva.php');
	include ('../inc/missatgesError.php');

	try {
		$username   = $_POST['mdlUsername'];
		$tipus 			= $_POST['typeCons'];
		$prioritat 	= $_POST['priorCons'];
		$course 		= $_POST['courseCons'];
		$device 		= $_POST['deviceCons'];
		$system 		= $_POST['systemCons'];
		$browser 		= $_POST['browserCons'];
		$url 				= $_POST['urlCons'];
		$message 		= $_POST['missatge'];

		$objIntranet = new IntranetTutor();

		if ( $username == '77922662' )
			$objIntranet = new IntranetTutorProva();

		$mostrar = 	$objIntranet->enviarMsgConsulta( $username, $tipus, $prioritat, $course,
		$device, $system, $browser, $url, $message );

		echo $mostrar;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());
	}

 ?>
