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

	try {
		$objIntranetAlumne 	= unserialize($_SESSION['objIntranetAlumne']);

		$mostrar = $objIntranetAlumne->mostrarDadesPersonalsCurricularsEditables();

		echo $mostrar;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());
	}

 ?>
