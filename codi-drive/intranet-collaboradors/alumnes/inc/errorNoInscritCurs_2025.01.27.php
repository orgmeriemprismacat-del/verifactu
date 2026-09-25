<?php
try {
  $conWeb = new ConnexioWeb();
  $conWeb->connectarBD();
  $shortname = $_REQUEST['shortname'];
  $course = substr( $shortname, 4, -3);

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

  $msg = "<div class='error'>
    <img class='capcalera' src='https://www.prisma.cat/campus/documents/activitat/banner_prisma_rectangle.png'>
  </div>
  <div class='col-md-12 cos_error'>
    <p>Esteu intentant accedir a un formulari de tutories. Per favor, proveu de tornar a entrar al curs <strong style='color: #23527c;'>".$titol_curs."</strong> que esteu realitzant i cliqueu a sobre de</p>
    <p><img src='https://www.prisma.cat/documents/imatges/banners/menus/menu_tutoria.png'/></p>
    <p>Si continueu sense poder-hi accedir, contacteu amb nosaltres al tel&egrave;fon ".$numFix." o a trav&eacute;s del correu suport@prisma.cat.</p>
    <p>Disculpeu les mol&egrave;sties.</p>
   </div>

   <div class='col-md-12 peu'>
    <p>".$adreca." · ".$cp." ".$poblacio." · ".$numFix." · ".$numMbl." · <a href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a> · ".$email."</p>
  </div>";

  echo $msg;
}
catch (Exception $e) {
  echo missatgeError( $e->getCode() );
}
?>
