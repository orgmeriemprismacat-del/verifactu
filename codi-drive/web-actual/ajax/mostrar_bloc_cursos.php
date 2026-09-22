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
session_start();

try {
   $filtres = json_decode($_GET['filtres']);
   $midaPantalla = $_GET['midaPantalla'];
   $pagina = $_GET['pagina'];
   $paginaAnt = $_GET['paginaAnt'];
   $dispositiu = $_GET['dispositiu'];
   $packs = $_GET['packs'];
   $cdd = $_GET['cdd'];
   $mixtos = $_GET['mixtos'];
   $subvencio = $_GET['subvencio'];
   $nousCursos = $_GET['nousCursos'];
   $midaMin4=$_GET['limitPantallaTablet'];
   $midaMin2=$_GET['limitPantallaMovil'];

   include("../inc/buscantCursosDisponibles.php");

   $elementsPerPagina=9;
   if ($midaPantalla >= $midaMin4)  $elementsPerPagina=9;
   else if ($midaPantalla <= $midaMin4 && $midaPantalla >= $midaMin2 ) $elementsPerPagina=6;
   else $elementsPerPagina=6;
   $inicPagina = ($pagina-1)*$elementsPerPagina;
   $elementsTotals = count($llistatCursos) + count($llistatCursosPacks);
   $numPagines = ceil($elementsTotals/$elementsPerPagina);

   if ($midaPantalla<$midaMin4)
      $pagVis = 3;
   else
      $pagVis = $numPagines;

   $random = $objOrd[3];

   $elementsMostrats = $elementsPerPagina;
   if ( $elementsTotals < ($inicPagina+$elementsMostrats) ) $elementsMostrats=$elementsTotals-$inicPagina;
   $mostrant = "<div class='mostrant w-100 text-center'>Mostrant ".($inicPagina+1)."-".($inicPagina+$elementsMostrats)." de ".$elementsTotals." cursos |
   <a role='link' class='negreta500' href='https://www.prisma.cat/cursos' target='_self' title='Cursos en línea que ofereix PrisMa'>Mostra tots els cursos <i class='fas fa-long-arrow-alt-right'></i></a></div>";

   $pagination="";
   if ($elementsTotals>0) {
      if ($numPagines>=1) {
         $pagination="<nav class='pagination d-flex flex-row justify-content-center flex-1-0-auto w-100' aria-label='Page navigation'>";
         $pagination.="<ul class='d-flex flex-row m-0'>";

         $pagination.="<li id='pagination-left' class='d-flex justify-content-center align-items-center border'><i class='fa fa-angle-left'></i></a></li>";

         for($i=1;$i<=$pagVis;$i++) {
            $pagination.="<li class='page d-flex justify-content-center align-items-center border";
            if ($pagina==$i) $pagination.=" active";
            $pagination.="'>".$i."</li>";
         }
         if ($numPagines>$pagVis) {
            $pagination.="<li class='d-flex justify-content-center align-items-end punts";
            $pagination.="'>...</li>";

            $pagination.="<li class='page d-flex justify-content-center align-items-center border";
            if ($pagina==$numPagines) $pagination.=" active";
            $pagination.="'>".$numPagines."</li>";
         }

         $pagination.="<li id='pagination-right' class='d-flex justify-content-center align-items-center border'><i class='fa fa-angle-right'></i></a></li>";
         $pagination.="</ul></nav>";
      }
   }
   else {
      $pagination = "<div class='text-center w-100 pt-4'>No s'han trobat resultats amb els filtres marcats.</div>";
      $mostrant = "";
   }

   if ( $pagina == 1 && $paginaAnt == 1 ) {
      $objLlistatCursos = new LlistatCursos($llistatCursos);
      $objLlistatCursosPacks = new LlistatCursos($llistatCursosPacks);
      if ( $random == "1" ) {
         //s'ha triat l'opció mostra aleatoriament
         $objLlistatCursos->ordenaAleatoriament($dispositiu);
         $objLlistatCursosPacks->ordenaAleatoriament($dispositiu);
      }
      else if ($ordre=="ANY,MES ASC") {
         $objLlistatCursos->ordenaMesVisitats($dispositiu);
         $objLlistatCursosPacks->ordenaMesVisitats($dispositiu);
       }
      else if ($ordre=="TITOL ASC") {
         $objLlistatCursos->ordenaAlfabeticamentASC($dispositiu);
         $objLlistatCursosPacks->ordenaAlfabeticamentASC($dispositiu);
       }
      else if ($ordre=="TITOL DESC") {
         $objLlistatCursos->ordenaAlfabeticamentDESC($dispositiu);
         $objLlistatCursosPacks->ordenaAlfabeticamentDESC($dispositiu);
       }
       $llistaTotal = $objLlistatCursos->getList();
       $numElementsCursos = count($llistaTotal);
       $llistaPacks = $objLlistatCursosPacks->getList();
       for ( $i = 0; $i < count($llistaPacks); $i++ ) {
         $llistaTotal[$numElementsCursos + $i] = $llistaPacks[$i];
       }
       $objLlistat = new LlistatCursos($llistaTotal);

      $_SESSION['llistatCursos'] = serialize($objLlistat);
   }
   else {
      $objLlistat = unserialize($_SESSION['llistatCursos']);
      $_SESSION['llistatCursos'] = serialize($objLlistat);
   }

	$list .= "<div class='sticky-body'><div class='theiaStickySidebar w-100'><div class='row'>";
   $list .= $objLlistat->mostrarLlistatCursosFiltres($inicPagina,$elementsPerPagina, "h3");
   $list .= $mostrant;
   $list .= $pagination;

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
