<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$dispositiu = $_GET['dispositiu'];
	$url = $_GET['url'];
	$id_url = buscarPagina($url);

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsDataMod="SELECT MODIFICAT FROM pagina WHERE ESTAT=1 AND ID_URL=?";
	$stmt = $connexio->prepare($cnsDataMod);
	$stmt->bind_param("d", $id_url);
	$stmt->execute();
	$stmt->bind_result($modificat);
	$stmt->fetch();
	$connexio->closeStmt();
	$connexio->desconectarBD();

	$data_llarga = new Text($modificat);

	$mostrar .= "Darrera actualització: ".$data_llarga->convertirDataLlarga();

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
		echo mostrarPagina404();
	else
		echo missatgeError($e->getCode());
}

?>
