<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../Url.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $connexio = new ConnexioBBDDSTMT();
   $connexio->connectarBD();
   $connexio2 = new ConnexioBBDDSTMT();
   $connexio2->connectarBD();

   /* Busco el limit d'edicions a mostrar */
   $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
   		DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
   $stmtParam = $connexio->prepare($cnsParams);
   $stmtParam->bind_param("ss", $tipus, $orderBy);
   $tipus='limit-editions';
   $orderBy='DATAI';
   $stmtParam->execute();
   $stmtParam->bind_result($limitEd);
   $stmtParam->fetch();


   /* Busco els dies que poden estar obert els cursos després de la data d'inscripció */
   $tipus='dies-inscriu-cursos';
   $orderBy='VALOR';
   $stmtParam->execute();
   $stmtParam->bind_result($valor);
   $diesOberts=[];
   $pos=0;
   while ($stmtParam->fetch()) {
      $valors = explode('|',$valor);
      if (count($valors) != 2)
         throw new Exception('',714);
      else {
         $cnt = 0;
         while ( $cnt < count($diesOberts) && $diesOberts[$cnt]!=$valors[1])
            $cnt++;
         if ($cnt==count($diesOberts)) {
            $diesOberts[$pos] = $valors[1];
            $pos++;
         }
      }

   }
   $connexio->closeStmt();

   $dateDifss="";
   for ($i=0; $i<count($diesOberts); $i++) {
      if ($i>0) $dateDifss.=" OR ";
      // $dateDifss.="DATEDIFF(DATAI + ".$diesOberts[$i].",CURRENT_DATE)>0";
      $dateDifss.="DATEDIFF(DATE_ADD(DATAI, INTERVAL ".$diesOberts[$i]." DAY),CURRENT_DATE)>0";
   }

   // /* Busco les edicions disponibles */
   $cnsEd = "SELECT MES, ANY FROM curs AS c INNER JOIN aula AS a ON
   	c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
   	INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE PUBLIC=1 AND
   	c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1
   	AND (".$dateDifss.") AND c.CURS NOT LIKE '%JOR%' AND
   	(a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
   	OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
   	AND ORDRE_TUTOR=1)) GROUP BY ANY, MES ORDER BY ANY, MES LIMIT 12";
   if ( $stmtEd = $connexio2->prepare($cnsEd) ) {
      // $stmtEd->bind_param("d", $limitEd);
      $stmtEd->execute();
      $stmtEd->store_result();
      // printf("Número de filas: %d.\n", $stmtEd->num_rows);
      $cntEd = 0;
      $edicions = "ed|00|2020";
      if ($stmtEd->num_rows() > 0) {
         $stmtEd->bind_result($mesEd, $anyEd);
         while ( $stmtEd->fetch() && $cntEd < $limitEd ){
            // echo $mesEd." ".$anyEd."<br />";
            if ( $edicions != '' ) $edicions .= "#";
            $edicions .= "ed|".$mesEd."|".$anyEd;
         	$cntEd++;
         }
      }
      // else {
      //    echo "0<br />";
      // }
   }
   else {
      echo "error";
   }
   $connexio2->closeStmt();

   $connexio2->desconectarBD();
   $connexio->desconectarBD();

	echo $edicions;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
