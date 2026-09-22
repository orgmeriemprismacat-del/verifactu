<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $connexio = new ConnexioBBDDSTMT();
   $connexio->connectarBD();
   $connexio2 = new ConnexioBBDDSTMT();
   $connexio2->connectarBD();
   $connexio3 = new ConnexioBBDDSTMT();
   $connexio3->connectarBD();
   $connexio4 = new ConnexioBBDDSTMT();
   $connexio4->connectarBD();
   $connexio5 = new ConnexioBBDDSTMT();
   $connexio5->connectarBD();

   $dades1 = "";
   $dades2 = "";
   $dades3 = "";

   $i = 11683;

   $cns = "SELECT NOM, MAIL, USUARI FROM mailing WHERE
   MAIL NOT LIKE 'BAIXA_%' AND MAIL NOT LIKE 'CANVI_%' AND MAIL NOT LIKE 'RETORNAT_%' AND ID > 13410 ORDER BY ID";
   $cns2 = "SELECT DNI FROM inscripcions WHERE USUARI LIKE ? AND ANY = 2023 AND MES = '11'";
   $cns3 = "SELECT DNI FROM inscripcions WHERE USUARI = ? ORDER BY ID DESC LIMIT 1";
   $ins = "INSERT INTO promocions (CODI_DESCOMPTE, DNI, MES, CURS, PERCENTATGE, DATAI, DATAF, COMENTARI)
   VALUES (?,?,'11',?,50, CURRENT_TIME, '2023-11-09 23:59:59', 'Promoció novembre 50%')";

   if ( $stmt = $connexio->prepare($cns) ) {
     if ( $stmt2 = $connexio2->prepare($cns2) ) {
       if ( $stmt3 = $connexio3->prepare($cns3) ) {
         if ( $stmt4 = $connexio4->prepare($ins) ) {
            $stmt2->bind_param("s", $usuari2);
            $stmt3->bind_param("s", $usuari2);
            $stmt4->bind_param("sss", $codiDesc, $dniIns, $cursIns);

            $stmt->execute();
            $stmt->bind_result($nom, $mail, $usuari);
            while ( $stmt->fetch() ) {
              $usuari2 = $usuari;
              $usuari3 = $usuari.'%';
              $stmt2->execute();
              $stmt2->store_result();
              if ( $stmt2->num_rows() <= 0 ) {
                $codiDesc = "PROMONOV#".$i;

                $stmt3->execute();
                $stmt3->bind_result($dni);
                $stmt3->fetch();

                $dniIns = $dni;
                if ( $i >= 0 && $i < 3900 ) {
                  $cursIns = 'ACO';
                  $stmt4->execute();
                  $cursIns = 'TUT';
                  $stmt4->execute();
                  $cursIns = 'ADOP';
                  $stmt4->execute();
                  $cursIns = 'LING';
                  $stmt4->execute();

                  // $dades1 .= $nom.";".$mail.";".$usuari.";".$codiDesc."<br>";
                }
                else if ( $i >= 3900 && $i < 8000 ) {
                  $cursIns = 'GEST';
                  $stmt4->execute();
                  $cursIns = 'AGC';
                  $stmt4->execute();
                  $cursIns = 'TEA';
                  $stmt4->execute();
                  $cursIns = 'FON';
                  $stmt4->execute();

                  // $dades2 .= $nom.";".$mail.";".$usuari.";".$codiDesc."<br>";
                }
                else if ( $i >= 8000 ) {
                  $cursIns = 'VEU';
                  $stmt4->execute();
                  $cursIns = 'REGIN';
                  $stmt4->execute();
                  $cursIns = 'CNV';
                  $stmt4->execute();
                  $cursIns = 'SIST';
                  $stmt4->execute();

                  // $dades3 .= $nom.";".$mail.";".$usuari.";".$codiDesc."<br>";
                }

                $i++;

              }
            }
          }
          else
            throw new Exception('', 1301);
          $connexio4->closeStmt();
          $connexio4->desconectarBD();
        }
        else
          throw new Exception('', 1302);
        $connexio3->closeStmt();
        $connexio3->desconectarBD();
      }
      else
        throw new Exception('', 1303);
      $connexio2->closeStmt();
      $connexio2->desconectarBD();
   }
   else
     throw new Exception('', 1304);
   $connexio->closeStmt();
   $connexio->desconectarBD();

   echo $dades1."<BR><BR><BR><HR>".$dades2."<BR><BR><BR><HR>".$dades3."<BR><BR><BR><HR>";

}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
