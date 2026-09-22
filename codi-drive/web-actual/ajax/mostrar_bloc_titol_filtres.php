<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Filtres.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $filtres = new Filtres();
   $mostrar="<div class='container'><div class='row'>
   <div class='col-12 d-flex justify-content-start align-items-center'>
   <h2 class='h1 flex-1-0-auto'>Els nostres cursos</h2>".$filtres->mostrarFiltresSuperiors()."</div></div></div>";

   echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
