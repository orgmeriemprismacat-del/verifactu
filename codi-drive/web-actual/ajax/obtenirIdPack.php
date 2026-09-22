<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");

/* Buscar la id a amigable a partir de la url obtinguda */
include("../inc/buscarPaginaStmt.php");

try {
	//Obtenir la url amigable de l'enllaç sencer
	$url_actual = $_GET['url'];
	$parts_link = explode('/',$url_actual);
	$nom_amigable = $parts_link[count($parts_link)-1];
	$url_consulta = "/packs/".$nom_amigable;
	$idUrl = buscarPagina($url_consulta);

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsPack = "SELECT ID_PACK FROM info_pack WHERE ID_URL=? AND ESTAT = 1";
	if ( $stm = $connexio->prepare($cnsPack) ) {
		$stm->bind_param("d", $idUrl);
		$stm->execute();
		$stm->bind_result($idPack);
		$stm->fetch();
		$connexio->closeStmt();
	}
	$connexio->desconectarBD();

	echo $idPack;
}
catch(Exception $e) {
	echo "Url. Missatge: ". $e->getMessage();
}

?>
