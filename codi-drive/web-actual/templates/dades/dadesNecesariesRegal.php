<?php
/* ######################################################################### */
/* ###################     Preparem el text deL PREU     #################### */
/* ######################## ################################################# */
$textPreu = "[PAY] €";
if ( $percentatgeBD>0 ) {
   $textPreu = "<span style='text-decoration: line-through'>[PAY_ORIG] €</span> [PAY] €";
}

/* ######################################################################### */
/* ###################   Preparem el text de pagament   #################### */
/* ######################## ################################################# */
// $textPagament = "<p>Per tal de formalitzar-la, pots realitzar el pagament
// de <strong>".$textPreu."</strong> escollint una de les opcions següents:</p>
// [TEXT_MANERES_PAGAR]
// <p>Un cop hagis realitzat el pagament, en menys d’un dia
// laboral rebràs la teva targeta regal amb les instruccions per bescanviar-la.</p>
// <p>És important que conservis el justificant bancari fins que t'arribi
// un correu electrònic que confirmi que hem rebut correctament el teu pagament.</p>";
$textPagament = "<p>Per tal de formalitzar-la, pots realitzar el pagament
de <strong>".$textPreu."</strong> escollint una de les opcions següents:</p>
[TEXT_MANERES_PAGAR]
<p>A més, en un termini màxim d’un dia laborable, rebràs la teva targeta regal
amb les instruccions per bescanviar-la.</p>";


?>
