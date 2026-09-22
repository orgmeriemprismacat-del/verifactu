<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD.php");

try {
	$dni = $_GET['dni'];
	
	$connexio = new ConnexioBBDD();
	$connexio->connectarBD();
					
	$connexio->consultarBD("SELECT ID FROM inscripcions WHERE DNI = '".$dni."' AND ((A_PAGAR>0 AND PAGAMENT>0) OR (A_PAGAR=0 AND OBSERVACIONS LIKE '%CURS REGAL%'))");		
	if ($connexio->obtenirNumRows()>0) $exalumne = "true";
	else $exalumne = "false";
	
	$connexio->lliurarConsulta();
	$connexio->desconectarBD();
	
	echo $exalumne;	
}
catch(Exception $e) {
	echo "Url. Missatge: ". $e->getMessage();
}

?>