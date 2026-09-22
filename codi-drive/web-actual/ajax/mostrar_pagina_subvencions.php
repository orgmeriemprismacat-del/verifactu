<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Curs.php");
include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$dispositiu = $_GET['dispositiu'];

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();
	$cnsUrlPerf="SELECT ID FROM amigable WHERE URL = '/cursos-subvencionats'";
	$stmt=$connexio->prepare($cnsUrlPerf);
	$stmt->execute();
	$stmt->bind_result($idUrl);
	$stmt->fetch();
	$connexio->closeStmt();
	$cnsIdContPage="SELECT ID_CONTINGUT FROM pagina WHERE ESTAT=1 AND ID_URL=?";
	$stmt=$connexio->prepare($cnsIdContPage);
	$stmt->bind_param("d", $idUrl);
	$stmt->execute();
	$stmt->bind_result($idCont);
	$stmt->fetch();
	$connexio->closeStmt();
	$cnsContPage="SELECT CONTINGUT FROM contingut WHERE ID=?";
	$stmt=$connexio->prepare($cnsContPage);
	$stmt->bind_param("d", $idCont);
	$stmt->execute();
	$stmt->bind_result($contingut);
	$stmt->fetch();
	$connexio->closeStmt();
	$connexio->desconectarBD();

	$mostrar = $contingut;
	echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
