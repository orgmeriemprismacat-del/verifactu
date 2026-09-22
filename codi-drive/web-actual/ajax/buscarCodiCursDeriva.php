<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");

$doc = $_GET['doc'];
$curs = $_GET['curs'];

//Busco el codi curs del qual deriva el curs actual

try {
	$cursDerivat = '';
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$mostrar = "false";

	$cnsCurs = "SELECT DERIVA FROM informacio WHERE CODI_CURS=? AND ESTAT=1";
   $stmt=$connexio->prepare($cnsCurs);
   $stmt->bind_param("s", $curs);
   $stmt->execute();
	$stmt->bind_result($deriva);
	$stmt->fetch();
   $connexio->closeStmt();

	if ($deriva!=null and $deriva!='') {
		$mostrar = $deriva;
	}

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
