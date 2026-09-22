<?php
   $textPreu = '';

   if ( !($numeroCursosDiff > 1) ) {
      if ( !$fracc ) {
         $textPreu .= "<p class='dades'>Preu: <span class='dada'>[PAY] euros</span></p>";
      }
      else {
         $textPreu .= "<p>Has triat l'opció de pagament fraccionat (sense recàrrec).</p>
         <p>Pots triar les quantitats i el termini del pagament sempre que facis
         un primer pagament abans de  l’inici del curs i que hagis abonat l’import complet
         com a màxim una setmana després de la finalització del curs.</p>
         <p class='dades'>Preu: <span class='dada'>[PAY] euros</span></p>
         </div>
         </div>";
      }
   }

   if ( $preuPagat > 0 ) {
      $textPreu .= "<p class='dades'>S'ha pagat: <span class='dada'>[PREU_PAGAT] euros</span></p>
      <p class='dades'>Falta pagar: <span class='dada'>[FALTA_PAGAR]euros</span></p>";
   }

   $missatge ="<div class='d-flex flex-column'>
      <div class=''>
         <h1 class='mb-4'>[TITOL_PAGE]</h1>
         <p class='dades pt-3'><em>Pack</em>: <span class='dada titol'>[TITOL]</span></p>
         <ul>[TEXT_INFO_PACK]</ul>
         [VISTA_PAY]
   </div>";

?>
