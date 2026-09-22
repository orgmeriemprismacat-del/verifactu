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

   if ( $stmt = $connexio->prepare($cnsPreu) ) {
	   $stmt->bind_param("d", $idPreu);
	   $stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows() > 0) {
		   $stmt->bind_result($import);
		   $stmt->fetch();
			$objImp = new Numero($import);
			$importFormatCorrecte = $objImp->mostrarNumeroDecimalsSense0();
		}
		$connexio->closeStmt();
	}
	else {
		throw new Exception('',2905);
	}

	echo $importFormatCorrecte;
	$connexio->desconectarBD();
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
