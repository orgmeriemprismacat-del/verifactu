<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Numero.php");

try {
   $codi = $_GET['codi'];
   $hores = $_GET['hores'];
   $idPreu = $_GET['idPreu'];
   $edicio = $_GET['edicio'];
   $checkCarnet = $_GET['checkCarnet']; //si ha marcat carnet jove, buscquem si té carnet jove
   $checkUSOC = $_GET['checkUSOC']; //si ha marcat carnet de discapacitat del 33%, buscquem si té carnet de discapacitat del 33%
   $checkDiscapacitat = $_GET['checkDiscapacitat']; //si ha marcat carnet de discapacitat del 33%, buscquem si té carnet de discapacitat del 33%
   $checkFamNum = $_GET['checkFamNum']; //si ha marcat carnet família numerosa, buscquem si té carnet família numerosa
   $checkFamMono = $_GET['checkFamMono']; //si ha marcat carnet família monoparental, preparem el preu del carnet de família monoparental
   $checkVictViolencia = $_GET['checkVictViolencia']; //si ha marcat carnet família monoparental, preparem el preu del carnet de família monoparental
   $doc = $_GET['doc']; //buscquem si és alumne prisma

   $connexio = new ConnexioBBDDSTMT();
   $connexio->connectarBD();

   //Busco el preu car
   $cnsPreu = "SELECT IMPORT FROM preu WHERE ID=? AND DATAI<=CURRENT_TIMESTAMP AND
               (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL)";
   $stmtPreu = $connexio->prepare($cnsPreu);
   $stmtPreu->bind_param("d", $idPreu);
   $stmtPreu->execute();
   $stmtPreu->bind_result($preuC);
   $stmtPreu->fetch();
   $connexio->closeStmt();

   //Busco els descomptes que poden tenir
   $consDesc = "SELECT TIPUS, MSG_INSC, MSG_INSC_MODAL, PREU
   				FROM descomptes WHERE DATAI<=CURRENT_TIMESTAMP AND
   				(CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL) AND ID_PREU=? AND
   				(CURS=? OR CURS=? OR CURS='TOTS') AND
               (MES='TOTS' OR MES=?) AND TIPUS!=0 ORDER BY TIPUS DESC";
   $stmtDesc = $connexio->prepare($consDesc);
   $stmtDesc->bind_param("dsss", $idPreu, $codi, $hores, $edicio);
   $stmtDesc->execute();
   $stmtDesc->store_result();
   if ($stmtDesc->num_rows() > 0) {
      $stmtDesc->bind_result($tipus, $msgInsc, $msgInscModal, $preu);
      $descomptes=[]; $i=0;
      while ($stmtDesc->fetch()) {
         $descomptes[$i]=[$tipus, $msgInsc, $msgInscModal, $preu];
         $i++;
      }
   }
   $connexio->closeStmt();

   $trobat=false; $pos=0; $posApl=0;
   while ($pos<$i && !$trobat) {
      if ( $descomptes[$pos][0]>10 && $descomptes[$pos][0] != 15 ) {
         $trobat=true;
         $posApl = $pos;
      }
      if ( $descomptes[$pos][0] == 15 && !($checkFamNum == 1 || $checkDiscapacitat == 1 || $checkFamMono == 1 || $checkUSOC == 1 || $checkVictViolencia == 1 ) ) {
         $trobat=true;
         $posApl = $pos;
      }
      else {
         if ($descomptes[$pos][0]==3) {
            //calculo si el dni és exalumne i d'una jornada
            include("../inc/buscarAlumneJornada.php");
            if ($alumneJornada) { $trobat=true; $posApl=$pos; }
            else $pos++;
         }
         else if ($checkVictViolencia==1 && $descomptes[$pos][0]==8) {
            $vectMissInsc = explode("|", $descomptes[$pos][1]);
            $vectMissInscModal = explode("|", $descomptes[$pos][2]);
            $descomptes[$pos][1] = $vectMissInsc[0];
            $descomptes[$pos][2] = $vectMissInscModal[0];
            $posApl=$pos;
            $trobat=true;
         }
         else if ($checkFamMono==1 && $descomptes[$pos][0]==7) {
            $vectMissInsc = explode("|", $descomptes[$pos][1]);
            $vectMissInscModal = explode("|", $descomptes[$pos][2]);
            $descomptes[$pos][1] = $vectMissInsc[0];
            $descomptes[$pos][2] = $vectMissInscModal[0];
            $posApl=$pos;
            $trobat=true;
         }
         else if ($checkFamNum==1 && $descomptes[$pos][0]==6) {
            $vectMissInsc = explode("|", $descomptes[$pos][1]);
            $vectMissInscModal = explode("|", $descomptes[$pos][2]);
            $descomptes[$pos][1] = $vectMissInsc[0];
            $descomptes[$pos][2] = $vectMissInscModal[0];
            $posApl=$pos;
            $trobat=true;
         }
         else if ($checkDiscapacitat==1 && $descomptes[$pos][0]==5) {
            $vectMissInsc = explode("|", $descomptes[$pos][1]);
            $vectMissInscModal = explode("|", $descomptes[$pos][2]);
            $descomptes[$pos][1] = $vectMissInsc[0];
            $descomptes[$pos][2] = $vectMissInscModal[0];
            $posApl=$pos;
            $trobat=true;
         }
         else if ($checkUSOC==1 && $descomptes[$pos][0]==4) {
            $vectMissInsc = explode("|", $descomptes[$pos][1]);
            $vectMissInscModal = explode("|", $descomptes[$pos][2]);
            $descomptes[$pos][1] = $vectMissInsc[0];
            $descomptes[$pos][2] = $vectMissInscModal[0];
            $posApl=$pos;
            $trobat=true;
         }
         else if ($checkCarnet==1 && $descomptes[$pos][0]==2) {
            //calculo si el dni té descompte de carnet jove
            include("../inc/buscarAlumneCarnetJove.php");
            $vectMissInsc = explode("|", $descomptes[$pos][1]);
            $vectMissInscModal = explode("|", $descomptes[$pos][2]);
            if ($carnetJove) {
               $descomptes[$pos][1] = $vectMissInsc[0];
               $descomptes[$pos][2] = $vectMissInscModal[0];
               $posApl=$pos;
               $trobat=true;
            }
            else {
               $descomptes[$pos][1] = $vectMissInsc[1];
               $descomptes[$pos][2] = $vectMissInscModal[1];
               $descomptes[$pos][3] = $preuC;
               $posApl=$pos;
               $trobat=true;
               $pos++;
            }
         }
         else if ($descomptes[$pos][0]==1) {
            //calculo si el dni és exalumne
            include("../inc/buscarAlumnePrisMa.php");
            if ($alumnePrisma) { $trobat=true; $posApl=$pos; }
            else $pos++;
         }
         else
            $pos++;
      }

   }

   $connexio->desconectarBD();

   if (!$trobat && $checkCarnet==0)
      $mostrar = '0|0|0|0';
   else {
      $objPreu = new Numero( $descomptes[$posApl][0] );
      $preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();

      $mostrar = $preuFormatCorrecte."|";
      $mostrar .= $descomptes[$posApl][3]."|";
      $mostrar .= $descomptes[$posApl][1]."|";
      $mostrar .= $descomptes[$posApl][2];
   }

   echo $mostrar;

}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
