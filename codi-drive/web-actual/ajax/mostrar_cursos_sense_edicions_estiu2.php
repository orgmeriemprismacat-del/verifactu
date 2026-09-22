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
   $mixtos = $_GET['mixtos'];
   $subvencio = $_GET['subvencio'];
   $nousCursos = $_GET['nousCursos'];
   $inicPagina=0;
   $elementsPerPagina=2000;

   print_r($filtres);

   include("../inc/buscantCursosDisponiblesSenseEdicionsEstiu2.php");

   $random = $objOrd[3];

   $elementsTotals = count($llistatCursos);

   $pagination =  "";
   if ($elementsTotals<=0)
      $pagination = "<div class='text-center w-100 pt-4'>No s'han trobat resultats amb els filtres marcats.</div>";

   $objLlistatCursos = new LlistatCursos($llistatCursos);
	$list .= "<div class='sticky-body'><div class='theiaStickySidebar w-100'><div class='row'>";
   if ( $random == "1" ) {
      //s'ha triat l'opció mostra aleatoriament
      $objLlistatCursos->ordenaAleatoriament($dispositiu);
   }
   else if ($ordre=="ANY,MES ASC")
      $objLlistatCursos->ordenaMesVisitats($dispositiu);
   else if ($ordre=="TITOL ASC")
      $objLlistatCursos->ordenaAlfabeticamentASC($dispositiu);
   else if ($ordre=="TITOL DESC")
      $objLlistatCursos->ordenaAlfabeticamentDESC($dispositiu);
   $list.= $objLlistatCursos->mostrarLlistatCursosFiltres($inicPagina,$elementsPerPagina, "h2");
   $list.=$pagination;

   $list.= "</div></div></div>";

	echo $list;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
