<?php
/* ######################################################################### */
/* ###################   Preparem el missatge a enviar   #################### */
/* ######################################################################### */
$missatge = "<p>Benvolgut/da [NOM_ALUMNE],</p>
<p>Hem rebut la teva sol·licitud i ens plau comunicar-te que ja t'hem inscrit en el
<em>pack</em> <strong style='color: #496baa'>[TITOL]</strong> que inclou els
cursos <strong>[TITOL1] ([DATAI_DATAF1])</strong> i
<strong>[TITOL2] ([DATAI_DATAF2])</strong> amb les dades personals següents:</p>
[TEXT_DADES_ALUMNE]
".$textCursReconegut."
".$textEstudiant."
".$textPagament."
".$textIniciCurs."
".$textConsentimentMailing."
<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";

?>
