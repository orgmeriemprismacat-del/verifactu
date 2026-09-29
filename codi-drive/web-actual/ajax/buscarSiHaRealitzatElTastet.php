<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");

// Compatibilitat temporal amb clients antics GET; el flux actual usa POST.
$request = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;
$doc = isset($request['doc']) ? $request['doc'] : '';
$curs = isset($request['curs']) ? $request['curs'] : '';

	if ($doc == '') {
		echo '';
		return;
	}

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$curs = '';

	$cnsInsc = "SELECT DATA_INSC FROM inscripcions_reptes WHERE CURS=? AND DNI=? AND INSC_CURS=1";
   $stmt=$connexio->prepare($cnsInsc);
   $stmt->bind_param("ss", $curs, $doc);
   $stmt->execute();
   $stmt->store_result();
	if ($stmt->num_rows() > 0) {
		$stmt->bind_result($dataInsc);
		$stmt->fetch();
		$connexio->closeStmt();

		$cnsTitol = "SELECT TITOL FROM reptes WHERE CODI_CURS=?";
		$stmt=$connexio->prepare($cnsTitol);
		$stmt->bind_param("s", $curs);
		$stmt->execute();
		$stmt->bind_result($titol);
		$stmt->fetch();
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
	else {
		$connexio->closeStmt();
	}
	$connexio->desconectarBD();

	$connexio->closeStmt();
	$connexio->desconectarBD();
	echo $mostrar;
}
catch(Throwable $e) {
	echo missatgeError($e->getCode());
}

?>