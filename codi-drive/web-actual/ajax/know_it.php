<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD.php");

$val = $_GET['val'];

$mostrar = "<div class='form-group field-wrap'>";
$mostrar .= "<select class='form-control' id='com_conegut' name='com_conegut' size='1' onChange='conegut_altres()'>";
$mostrar .= "<option value='' selected>Com has conegut aquest curs? Tria una opci&oacute;</option>";
$mostrar .= "<option value='Recomanacio'>Me l'han recomanat</option>";
$mostrar .= "<option value='Cercador'>L'he trobat en un cercador (Google, Bing...)</option>";
$mostrar .= "<option value='Xarxes'>L'he vist a les xarxes socials (Facebook, Instagram, Twitter...)</option>";
$mostrar .= "<option value='Web PrisMa'>L'he vist al web de PrisMa</option>";

if ($val=="0") {
	$mostrar .= "<option value='Mailing'>He rebut el butllet&iacute electronic (<em>Newsletter</em>)</option>";
}

$mostrar .= "<option value='Altres'>Altres (indica'ns com)</option>";

$mostrar .= "</select><span id='conegut_erroni' class='select_erroni'></span>";
$mostrar .= "</div>";
echo $mostrar;

?>