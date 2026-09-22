<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

function construirAccordion($id, $textPregunta, $textBody, $obert) {
	$id_heading = 'heading'.$id;
	$id_collapse = 'collapse'.$id;

	$mostrar .= "<div class='panel panel-default'><div  role='tab' id='".$id_heading."' ";
	$mostrar .= "class='panel-heading collapsed' role='button' data-toggle='collapse' ";
	$mostrar .= "data-target='#".$id_collapse."' href='#".$id_collapse."' ";
	$mostrar .= "aria-expanded='' aria-controls='".$id_collapse."' class='collapsed'>";
	$mostrar .= "<h2 class='panel-title'>".$textPregunta.'</h2></div>';

	$mostrar .= "<div id='".$id_collapse."' class='panel-collapse collapse' role='tabpanel' aria-labelledby='".$id_heading."' aria-expanded='false' style='height: 0px;'>";
	$mostrar .= "<div class='panel-body'>".$textBody;
	$mostrar .= '</div></div></div>';

	return $mostrar;
}

try {
	$dispositiu = $_GET['dispositiu'];

	/* Estructura accordion */
	$mostrar = "<div class='panel-group' id='accordion' role='tablist' aria-multiselectable='true'>";

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsFaq="SELECT ID, PREGUNTA, RESPOSTA FROM faq WHERE
		TIPUS=? AND (APARTAT='' OR APARTAT IS NULL) AND CURS=? ORDER BY ORDRE";
	$stmt=$connexio->prepare($cnsFaq);
	$stmt->bind_param("ds", $tipus, $curs);
	$tipus=1;
	$curs='TOTS';
	$stmt->execute();
	$stmt->bind_result($id, $pregunta, $resposta);
	while ($stmt->fetch() ){
		if ($cnt == 0) $obert = true;
		else $obert = false; //si no ha d'estar desplegat
		$mostrar .= construirAccordion($id, $pregunta, $resposta, $obert);
		$cnt++;
	}
	$connexio->closeStmt();
	$mostrar .= "</div>";
	$connexio->desconectarBD();
	echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}


?>
