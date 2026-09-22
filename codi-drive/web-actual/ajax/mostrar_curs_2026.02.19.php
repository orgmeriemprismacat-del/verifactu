<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Info.php");
include("../Text.php");
include("../Url.php");
include("../LlistatCursos.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $url_actual = $_GET['url'];
   $dispositiu = $_GET['dispositiu'];

   /* ########### BUSCAR INFO DE LA PÀGINA ########### */
   $id_url_actual = buscarPagina($url_actual);
   $url = new Url($id_url_actual);
   $info = new Info($url, $dispositiu);

   /* ########### OBTENIR CURSOS RELACIONATS ########### */
   $numero_cursos = $info->obtenirCursosRelacionats()->obtenirNumeroElements();
   $cnt = 0;

   /* ########### OBTENIR PACKS RELACIONATS ########### */
   $packsrel = $info->obtenirPacksRelacionats();
   if ( $packsrel != null ) $numero_packs_relacionats = $packsrel->obtenirNumeroElements();
   $cntPacks = 0;


   $mostrar = "<div class='prisma-course-details-1x'>";
   $mostrar .= $info->mostrarInfo();

   while ($cnt < $numero_cursos) {
      $curs = $info->obtenirCursRelacionat($cnt);
      $cnt++;
   }
   while ( $packsrel != null && $cntPacks < $numero_packs_relacionats) {
      $pack = $info->obtenirPackRelacionat($cntPacks);
      $cntPacks++;
   }
   $mostrar .= "</div>";

   /* ########### MOSTRAR CURSOS RELACIONATS ########### */
   $mostrarCursosRel = "";
   if ( $cnt > 0 ) {
      $mostrarCursosRel = "<div class='col-md-12'>
      <h2 class='h1'>Cursos que et poden interessar</h2>
      </div>".$info->mostrarCursosRelacionats()."</div>";
   }
   /* ########### MOSTRAR PACKS RELACIONATS ########### */
   $mostrarPacksRel = "";
   if ( $cntPacks > 0 ) {
      $mostrarPacksRel = "<div class='col-md-12'>
      <h2 class='h1'>Packs que et poden interessar</h2>
      </div>".$info->mostrarPacksRelacionats()."</div>";
   }

   $mostrar .= "<div class='prisma-related-course-container mt-0 mt-md-4 separacio-peu'>";
   $mostrar .= "<div class='container'><div class='row'><div class='sticky-stopper'></div>";
   $mostrar .= $mostrarCursosRel.$mostrarPacksRel."</div></div></div>";

   echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else if ($e->getCode()==710)
      echo missatgeErrorCursNoDisponbile();
   else
      echo missatgeError($e->getCode());
}

?>
