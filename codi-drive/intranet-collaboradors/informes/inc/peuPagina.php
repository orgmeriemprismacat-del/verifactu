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

  $msg = "<div id='peu' class='d-flex justify-content-center align-items-center text-center pb-3 w-100'>
    <p>".$numFix." · ".$numMbl." · <a class='font-weight-bold' href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a> · ".$email."</p>
    </div>";

  echo $msg;
}
catch (Exception $e) {
  echo missatgeError( $e->getCode() );
}
?>
