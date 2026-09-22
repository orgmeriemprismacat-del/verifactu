<?php
/* ######################################################################### */
/* #################     Preparem el text del miailing     ################## */
/* ######################## ################################################# */
if ($mailing=='Registred') $textMailing = 'Ja està subscrit';
else if ($mailing=='Yes') $textMailing = 'Sí';
else $textMailing = 'No';

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
   <p><strong>Codi regal:</strong> [CODI_REGAL]</p>
   <p><strong>Consentiment mailing:</strong> ".$textMailing."</p>
   <p><strong>Comentaris:</strong> [COMENTARIS_ALUMNE]</p>";

?>
