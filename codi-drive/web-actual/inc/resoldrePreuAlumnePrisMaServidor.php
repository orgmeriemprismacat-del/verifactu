<?php

/**
 * Resol el preu Alumne PrisMa de manera autoritativa al servidor.
 *
 * Manté deliberadament la mateixa regla d'elegibilitat que la web legacy
 * (inclosa la semàntica actual de la branca FACTURA_RELACIONADA) per no
 * convertir una decisió de negoci pendent en un canvi silenciós.
 *
 * @return array{base: float, net: float}
 */
function resoldrePreuAlumnePrisMaServidor(
   $connexio,
   $documentacio,
   $idPreu,
   $codiCurs,
   $hores,
   $edicio
) {
   $documentacio = trim((string) $documentacio);
   $codiCurs = trim((string) $codiCurs);
   $hores = trim((string) $hores);
   $edicio = trim((string) $edicio);

   if ($documentacio === '' || !is_numeric($idPreu) || (int) $idPreu <= 0
      || $codiCurs === '' || $hores === '' || $edicio === '') {
      throw new Exception('Context AP invàlid', 409);
   }

   // Mateixa regla ACTUAL que buscarAlumnePrisMa.php. No es corregeix aquí
   // FACTURA_RELACIONADA != NULL perquè el criteri de negoci encara és pendent.
   $sqlElegibilitat = "SELECT ID FROM inscripcions WHERE DNI=? AND
      ((A_PAGAR>0 AND PAGAMENT>0) OR
       (A_PAGAR=0 AND OBSERVACIONS LIKE '%CURS REGAL%') OR
       (GENERAT=1) OR
       (A_PAGAR>0 AND PAGAMENT=0 AND IDPAG=0
        AND FACTURA_RELACIONADA != NULL AND FACTURA_RELACIONADA != ''))
      AND UPPER(`INSC CURS`)!='D' AND UPPER(`INSC CURS`)!='M'";

   $stmt = $connexio->prepare($sqlElegibilitat);
   $stmt->bind_param('s', $documentacio);
   $stmt->execute();
   $stmt->store_result();
   $elegible = $stmt->num_rows() > 0;
   $connexio->closeStmt();

   if (!$elegible) {
      throw new Exception('Alumne PrisMa no acreditat al servidor', 409);
   }

   $sqlBase = "SELECT IMPORT FROM preu
      WHERE ID=? AND DATAI<=CURRENT_TIMESTAMP
      AND (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL)";
   $stmt = $connexio->prepare($sqlBase);
   $stmt->bind_param('d', $idPreu);
   $stmt->execute();
   $stmt->store_result();

   if ($stmt->num_rows() !== 1) {
      $connexio->closeStmt();
      throw new Exception('Tarifa base no disponible o ambigua', 409);
   }

   $stmt->bind_result($preuBase);
   $stmt->fetch();
   $connexio->closeStmt();

   if (!is_numeric($preuBase) || (float) $preuBase <= 0) {
      throw new Exception('Tarifa base invàlida', 409);
   }

   $sqlAp = "SELECT PREU FROM descomptes
      WHERE DATAI<=CURRENT_TIMESTAMP
      AND (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL)
      AND ID_PREU=? AND TIPUS=1
      AND (CURS=? OR CURS=? OR CURS='TOTS')
      AND (MES='TOTS' OR MES=?)";

   $stmt = $connexio->prepare($sqlAp);
   $stmt->bind_param('dsss', $idPreu, $codiCurs, $hores, $edicio);
   $stmt->execute();
   $stmt->store_result();

   if ($stmt->num_rows() <= 0) {
      $connexio->closeStmt();
      throw new Exception('Tarifa Alumne PrisMa no disponible', 409);
   }

   $stmt->bind_result($preuAp);
   $preus = array();
   while ($stmt->fetch()) {
      if (!is_numeric($preuAp) || (float) $preuAp <= 0) {
         $connexio->closeStmt();
         throw new Exception('Tarifa Alumne PrisMa invàlida', 409);
      }
      $normalitzat = number_format((float) $preuAp, 2, '.', '');
      $preus[$normalitzat] = true;
   }
   $connexio->closeStmt();

   if (count($preus) !== 1) {
      throw new Exception('Tarifa Alumne PrisMa ambigua', 409);
   }

   $preuNet = (float) array_key_first($preus);
   $preuBase = (float) $preuBase;

   if ($preuNet >= $preuBase) {
      throw new Exception('Tarifa Alumne PrisMa incoherent', 409);
   }

   return array(
      'base' => $preuBase,
      'net' => $preuNet,
   );
}
?>
