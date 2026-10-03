<?php

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $requestMethod === 'POST' ? $_POST : $_GET;

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Numero.php");

try {
	$documentacio = new Text($input['dni']);
	$any = new Numero($input['any']);
	$edicio = new Text($input['edicio']);
	$codiCurs = new Text($input['codiCurs']);

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$documentacio->arreglarParaulaBD('text');
	$edicio->arreglarParaulaBD('text');
	$codiCurs->arreglarParaulaBD('text');

	$mostrar = '';

	$cnsInsc = "SELECT ID FROM inscripcions WHERE DNI=? AND CURS=? AND ANY=?
					AND MES=? AND (`INSC CURS`='1' OR `INSC CURS`='0')";
   $stmt=$connexio->prepare($cnsInsc);
   $stmt->bind_param("ssds", $doc, $codi, $year, $edition);
	$doc = $documentacio->obtenirText();
	$codi = $codiCurs->obtenirText();
	$year = $any->obtenirNumero();
	$edition = $edicio->obtenirText();
   $stmt->execute();
	$stmt->store_result();
	if ($stmt->num_rows() > 0) {
		$stmt->bind_result($id);
		$stmt->fetch();
		$mostrar = $id;
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
