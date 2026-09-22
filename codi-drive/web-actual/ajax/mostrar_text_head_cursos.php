<?php

include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../Url.php");
include("../Curs.php");
include("../Pack.php");
include("../LlistatCursos.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $filtres = json_decode($_GET['filtres']);
   $idfrase = $_GET['idfrase'];
   $tipusParam = '';
   $frase = '';

   if ( explode('|', $filtres[0][0])[1] == '07' )
      $tipusParam = 'frase-nomenament-juliol';
   if ( explode('|', $filtres[0][0])[1] == '06' )
      $tipusParam = 'frase-nomenament-juliol-cursos-juny';


   if ( $tipusParam != '' || $idfrase != '') {
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $cns="SELECT VALOR FROM params WHERE TIPUS=? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ";
      if ( $stmt = $connexio->prepare($cns) ) {
         $stmt->bind_param("s", $tipus);
         if ( $idfrase != '') {
            $tipus = $idfrase;
         	$stmt->execute();
         	$stmt->bind_result($valor);
            $stmt->fetch();
            $frase .= $valor;
         }
         if ( $tipusParam != '') {
            $tipus = $tipusParam;
         	$stmt->execute();
         	$stmt->bind_result($valor);
            $stmt->fetch();
            $frase .= $valor;

         }
      	$connexio->closeStmt();
      }
      $connexio->desconectarBD();
   }
   echo $frase;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
