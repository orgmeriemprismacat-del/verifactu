<?php
error_reporting(E_ERROR | E_WARNING | E_PARSE);
date_default_timezone_set('Europe/Madrid');
$dbhost="localhost";
$dbname="gestio";
$dbuser="suport";
$dbpass="1324GiRoNa";
$tabla="calendaris";
$db = new mysqli($dbhost,$dbuser,$dbpass,$dbname);
//$db->query('set name utf8');
$acentos = $db->query("SET NAMES 'utf8'");
if ($db->connect_errno) {
	die ("<h1>Fallo al conectar a MySQL: (" . $db->connect_errno . ") " . $db->connect_error."</h1>");
}
?>
