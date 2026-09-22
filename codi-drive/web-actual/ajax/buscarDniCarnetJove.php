<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD.php");
require_once('../lib/nusoap.php');

try {
	$dni = $_GET['dni'];
	
	//Paràmetres d'entrada
	$usuari = "entitat_colaboradora";
	$contrasenya = "h14np0PG5s";

	//url del webservice
	$wsdl="http://www.carnetjove.cat/carnetjove/service/webService?wsdl";

	//intanciant un nou objecte client per el webservice
	$client=new nusoap_client($wsdl,true);
	//passant per parametres a un array
	$param=array(
		"login"=> $usuari,
		"password" => $contrasenya,
		"numeroDocument" => $dni
	);
	//crida al mètode i passant amb els parametres
	$resultado = $client->call('usuariExistent', $param);
	if ($resultado['return']=="true")
		$carnet_val="true";
	else
		$carnet_val="false";
	
	$connexio = new ConnexioBBDD();
	$connexio->connectarBD();
	
	echo $carnet_val;	
}
catch(Exception $e) {
	echo "Url. Missatge: ". $e->getMessage();
}

?>