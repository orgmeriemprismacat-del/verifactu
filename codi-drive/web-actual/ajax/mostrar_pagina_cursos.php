<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../Url.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
   $dispositiu = $_GET['dispositiu'];

	$mostrar.="<div class='cnt-cursos'>
   <div class='container'>
      <div class='row'>
         <div class='cnt-titol-filtres col-12 d-flex justify-content-start align-items-center'></div>
         <div class='cnt-bloc-filtres col-3'></div>
         <div class='cnt-bloc-cursos col-9'></div>
      </div>
   </div>
   <div class='modal' id='modalLoading' tabindex='-1' role='dialog'
   aria-labelledby='modalLoading' style='display: none' aria-modal='true'>
      <div class='modal-dialog modal-dialog-centered' role='document'>
         <div class='modal-content w-100 border-0'>
            <div class='modal-body'>
               <div id='loading-wrapper'>
                  <div id='loading-text'>Buscant...</div>
                  <div id='loading-content'></div>
               </div>
            </div>
         </div>
      </div>
 	</div>
   <div class='cnt-baner regala d-none'></div>
   <div class='cnt-baner descomptes'></div>";
	echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
