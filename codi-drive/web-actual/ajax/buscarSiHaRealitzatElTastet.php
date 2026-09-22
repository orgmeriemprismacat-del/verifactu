<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Date.php");

$doc = $_GET['doc'];
$curs = $_GET['curs'];

//Consulta ajax per comprovar si el usuari XXX ha realitzat el curs xxx (retornar l'edicio|any en que va fer-lo)

try {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$mostrar='';

	$cnsInsc = "SELECT DATA_INSC FROM inscripcions_reptes WHERE CURS=? AND DNI=? AND INSC_CURS=1";
   $stmt=$connexio->prepare($cnsInsc);
   $stmt->bind_param("ss", $curs, $doc);
   $stmt->execute();
   $stmt->store_result();
	if ($stmt->num_rows() > 0) {
		$stmt->bind_result($dataInsc);
		$stmt->fetch();
	   $connexio->closeStmt();

		$cnsTitol = "SELECT TITOL FROM reptes WHERE CODI_CURS=?";
	   $stmt=$connexio->prepare($cnsTitol);
	   $stmt->bind_param("s", $curs);
	   $stmt->execute();
		$stmt->bind_result($titol);
		$stmt->fetch();
		$connexio->closeStmt();

		$objDate = new Date($dataInsc);
		$textDate = $objDate->getPronomEl().$objDate->getDataLlarga();

		$mostrar = $titol."|".$textDate;
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
