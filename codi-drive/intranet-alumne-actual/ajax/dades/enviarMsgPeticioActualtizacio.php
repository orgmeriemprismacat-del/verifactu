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

  $idInsc   = $_GET['idInsc'];
  $nom      = $_GET['nom'];
  $cognoms  = $_GET['cognoms'];
  $dni      = $_GET['dni'];
  $email    = $_GET['email'];
  $telefon  = $_GET['telefon'];
  $adreca   = $_GET['adreca'];
  $cp       = $_GET['cp'];
  $poble    = $_GET['poble'];
  $perfil   = $_GET['perfil'];
  $titol    = $_GET['titulacio'];
  $coment   = $_GET['comentari'];

	try {
		$objIntranetAlumne 	= unserialize($_SESSION['objIntranetAlumne']);

		$mostrar = $objIntranetAlumne->enviarMsgSolicitantModificacioDades( $idInsc,
      $nom, $cognoms, $dni, $email, $telefon, $adreca, $cp, $poble,
      $perfil, $titol, $coment );

		echo $mostrar;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());
	}

 ?>
