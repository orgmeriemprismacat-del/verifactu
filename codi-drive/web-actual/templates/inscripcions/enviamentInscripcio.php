<?php
/* ######################################################################### */
/* ###################   Preparem el missatge a enviar   #################### */
/* ######################################################################### */
$missatge = "<p>Benvolgut/da [NOM_ALUMNE],</p>
<p>Hem rebut la teva sol·licitud i ens plau comunicar-te que
ja t'hem inscrit en el curs en línia <strong style='color: #496baa'>[TITOL]</strong>
que es realitza <strong>[DATAI_DATAF]</strong> amb les dades personals següents:</p>
[TEXT_DADES_ALUMNE]
".$textCursReconegut."
".$textEstudiant."
[TEXT_HAS_REALITZAT_CURS]
".$textPagament."
".$textIniciCurs."
[TEXT_ALERT_CONF_INSCR]
".$textConsentimentMailing."
<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";

?>
