<?php
   $textPreu = '';

   if ( $numeroCursosDiff > 1 ) {
      $textPreu .= "<h3 class='mb-4'>Preu total: <span class='tatxat'
      style='text-decoration: line-through;color: #b2b2b2;;'>[PAY_TOTAL_ORIG] euros</span>
      <span class='dada'>[PAY_TOTAL_DESC] euros</span></h3>";
   }
   else {
      $textPreu .= "<p class='dades pt-3'>Curs: <span class='dada titol'>[TITOL]</span></p>
      <p class='dades'>Dates: <span class='dada'>[DATAI_FATAF]</span></p>
      <p class='dades'>Durada: <span class='dada'>[DURADA] hores</span></p>";

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
      $textPreu .= "<p class='dades'>S'ha pagat: <span class='dada'>[PRICE_PAY] euros</span></p>
      <p class='dades'>Falta pagar: <span class='dada'>[FALTA_PAY] euros</span></p>";
   }

   $missatge ="<div class='d-flex flex-column'>
      <div class=''>
      <h1 class='mb-4'>[TITOL_PAGE]</h1>
      [DADES]
      ".$textPreu."
      [VISTA_PAY]
   </div>";

?>
