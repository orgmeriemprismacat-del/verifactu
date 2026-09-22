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
    $tipusInsc   = $_GET['tipusInsc'];
    $idPag   = $_GET['idPag'];

		$objIntranetAlumne 	= unserialize($_SESSION['objIntranetAlumne']);

		$url = $objIntranetAlumne->obtenirUrlPagament( $tipusInsc, $idPag );

		echo $url;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());
	}

 ?>
