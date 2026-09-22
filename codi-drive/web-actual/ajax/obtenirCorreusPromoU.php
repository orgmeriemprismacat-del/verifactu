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

   $dades1 = "";
   $dades2 = "";
   $dades3 = "";

   $cnsPROMO = "SELECT CODI_DESCOMPTE, DNI, CURS FROM promocions  WHERE CODI_DESCOMPTE LIKE 'PROMONOV#%' AND DNI IS NOT NULL GROUP BY DNI";
   $cnsUSER = "SELECT USUARI FROM inscripcions WHERE dni = ? order by id desc limit 1";
   $cnsMAIL = "SELECT NOM, MAIL FROM mailing WHERE USUARI LIKE ?";

   if ( $stmt = $connexio->prepare($cnsPROMO) ) {
     if ( $stmt2 = $connexio2->prepare($cnsUSER) ) {
       if ( $stmt3 = $connexio3->prepare($cnsMAIL) ) {
            $stmt2->bind_param("s", $dniInsc);
            $stmt3->bind_param("s", $usuariMail);

            $stmt->execute();
            $stmt->bind_result($codiDesc, $dni, $curs);
            while ( $stmt->fetch() ) {
              $dniInsc = $dni;

              $stmt2->execute();
              $stmt2->bind_result($usuari);
              $stmt2->fetch();

              $usuariMail = $usuari;

              $stmt3->execute();
              $stmt3->bind_result($nom, $mail);
              $stmt3->fetch();

              if ( $curs == 'ACO' || $curs == 'TUT' || $curs == 'ADOP' || $curs == 'LING' )
                $dades1 .= $nom.";".$mail.";".$usuari.";".$codiDesc."<br>";
              else if ( $curs == 'GEST' || $curs == 'AGC' || $curs == 'TEA' || $curs == 'FON' )
                $dades2 .= $nom.";".$mail.";".$usuari.";".$codiDesc."<br>";
              else if ( $curs == 'VEU' || $curs == 'REGIN' || $curs == 'CNV' || $curs == 'SIST' )
                $dades3 .= $nom.";".$mail.";".$usuari.";".$codiDesc."<br>";

            }
        }
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

   // echo $dades1;
   // echo $dades2;
   echo $dades3;


}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
