<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../ConnexioMoodle.php');
include ('../../ConnexioMoodleAntic.php');
include ('../../Text.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../Date.php');
include ('../../Mail.php');
include ('../../inc/missatgesError.php');
session_start();

try {

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	$idInsc 			= $_GET['idinsc'];
	$anyC 			= $_GET['any'];
	$mesC 			= $_GET['mes'];
	$cursC 			= $_GET['curs'];
	$numeroCanvi 	= $_GET['numero'];
	$apagarC	 		= $_GET['apagar'];
	$pagatC			= $_GET['pagat'];
	$pendentC 		= $_GET['pendent'];
	$despesesC 		= $_GET['despeses'];
	$obsCanvi 		= $_GET['obs'];
	$motiuCanvi 	= $_GET['motiu'];
	$enviarCoreu 	= $_GET['enviarCoreu'];
	$tipusDesc 		= $_GET['tipusDesc'];
	$validDesc 		= $_GET['validDesc'];

	echo $_SESSION['intranet']->realitzarCanviCurs_modalCanviCurs($idInsc, $anyC,
	$mesC, $cursC, $numeroCanvi, $apagarC, $pagatC, $pendentC, $despesesC,
	$obsCanvi, $motiuCanvi, $enviarCoreu, $tipusDesc, $validDesc);

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
