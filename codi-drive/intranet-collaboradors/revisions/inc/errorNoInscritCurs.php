<?php
try {
  $conWeb = new ConnexioWeb();
  $conWeb->connectarBD();

  $sql_course = "SELECT NOM_CURS as nom FROM curs WHERE DATAI >= CURRENT_DATE
  AND CURS LIKE ? ORDER BY id_Curs ASC LIMIT 1";

  if ( $stmt = $conWeb->prepare( $sql_course ) ) {
    $stmt->bind_param('s', $course);
    $stmt->execute();
    $stmt->store_result();
    if ( $stmt->num_rows() > 0 ) {
      $stmt->bind_result($titol_curs);
      $stmt->fetch();
    }
  }
  else {
    throw new Exception('', 11102);
  }

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
    <p>Per accedir a l’activitat has d’estar inscrit al curs <strong style='color: #23527c;'>".$titol_curs."</strong> i aquest encara ha d’estar obert.</p>
    <p>".$adreca." · ".$cp." ".$poblacio." · ".$numFix." · ".$numMbl." · <a href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a> · ".$email."</p>
  </div>";

  echo $msg;
}
catch (Exception $e) {
  echo missatgeError( $e->getCode() );
}
?>
