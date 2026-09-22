<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../inc/apiRedsys.php");
include("../Mail.php");

try {
	$codiRegal= $_GET['codiRegal'];
	$titolPag = $_GET['titol'];
	$dniTitularPag = $_GET['dni'];
	$nomTitularPag = $_GET['nomTit'];
	$importPag = $_GET['import'];
	$id=time();

	$titular=$dniTitularPag;
	$tit=stripslashes($nomTitularPag);

	//enviem el nom de l'alumne/a per mail amb l'ordre
	$nomFrom = "Gestió PrisMa";
	$correuFrom = "gestio@prisma.cat";
	$correuReply = "gestio@prisma.cat";
	$subject = "Pagament ".$codiRegal." | ".$id;
	$to = "gestio@prisma.cat";
	// TO DO: MODIFICAR
		$to = "meriem.prisma.cat@gmail.com";
	$missPreDiv = "<p>Dades de l'intent de pagament:</p>";
	$missatge="<p><strong>Ordre:</strong> ".$id."</p>
				  <p><strong>Codi regal:</strong> ".$codiRegal."</p>
				  <p><strong>Curs:</strong> ".$titolPag."</p>
				  <p><strong>Nom titular de la targeta:</strong> ".$tit." (".$titular.")</p>
				  <p><strong>DNI:</strong> ".$dniTitularPag."</p>
				  <p><strong>Import pagat:</strong> ".$importPag." euros</p>";

	$mailInscripcions = new Mail();
	$mailInscripcions->addHeaders($nomFrom, $correuFrom, $correuReply);
	$mailInscripcions->addSubject($subject);
	$mailInscripcions->addTo($to);
	$mailInscripcions->addMissatgeTiquet($missPreDiv, $missatge, '');
	$mailInscripcions->sendMessage();
}
catch(Exception $e) {
	if ($e->getCode()==404)
		echo mostrarPagina404();
	else
		echo missatgeError($e->getCode());
}

?>
