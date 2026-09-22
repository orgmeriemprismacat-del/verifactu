<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $codi = $_GET['codi'];

   $mostrar = '';

   $connexio = new ConnexioBBDDSTMT();
   $connexio->connectarBD();

   //Busco els descomptes que poden tenir
   $consDesc = "SELECT CODI_DESCOMPTE, CURS_DESCOMPTE, DATA_INICI
   				FROM trobades WHERE DATA_INICI<=CURRENT_TIME AND CURS_DESCOMPTE LIKE ? AND ESTAT=1";
   $stmtDesc = $connexio->prepare($consDesc);
   $stmtDesc->bind_param("s", $cursDesc);
   $cursDesc = $codi."|%";
   $stmtDesc->execute();
   $stmtDesc->store_result();
   if ( $stmtDesc->num_rows() > 0 ) {
      $stmtDesc->bind_result($codiDesc, $cursDesc, $dataI);
      while( $stmtDesc->fetch() ) {
         if ( $mostrar != '' ) $mostrar.='$';
         $dataAct = new DateTime("now");
   		$diesCodiDescompte = explode('|', $codiDesc)[1];

         $dataFi = new DateTime($dataI);
         $dataFi->add(new DateInterval('P'.$diesCodiDescompte.'D'));
         $dataFiAmbHora = $dataFi->format('Y-m-d')." 23:59:59";
   		$dataFiCanviHora = new DateTime($dataFiAmbHora);

         $diff = $dataAct->diff($dataFiCanviHora);

         //Encara està disponible
         if ( $diff->invert == 0 ) {
            $codiPromo = explode('|', $codiDesc)[0];
            $percentPromo = explode('|', $cursDesc)[1];
            $edicioPromo = explode('|', $cursDesc)[2];

            $mostrar .= $codiPromo.'|'.$percentPromo.'|'.$edicioPromo;
         }
      }

   }

   $connexio->closeStmt();

   $connexio->desconectarBD();

   echo $mostrar;

}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
