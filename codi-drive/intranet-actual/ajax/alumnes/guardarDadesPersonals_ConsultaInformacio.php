<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');
session_start();

try {

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$idInsc 		= $_GET['idinsc'];
	$nom 			= $_GET['nom'];
	$cog 			= $_GET['cog'];
	$dni 			= $_GET['dni'];
	$email 		= $_GET['email'];
	$tel 			= $_GET['tel'];
	$adreca 		= $_GET['adreca'];
	$cp 			= $_GET['cp'];
	$poblacio 	= $_GET['poblacio'];
	$perfil 		= $_GET['perfil'];
	$titulacio 	= $_GET['titulacio'];
	$dataInsc	= $_GET['dates'];
	$inscrit		= $_GET['inscrit'];
	$aulaoberta	= $_GET['ao'];
	$mailing		= $_GET['mailing'];
	$certificat	= $_GET['certificat'];
	$obscert		= $_GET['obscertificat'];
	$generat		= $_GET['generat'];
	$obs			= $_GET['obs'];
	$comentaris	= $_GET['coment'];
	$databaixa	= $_GET['databaixa'];
	$baixa		= $_GET['motiubaixa'];
	$quibaixa	= $_GET['quibaixa'];

	echo $_SESSION['intranet']->guardarDadesPersonals_modalsresultatCerca($idInsc,
	$nom, $cog, $email, $dni, $tel, $adreca, $cp, $poblacio, $perfil, $titulacio,
	$dataInsc, $inscrit, $aulaoberta, $mailing, $certificat, $obscert, $generat,
	$obs, $comentaris, $databaixa, $baixa, $quibaixa);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
