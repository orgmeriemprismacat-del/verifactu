<?php
session_start();
require_once '../regal/dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include('../Text.php');
include("../Numero.php");
include('../Url.php');
include('../RegalCurs.php');
include("../Mail.php");
include("../MailSMTP.php");
include("../MailSMTPComvive.php");

require_once '../regal/dompdf/autoload.inc.php';

try {
	$nom = $_GET['nom'];
	$cog = $_GET['cog'];
	$dni = $_GET['dni'];
	$telf = $_GET['telf'];
	$email = $_GET['email'];
	$adreca = $_GET['adreca'];
	$cp = $_GET['codiPostal'];
	$poblacio = $_GET['poblacio'];
	$comentaris = $_GET['comentaris'];
	$codiCurs = $_GET['codiCurs'];
	$nomCurs = $_GET['nomCurs'];
	$preu = $_GET['preu'];
	$percentatge = $_GET['percentatge'];
	$hores = $_GET['hores'];
	$codiRegal = $_GET['codiRegal'];
	$estilRegal = $_GET['estilRegal'];
	$desti = $_GET['desti'];
	$origen = $_GET['origen'];
	$dedicatoria = $_GET['dedicatoria'];

	$regal = new RegalCurs('ordinador');

	$mostrar = $regal->enviarInscripcioRegal($nom, $cog, $dni, $telf, $email, $adreca, $cp, $poblacio, $comentaris, $codiCurs, $nomCurs, $preu, $percentatge, $hores, $codiRegal, $estilRegal, $origen, $desti, $dedicatoria);

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
