<?php

include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../Url.php");
include("../Curs.php");
include("../Pack.php");

include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $dispositiu = $_GET['dispositiu'];
   $codiCurs = $_GET['codi'];$tipus = $_GET['tipus'];
   $tipus = $_GET['tipus'];

   if ( $tipus == 0 ) {
   	$curs = new Curs($codiCurs, $dispositiu);
   	$list = $curs->crearTastet();
   }
   else if  ( $tipus == 1 ) {
      $curs = new Pack($codiCurs, $dispositiu);
      $list = $curs->crearTastet();
   }

	echo $list;
}
catch (Throwable $e) {
    error_log(
        $e->getMessage().
        ' a '.$e->getFile().
        ':'.$e->getLine()
    );

    echo "ERROR: ".$e->getMessage();
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
