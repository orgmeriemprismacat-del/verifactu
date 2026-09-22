<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

$mail = $_GET['mail'];

if ($mail != '') {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$stmt = $connexio->prepare("SELECT mail FROM mailing WHERE mail=?");
	$stmt->bind_param("s", $mail);
	$stmt->execute();
   $stmt->store_result();

	if ($stmt->num_rows()<=0) {
		$mostrar = "
		<div class='form-group field-wrap'>
			<input type='hidden' id='valmail' name='valmail' value='1'>
			<p class='mailing'>Vols rebre al correu electr&ograve;nic informaci&oacute; dels cursos i serveis de l'Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa? (Podr&agrave;s donar-t'hi de baixa en qualsevol moment)
			<input type='radio' name='insc_mailing' id='radio_mailing_yes' value='mailing_consentit'>
			<span>S&iacute</span>
			<input type='radio' name='insc_mailing' id='radio_mailing_no' value='mailing_no_consentit'>
			<span>No</span>
			<span id='mailing_erroni' class='text-center text-white'></span></p>
		</div>";
	}
	else {
		$mostrar = "<input type='hidden' id='valmail' name='valmail' value='0'>";
	}

	$connexio->closeStmt();
	$connexio->desconectarBD();
}
else {
	$mostrar = "<input type='hidden' id='valmail' name='valmail' value='0'>";
}

echo $mostrar;

?>
