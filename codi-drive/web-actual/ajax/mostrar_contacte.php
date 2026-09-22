<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Contacte.php");
include("../Text.php");
include("../Url.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$url_actual = $_GET['url'];
	$dispositiu = $_GET['dispositiu'];

	$id_url_actual = buscarPagina($url_actual);
	$url = new Url($id_url_actual);
	$contacte = new Contacte($dispositiu);

	$mostrar =$contacte->mostrarIcones(); //Mostrar info inicial i icones
	$mostrar.=$contacte->mostrarFormulari(); //Mostrar Formulari
	$mostrar.=$contacte->mostrarMapa(); //Mostrar mapa
	$mostrar.=$contacte->modalError(); //Mostrar error
	$mostrar.=$contacte->consultaEnviada(); //Mostrar consulta enviada
	echo $mostrar;

}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}


?>
