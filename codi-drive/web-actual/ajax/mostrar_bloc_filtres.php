<?php;
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Filtres.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $filtres = new Filtres();
   $mostrar = $filtres->mostrarFiltresLaterals();

   echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
