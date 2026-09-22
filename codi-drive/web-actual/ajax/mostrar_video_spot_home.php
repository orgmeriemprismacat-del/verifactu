<?php

include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../Video.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $mostrar = "";

   $connexio = new ConnexioBBDDSTMT();
   $connexio->connectarBD();

   $cnsParam = "SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
               DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY VALOR DESC";
   $stmt = $connexio->prepare($cnsParam);
   $stmt->bind_param("s", $tipus);
   $tipus = "video-spot";
   $stmt->execute();
   $stmt->bind_result($idVideo);
   $stmt->store_result();
   if ($stmt->num_rows() > 0) {
     $stmt->fetch();
     $connexio->closeStmt();
     if ($idVideo!=null and $idVideo!='') {
        $video = new Video($idVideo);
        $mostrar = "<div class='container'><div class='row m-0'>";
        $mostrar .= $video->mostrarVideoInfo();
        $mostrar .= "</div></div>";
     }
   }
   $connexio->desconectarBD();

	echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
