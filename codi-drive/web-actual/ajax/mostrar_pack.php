<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../InfoPack.php");
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
   $infoPack = new InfoPack($url, $dispositiu);

   /* ########### OBTENIR CURSOS RELACIONATS ########### */
   $numero_cursos = $infoPack->obtenirCursosRelacionats()->obtenirNumeroElements();
   $cnt = 0;

   /* ########### OBTENIR PACKS RELACIONATS ########### */
   $packsrel = $infoPack->obtenirPacksRelacionats();
   if ( $packsrel != null ) $numero_packs_relacionats = $packsrel->obtenirNumeroElements();
   $cntPacks = 0;

   $mostrar = "<div class='prisma-course-details-1x'>";
   $mostrar .= $infoPack->mostrarInfo();

   while ($cnt < $numero_cursos) {
      $curs = $infoPack->obtenirCursRelacionat($cnt);
      $cnt++;
   }
   while ( $packsrel != null && $cntPacks < $numero_packs_relacionats) {
      $pack = $infoPack->obtenirPackRelacionat($cntPacks);
      $cntPacks++;
   }

   $mostrar .= "</div>";

   /* ########### MOSTRAR CURSOS RELACIONATS ########### */
   $mostrarCursosRel = "";
   if ( $cnt > 0 ) {
      $mostrarCursosRel = "<div class='col-md-12'>
      <h2 class='h1'>Cursos que et poden interessar</h2>
      </div>".$infoPack->mostrarCursosRelacionats()."</div>";
   }
   /* ########### MOSTRAR PACKS RELACIONATS ########### */
   $mostrarPacksRel = "";
   if ( $cntPacks > 0 ) {
      $mostrarPacksRel = "<div class='col-md-12'>
      <h2 class='h1'><em>Packs</em> que et poden interessar</h2>
      </div>".$infoPack->mostrarPacksRelacionats()."</div>";
   }

   $mostrar .= "<div class='prisma-related-course-container mt-0 mt-md-4 separacio-peu'>";
   $mostrar .= "<div class='container'><div class='row'><div class='sticky-stopper'></div>";
   $mostrar .= $mostrarCursosRel.$mostrarPacksRel."</div></div></div>";

   echo $mostrar;
}
catch(Exception $e) {

   if ($e->getCode()==404)
      echo mostrarPagina404();
   else if ($e->getCode()==2603 OR $e->getCode()==2605 OR $e->getCode()==2606 OR $e->getCode()==2624)
      echo missatgeErrorCursNoDisponbile();
   else
      echo missatgeError($e->getCode());

    echo $e->getCode();
}

?>
