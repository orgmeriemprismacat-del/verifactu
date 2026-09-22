<?php

$textImportAPagar = "<p>L'import a pagar és de <span class='font-weight-bold'>
<span class='preu'>[PAY_FALTA]</span> euros</span>.</p>";

/* ######################################################################### */
/* ###################   Preparem el text introductori   #################### */
/* ######################################################################### */
if ($vista==2) {
   $textIntro = "<p>Per tal de poder realitzar el <span class='font-weight-bold'>
   pagament amb targeta</span>, cal que tinguis activat el codi
   de compra segura facilitat per la teva entitat bancària.</p>";
   $textImportAPagar = "";
   $msgPagar = "Vull pagar ara";
}
else {
   $textIntro = "<h3>Pagament amb targeta</h3>
   <p>Per tal de poder realitzar el pagament amb targeta, cal que tinguis activat
   el <span class='font-weight-bold'>codi de compra segura</span> facilitat per
   la teva entitat bancària.</p>";
   $msgPagar = "Efectua el pagament";
}

$inputsHidden = "[INPUTS_HIDDEN]";
if ( $potsFracc && !$fracc ) {
   $inputsHidden .= "<input type='hidden' id='importPagare' name='importPagare' value='[PAY_FALTA]'>";
   $inputsHidden .= "<input type='hidden' id='frac' name='frac' value='0'>";
   $vistaImportAPagar = $textImportAPagar;
}
else if ( $potsFracc && $fracc ) {
   $inputsHidden .= "<input type='hidden' id='frac' name='frac' value='1'>";
   $vistaImportAPagar = "[INPUT_FRACC]";
}
else {
   if ( $vista == 2 )
      $vistaImportAPagar = "";
   else
      $vistaImportAPagar = $textImportAPagar;
}

$inputsHidden .= "
   <input type='hidden' id='import' name='import' value='[PAY_ORIG]'>
   <input type='hidden' id='importPagat' name='importPagat' value='[PAY_PAGAT]'>";

// $textInputs = "
//    <input type='hidden' id='codiCurs' name='codiCurs' value='[CODI_CURS]'>
//    <input type='hidden' id='codiRegal' name='codiRegal' value='[CODI_REGAL]'>
//    <input type='hidden' id='titol' name='titol' value=\"[TITOL]\">
//    <input type='hidden' id='email' name='email' value='[CORREU]'>";

// $formulariNom = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
//    <div class='col-12 pl-0 pr-0 pr-md-2'>
//       <div class='form-group field-wrap position-relative'>
//          <label class='position-absolute mb-0'>
//             <span class='camp'>Nom i cognoms del titular de la targeta</span>
//             <span class='req'>*</span>
//          </label>
//          <input type='text' class='form-control' id='nom-titular' name='nom-titular'>
//          <span id='nom_cognom_titular_erroni'
//             class='d-flex justify-content-center align-items-center px-2
//             position-absolute text-center text-white'></span>
//       </div>
//    </div>
// </div>";
//
// $formulariDni = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
//    <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
//       <div class='form-group field-wrap position-relative'>
//          <div id='doc' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
//             <span class='element-selected font-weight-normal w-100'>NIF/NIE</span>
//             <ul class='select-list position-absolute ' style='display: none;'>
//                <li class='border-bottom m-0' id='doc-dni'><a href='#'>NIF/NIE</a></li>
//                <li class='border-bottom m-0' id='doc-passaport'><a href='#'>Altres</a></li>
//             </ul>
//             <i class='fa triangle-inferior fa-angle-down position-absolute'></i>
//          </div>
//       </div>
//    </div>
//    <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2' id='input_doc'>
//       <div class='form-group field-wrap position-relative'>
//          <label class='position-absolute mb-0'>
//             <span class='camp'>DNI amb lletra</span>
//             <span class='req'>*</span>
//          </label>
//          <input type='text' class='form-control' id='nif' name='nif'>
//          <span id='dni_erroni' class='d-flex justify-content-center
//                 align-items-center px-2 position-absolute text-center
//                 text-white'></span>
//       </div>
//     </div>
//  </div>";

/* ######################################################################### */
/* ###################   Preparem el missatge a enviar   #################### */
/* ######################################################################### */

$missatge = "
<div class='form-dades'>
   ".$textIntro."
   <div class='d-flex flex-column algin-items-center justify-content-center'>
      <form id='frm' name='frm' action='[URL_PAY]' method='post'>
         [FORM_NOM]
         [FORM_DNI]
         ".$textImportAPagar."
         ".$inputsHidden."
         <div class='d-flex cnt_enviar_dades border-0 justify-content-center'>
            <a id='form_enviar_dades' role='button' class='boto-blau
            position-relative text-white text-center border-0
            border-radius-2 w-px-4 py-2 my-2'>".$msgPagar."
            </a>
         </div>
      </form>
   </div>
</div>";
?>
