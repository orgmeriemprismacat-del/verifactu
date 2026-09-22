<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Page2.php");
include("../Url.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$url_actual = $_GET['url'];
	$dispositiu = $_GET['dispositiu'];

	$id_url_actual = buscarPagina($url_actual);
  $url = new Url($id_url_actual);
	$pagina = new Page($url);

	echo $pagina->getContent();
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
