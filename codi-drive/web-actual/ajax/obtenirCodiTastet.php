<?php
include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	if (!isset($_GET['url']) || trim($_GET['url']) === '')
		throw new Exception('',404);

	$urlActual = trim($_GET['url']);
	$partsLink = explode('/', rtrim($urlActual, '/'));
	$nomAmigable = $partsLink[count($partsLink)-1];
	$urlConsulta = "/tastets/".$nomAmigable;
	$idUrlConsulta = buscarPagina($urlConsulta);

	if ($idUrlConsulta == null || $idUrlConsulta == '')
		throw new Exception('',404);

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cns = "SELECT CODI_CURS FROM reptes WHERE ID_URL=? AND ESTAT=1";
	$stmt = $connexio->prepare($cns);
	$stmt->bind_param("d", $idUrlConsulta);
	$stmt->execute();
	$stmt->store_result();

	if ($stmt->num_rows() != 1) {
		$connexio->closeStmt();
		$connexio->desconectarBD();
		throw new Exception('',404);
	}

	$stmt->bind_result($codiCurs);
	$stmt->fetch();
	$connexio->closeStmt();
	$connexio->desconectarBD();

	echo $codiCurs;
}
catch(Exception $e) {
	if ($e->getCode()==404)
		echo mostrarPagina404();
	else
		echo missatgeError($e->getCode());
}
?>