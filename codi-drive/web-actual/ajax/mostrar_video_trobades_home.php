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
   $tipus = "video-trobades";
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
        $mostrar .= "<div class='d-flex justify-content-end w-100 pt-2'>
        <p class='text-align-right'><a role='link' class='mes-informacio'
        href='https://www.prisma.cat/trobades-en-linia' target='_self' title='Mostra més informació de les trobades en línia'>
        Més informació de les trobades en línia <i class='fas fa-long-arrow-alt-right'></i></a></p></div>";
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
