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
	$date = $_GET['date'];

	$objDate = new Date($date);

	echo $objDate->getNomMes();

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
}

?>
