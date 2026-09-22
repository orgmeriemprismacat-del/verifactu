<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD.php");
include("../ConnexioBBDD_PreparedStatment.php");
// include("../Jornada.php");
include("../JornadaStmt.php");
include("../Text.php");
include("../Url.php");

/* Buscar la id a amigable a partir de la url obtinguda */
include("../inc/buscarPagina.php");

try {
	//Obtenir la url amigable de l'enllaç sencer
	$url_actual = $_GET['url'];
	$dispositiu = $_GET['dispositiu'];

	$id_url_actual = buscarPagina($url_actual);
	$url = new Url($id_url_actual);
	// echo "crear jornada stmt<br />";
	$jornada = new JornadaStmt($url, $dispositiu);

	$mostrar = $jornada->mostrarJornada();

	echo $mostrar;
}
catch(Exception $e) {
    include("../inc/mostrarPagina404.php");
		if ($e->getCode()==404) {
				$misatgeError = buscarPagina404($url_actual);
		}
		else if ($e->getCode()>=101 && $e->getCode()<=118) {
				/********************* ERRORS ******************
				101: No existeix la jornada a la taula JORNADES o que l'ESTAT de la jornada es 0
				102: No existeix la jornada a la taula CURSOS
				103: No existeix el titol de la jornada
				104: No existeix la data d'inici de la jornada
				105: No existeix la ciutat de la jornada
				106: No existeix la imatge llarga de la jornada
				107: No existeix el url de la jornada
				108: No existeix la presentacio de la jornada
				109: No existeix els destinataris de la jornada
				110: No existeix els objectius de la jornada
				111: No existeix el programa presencial de la jornada
				112: No existeixen els ponents de la jornada
				113: No existeix el lloc de la jornada
				114: No existeix el preu de la jornada
				115: no existeix cap preu associat a la ID_PREU de la jornada
				116: El preu de la jornada no està disponible (El numero de preus associat a la ID_PREU de la jornada és diferent de 2 i 4)
				117: Existeixen 4 preus i no existeix la data d'anticipi a la jornada
				118: No existeix el camp de fraccionar (es null)
				********************* FI ERRORS ******************/
				$misatgeError = "<p>Error ".$e->getCode()." ".$e->getMessage()."<p>";
				$misatgeError .= "<p>Contacta amb Suport per resoldre el problema</p>";
		}
		else
				$misatgeError = "Jornada. Missatge: ". $e->getMessage();

		echo $misatgeError;
}

?>
