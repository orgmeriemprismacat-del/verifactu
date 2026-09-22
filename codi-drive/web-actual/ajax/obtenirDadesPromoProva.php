<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $promo = $_GET['promo'];
   $codiCurs = $_GET['codi'];

   $mostrar = '';

   $connexio = new ConnexioBBDDSTMT();
   $connexio->connectarBD();

   //Busco la promocio $promo
   // $cns = "SELECT DNI, MES, CURS, PERCENTATGE, USED, DATAI, DATAF FROM promocions WHERE CODI_DESCOMPTE = ? ";
   // $cns = "SELECT DNI, MES, CURS, PERCENTATGE, USED, DATAI, DATAF FROM promocions
   // WHERE CODI_DESCOMPTE = ? AND CURS = ?";
   $cns = "SELECT DNI, MES, CURS, PERCENTATGE, USED, DATAI, DATAF,
   TIPUS_CALC, PREU, PREU_FIX, ACUM
   FROM promocions
   WHERE CODI_DESCOMPTE = ? AND
   ( CURS = 'TOTS' OR CURS = '30' OR CURS = '40' OR CURS = '60' OR CURS = '100'
      OR CURS = '15' OR CURS = '50' OR CURS = ? )";
   if ( $stmt = $connexio->prepare($cns) ) {
      // $stmt->bind_param("s", $promo);
      $stmt->bind_param("ss", $promo, $codiCurs);
      $stmt->execute();
      $stmt->store_result();
      if ( $stmt->num_rows() > 0 ) {
         $stmt->bind_result($dni, $mes, $curs, $percentatge, $used, $datai, $dataf, $tipusCalc, $preu, $preufix, $acum);
         $stmt->fetch();

         $dataAct = new DateTime("now");
         $dataInici = new DateTime($datai);
         $dataFi = new DateTime($dataf);

         if ( $dataInici >= $dataAct ) $actiu = 2;
         else if ( $dataInici <= $dataAct && $dataAct <= $dataFi ) $actiu = 1;
         else $actiu = 0;

         $dadesPromo = "1|".$actiu."|".$used."|".$dni."|".$mes."|".$curs."|".$percentatge."|".$tipusCalc."|".$preu."|".$preufix."|".$acum;
      }
      else {
         $dadesPromo = "0";
      }
      $connexio->closeStmt();
      $connexio->desconectarBD();
      echo $dadesPromo;
   }
   else {
      $connexio->desconectarBD();
      throw new Exception('', 1305);
   }
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
