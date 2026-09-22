<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Tutor.php");
include("../Text.php");
include("../Imatge.php");
include("../Url.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $url_actual = $_GET['url'];
   $dispositiu = $_GET['dispositiu'];
   $midaPantalla = $_GET['midaPantalla'];

   $id_url = buscarPagina($url_actual);
   $url = new Url($id_url);
   $tutor = new Tutor($url, $dispositiu);
   $llistat = $tutor->obtenirLlistat();
   $dni = $tutor->obtenirDni()->obtenirText();

   $mostrar = "<div class='container single-tutor d-flex flex-column'>".$tutor->mostrarTutor()."</div>";
   if ( $llistat->obtenirNumeroElements() > 0 ) {
      $mostrar .= "<div class='cursos-tutoritzats mt-4'><div class='container'>";
      if (!$tutor->obtenirNomesAutor()) {
   		$mostrar .= "<h2 class='h1'>Cursos que tutoritza</h2>";
   		$mostrar .= $llistat->mostrarCursTutoritzat($dni, $midaPantalla);

   	   $mostrar .= "<div class='container pl-0 m-0 separacio-peu'><p class='mb-0'><a role='link' ";
   	   $mostrar .= "class='mostrar-tots' href='https://www.prisma.cat/cursos/' ";
   	   $mostrar .= "target='_self' title='Visualitza tots els cursos'>";
   	   $mostrar .= "<i class='fas fa-long-arrow-alt-left'></i> Mostra tots els cursos";
   	   $mostrar .= "</a></p></div></div>";
   	}
      $mostrar .= "</div>";
   }

   echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else if ($e->getCode()==206)
      echo missatgeErrorTutorNoDisponbile();
   else
      echo missatgeError($e->getCode());
}

?>
