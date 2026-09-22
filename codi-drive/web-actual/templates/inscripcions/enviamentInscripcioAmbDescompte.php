<?php

$textMsgNovell = '';
if ( $novell )
   $textMsgNovell .= "<p>I tan bon punt hàgim validat el títol de docent i rebut el pagament,
   t’enviarem un codi per descomptar l’import pagat en el pròxim curs de PrisMa a què et matriculis.</p>";

$missatge = "<p>Benvolgut/da [NOM_ALUMNE],</p>
[TEXT_INTRO_CAS_DESCOMPTE]
[TEXT_DADES_ALUMNECURS]
<p>Un cop confirmat, acabarem el procés de reserva de la plaça i t’enviarem les
dades per fer el pagament amb el descompte.</p>
".$textMsgNovell."
<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";

?>
