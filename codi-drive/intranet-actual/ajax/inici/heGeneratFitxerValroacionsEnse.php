<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../ConnexioMoodle.php');
include ('../../ConnexioMoodleAntic.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');
session_start();

try {

	$any		= $_GET['any'];
	$mes		= $_GET['mes'];
	$idCurs	= $_GET['idCurs'];

	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] = unserialize($_SESSION['intranet']);

	echo $_SESSION['intranet']->generatFitxerValoracionsEnse($any, $mes, $idCurs);
	// $csv_file = "valoracions-ensenyament-".$any."-".$mes.".txt";
	// $path_file = "../../fitxers/".$csv_file;
	//
	// $csv.= $idCurs."|";
	//
	// //Si el fitxer no existeix, generem el fitxer
	// if (!$handle = fopen($path_file, "a")) {
	// 	 echo "Cannot open file";
	// 	 exit;
	// }
	// //Omplim el fitxer
	// if (fwrite($handle, utf8_decode($csv)) === FALSE) {
	// 	 echo "Cannot write to file";
	// 	 exit;
	// }
	// fclose($handle);
	//
	// $mostrar = $csv_file;
	//
	// echo $mostrar;

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
