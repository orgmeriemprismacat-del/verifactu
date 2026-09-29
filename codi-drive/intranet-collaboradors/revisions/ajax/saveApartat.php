<?php

$shortname = $_REQUEST['shortname'];
$codificacio = $_REQUEST['codificacio'];
$camp = $_REQUEST['campUpd'];

$codificacio = str_replace('"', '\"', $codificacio);

include ('../../ConnexioWeb.php');
include ('../../ConnexioMoodle.php');

try {
  $conWeb = new ConnexioWeb();
  $conWeb->connectarBD();

  $cnsExisteixRevisio = "SELECT guardat, finalitzat FROM revisio_tutor WHERE codic = ?";

  if ( $stmt = $conWeb->prepare( $cnsExisteixRevisio ) ) {
    $stmt->bind_param('s', $shortname);
    $stmt->execute();
    $stmt->store_result();
    $rowcount = $stmt->num_rows();
    $conWeb->closeStmt();
  }
  else {
    throw new Exception('', 11111);
  }

  if ( $rowcount > 0 ) {
     $cnsRevisio = "UPDATE revisio_tutor SET ".$camp." = \"".$codificacio."\", guardat = CURRENT_DATE WHERE codic = '".$shortname."'";
  }
  else {
     $cnsRevisio = "INSERT INTO revisio_tutor (".$camp.", codic, guardat) VALUES (\"".$codificacio."\", \"".$shortname."\", CURRENT_DATE)";
  }

  if ( $stmt = $conWeb->prepare( $cnsRevisio ) ) {
    $stmt->execute();
    $conWeb->closeStmt();
  }
  else {
    throw new Exception('', 11112);
  }

  $conWeb->desconectarBD();
}
catch (Exception $e) {
  echo missatgeError( $e->getCode() );
}
?>
