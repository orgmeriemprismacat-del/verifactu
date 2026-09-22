<?php

   /* ######################################################################### */
   /* ################# Preparem el text del text reconegut  ################## */
   /* ######################## ################################################# */
   $textCursReconegut = "<p>Aquest curs està reconegut pel Departament d'Educació de la
   Generalitat de Catalunya i té una durada lectiva de <strong>[HORES] hores</strong>.</p>";

   if ( $dataResol == null || $dataResol == '' )
      $textCursReconegut = "<p>Aquest curs té una durada lectiva de <strong>[HORES] hores</strong>.<p>
      <p>PrisMa, com a entitat organitzadora, ha sol·licitat el reconeixement de
      les edicions del curs escolar [CURS_ESCOLAR] al Departament d'Educació. Tan bon
      punt surti la resolució t'avisarem a través del correu electrònic.";

   if ( $titolDocencia )
      $textCursReconegut .= "Els nostres cursos compten com a formació permanent del
      professorat sempre que es realitzin posteriorment a la data d’expedició del
      títol d’accés a la docència (Magisteri o CAP / Màster en Educació Secundària).";

   /* ######################################################################### */
   /* ###################   Preparem el text d'estudiant   #################### */
   /* ######################################################################### */
   $textEstudiant = '';
   if ( strpos( $titulacioAlumne, 'Encara no tinc cap titulació, sóc estudiant de,') !== false ) {
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
      if ( !$alumnePrisma )
         $textIniciCurs = "<p>En un període de 24 hores laborals rebràs un correu
         electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
   }
   else {
      $textIniciCurs = "<p>Uns dies abans de l'inici del curs podràs accedir a
      l'apartat general de l'aula amb les teves claus.</p>";
      if ( !$alumnePrisma )
         $textIniciCurs = "<p>Uns dies abans de l'inici del curs rebràs un correu
         electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
   }

   /* ######################################################################### */
   /* ###################   Preparem el text de pagament   #################### */
   /* ######################## ################################################# */
   if ( $mostrarPagament ) {
      $textPagament = "<p>Si tens algun dubte sobre el pagament o sobre la teva
      disponibilitat per fer el curs en aquestes dates, posa’t en contacte amb
      <strong>[NOM_COG_CONTACTE]</strong> al correu electrònic
      <strong>[EMAIL_CONTACTE]</strong>.</p>";
   }
?>
