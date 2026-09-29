<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");

try {
	$input = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;
	$doc = isset($input['doc']) ? trim($input['doc']) : '';
	$urlTastet = isset($input['urlTastet']) ? trim($input['urlTastet']) : '';
	$cursLegacy = isset($input['curs']) ? trim($input['curs']) : '';

	if ($doc == '') {
		echo '';
		return;
	}

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$curs = '';

	if ($urlTastet != '') {
		$idUrl = buscarPagina($urlTastet);
		if ($idUrl != null && $idUrl != '') {
			$cnsRepte = "SELECT CODI_CURS FROM reptes WHERE ID_URL=? AND ESTAT=1";
			$stmt = $connexio->prepare($cnsRepte);
			$stmt->bind_param("d", $idUrl);
			$stmt->execute();
			$stmt->store_result();

			if ($stmt->num_rows() > 0) {
				$stmt->bind_result($curs);
				$stmt->fetch();
			}
			$connexio->closeStmt();
		}
	}
	else if ($cursLegacy != '') {
		// Compatibilitat temporal amb clients JS antics.
		$cnsRepte = "SELECT CODI_CURS FROM reptes WHERE CODI_CURS=? AND ESTAT=1";
		$stmt = $connexio->prepare($cnsRepte);
		$stmt->bind_param("s", $cursLegacy);
		$stmt->execute();
		$stmt->store_result();

		if ($stmt->num_rows() > 0) {
			$stmt->bind_result($curs);
			$stmt->fetch();
		}
		$connexio->closeStmt();
	}

	if ($curs == '') {
		$connexio->desconectarBD();
		echo "Error: el tastet no està disponible.";
		return;
	}

	$mostrar = '';
	$cnsInsc = "SELECT ID
		FROM inscripcions_reptes
		WHERE CURS=? AND DNI=? AND INSC_CURS=1
		LIMIT 1";
	$stmt = $connexio->prepare($cnsInsc);
	$stmt->bind_param("ss", $curs, $doc);
	$stmt->execute();
	$stmt->store_result();

	if ($stmt->num_rows() > 0) {
		$mostrar = 'DUPLICATE';
	}

	$connexio->closeStmt();
	$connexio->desconectarBD();
	echo $mostrar;
}
catch(Throwable $e) {
	echo missatgeError($e->getCode());
}

?>