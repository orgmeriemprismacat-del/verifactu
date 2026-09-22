<?php

if ( $tipus == 1 ) {
   $textPreu = "<p><strong>Preu:</strong>
      <span style='text-decoration:line-through; color: #a7a7a7'>[PAY_ORIG_ALUMNE] euros</span> 
      [PAY_DESC_ALUMNE] euros ([MOTIU_DESCOMPTE_ALUMNE])
   </p>";
}
else if ( $tipus == 2 ) {
   $textPreu = "<p><strong>Preu:</strong>
      [PAY_DESC_ALUMNE] euros
   </p>";
}

$missatge = "<div style='background-color:#e8ecf5;border:1px solid #d7deee;
border-radius:2px;padding:5px 25px;margin-bottom:20px'>
   <p><strong>Nom:</strong> [NOM_ALUMNE] [COG_ALUMNE]</p>
   <p><strong>NIF/NIE/passaport:</strong> [DNI_ALUMNE]</p>
   <p><strong>Correu electrònic:</strong> [EMAIL_ALUMNE]</p>
   <p><strong>Telèfon de contacte:</strong> [TEL_ALUMNE]</p>
   <p><strong>Curs en línia:</strong> [TITOL]</p>
   <p><strong>Dates:</strong> [DATAI_DATAF]</p>
   ".$textPreu."
</div>";

?>
