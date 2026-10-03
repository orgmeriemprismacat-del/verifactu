<?php

/*
 * UC-020 / ALUMNE_PRISMA_WEB_LEGACY_V2
 *
 * El preview reprodueix els criteris d'elegibilitat executables de la policy:
 * - pagament positiu sobre una inscripció de pagament;
 * - curs regal;
 * - GENERAT=1;
 * - exclusió D/M.
 *
 * Una factura només emesa i no cobrada NO acredita Alumne PrisMa.
 */
$consAlumnePrisMa = "SELECT ID FROM inscripcions WHERE DNI=? AND
                     ((A_PAGAR>0 AND PAGAMENT>0) OR
                      (A_PAGAR=0 AND OBSERVACIONS LIKE '%CURS REGAL%') OR
                      (GENERAT=1))
                     AND UPPER(`INSC CURS`)!='D'
                     AND UPPER(`INSC CURS`)!='M'";
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
