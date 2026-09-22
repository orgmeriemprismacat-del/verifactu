<?php
$msgCentreEscolar = '';
if ( $tipusInsc == 'centre-escolar' )
   $msgCentreEscolar = "<p><strong>Centre escolar:</strong> [CENTRE_ESCOLAR]</p>";

/* ######################################################################### */
/* ###################   Preparem el missatge a enviar   #################### */
/* ######################################################################### */
$missatge = "<h2><strong>Dades de contacte</strong></h2>
<div style='background-color:rgb(232,236,245);border:1px solid rgb(215,222,238);
border-top-left-radius:2px;border-top-right-radius:2px;border-bottom-right-radius:2px;
border-bottom-left-radius:2px;padding:25px'>
   ".$msgCentreEscolar."
   <p><strong>Nom:</strong> [NOM] [COGNOMS]</p>
   <p><strong>Document d’identificació:</strong> [DNI]</p>
   <p><strong>Email:</strong> [EMAIL]</p>
   <p><strong>Telèfon:</strong> [TELEFON]</p>
   <p><strong>Adreça:</strong> [ADRECA]</p>
   <p><strong>CP:</strong> [CP]</p>
   <p><strong>Població:</strong> [POBLACIO]</p>
   <p><strong>Consentiment mailing:</strong> [MAILING]</p>
   <p><strong>Comentaris:</strong> [COMENTARIS]</p>
</div>";

?>
