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
/* ###################   Preparem el text d'estudiant   #################### */
/* ######################################################################### */
$textEstudiant = '';
if ( $textTitulacioEstudiant != null) {
   $textEstudiant = "<p>En el teu cas, ​atès que encara ​no disposes d'aquesta titulació
   finalitzada, rebràs un certificat de PrisMa que p​odràs fer constar​ com ​a ​
   currículum personal però ​que ​no ​te donarà punts en convocatòries oficials
   del Departament d'Educació. En el cas que tinguis uns estudis universitaris
   finalitzats, si us plau, contacta amb nosaltres perquè rectifiquem la sol·licitud.</p>";
}


/* ######################################################################### */
/* ################### Busquem la data d'inici del curs #################### */
/* ######################################################################### */
if ( $datai <= date('Y-m-d') ) { /*ha començat el curs*/
   $textIniciCurs = "<p>En un període de 24 hores laborals podràs accedir al
   curs amb les teves claus.</p>";
   if ( $tipusDescompte == 0 )
      $textIniciCurs = "<p>En un període de 24 hores laborals rebràs un correu
      electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
}
else {
   $textIniciCurs = "<p>Uns dies abans de l'inici del curs podràs accedir a
   l'apartat general de l'aula amb les teves claus.</p>";
   if ( $tipusDescompte == 0 )
      $textIniciCurs = "<p>Uns dies abans de l'inici del curs rebràs un correu
      electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
}

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
$textManeresPagar = "[TEXT_MANERES_PAGAR]";
if ( $tipusDescompte == 0 ) {
   $textManeresPagar .= "<p style='text-decoration:underline'>Nota: si estàs inscrit en un altre
      curs <em>on-line</em> de PrisMa però et falta efectuar el pagament o
      b&eacute; t'has trobat amb alguna incidència en l'import, contacta amb
      nosaltres per recalcular el preu.</p>";
}

if ( $mostrarPagament ) {
   $textPagament = '';
   if ($pagFrac == 'Yes')
      $textPagament = "<p>Perquè puguem fraccionar el pagament, t'agrairíem que ens
         comuniquis, contestant aquest mateix correu, com vols pagar els
         <strong>[PAY_DESC_ALUMNE] euros</strong> (quantitats i terminis) i que
         efectuís tots els ingressos escollint una de les opcions següents: </p>
         ".$textManeresPagar."
         <p>T'informem que cal efectuar un primer pagament abans de l'inici del curs i
         que després de la finalització d'aquest encara disposaràs d'uns dies
         per acabar de pagar els [PAY_DESC_ALUMNE] euros.</p>";
   else
      $textPagament = "
         <p>Per tal de pagar els <strong>[PAY_DESC_ALUMNE] euros</strong> de la matrícula
         [TEXT_DESC_ALUMNE], pots escollir una de les opcions següents:</p>
         ".$textManeresPagar;
}

if ( $tipusCurs == 'S' )
   $textPagament = "<p><strong>Curs subvencionat per a docents de centres
   públics i de centres privats sostinguts amb fons públics, en el marc del
   Pla de Recuperació, Transformació i Resiliència (PRTR), finançat per la
   Unió Europea - Next Generation EU.</strong></p>";


?>
