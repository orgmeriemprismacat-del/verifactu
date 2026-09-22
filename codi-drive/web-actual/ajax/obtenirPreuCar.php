<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Numero.php");

try {
	$idPreu = $_GET['idPreu'];

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();
	$cnsPreu = "SELECT IMPORT FROM preu WHERE ID=? AND DATAI<=CURRENT_TIME AND
					(CURRENT_TIME<=DATAF OR DATAF IS NULL)";
   $stmtPreu = $connexio->prepare($cnsPreu);
   $stmtPreu->bind_param("d", $idPreu);
   $stmtPreu->execute();
   $stmtPreu->bind_result($import);
   $stmtPreu->fetch();
	$connexio->closeStmt();
	if ($import=='' or $import==null)
		throw new Exception('',1302);
	else {
		$objImport = new Numero($import);
		$preuFormatCorrecte = $objImport->mostrarNumeroDecimalsSense0();
		echo $preuFormatCorrecte;
	}
	$connexio->desconectarBD();
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
