<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');
session_start();

try {
	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$dni 			= $_GET['dni'];
	$email 		= $_GET['email'];
	$nom 			= $_GET['nom'];
	$cog 			= $_GET['cognoms'];
	$tel			= $_GET['telefon'];
	$any 			= $_GET['any'];
	$mes 			= $_GET['mes'];
	$curs 		= $_GET['curs'];
	$inscrit 	= $_GET['inscrit'];
	$certificat = $_GET['certificat'];
	$poblacio 	= $_GET['poblacio'];
	$perfil 		= $_GET['perfil'];
	$titulacio 	= $_GET['titulacio'];
	$comhapag 	= $_GET['comhapagat'];
	$obspag 		= $_GET['obspagament'];
	$reclamat 	= $_GET['reclamat'];
	$obs 			= $_GET['obs'];
	$coment		= $_GET['comentaris'];
	$databaixa 	= $_GET['databaixa'];
	$perenne 	= $_GET['perenne'];

	$mostrar = 	$_SESSION['intranet']->buscarUsuaris($dni, $email, $nom, $cog,
					$tel, $any, $mes, $curs, $inscrit, $certificat, $poblacio,
					$perfil, $titulacio, $comhapag, $obspag, $reclamat, $obs,
					$coment, $databaixa, $perenne );

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
