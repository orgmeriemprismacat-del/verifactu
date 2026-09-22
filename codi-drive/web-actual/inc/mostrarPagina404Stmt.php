<?php

function mostrarPagina404() {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$consultaId404 = "SELECT ID FROM amigable WHERE URL=?";
	$consultaIdCnt404 = "SELECT ID_CONTINGUT FROM pagina WHERE ID_URL=? AND ESTAT=1";
	$consultaCnt404 = "SELECT CONTINGUT FROM contingut WHERE ID=?";

	$stmtId404 = $connexio->prepare($consultaId404);
	$stmtId404->bind_param("s", $url);
	$url='/404';
	$stmtId404->execute();
	$stmtId404->bind_result($idUrl);
	$stmtId404->fetch();
	$connexio->closeStmt();

	$stmtIdCnt404 = $connexio->prepare($consultaIdCnt404);
	$stmtIdCnt404->bind_param("d", $idUrl);
	$stmtIdCnt404->execute();
	$stmtIdCnt404->bind_result($idCnt);
	$stmtIdCnt404->fetch();
	$connexio->closeStmt();

	$stmtCnt404 = $connexio->prepare($consultaCnt404);
	$stmtCnt404->bind_param("d", $idCnt);
	$stmtCnt404->execute();
	$stmtCnt404->bind_result($cnt404);
	$stmtCnt404->fetch();
	$connexio->closeStmt();

	$connexio->desconectarBD();

	return $cnt404;
}
?>
