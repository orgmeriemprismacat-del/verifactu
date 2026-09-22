<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../InfoTaller.php");
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
   $info = new InfoTaller($url, $dispositiu);

   $mostrar = "<div class='prisma-course-details-1x'>";
   $mostrar .= $info->mostrarInfo();
   $numero_cursos = $info->obtenirCursosRelacionats()->obtenirNumeroElements();
   $cnt = 0;
   while ($cnt < $numero_cursos) {
      $curs = $info->obtenirCursRelacionat($cnt);
      $cnt++;
   }
   $mostrar .= "</div>";

   $mostrar .= "<div class='prisma-related-course-container mt-0 mt-md-4 separacio-peu'>";
   $mostrar .= "<div class='container'><div class='row'>";
   $mostrar .= "<div class='sticky-stopper'></div><div class='col-md-12'>";
   $mostrar .= "<h2 class='h1'>Altres cursos que et poden interessar</h2></div>";
   $mostrar .= $info->mostrarCursosRelacionats()."</div></div></div>";

   echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else if ($e->getCode()==3201 || $e->getCode()==3210)
      echo missatgeErrorTallerNoDisponbile();
   else
      echo missatgeError($e->getCode());
}

?>
