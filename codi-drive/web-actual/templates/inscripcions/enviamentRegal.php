<?php

if ( intval($codiCurs) != 0)
   $textCurs = "d'un <strong>curs de [CODI_CURS] hores</strong>";
else
   $textCurs = "del curs <strong>[TITOL]</strong>";

/* ######################################################################### */
/* ###################   Preparem el text del mailing   #################### */
/* ######################################################################### */
$missatge = "<p>Benvolgut/da [NOM_ALUMNE],</p>
<p>Hem rebut correctament la teva comanda ".$textCurs.".</p>
   ".$textPagament."
   [TEXT_ALERT_CONF_INSCR]
   <p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>
";
?>
