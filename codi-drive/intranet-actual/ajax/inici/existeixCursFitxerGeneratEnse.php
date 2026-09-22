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

	$csv_file = "valoracions-ensenyament-".$any."-".$mes.".txt";
	$path_file = "../../fitxers/".$csv_file;

	if (!$handle = fopen($path_file, "r")) {
		 echo "Cannot open file";
		 exit;
	}
	else {
		$content = fread($handle, filesize($path_file));

		$trobat = false;
		$cursos = explode('|', $content);
		$lcursos = count($cursos);
		$cnt = 0;

		while ( $cnt < $lcursos && !$trobat ) {
			if ( $cursos[$cnt] == $idCurs )
				$trobat = true;
			else
				$cnt++;
		}

		echo $trobat;
	}

	fclose($handle);

	echo $mostrar;

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);
}

?>
