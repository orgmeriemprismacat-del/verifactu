<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD.php");

/* Buscar la id a amigable a partir de la url obtinguda */
include("../inc/buscarPagina.php");

try {
	//Obtenir la url amigable de l'enllaç sencer
	$url_actual = $_GET['url'];
	$parts_link = explode('/',$url_actual);
	$nom_amigable = $parts_link[count($parts_link)-1];
	$url_consulta = "/cursos/".$nom_amigable;
	$id_url_consulta = buscarPagina($url_consulta);

	$connexio = new ConnexioBBDD();
	$connexio->connectarBD();

	$connexio->consultarBD("SELECT CODI_CURS FROM informacio WHERE ID_AMIGABLE=".$id_url_consulta." AND ESTAT = 1");
	$row = $connexio->obtenirResultat();
	$codi_curs = $row['CODI_CURS'];

	$connexio->lliurarConsulta();
	$connexio->desconectarBD();

	echo $codi_curs;
}
catch(Exception $e) {
	echo "Url. Missatge: ". $e->getMessage();
}

?>
