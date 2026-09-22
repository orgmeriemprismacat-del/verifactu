<?php

if (intval($codiCurs)!=0)
   $textCurs .= "<p><strong>Curs:</strong> Curs de [CODI_CURS] hores</p>";
else
   $textCurs .= "<p><strong>Curs:</strong> [TITOL]</p>";

if ( $dedicatoria != '' )
   $textDedicatoria .= "<p><strong>Dedicatòria:</strong> [DEDICATORIA]</p>";
else
   $textDedicatoria .= "<p><strong>Dedicatòria escrita a mà</strong></p>";

$textComentaris = '';
if ( $comentaris != '' )
   $textComentaris .= "<p><strong>Comentaris:</strong> [COMENTARIS_ALUMNE]</p>";


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
   ".$textCurs."
   <p><strong>Preu:</strong> [PAY] euros</p>
   <p><strong>Data:</strong> [DATA_ACTUAL]</p>
   <p><strong>Per a qui:</strong> [DESTI]</p>
   ".$textDedicatoria."
   <p><strong>De qui:</strong> [ORIGEN]</p>
   <p><strong>Estil:</strong> [ESTIL]</p>
   ".$textComentaris."
   <p><strong>URL pagament:</strong> [URL_PAGAMENT]</p>
";
?>
