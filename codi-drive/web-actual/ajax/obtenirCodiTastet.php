<?php
include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");

try {
	$urlActual = isset($_GET['url']) ? trim($_GET['url']) : '';
	if ($urlActual == '') {
		echo '';
		return;
	}

	$partsLink = explode('/', $urlActual);
	$nomAmigable = $partsLink[count($partsLink)-1];
	$urlConsulta = "/tastets/".$nomAmigable;
	$idUrlConsulta = buscarPagina($urlConsulta);

	if ($idUrlConsulta == null || $idUrlConsulta == '') {
		echo '';
		return;
	}

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cns = "SELECT CODI_CURS FROM reptes WHERE ID_URL=? AND ESTAT=1";
	$stmt = $connexio->prepare($cns);
	$stmt->bind_param("d", $idUrlConsulta);
	$stmt->execute();
	$stmt->store_result();

	$codiCurs = '';
	if ($stmt->num_rows() > 0) {
		$stmt->bind_result($codiCursBD);
		$stmt->fetch();
		$codiCurs = $codiCursBD;
	}

	$connexio->closeStmt();
	$connexio->desconectarBD();

	echo $codiCurs;
}
catch(Throwable $e) {
	echo '';
}
?>