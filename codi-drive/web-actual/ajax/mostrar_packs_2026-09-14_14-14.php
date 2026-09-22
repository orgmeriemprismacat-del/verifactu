<?php

include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../Url.php");
include("../Curs.php");
include("../Pack.php");
include("../LlistatCursos.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $filtres = json_decode($_GET['filtres']);
   $dispositiu = $_GET['dispositiu'];
   $packs = $_GET['packs'];
   $cdd = $_GET['cdd'];
   $nousCursos = $_GET['nousCursos'];

   $inicPagina=0;
   $elementsPerPagina=2000;

   include("../inc/buscantPacksDisponibles.php");

   $random = $objOrd[3];

   $elementsTotals = count($llistatCursosPacks);

   $pagination =  "";
   if ($elementsTotals<=0)
      $pagination = "<div class='text-center w-100 pt-4'>No s'han trobat resultats amb els filtres marcats.</div>";

   $objLlistatCursosPacks = new LlistatCursos($llistatCursosPacks);

	$list .= "<div class='sticky-body'><div class='theiaStickySidebar w-100'><div class='row'>";
   if ( $ordre=="ANY,MES ASC" ) {
      $objLlistatCursosPacks->ordenaMesVisitats($dispositiu);
   }
   else if ( $ordre=="TITOL ASC" ) {
      $objLlistatCursosPacks->ordenaAlfabeticamentASC($dispositiu);
   }
   else if ( $ordre=="TITOL DESC" ) {
      $objLlistatCursosPacks->ordenaAlfabeticamentDESC($dispositiu);
   }
   else if ( $random == "1" ) {
      $objLlistatCursosPacks->ordenaAleatoriament($dispositiu);
   }
   $list .= $objLlistatCursosPacks->mostrarLlistatCursosFiltres($inicPagina,$elementsPerPagina, "h2");

   $list .= $pagination;
   $list .= "</div></div></div>";

	echo $list;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
