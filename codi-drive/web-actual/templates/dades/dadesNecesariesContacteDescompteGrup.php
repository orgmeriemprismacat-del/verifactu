<?php

/* ######################################################################### */
/* ################# Preparem el text del text reconegut  ################## */
/* ######################## ################################################# */
$textCursReconegut = "<p>Aquest curs està reconegut pel Departament d'Educació de la
Generalitat de Catalunya i té una durada lectiva de <strong>[HORES] hores</strong>.</p>";

if ( $dataResol == null || $dataResol == '' )
   $textCursReconegut = "<p>Aquest curs té una durada lectiva de <strong>[HORES] hores</strong>.<p>
   <p>PrisMa, com a entitat organitzadora, ha sol·licitat el reconeixement de
   les edicions del curs escolar 2024-2025 al Departament d'Educació. Tan bon
   punt surti la resolució t'avisarem a través del correu electrònic.";

if ( $titolDocencia )
   $textCursReconegut .= "Els nostres cursos compten com a formació permanent del
   professorat sempre que es realitzin posteriorment a la data d’expedició del
   títol d’accés a la docència (Magisteri o CAP / Màster en Educació Secundària).";

/* ######################################################################### */
/* ###################   Preparem el text del mailing   #################### */
/* ######################################################################### */
$textConsentimentMailing = '';
if ( $mailing == 'Yes' ) {
   $textConsentimentMailing = "<p>Et recordem que has marcat la casella per rebre
   correus electrònics informatius dels nostres cursos i serveis. Tot i això,
   podràs donar-te de baixa de la nostra llista de correus en qualsevol moment.</p>";
}

/* ######################################################################### */
/* ###################   Preparem el text de pagament   #################### */
/* ######################## ################################################# */
if ( $mostrarPagament ) {
   $textPagament = "<p>Per tal de pagar els <strong>[PAY_DESC] euros</strong>
   de la matrícula, pots escollir una de les opcions següents:</p>
   [TEXT_MANERES_PAGAR]";
}
?>
