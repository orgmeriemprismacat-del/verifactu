<?php

$consAlumnePrisMa = "SELECT ID FROM inscripcions WHERE DNI=? AND
                     ((A_PAGAR>0 AND PAGAMENT>0) OR
                     (A_PAGAR=0 AND OBSERVACIONS LIKE '%CURS REGAL%') OR
					      (GENERAT=1) OR
                     (A_PAGAR>0 AND PAGAMENT = 0 AND IDPAG = 0 AND FACTURA_RELACIONADA != NULL AND FACTURA_RELACIONADA != ''))
                     AND UPPER(`INSC CURS`)!='D' AND UPPER(`INSC CURS`)!='M'";
$stmtPrisMa = $connexio->prepare($consAlumnePrisMa);
$stmtPrisMa->bind_param("s", $doc);
$stmtPrisMa->execute();
$stmtPrisMa->store_result();
if ($stmtPrisMa->num_rows() > 0)
   $alumnePrisma = true;
else
   $alumnePrisma = false;
$connexio->closeStmt();

?>
