<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../CarousselPerfils.php");
include("../Text.php");
include("../Url.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$midaPantalla = $_GET['midaPantalla'];
	$dispositiu = $_GET['dispositiu'];
   $midaMin4=$_GET['limitPantallaTablet'];
   $midaMin2=$_GET['limitPantallaMovil'];

	$id = 'carouselPerfiles';
	$titol = "Perfils professionals";
	$ariaLabelGeneral = "Cursos que acrediten perfils professionals";
	$ariaLabelUnic = "Curs que acredita un perfil professional";
	$caroussel = new CarousselPerfils($dispositiu);
	$caroussel->setinfo($id, $titol, $ariaLabelGeneral, $ariaLabelUnic);
	$mostrar .= "<div class='container'>";
	$mostrar .= $caroussel->vista($midaPantalla, $midaMin4, $midaMin2);
	$mostrar .= "</div>";

	echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
