<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD.php");

$cp = $_GET['cp'];

try {

	$connexio = new ConnexioBBDD();
	$connexio->connectarBD();

	$connexio->consultarBD("SELECT POBLE FROM poblacions WHERE CP='".$cp."' ORDER BY POBLE");
	
	$num_pobles = $connexio->obtenirNumRows();
	
	$mostrar = "{";

	if ($num_pobles > 0) {
		$row_poble = $connexio->obtenirResultat();
		
		$mostrar .= "\"places\": [{\"place name\": \"".$row_poble['POBLE']."\"}";
	
		while($row_poble = $connexio->obtenirResultat()) {
			$mostrar .= ", {\"place name\": \"".$row_poble['POBLE']."\"}";		
		}
		$mostrar .= "]";
	}
	
	$mostrar .= "}";	

	$connexio->lliurarConsulta();
	$connexio->desconectarBD();

	echo $mostrar;
}
catch(Exception $e) {
	echo "Poblacio. Missatge: ". $e->getMessage();
}

?>
