<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../inc/LegacyPaymentToken.php");
include("../Text.php");
include("../Numero.php");
include("../PagamentCursAutomatic.php");

try {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	$stmt=$connexio->prepare($cnsParam);
	$stmt->bind_param("s", $tipusParam);
	$tipusParam = 'keyEncriptar';
	$stmt->execute();
	$stmt->bind_result($keyEncr);
	$stmt->fetch();
	$connexio->closeStmt();

	$mostrar = '';
	$originalId = LegacyPaymentToken::decode((string) ($_GET['keyEncr'] ?? ''), (string) $keyEncr);

	$cnsInsc = "SELECT IDPAG FROM inscripcions WHERE ID=?";
	$stmt=$connexio->prepare($cnsInsc);
	$stmt->bind_param("d", $originalId);
	$stmt->execute();
	$stmt->store_result();
	if ( $stmt->num_rows() > 0 ) {
		$stmt->bind_result($idPag);
		$stmt->fetch();
		$pagamentInscripcio= new PagamentCursAutomatic($idPag);
		$mostrar = $pagamentInscripcio->mostrarPaginaConfirmacio();
	}
	$connexio->closeStmt();

	$connexio->desconectarBD();
	echo $mostrar;
}
catch(Throwable $e) {
	if (isset($connexio) && is_object($connexio)) {
		try { $connexio->desconectarBD(); } catch (Throwable $ignored) {}
	}

	if ($e->getCode()==404)
		echo mostrarPagina404();
	else
		echo missatgeError('1401');
}

?>
