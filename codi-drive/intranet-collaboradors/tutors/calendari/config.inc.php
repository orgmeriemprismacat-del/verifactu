<?php
date_default_timezone_set('Europe/Madrid');
$dbhost="localhost";
$dbname="gestio";
$dbuser="suport";
$dbpass="1324GiRoNa";
$con=mysql_connect($dbhost,$dbuser,$dbpass) or die("<h1>Impossible conectar a la base de dades.");
mysql_set_charset('utf8');
?>
