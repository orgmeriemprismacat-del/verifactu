<?php

$pendentPay = $urlPendentPay = '';
if ( $frac == 1 && $pendentPagar > 0 ) {
   $pendentPay = "<p><strong>Pendent de pagar:</strong> [PENDENT_PAY] € de [PAY_ORIG] €</p>";
   $urlPendentPay = "<p>Et recordem l'enllaç per fer els pagaments posteriors:
   <a href='[URL_PAY]' title='Pagament del curs [TITOL]'>[URL_PAY]</a></p>";
}

/* ######################################################################### */
/* ###################   Preparem el missatge a enviar   #################### */
/* ######################################################################### */
$missatge = "<div style='background-color: #e8ecf5; border: 1px solid #D7DEEE;
border-radius: 2px; padding: 5px 25px; margin-bottom: 20px'>
   <p><strong>N. de comanda:</strong> [NU_COMANDA]</p>
   <p><strong>Concepte:</strong> [CONCEPTE]</p>
   <p><strong>Correu electrònic: </strong> [EMAIL]</p>
   <p><strong>Import:</strong> [IMPORT] €</p>
   <p><strong>Resultat:</strong> Acceptat</p>
   <p><strong>Data i hora:</strong> [DATETIME_COMANDA]</p>
   ".$pendentPay."
</div>".$urlPendentPay;

?>
