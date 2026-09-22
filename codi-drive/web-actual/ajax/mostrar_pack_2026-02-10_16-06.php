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

   $id_url_actual = buscarPagina($url_actual);
   $url = new Url($id_url_actual);
   $infoPack = new InfoPack($url, $dispositiu);

   $mostrar = "<div class='prisma-course-details-1x'>";
   $mostrar .= $infoPack->mostrarInfo();
   $numero_cursos = $infoPack->obtenirCursosRelacionats()->obtenirNumeroElements();
   $cnt = 0;
   while ($cnt < $numero_cursos) {
      $curs = $infoPack->obtenirCursRelacionat($cnt);
      $cnt++;
   }
   $mostrar .= "</div>";

   $mostrar .= "<div class='prisma-related-course-container mt-0 mt-md-4 separacio-peu'>
   <div class='container'><div class='row'>
   <div class='sticky-stopper'></div>
   <div class='col-md-12'><h2 class='h1'>Altres cursos que et poden interessar</h2></div>";
   $mostrar .= $infoPack->mostrarCursosRelacionats()."</div></div></div>";
   $mostrar .= "</div></div></div>";

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
