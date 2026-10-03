<?php

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	exit;
}
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../BescanviaRegal.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codiRegal = $_POST['codiRegal'];
	$dispositiu = $_POST['dispositiu'];

	$bescanvia = new BescanviaRegal($dispositiu);
	if ($bescanvia->codiRegalValid($codiRegal) !== '') {
		http_response_code(409);
		exit;
	}
	$mostrar = $bescanvia->buscarCursRegalat($codiRegal);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
