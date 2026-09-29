<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Numero.php");
include("../PaginaConfirmacioTastet.php");
include("../Uc108ConfirmationToken.php");

try {
	$encr = isset($_GET['keyEncr']) ? trim($_GET['keyEncr']) : '';
	$urlTastet = isset($_GET['urlTastet']) ? trim($_GET['urlTastet']) : '';

	if ($encr == '') {
		echo missatgeError('1401');
		return;
	}

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	$stmt = $connexio->prepare($cnsParam);
	$stmt->bind_param("s", $tipusParam);
	$tipusParam = 'keyEncriptar';
	$stmt->execute();
	$stmt->bind_result($keyEncr);
	$stmt->fetch();
	$connexio->closeStmt();

	$originalId = null;
	$tokenValid = false;

	if (strpos($encr, Uc108ConfirmationToken::PREFIX) === 0) {
		$payload = Uc108ConfirmationToken::verify($encr, $urlTastet, $keyEncr);
		if ($payload !== null) {
			$originalId = $payload['id'];
			$tokenValid = true;
		}
	}
	else {
		$legacyId = Uc108ConfirmationToken::verifyLegacy($encr, $keyEncr);
		if ($legacyId !== null) {
			$originalId = $legacyId;
			$tokenValid = true;
		}
	}
	if ($tokenValid) {
		$pagina = new PaginaConfirmacioTastet($originalId);
		$mostrar = $pagina->mostrarPaginaConfirmacio();
	}
	else {
		$mostrar = missatgeError('1401');
	}

	$connexio->desconectarBD();
	echo $mostrar;
}
catch(Throwable $e) {
	if ($e->getCode() == 404)
		echo mostrarPagina404();
	else
		echo missatgeError($e->getCode());
}

?>