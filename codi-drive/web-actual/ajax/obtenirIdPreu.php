<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$codi = $_GET['codi'];

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsIdPreu = "SELECT HORES, ID_PREU FROM curs WHERE
		( (DATAI+7>CURRENT_DATE AND (HORES=30 OR HORES=40 OR HORES=60)) OR
		(DATAI+14>CURRENT_DATE AND (HORES=100)) ) AND CURS LIKE ? AND PUBLIC=1
		AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
		ORDER BY ANY, MES LIMIT 1";
	$stmtIdPreu=$connexio->prepare($cnsIdPreu);
	$stmtIdPreu->bind_param("s", $codi);
	$stmtIdPreu->execute();
	$stmtIdPreu->store_result();

	if ( $stmtIdPreu->num_rows() > 0 ) {
		$stmtIdPreu->bind_result($hores, $idPreu);
		$stmtIdPreu->fetch();
		$connexio->closeStmt();
	}
	else {
		//Consulto les hores de la ultima edicio
		$cnsHoresLastEd="SELECT HORES
		FROM curs WHERE CURS LIKE ? AND PUBLIC=1
		AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
		ORDER BY ANY DESC, MES DESC LIMIT 1";
		$stmtLastEd = $connexio->prepare($cnsHoresLastEd);
		$stmtLastEd->bind_param("s", $codi);
		$stmtLastEd->execute();
		$stmtLastEd->bind_result($hores);
		$stmtLastEd->fetch();
		$connexio->closeStmt();

		/*Consulto si hi ha un canvi en els dies que estan oberts després de la
		data d'inici del curs per inscriure's al curs*/
		$cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
		DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
		$stmtParam = $connexio->prepare($cnsParams);
		$stmtParam->bind_param("ss", $tipus, $orderBy);
		$tipus='dies-inscriu-cursos';
		$orderBy='VALOR';
		$stmtParam->execute();
		$stmtParam->bind_result($valor);
		$diesObets=0;
		while ($stmtParam->fetch()) {
			$valors = explode('|',$valor);
			if (count($valors) != 2)
				$diesObets=0;
			else if (intval($valors[0])>0 && $valors[0]==$hores) //si el valor és un numero i les hores son iguals al curs
				$diesObets = $valors[1];
			else if (intval($valors[0])<=0 && $valors[0]==$codi) //si el valor no és un numero i el codi és igual al curs
				$diesObets = $valors[1];
		}
		$connexio->closeStmt();

		$consultaHoresPreu = "SELECT HORES, ID_PREU FROM curs WHERE
			 DATAI+?>CURRENT_DATE AND CURS LIKE ? AND PUBLIC=1
			 AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
			 ORDER BY ANY, MES LIMIT 1";
		 $stmtIdPreu=$connexio->prepare($consultaHoresPreu);
		 $stmtIdPreu->bind_param("ds", $diesObets, $codi);
		 $stmtIdPreu->execute();
		 $stmtIdPreu->store_result();
		 if ( $stmtIdPreu->num_rows() > 0 ) {
			 $stmtIdPreu->bind_result($hores, $idPreu);
			 $stmtIdPreu->fetch();
		 }
		 $connexio->closeStmt();
	}
	if ($idPreu=='' or $idPreu==null) {
		throw new Exception('',1302);
	}
	else echo $idPreu."|".$hores;

	$connexio->desconectarBD();
}
catch(Exception $e) {
if ($e->getCode()==404)
	echo mostrarPagina404();
else
	echo missatgeError($e->getCode());
}

?>
