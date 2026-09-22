<?php

include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");

$connexio = new ConnexioBBDDSTMT();
$connexio->connectarBD();
$connexio2 = new ConnexioBBDDSTMT();
$connexio2->connectarBD();

$cns = "SELECT NOM, COGNOMS, DNI, Codi_Postal, Poblacio FROM inscripcions GROUP BY DNI ORDER BY DATA_INSC DESC";
$cns2 = "SELECT CORREU, TELEFON FROM inscripcions WHERE DNI = ? ORDER BY DATA_INSC DESC LIMIT 3";

$stmt = $connexio->prepare($cns);
$stmt2 = $connexio2->prepare($cns2);

$stmt2->bind_param("s", $dni2);

$stmt->execute();
$stmt->bind_result($nom, $cognoms, $dni, $cp, $poble);
while ( $stmt->fetch() ) {
   $stmt->bind_result($valor);
   while ( $stmt->fetch() ) {
      $dni2 = $dni;
      $stmt2->execute();
      $stmt2->bind_result($email, $tel);
      $i=0; $emails = ["", "", ""]; $telefons = ["", "", ""];
      //busquem els tres primers correus i els tres primers telefons
      while ( $stmt2->fetch() ) {
         $emails[$i] = $email;
         $telefons[$i] = $tel;
         $i++;
      }

      echo $emails[0].";";
      echo $emails[1].";";
      echo $emails[2].";";
      echo $telefons[0].";";
      echo $telefons[1].";";
      echo $telefons[2].";";
      echo $nom.";";
      echo $cognoms.";";
      echo $cp.";";
      echo $poble;
      echo "<br>";
   }
}

$connexio2->closeStmt();
$connexio->closeStmt();
$connexio2->desconectarBD();
$connexio->desconectarBD();
?>
