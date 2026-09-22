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

function mostrarAccordionsCursosPerfils($id, $accordion, $obert, $idPerf,$codiCurs,$codiEsc) {
	$cap="Curs de l'activitat: ".$codiEsc;
	$cont='';

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();
	$cnsPerfils="SELECT NOM_CURS, c.HORES, MES, ANY, CODI_GTAF
		FROM perfils AS p
		INNER JOIN curs AS c ON c.CURS=p.CODI AND c.HORES=p.HORES AND c.CURS_ESCOLAR=p.CURS_ESCOLAR
		INNER JOIN aula AS a ON a.ID_AULA = c.ID_AULA AND p.CODI_GTAF=a.GTAF_AULA
		WHERE CODI=? AND ID_PERFIL=? AND p.CURS_ESCOLAR=? AND c.PUBLIC = 1 AND c.ESTAT != '0' AND c.ESTAT != 'P'
		GROUP BY codi_gtaf 
		ORDER BY ANY DESC, MES DESC";
	$stmt=$connexio->prepare($cnsPerfils);
	$stmt->bind_param("sds", $codiCurs, $idPerf, $codiEsc);
	$stmt->execute();
	$stmt->store_result();
	if ( $stmt->num_rows() > 0 ) {
		$cont .= "<table class='table table-striped table-hover w-100'>";
		$cont .= '<thead><tr>';
		$cont .= "<th scope='col'>Títol activitat</th>";
		$cont .= "<th scope='col'>Hores</th>";
		$cont .= "<th scope='col'>Mes</th>";
		$cont .= "<th scope='col'>Any</th>";
		$cont .= "<th scope='col'>Codi activitat</th>";
		$cont .= '</tr></thead><tbody>';
		$stmt->bind_result($nomCurs, $hores, $mes, $any, $codiGtaf);
		while ( $stmt->fetch() ) {
			$cont .= '<tr><td>'.$nomCurs.'</td><td>'.$hores.'</td>';
			$cont .= '<td>'.$mes.'</td><td>'.$any.'</td><td>'.$codiGtaf.'</td></tr>';
		}
		$cont .= '</tbody></table>';
	}
	$connexio->closeStmt();
	$connexio->desconectarBD();

	if ( $cont != '' ) $mostrar .= construirAccordion($id, $accordion, $cap, $cont, $obert);

	return $mostrar;
}

function mostrarAccordionsCursosPerfilsSubv($id, $accordion, $obert, $idPerf,$codiCurs,$codiEsc) {
	$cap="Curs de l'activitat: ".$codiEsc;
	$cont='';

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();
	$cnsPerfils="SELECT NOM_CURS, HORES, gtafs_subv.MES, gtafs_subv.ANY,
	gtafs_subv.AULA, gtafs_subv.GTAF FROM gtafs_subv INNER JOIN curs ON
	curs.ANY = gtafs_subv.ANY AND curs.MES = gtafs_subv.MES AND curs.CURS = gtafs_subv.CURS
	WHERE gtafs_subv.CURS = ? AND CURS_ESCOLAR  = ? AND curs.PUBLIC = 1 AND curs.ESTAT != '0' AND curs.ESTAT != 'P' ORDER BY gtafs_subv.ANY DESC, gtafs_subv.MES DESC";

	$stmt=$connexio->prepare($cnsPerfils);
	$stmt->bind_param("ss", $codiCurs, $codiEsc);
	$stmt->execute();
	$stmt->store_result();
	$stmt->bind_result($nomCurs, $hores, $mes, $any, $aula, $codiGtaf);
	if ( $stmt->num_rows() > 0 ) {
		$cont .= "<table class='table table-striped table-hover w-100'>";
		$cont .= '<thead><tr>';
		$cont .= "<th scope='col'>Títol activitat</th>";
		$cont .= "<th scope='col'>Hores</th>";
		$cont .= "<th scope='col'>Mes</th>";
		$cont .= "<th scope='col'>Any</th>";
		$cont .= "<th scope='col'>Codi activitat</th>";
		$cont .= '</tr></thead><tbody>';
		while ( $stmt->fetch() ) {
			$cont .= '<tr><td>'.$nomCurs.'</td><td>'.$hores.'</td>';
			$cont .= '<td>'.$mes.'</td><td>'.$any.'</td><td>'.$codiGtaf.'</td></tr>';
		}
		$cont .= '</tbody></table>';
	}
	$connexio->closeStmt();
	$connexio->desconectarBD();

	if ( $cont != '' ) $mostrar .= construirAccordion($id, $accordion, $cap, $cont, $obert);

	return $mostrar;
}

function consultaCursosPerfilSubvencionat($cnt, $codiCurs) {
	$idPerf = 7;
	$obert='true';
	$anyAct=date("Y");

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$consCursEscolarPerfilSubv="SELECT CURS_ESCOLAR FROM gtafs_subv INNER JOIN curs ON
	curs.ANY = gtafs_subv.ANY AND curs.MES = gtafs_subv.MES AND curs.CURS = gtafs_subv.CURS
	WHERE gtafs_subv.CURS = ? GROUP BY CURS_ESCOLAR ORDER BY CURS_ESCOLAR DESC";
	$stmt=$connexio->prepare($consCursEscolarPerfilSubv);
	$stmt->bind_param("s", $codiCurs);
	$stmt->execute();
	$stmt->bind_result($codiEsc);
	while ($stmt->fetch()) {
		$partCursEsc = explode('/',$codiEsc);
		// if ($partCursEsc[0] == $anyAct or $partCursEsc[1]==$anyAct)
		// 	$obert='true';
		$mostrar .= mostrarAccordionsCursosPerfilsSubv($cnt, 'accordion'.$idPerf, $obert, $idPerf, $codiCurs, $codiEsc);
		$cnt++;
	}

	$connexio->closeStmt();
	$connexio->desconectarBD();

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
	$mostrar.=$titol."</a></strong> que compten o han comptat per acreditar els diferents perfils. ";
	$mostrar.="Recordeu que cada edició del curs té un codi diferent.</p>";
	$mostrar.="<p>Podeu comprovar que aquest curs compta per acreditar el perfil ";
	$mostrar.="cercant-lo per codi i curs escolar a la pàgina <a role='link' rel='noopener' class='font-weight-bold' ";
	$mostrar.="href=\"https://aplicacions.ensenyament.gencat.cat/pls/apex/f?p=2017002:35\" ";
	$mostrar.="target='_blank' title='Activitats formatives i d&#39;innovació acollides als perfils professionals'>";
	$mostrar.="Consulta d'activitats formatives i d'innovació acollides als perfils professionals</a> del Departament d'Educació.<p>";

	//MIREM SI EL CURS TÉ UN CURS SUBVENCIONAT
	$contMostrar = []; $exCursSubv = 0; $cntContMostrar = 0;
	$cnsExCursSubv="SELECT ID FROM informacio WHERE TIPUS_CURS = 'S' AND  CODI_CURS=?";
	$stmt=$connexio->prepare($cnsExCursSubv);
	$stmt->bind_param("s", $codiCurs);
	$stmt->execute();
	$stmt->store_result();
	if ($stmt->num_rows() > 0) $exCursSubv = 1;
	$connexio->closeStmt();

	$consPerfil="SELECT HORES, CODI_GTAF, CURS_ESCOLAR, ID_PERFIL FROM perfils
		WHERE CODI=? GROUP BY CURS_ESCOLAR, ID_PERFIL ORDER BY ID_PERFIL, CURS_ESCOLAR DESC";
	$stmt=$connexio->prepare($consPerfil);
	$stmt->bind_param("s", $codiCurs);
	$stmt->execute();
	$stmt->bind_result($hores, $codiGtaf, $codiEsc, $idPerf);
	$idPerfAnt=0;
	$cnt=1;
	$anyAct=date("Y");
	while ($stmt->fetch()) {
		$obert='false';
		if ($idPerf!=$idPerfAnt) {
			$perfil = new Perfil($idPerf);
			$nomPerfilCurt=$perfil->obtenirNomCurt()->obtenirText();
			$nomPerfilLlarg=$perfil->obtenirNomLlarg()->obtenirText();

			$textClassPerfil = new Text($nomPerfilCurt);
         $textClassPerfil->obtenirNomCurt();
         $classPerfil = $textClassPerfil->obtenirText();

			$contingutPerfils .= "<h2 class='".$classPerfil." pt-1'>Perfil: ".$nomPerfilLlarg."</h2>";
			$contingutPerfils .= "<h3>".$perfil->obtenirNomDepartament()->obtenirText()."</h3>";
			if ($cnt != 1)
				$contingutPerfils .= '</div>';
			$contingutPerfils .= "<div class='panel-group' id='accordion".$idPerf."' role='tablist' aria-multiselectable='true'>";

			$partCursEsc = explode('/',$codiEsc);
			if ($partCursEsc[0] == $anyAct or $partCursEsc[1]==$anyAct)
				$obert='true';
		}
		if ( $exCursSubv == 1 ) {
			$contMostrar[$cntContMostrar][0] = $idPerf;
			$contMostrar[$cntContMostrar][1] = mostrarAccordionsCursosPerfils($cnt, 'accordion'.$idPerf, $obert, $idPerf, $codiCurs, $codiEsc);
			// ECHO "1-1";
			// ECHO $contMostrar[$cntContMostrar][1];
			// ECHO "1-2";
			// ECHO mostrarAccordionsCursosPerfils($cnt, 'accordion'.$idPerf, $obert, $idPerf, $codiCurs, $codiEsc);
			$cntContMostrar++;
		}
		else {
			$contingutPerfils .= mostrarAccordionsCursosPerfils($cnt, 'accordion'.$idPerf, $obert, $idPerf, $codiCurs, $codiEsc);
		}

		$idPerfAnt=$idPerf;
		$cnt++;
	}
	$connexio->closeStmt();

	//BUSQUEM SI EXISTEIX UN CURS SUBVENCIONAT D'AQUEST CODI CURS.
	if ( $exCursSubv == 1 ) {
		for ( $i = 0; $i < count($contMostrar); $i++ ) {
			// ECHO "2";
			$contingutPerfils .= $contMostrar[$i][1];
			// ECHO "3";
			// ECHO $contMostrar[$i][1];
			if ( $contMostrar[$i][0] == 7 ) {
				//Busquem els cursos subvencionats
				$idPerf = 7;
				$contingutPerfils .= consultaCursosPerfilSubvencionat($cnt, $codiCurs);
			}
		}
		if ( $contingutPerfils == '' ) {
			$idPerf = 7;
			$perfil = new Perfil($idPerf);
			$nomPerfilCurt=$perfil->obtenirNomCurt()->obtenirText();
			$nomPerfilLlarg=$perfil->obtenirNomLlarg()->obtenirText();

			$textClassPerfil = new Text($nomPerfilCurt);
         $textClassPerfil->obtenirNomCurt();
         $classPerfil = $textClassPerfil->obtenirText();

			$contingutPerfils .= "<h2 class='".$classPerfil." pt-1'>Perfil: ".$nomPerfilLlarg."</h2>";
			$contingutPerfils .= "<h3>".$perfil->obtenirNomDepartament()->obtenirText()."</h3>";
			if ($cnt != 1)
				$contingutPerfils .= '</div>';
			$contingutPerfils .= "<div class='panel-group' id='accordion".$idPerf."' role='tablist' aria-multiselectable='true'>";

			$contingutPerfils .= consultaCursosPerfilSubvencionat($cnt, $codiCurs);
		}
	}

	$mostrar .= $contingutPerfils;

	$connexio->desconectarBD();
	$mostrar.="</div><div class='mostrar-perfils-professionals mt-2'>";
	$mostrar.="<a role='link' class='mostrar-tots' href='https://www.prisma.cat/perfils-professionals/' ";
	$mostrar.="target='_self' title='Visualitzar tots els cursos que acrediten algun perfil professional'>";
	$mostrar.="<i class='fas fa-long-arrow-alt-left'></i> Mostra tots els cursos ";
	$mostrar.="de PrisMa que acrediten algun perfil professional</a></div>";
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
