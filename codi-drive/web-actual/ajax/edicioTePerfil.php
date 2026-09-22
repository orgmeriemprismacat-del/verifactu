<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");

$edicio = $_GET['edicio'];
$any = $_GET['any'];
$curs = $_GET['curs'];

try {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$mostrar = 'false';

	$cnsCurs = "SELECT HORES, CURS_ESCOLAR, GTAF  FROM curs WHERE (CURS LIKE ?
               AND ANY=? AND MES=?)";
   $stmt=$connexio->prepare($cnsCurs);
   $stmt->bind_param("sds", $curs, $any, $edicio);
   $stmt->execute();
	$stmt->bind_result($hores, $cursEscolar, $gtaf);
	$stmt->fetch();
   $connexio->closeStmt();

	$cnsPerf = "SELECT ID_PERFIL FROM perfils WHERE (CODI LIKE ?
               AND HORES=? AND CURS_ESCOLAR=? AND CODI_GTAF=?) GROUP BY ID_PERFIL";
   $stmt=$connexio->prepare($cnsPerf);
   $stmt->bind_param("sdss", $curs, $hores, $cursEscolar, $gtaf);
   $stmt->execute();
   $stmt->store_result();
	if ($stmt->num_rows() > 0) {
		$mostrar = "true";
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
