<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

$val = $_GET['val'];

$mostrar .= "
<li class='border-bottom m-0' id='conegut-recomenacio'>Me l'han recomanat</li>
<li class='border-bottom m-0' id='conegut-cercador'>L'he trobat en un cercador (Google, Bing...)</li>
<li class='border-bottom m-0' id='conegut-xarxes'>L'he vist a les xarxes socials (Facebook, Instagram, Twitter...)</li>
<li class='border-bottom m-0' id='conegut-webPrisMa'>L'he vist al web de PrisMa</li>";

if ($val=='0')
	$mostrar .= "<li class='border-bottom m-0' id='conegut-mailing'>He rebut el butllet&iacute electronic (<em>Newsletter</em>)</li>";

$mostrar .= "<li class='border-bottom m-0' id='conegut-altres'>Altres (indica'ns com)</li>";

echo $mostrar;

?>
