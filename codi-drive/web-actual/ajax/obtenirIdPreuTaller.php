<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codiCurs = $_GET['codiCurs'];

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsIdPreu = "SELECT ID_PREU FROM info_taller WHERE
	 	CODI_CURS LIKE ? AND ESTAT=1 AND DATAI >= CURRENT_DATE";
	$stmtIdPreu=$connexio->prepare($cnsIdPreu);
	$stmtIdPreu->bind_param("s", $codiCurs);
	$stmtIdPreu->execute();
	$stmtIdPreu->store_result();
	if ( $stmtIdPreu->num_rows() > 0 ) {
		$stmtIdPreu->bind_result($idPreu);
		$stmtIdPreu->fetch();
		$connexio->closeStmt();
	}

	echo $idPreu;

	$connexio->desconectarBD();
}
catch(Exception $e) {
if ($e->getCode()==404)
	echo mostrarPagina404();
else
	echo missatgeError($e->getCode());
}

?>
