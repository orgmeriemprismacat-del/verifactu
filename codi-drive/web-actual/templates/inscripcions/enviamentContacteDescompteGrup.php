<?php
/* ######################################################################### */
/* ###################   Preparem el missatge a enviar   #################### */
/* ######################################################################### */
$missatge = "<p>Benvolgut/da [NOM],</p>
<p>Hem rebut la teva sol·licitud i ens plau comunicar-te que teniu [NUM_ALUMN]
places disponibles en el curs en línia <strong style='color: #496baa'>[TITOL]</strong>
que es realitza <strong>[DATAI_DATAF]</strong>.
Les dades personals de la persona de contacte que ens heu proporcionat són: </p>
[TEXT_DADES_CONTACTE]
".$textCursReconegut."
[TEXT_ALUMNES]
<p>El preu normal seria de [PAY_ORIG] euros,
però amb el descompte pertinent queda reduït a
<span style='font-weight: bold'>[PAY_DESC] euros</span>.</p>
".$textPagament."
[TEXT_ALERT_CONF_INSCR]
".$textConsentimentMailing."
<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";

?>
