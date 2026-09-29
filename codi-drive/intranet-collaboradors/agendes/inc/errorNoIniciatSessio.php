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
    throw new Exception('', 11101);
  }

  $conWeb->desconectarBD();

  $msg = "<div id='tot' class='w-100 h-100 d-flex flex-column bg-white'>
              <div class='prisma-header w-100'>
         <nav id='nav-header' class='navbar prisma-nav w-100 px-0'>
             <div class='container'>
                  <a role='link' class='navbar-brand prisma-brand mr-0 ml-0' href='https://www.prisma.cat/' target='_self' title='Veure la pàgina principal de PrisMa'>
                      <img width='139.39' height='46' role='img' src='https://www.prisma.cat/img/logo-prisma-light.png' alt='Logo PrisMa'>
                  </a>
             </div>
         </nav>
      </div>
     <div id='cos' class='container d-flex flex-column justify-content-center align-items-center text-center w-100 py-0 my-0'>
     	<p>Per accedir a l’activitat has d’haver iniciat sessió al Campus PrisMa.</p>
      <p>".$adreca." · ".$cp." ".$poblacio." · ".$numFix." · ".$numMbl." · <a class='font-weight-bold' href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a> · ".$email."</p>
	</div>";

  echo $msg;
}
catch (Exception $e) {
  echo missatgeError( $e->getCode() );
}
?>
