<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD.php");

$mail = $_GET['mail'];

$connexio = new ConnexioBBDD();
$connexio->connectarBD();
$connexio->consultarBD("SELECT mail FROM mailing WHERE mail = '".$mail."'");

if ($connexio->obtenirNumRows()<=0) {
	$mostrar = "<div class='form-group field-wrap'>";
	$mostrar .= "<input type='hidden' id='valmail' name='valmail' value='1'>";
	$mostrar .= "<div class='col-md-12'><p class='mailing'>Vols rebre al correu electrònic informació dels cursos i serveis de l'Associació per al Desenvolupament Infantil i Familiar PrisMa? (Podràs donar-t'hi de baixa en qualsevol moment)";
	$mostrar .= "<input type='radio' name='insc_mailing' id='radio_mailing_yes' value='mailing_consentit'>";
	$mostrar .= "<span>S&iacute</span>";
	$mostrar .= "<input type='radio' name='insc_mailing' id='radio_mailing_no' value='mailing_no_consentit'>";
	$mostrar .= "<span>No</span>";
	$mostrar .= "<span id='mailing_erroni' class='textPreuInformatiu carnetWrong'></span></p></div>";
	$mostrar .= "</div>";
}
else {
	$mostrar = "<input type='hidden' id='valmail' name='valmail' value='0'>";
}

echo $mostrar;

	$connexio->lliurarConsulta();
	$connexio->desconectarBD();

?>