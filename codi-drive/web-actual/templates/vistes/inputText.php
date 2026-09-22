<?php

   $textMaxLenght = '';
   if ( $id == 'fraccionat' ) $textMaxLenght = " maxlength='6'";

   $input ="<div class='form-group field-wrap position-relative p-1 w-100'>
      <label>
         <span class='fons'></span>
         <span class='camp'>[NOM_LABEL]</span>
         <span class='req ml-1'>*</span>
      </label>
      <input type='text' class='form-control'
         id='[ID_INPUT]' name='[ID_INPUT]'>
      <span id='[ID_SPAN_ERRONI]'></span>
   </div>";
?>
