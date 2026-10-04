<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../inc/LegacyPaymentToken.php");
include("../Text.php");
include("../Numero.php");
include("../PagamentTallerAutomatic.php");
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
	$originalIdPag = LegacyPaymentToken::decode((string) ($_GET['keyEncr'] ?? ''), (string) $keyEncr);

	$cnsInsc = "SELECT TIPUS_INSC FROM inscripcions WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1')";
	$stmt=$connexio->prepare($cnsInsc);
	$stmt->bind_param("d", $originalIdPag);
	$stmt->execute();
	$stmt->bind_result($tipusInsc);
	$stmt->fetch();
	$connexio->closeStmt();

	if ( $tipusInsc == 'T' ) {
		$pagamentInscripcio = new PagamentTallerAutomatic($originalIdPag);
		$mostrar = $pagamentInscripcio->mostrar();
	}
	else {
		$pagamentInscripcio = new PagamentCursAutomatic($originalIdPag);
		$mostrar = $pagamentInscripcio->mostrar();
	}

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
		echo missatgeError('1501');
}

?>
