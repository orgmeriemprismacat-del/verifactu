<?php

$textAdreca = "<p>
   <strong>Adreça:</strong> [ADRECA_ALUMNE] - [CP_ALUMNE] [POBLACIO_ALUMNE]
</p>";

if ( $tipus == 0 )
   $textAdreca = "";

$missatge = "<div style='background-color:#e8ecf5;border:1px solid #d7deee;
border-radius:2px;padding:5px 25px;margin-bottom:20px'>
   <p><strong>Nom:</strong> [NOM_ALUMNE] [COG_ALUMNE]</p>
   <p><strong>NIF/NIE/passaport:</strong> [DNI_ALUMNE]</p>
   <p><strong>Correu electrònic:</strong> [EMAIL_ALUMNE]</p>
   <p><strong>Telèfon de contacte:</strong> [TEL_ALUMNE]</p>
   ".$textAdreca."
</div>";

?>
