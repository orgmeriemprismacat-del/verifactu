<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$idPack = $_GET['idPack'];

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsIdPreu = "SELECT ID_PREU FROM info_pack WHERE ID_PACK = ? AND ESTAT = 1";
	if ( $stmt=$connexio->prepare($cnsIdPreu) ) {
		$stmt->bind_param("s", $idPack);
		$stmt->execute();
		$stmt->bind_result($idPreu);
		$stmt->fetch();
		$connexio->closeStmt();
	}
	else {
		throw new Exception('',2904);
	}
	$connexio->desconectarBD();

	if ($idPreu=='' or $idPreu==null) {
		throw new Exception('',2903);
	}
	else
		echo $idPreu;

}
catch(Exception $e) {
if ($e->getCode()==404)
	echo mostrarPagina404();
else
	echo missatgeError($e->getCode());
}

?>
