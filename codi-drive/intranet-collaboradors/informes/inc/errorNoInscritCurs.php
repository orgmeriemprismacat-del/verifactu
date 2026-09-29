<?php
try {
  $conWeb = new ConnexioWeb();
  $conWeb->connectarBD();

  if ( $stmt = $conWeb->prepare( "SELECT ADRECA, CP, POBLE, FIX, MBL, EMAIL
          FROM contacte WHERE ESTAT=1" ) ) {
    $stmt->execute();
    $stmt->store_result();
    if ( $stmt->num_rows() > 0 ) {
      $stmt->bind_result($adreca, $cp, $poblacio, $numFix, $numMbl, $email);
      $stmt->fetch();
    }
  }
  else {
    throw new Exception('', 11103);
  }

  $conWeb->desconectarBD();

  $msg = "<div class='container no-inscrit d-flex flex-column justify-content-center align-items-center text-center w-100 py-0 my-0'>
    <p>No tens permisos per accedir a aquest informe.</p>
  </div>";

  echo $msg;
}
catch (Exception $e) {
  echo missatgeError( $e->getCode() );
}
?>
