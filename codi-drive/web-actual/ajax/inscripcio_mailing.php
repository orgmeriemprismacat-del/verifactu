<!-- AQUEST FITXER ÉS UITLITZAT PER:
/informacio/js/buscar_mail.js   -->

<?php
$mail = $_REQUEST['mail'];

include('../parametres_connexio.php');

$connexio = mysqli_connect($servidor,$usuari,$pw,$bbdd);
if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($connexio, "utf8");

$result = mysqli_query ($connexio, "SELECT mail FROM mailing WHERE mail = '".$mail."'");

if (mysqli_num_rows($result)==0)
{
	?>
    <input type="hidden" id="valmail" name="valmail" value="1">
    <font class="normal" style="float:left;">Vols rebre al correu electr&ograve;nic informaci&oacute; dels cursos i serveis de l'Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa? (Podr&agrave;s donar-t'hi de baixa en qualsevol moment) </font>
    <div id="error_mailing" style="float:left; margin-left: 5px; height: 24px; padding: 1px 5px 0px 2px;">
        <input type="radio" name="insc_mailing" id="insc_mailing" value="mailing_consentit">
        <font class="normal">S&iacute;</font>
        <input type="radio" name="insc_mailing" id="insc_mailing" value="mailing_no_consentit">
        <font class="normal">No</font>
    </div>
    <br>
    <font class="normal">(podr&agrave;s donar-t'hi de baixa en qualsevol moment)</font>

     <?php
}
else
{
	?>
	<input type="hidden" id="valmail" name="valmail" value="0">
    <?php
}


mysqli_close($conexion);
?>
