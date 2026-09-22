<?php
   $textPreu = '';
   if ( $fracc ) {
      $textPreu .= "<p>Has triat l'opció de pagament fraccionat (sense recàrrec).</p>
      <p>Pots triar les quantitats i el termini del pagament sempre que facis un
      primer pagament abans de  l’inici del curs i que hagis abonat l’import complet
      com a màxim una setmana després de la finalització del curs.</p>
      <p class='dades'>Preu: <span class='dada'>[PAY] euros</span></p></div>
      <ul>
         <li class='dades'>Pagat: <span class='dada'>[PRICE_PAY] euros</span></li>
         <li class='dades'>Pendent: <span class='dada'>[FALTA_PAY] euros</span></li>
      </ul>";
   }
   else {
      $textPreu .= "<p class='dades'>Preu: <span class='dada'>[PAY] euros</span></p>";
   }

   $missatge ="<div class='d-flex flex-column'>
      <div class=''>
         <h1 class='mb-4'>[TITOL_PAGE]</h1>
         <p class='dades pt-3'>Curs regal: <span class='dada titol'>[TITOL]</span></p>
         <p class='dades'>Dates: <span class='dada'>[DATAI_FATAF]</span></p>
         <p class='dades'>Durada: <span class='dada'>[DURADA] hores</span></p>
         ".$textPreu."
      </div>
      [VISTA_PAY]
   </div>";

?>
