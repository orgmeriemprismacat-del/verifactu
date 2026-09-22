<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Perfil.php");
include("../Url.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

function construirAccordion($id, $accordion, $textPregunta, $textBody, $obert) {
	$idHead='heading'.$id;
	$idColl='collapse'.$id;
	$ob='';
	$style='';
	if ($obert == "true") {
		$ob='show';
		$coll='';
	}
	else {
		$style='height: 0;';
		$coll='collapsed';
	}

	$mostrar ="<div class='panel panel-default'><div role='tab' id='".$idHead."' ";
	$mostrar.="class='panel-heading ".$coll."' data-toggle='collapse' aria-expanded='".$obert."' ";
	$mostrar.="data-target='#".$idColl."' href='#".$idColl."' aria-controls='".$idColl."'>";
	$mostrar.="<h4 class='panel-title'>".$textPregunta.'</h4></div>';

	$mostrar.="<div id='".$idColl."' class='panel-collapse collapse ".$ob."' ";
	$mostrar.="role='tabpanel' aria-labelledby='".$idHead."' aria-expanded='".$obert."' ";
	$mostrar.="style='".$style."'><div class='panel-body p-0'>".$textBody."</div></div></div>";

	return $mostrar;
}

function mostrarAccordionsCursos($id, $accordion, $obert, $codiCurs,$codiEsc) {
	$cap="Curs de l'activitat: ".$codiEsc;
	$cont='';

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();
	$connexio2 = new ConnexioBBDDSTMT();
	$connexio2->connectarBD();
	$cnsPerfils="SELECT NOM_CURS, c.HORES, MES, ANY, GTAF, i.TIPUS_CURS
		FROM curs AS c INNER JOIN informacio AS i ON c.CURS = i.CODI_CURS
		WHERE i.CODI_CURS=? AND c.CURS_ESCOLAR=? AND CDD != 0
		GROUP BY ANY, MES, c.CURS ORDER BY ANY DESC, MES DESC";
	$cnsGtaf="SELECT AULA, GTAF
		FROM gtafs_subv
		WHERE ANY=? AND MES=? AND CURS=?";
	$stmt=$connexio->prepare($cnsPerfils);
	$stmt2=$connexio2->prepare($cnsGtaf);
	$stmt->bind_param("ss", $codiCurs, $codiEsc);
	$stmt->execute();
	$stmt->bind_result($nomCurs, $hores, $mes, $any, $codiGtaf, $tipusCurs);
	$cont .= "<table class='table table-striped table-hover w-100'>";
	$cont .= '<thead><tr>';
	$cont .= "<th scope='col'>Títol activitat</th>";
	$cont .= "<th scope='col'>Hores</th>";
	$cont .= "<th scope='col'>Mes</th>";
	$cont .= "<th scope='col'>Any</th>";
	$cont .= "<th scope='col'>Codi activitat</th>";
	$cont .= '</tr></thead><tbody>';
	while ($stmt->fetch()) {
		if ( $tipusCurs == 'S' ) {
			$stmt2->bind_param("dss",$any, $mes, $codiCurs);
			$stmt2->execute();
			$stmt2->bind_result($aula, $codiGtafs);
			while ($stmt2->fetch()) {
				$cont .= '<tr>
					<td>'.$nomCurs.'</td>
					<td>'.$hores.'</td>
					<td>'.$mes.'</td>
					<td>'.$any.'</td>
					<td>'.$codiGtafs.'</td>
				</tr>';
			}
		}
		else {
			$cont .= '<tr>
				<td>'.$nomCurs.'</td>
				<td>'.$hores.'</td>
				<td>'.$mes.'</td>
				<td>'.$any.'</td>
				<td>'.$codiGtaf.'</td>
			</tr>';
		}
	}
	$cont .= '</tbody></table>';
	$connexio2->closeStmt();
	$connexio2->desconectarBD();
	$connexio->closeStmt();
	$connexio->desconectarBD();

	$mostrar .= construirAccordion($id, $accordion, $cap, $cont, $obert);

	return $mostrar;
}

try {
	$url_actual=$_GET['url'];
	$dispositiu=$_GET['dispositiu'];

	$id_url_actual=buscarPagina($url_actual);
	$url=new Url($id_url_actual);

	$partsLink=explode('/',$url->obtenirLink());
	$nomAmig=$partsLink[count($partsLink)-1];

	$mostrar="<div class='container separacio-peu'>";

	$connexio=new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$urlCons='/cursos/'.$nomAmig;
	$idUrlCons=buscarPagina($urlCons);

	$consInfo="SELECT CODI_CURS, TITOL FROM informacio WHERE ID_AMIGABLE=?";
	$stmt=$connexio->prepare($consInfo);
	$stmt->bind_param("d", $idUrlCons);
	$stmt->execute();
	$stmt->bind_result($codiCurs, $titol);
	$stmt->fetch();
	$connexio->closeStmt();

	$mostrar.="<h1 class='prisma-main-title'>".$titol."</h1>
	<p>A continuació trobareu les edicions del curs <strong><a role='link' ";
	$mostrar.="href='https://www.prisma.cat".$urlCons."' title='Informació del curs ".$titol."'>";
	$mostrar.=$titol."</a></strong> que compten o han comptat per acreditar la competència digital. ";
	$mostrar.="Recordeu que cada edició del curs té un codi diferent.</p>";

	$consPerfil="SELECT HORES, GTAF, CURS_ESCOLAR FROM curs
		WHERE CURS=? AND CDD != 0 GROUP BY CURS_ESCOLAR ORDER BY CURS_ESCOLAR DESC";
	$stmt=$connexio->prepare($consPerfil);
	$stmt->bind_param("s", $codiCurs);
	$stmt->execute();
	$stmt->bind_result($hores, $codiGtaf, $codiEsc);
	$cnt=1;
	$anyAct=date("Y");
	while ($stmt->fetch()) {
		$obert='false';
		$mostrar .= "<div class='panel-group' id='accordion".$i."' role='tablist' aria-multiselectable='true'>";
		$partCursEsc = explode('/',$codiEsc);
		if ($partCursEsc[0] == $anyAct or $partCursEsc[1]==$anyAct)
			$obert='true';
		$mostrar .= mostrarAccordionsCursos($cnt, 'accordion'.$i, $obert, $codiCurs, $codiEsc);
		$cnt++;
	}
	$connexio->closeStmt();
	$connexio->desconectarBD();
	$mostrar.="</div><div class='mostrar-cursos mt-2'>";
	$mostrar.="<a role='link' class='mostrar-tots' href='https://www.prisma.cat/competencia-digital' ";
	$mostrar.="target='_self' title='Visualitzar tots els cursos que acrediten la competencia digital'>";
	$mostrar.="<i class='fas fa-long-arrow-alt-left'></i> Mostra tots els cursos ";
	$mostrar.="de PrisMa que acrediten la competencia digital</a></div>";
	$mostrar.='</div>';

	echo $mostrar;

}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
