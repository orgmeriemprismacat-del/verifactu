<?php
  include ('../../ConnexioIntranet.php');
  include ('../../ConnexioWeb.php');
  include ('../../Text.php');
  include ('../../Usuari.php');
  include ('../../IntranetAlumne.php');
  include ('../../Date.php');
  include ('../../inc/missatgesError.php');
  require('../../../config.php');

  session_start();

  $nom          = $_GET['nom'];
  $cognoms      = $_GET['cognoms'];
  $email        = $_GET['email'];
  $telefon      = $_GET['telefon'];
  $typeCons     = $_GET['typeCons'];
  $reasonCons   = $_GET['motiuConsulta'];
  $reasonConsA  = $_GET['motiuConsultaAltres'];
  $courseCons   = $_GET['courseCons'];
  $deviceCons   = $_GET['deviceCons'];
  $systemCons   = $_GET['systemCons'];
  $browserCons  = $_GET['browserCons'];
  $subject     = $_GET['assumpte'];
  $message     = $_GET['missatge'];

	try {
		$objIntranetAlumne 	= unserialize($_SESSION['objIntranetAlumne']);

		$mostrar = $objIntranetAlumne->enviarMsgConsulta( $nom, $cognoms, $email, $telefon,
      $typeCons, $reasonCons, $reasonConsA, $courseCons,
      $deviceCons, $systemCons, $browserCons, $subject, $message );

		echo $mostrar;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());
	}

 ?>
