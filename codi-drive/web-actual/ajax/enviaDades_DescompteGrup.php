<?php
include("../ConnexioBBDD_PreparedStatment.php");
include('../Date.php');
include('../Text.php');
include('../Url.php');
include('../Numero.php');
include('../Mail.php');
include('../MailSMTP.php');
include("../MailSMTPComvive.php");
include('../DescompteGrup.php');
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
session_start();

try {
	$comentaris = $_GET['comentaris'];
	$mailing = $_GET['mailing'];
	$tipusInsc = $_GET['tipusInsc'];

	$descompteGrup = unserialize($_SESSION['descompteGrup']);

	$mostrar = $descompteGrup->enviarDades($comentaris, $mailing, $tipusInsc);

	$_SESSION['descompteGrup'] = serialize($descompteGrup);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
