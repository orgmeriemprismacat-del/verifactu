<?php

$consAlumneJornada= "SELECT ID FROM inscripcions WHERE CURS LIKE ? AND DNI=? AND
                     ((A_PAGAR>0 AND PAGAMENT>0) OR (A_PAGAR=0 AND OBSERVACIONS LIKE '%CURS REGAL%'))
                     AND UPPER(`INSC CURS`)!=?";
$stmtJor = $connexio->prepare($consAlumneJornada);
$stmtJor->bind_param("sss", $cursJor, $doc, $inscurs);
$cursJor = $codi.'%0%';
$inscurs = 'D';
$stmtJor->execute();
$stmtJor->store_result();
if ($stmtJor->num_rows() > 0)
   $alumneJornada = true;
else
   $alumneJornada = false;
$connexio->closeStmt();

?>
