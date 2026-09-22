<?php

function buscarPagina($urlActual) {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$consultaUrl = "SELECT ID FROM amigable WHERE URL=?";
	$stmt = $connexio->prepare($consultaUrl);
	$stmt->bind_param("s", $urlActual);
	$stmt->execute();
	$stmt->store_result();
	$stmt->bind_result($idUrl);
	$stmt->fetch();
	$connexio->closeStmt();
	$connexio->desconectarBD();

	return $idUrl;
}
?>
