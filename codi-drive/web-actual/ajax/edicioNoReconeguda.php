<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

$curs = $_GET['curs'];
$edicio = $_GET['edicio'];
$any = $_GET['any'];

if ($any != 0 && $edicio != '0') {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$stmt = $connexio->prepare("SELECT DATA_RESOL FROM curs WHERE ANY=? AND MES=? AND CURS=?");
	$stmt->bind_param("dss", $any, $edicio, $curs);
	$stmt->execute();
	$stmt->bind_result($dataResol);
	$stmt->fetch();
	if ($dataResol==null or $dataResol=='') {
		$mostrar = "PrisMa, com a entitat organitzadora, ha sol&middot;licitat el
      reconeixement de l'<strong>edici&oacute; marcada </strong> del curs al
      Departament d'Ensenyament. Les edicions anteriors tenen data de resoluci&oacute;
      i per tant, ja estan reconegudes com a Formaci&oacute; Permanent del Professorat.";
	}
	else {
		$mostrar = "";
	}
	$connexio->closeStmt();
	$connexio->desconectarBD();
}
else {
	$mostrar = "";
}

echo $mostrar;

?>
