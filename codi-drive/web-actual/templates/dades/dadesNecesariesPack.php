<?php

/* ######################################################################### */
/* ################# Preparem el text del text reconegut  ################## */
/* ######################## ################################################# */
$textCursReconegut = "<p>Aquests cursos estan reconeguts pel Departament d'Educació
de la Generalitat de Catalunya.</p>";

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
/* ###################   Preparem el text de pagament   #################### */
/* ######################## ################################################# */
$textManeresPagar = "[TEXT_MANERES_PAGAR]";

$textPagament = '';
if ($pagFrac == 'Yes')
   $textPagament = "<p>Perquè puguem fraccionar el pagament, t'agrairíem que ens
      comuniquis, contestant aquest mateix correu, com vols pagar els
      <strong>[PAY_PACK_ALUMNE] euros</strong> (quantitats i terminis) i que
      efectuís tots els ingressos escollint una de les opcions següents: </p>
      ".$textManeresPagar."
      <p>T'informem que cal efectuar un primer pagament abans de l'inici del curs i
      que després de la finalització d'aquest encara disposaràs d'uns dies
      per acabar de pagar els [PAY_PACK_ALUMNE] euros.</p>

      <p>T'informem que cal efectuar un primer pagament a l'inici del curs i
      que després de la finalització de l’últim dels cursos del <em>pack</em> ([DATAF_LLARGA]) encara
      disposaràs d'una setmana per acabar de pagar els <strong>[PAY_PACK_ALUMNE] euros</strong>.</p>";
else
   $textPagament = "
      <p>Per tal de pagar els <span style='text-decoration: line-through; color: #A7A7A7;'>
      [PAY_ORIG_ALUMNE] euros</span> <strong>[PAY_PACK_ALUMNE] euros</strong> de la matrícula
      [TEXT_DESC_ALUMNE], pots escollir una de les opcions següents:</p>
      ".$textManeresPagar;

/* ######################################################################### */
/* ################### Busquem la data d'inici del curs #################### */
/* ######################################################################### */
if ( $datai <= date('Y-m-d') ) { /*ha començat el curs*/
   $textIniciCurs = "<p>En un període de 24 hores laborals podràs accedir al
   curs amb les teves claus.</p>";
   if ( !$esAlumne )
      $textIniciCurs = "<p>En un període de 24 hores laborals rebràs un correu
      electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
}
else {
   $textIniciCurs = "<p>Uns dies abans de l'inici del curs podràs accedir a
   l'apartat general de l'aula amb les teves claus.</p>";
   if ( !$esAlumne )
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
?>
