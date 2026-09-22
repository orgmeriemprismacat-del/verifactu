<?php
session_start();
include ('../ConnexioIntranet.php');
include ('../ConnexioWeb.php');
include ('../Text.php');
include ('../Usuari.php');

$_SESSION['usuari'] = unserialize($_SESSION['usuari']);

$usuari = $_SESSION['usuari']->getUsuari()->get();
$password = $_SESSION['usuari']->getHashPass()->get();

$mostrar = "
	<div class='sidebar-background position-absolute h-100 w-100'></div>
	<div class='sidebar-wrapper position-relative pb-5 ps ps--active-y'>
		<div class='user pb-3 mt-2 mb-4 position-relative d-flex flex-column'></div>
		<ul class='nav'></ul>
	</div>";

echo $mostrar;

$_SESSION['usuari'] = serialize($_SESSION['usuari']);

 ?>
