<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");

try {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$mostrar='';

	$cnsInsc = "SELECT valor FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
	AND  (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL)";
   $stmt=$connexio->prepare($cnsInsc);
   $stmt->bind_param("s", $tipus);
	$tipus='correusAltertInsc';
   $stmt->execute();
   $stmt->store_result();
	if ($stmt->num_rows() > 0) {
		$stmt->bind_result($correus);
		$stmt->fetch();

		$tipus='correusAltertInscNom';
	   $stmt->execute();
		$stmt->bind_result($noms);
		$stmt->fetch();

		$mostrar = $correus.",".$noms;
	}
   $connexio->closeStmt();
	$connexio->desconectarBD();

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
