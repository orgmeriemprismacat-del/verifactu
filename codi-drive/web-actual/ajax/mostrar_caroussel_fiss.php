<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../CarousselFiss.php");
include("../Text.php");
include("../Url.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$midaPantalla = $_GET['midaPantalla'];
	$dispositiu = $_GET['dispositiu'];
   $midaMin4=$_GET['limitPantallaTablet'];
   $midaMin2=$_GET['limitPantallaMovil'];

	$id = 'carouselFiss';
	$titol = "Formació d'interès en serveis socials";
	$ariaLabelGeneral = "Cursos que acrediten fromació d'interès en serveis socials";
	$ariaLabelUnic = "Curs que acredita fromació d'interès en serveis socials";
	$caroussel = new CarousselFiss($dispositiu);
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
