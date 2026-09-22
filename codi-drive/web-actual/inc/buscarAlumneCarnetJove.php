<?php

require_once('../lib/nusoap.php');

//Paràmetres d'entrada
$usuari = "entitat_colaboradora";
$contrasenya = "h14np0PG5s";

//url del webservice
$wsdl="http://www.carnetjove.cat/carnetjove/service/webService?wsdl";

//instanciant un nou objecte client per el webservice
$client=new nusoap_client($wsdl,true);
//passant per parametres a un array
$param=array(
   "login"=> $usuari,
   "password" => $contrasenya,
   "numeroDocument" => $doc
);
//crida al mètode i passant amb els parametres
$resultat = $client->call('usuariExistent', $param);
if ($resultat['return']=="true")
   $carnetJove=true;
else
   $carnetJove=false;

?>
