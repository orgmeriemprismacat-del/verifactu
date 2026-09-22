<?php

/* ######################################################################### */
/* #################      Preu, idpag, url pagament       ################## */
/* ######################## ################################################# */
$textSubv = '';
if ( $tipusCurs == 'S' )
   $textSubv = "<p><strong>Preu:</strong> Subvencionat</p>";
else {
   $textPagament = "
      <p><strong>IDPAG:</strong> [IDPAG_PAY]</p>
      <p><strong>URL pagament:</strong> [URL_PAGAMENT]</p>
   ";
}

/* ######################################################################### */
/* #################      Preparem el text de novell       ################## */
/* ######################## ################################################# */
$textNovell = '';
if ( $codiCurs == 'JASOM' )
   $textNovell = "<p><strong>Novell:</strong> [TEXT_NOVELL]</p>";

/* ######################################################################### */
/* ################# Preparem el text del Codi descompte  ################## */
/* ######################## ################################################# */
if ( $promocioATrobadaplicada != '' )  {
   $textPromocioTobada = "<p><strong>Codi descompte del
   ".explode('|', $promocioATrobadaplicada)[1]."% per una trobada:</strong>
   ".explode('|', $promocioATrobadaplicada)[0]."</p>";
}

/* ######################################################################### */
/* ################# Preparem el text del Codi promociona  ################## */
/* ######################## ################################################# */
if ( $promocioAplicada != '' )  {
   if ( explode('|', $promocioAplicada)[2] == 0 )
      $textPromocio = "<p><strong>Codi promocional d'un
      ".explode('|', $promocioAplicada)[1]."%:</strong>
      ".explode('|', $promocioAplicada)[0]."</p>";
   else
      $textPromocio = "<p><strong>Codi promocional de
      ".explode('|', $promocioAplicada)[1]."€:</strong>
      ".explode('|', $promocioAplicada)[0]."</p>";
}

/* ######################################################################### */
/* #################     Preparem el text del miailing     ################## */
/* ######################## ################################################# */
if ($mailing=='Registred') $textMailing = 'Ja està subscrit';
else if ($mailing=='Yes') $textMailing = 'Sí';
else $textMailing = 'No';

/* ######################################################################### */
/* ################ Preparem el text del tipus de descompte ################# */
/* ######################## ################################################# */
if ($tipusDescompte==2) $textTipusDescompte = '<p>Carnet Jove</p>';
else if ($tipusDescompte==4) $textTipusDescompte = '<p>Carnet USOC</p>';
else if ($tipusDescompte==5) $textTipusDescompte = '<p>Carnet de discapacitat</p>';
else if ($tipusDescompte==6) $textTipusDescompte = '<p>Carnet de familia nombrosa</p>';
else if ($tipusDescompte==7) $textTipusDescompte = '<p>Carnet de familia monoparental</p>';

/* ######################################################################### */
/* ############### Preparem el text del pagament fraccionat  ################ */
/* ######################## ################################################# */
$textPagFracc = '';
if ( $tipusCurs != 'S' && $pagFrac == 'Yes' )
   $textPagFracc = "<p>Pagament fraccionat</p>";

   /* ######################################################################### */
   /* ###################   Preparem el text del mailing   #################### */
   /* ######################################################################### */
$missatge = "
   <p><strong>Nom:</strong> [NOM_ALUMNE] [COG_ALUMNE]</p>
   <p><strong>Document:</strong> [DNI_ALUMNE]</p>
   <p><strong>Email:</strong> [EMAIL_ALUMNE]</p>
   <p><strong>Telèfon:</strong> [TEL_ALUMNE]</p>
   <p><strong>Adreça:</strong> [ADRECA_ALUMNE]</p>
   <p><strong>CP:</strong> [CP_ALUMNE]</p>
   <p><strong>Població:</strong> [POBLACIO_ALUMNE]</p>
   <p><strong>Estic treballant a:</strong> [PERFIL_ALUMNE]</p>
   <p><strong>Titulació / especialitat:</strong> [TITULACIO_ALUMNE]</p>
   <p><strong>Curs:</strong> [TITOL]</p>
   <p><strong>Data:</strong> [DATAI_DATAF]</p>
   <p><strong>Com has conegut aquest curs?:</strong> [CONEGUT_ALUMNE]</p>
   ".$textSubv."
   ".$textNovell."
   <p><strong>Preu:</strong> [PAY_DESC_ALUMNE] euros</p>
   ".$textPromocioTobada."
   ".$textPromocio."
   ".$textPagament."
   <p><strong>Consentiment mailing:</strong> ".$textMailing."</p>
   <p><strong>Comentaris:</strong> [COMENTARIS_ALUMNE]</p>
   ".$textTipusDescompte."
   ".$textPagFracc."
";

?>
