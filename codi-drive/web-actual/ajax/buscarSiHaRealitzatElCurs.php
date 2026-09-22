<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");

$doc = $_GET['doc'];
$curs = $_GET['curs'];

//Consulta ajax per comprovar si el usuari XXX ha realitzat el curs xxx (retornar l'edicio|any en que va fer-lo)

try {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$mostrar='';

	$cnsInsc = "SELECT ANY, MES FROM inscripcions WHERE CURS=? AND DNI=? AND GENERAT=1";
   $stmt=$connexio->prepare($cnsInsc);
   $stmt->bind_param("ss", $curs, $doc);
   $stmt->execute();
   $stmt->store_result();
	if ($stmt->num_rows() > 0) {
		$stmt->bind_result($any, $mes);
		$stmt->fetch();
	   $connexio->closeStmt();

		$cnsTitol = "SELECT NOM_CURS FROM curs WHERE CURS=? AND ANY=? AND MES=?";
	   $stmt=$connexio->prepare($cnsTitol);
	   $stmt->bind_param("sds", $curs, $any, $mes);
	   $stmt->execute();
		$stmt->bind_result($titol);
		$stmt->fetch();
		$connexio->closeStmt();

		$objMes = new Text($mes);
		$textMes = $objMes->obtenirMesLlarg();

		$mostrar = $titol."|".$any."|".$textMes;
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
