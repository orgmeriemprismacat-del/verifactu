<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Text.php');
include('../Url.php');
include('../InscripcioCurs.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$urlAct = $_GET['url'];
	$tipus = $_GET['tipus'];
	$dispositiu = $_GET['dispositiu'];

	$idUrlAct = buscarPagina($urlAct);
	$url = new Url($idUrlAct);

	$partsLink = explode('/',$url->obtenirLink());
	$nomAmig = $partsLink[count($partsLink)-1];

	if ($tipus==0) {
		$urlCons = '/cursos/'.$nomAmig;
		$idUrlCons = buscarPagina($urlCons);
		$inscripcio = new InscripcioCurs($idUrlCons, $tipus, $dispositiu);
	}
	$mostrar = $inscripcio->mostrar();

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
