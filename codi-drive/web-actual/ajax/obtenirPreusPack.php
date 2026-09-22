<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Numero.php");

try {
	$idPreuAc = $_GET['idPreu'];
	$idPack = $_GET['idPack'];

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();
	$connexio2 = new ConnexioBBDDSTMT();
	$connexio2->connectarBD();
	$cnsPreu = "SELECT IMPORT FROM preu WHERE ID=? AND DATAI<=CURRENT_TIME AND
					(CURRENT_TIME<=DATAF OR DATAF IS NULL)";
	$cnsIdPreuEd = "SELECT curs.ID_PREU FROM info_pack INNER JOIN packs ON
		info_pack.ID_PACK=packs.ID_PACK INNER JOIN curs ON packs.ID_CURS=curs.ID_CURS
		WHERE packs.ID_PACK = ? AND packs.PUBLIC = 1";
   if ( $stmt = $connexio->prepare($cnsPreu) ) {
	   $stmt->bind_param("d", $idPreuACons);

		//Calcular el preu original del curs
		$preuOrig = 0;

		if ( $stmt2 = $connexio2->prepare($cnsIdPreuEd) ) {
			$stmt2->bind_param("s", $idPack);
			$stmt2->execute();
			$stmt2->bind_result($idPreu);
			while ( $stmt2->fetch() ){
				$idPreuACons = $idPreu;
				$stmt->execute();
				$stmt->bind_result($import);
				$stmt->fetch();
				$preuOrig = floatval($import) + $preuOrig;
			}
		}
		else {
			throw new Exception('',2908);
		}

		$objImp = new Numero($preuOrig);
		$importOrigFormCorrecteOK = $objImp->mostrarNumeroDecimalsSense0();

		//Buscar el preu del pack
		$idPreuACons = $idPreuAc;

	   $stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows() > 0) {
		   $stmt->bind_result($import);
		   $stmt->fetch();
			$objImp = new Numero($import);
			$importFormatCorrecte = $objImp->mostrarNumeroDecimalsSense0();
		}
		$connexio->closeStmt();

		if ($importFormatCorrecte=='' or $importFormatCorrecte==null)
			throw new Exception('',2906);
		else if ($importOrigFormCorrecteOK=='' or $importOrigFormCorrecteOK==null)
			throw new Exception('',2907);
		else {
			echo $importOrigFormCorrecteOK."|".$importFormatCorrecte;
		}
	}
	else {
		throw new Exception('',2905);
	}
	$connexio2->desconectarBD();
	$connexio->desconectarBD();
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
