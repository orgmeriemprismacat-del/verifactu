<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");

$cp = $_GET['cp'];

try {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$mostrar = '';

	$stmt = $connexio->prepare("SELECT POBLE FROM poblacions WHERE CP=? ORDER BY POBLE");
	$stmt->bind_param("s", $cp);
	$stmt->execute();
	$stmt->bind_result($poble);
	while ($stmt->fetch()) {
		$textPoble = new Text($poble);
		$textPoble->obtenirNomCurt();
		$idPoble = $textPoble->obtenirText();
		$mostrar .= "<li class='border-bottom m-0' id=\"poble-".$idPoble."\"><a href='#'>".$poble."</a></li>";
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
