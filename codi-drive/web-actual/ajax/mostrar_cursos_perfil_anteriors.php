<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

function mostrarLiniaPerfil($codiCurs, $nomCurs, $hores, $numEd) {
	$existEnllac = 0;

	$connexio2 = new ConnexioBBDDSTMT();
	$connexio2->connectarBD();

	$cns2="SELECT ID_AMIGABLE, URL
		FROM informacio AS i INNER JOIN amigable AS a ON i.ID_AMIGABLE = a.ID
		WHERE i.CODI_CURS = ? ORDER BY i.ID LIMIT 1";
	$stmt2 = $connexio2->prepare($cns2);
	$stmt2->bind_param("s", $codiCurs);
	$stmt2->execute();
	$stmt2->store_result();
	if ($stmt2->num_rows() > 0)  {
		$stmt2->bind_result($idAmig, $url);
		$stmt2->fetch();
		$existEnllac = 1;
	}
	$connexio2->closeStmt();
	$connexio2->desconectarBD();

	if ( $existEnllac ) {
		$partsLink = explode('/',$url);
		$nomAmig = $partsLink[count($partsLink)-1];

		$title = "Totes les edicions que acrediten algun perfil del curs de PrisMa «".$nomCurs."»";
		$link = "https://www.prisma.cat/perfils-professionals/".$nomAmig;
	}

	$mostrar .= "<li>";

	if ( $existEnllac )
		$mostrar .= "<a class='font-weight-bold' role='link' href=\"".$link."\" target='_self' title=\"".$title."\">";

	$mostrar .= $nomCurs." | ".$hores."h";

	if ( $existEnllac )
		$mostrar .= "</a>";

	$mostrar .= " (".$numEd." edicions)</li>";

	return $mostrar;
}

try {
	$dispositiu = $_GET['dispositiu'];
	$url = $_GET['url'];

	$mostrar = '';

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();
	$connexio2 = new ConnexioBBDDSTMT();
	$connexio2->connectarBD();

	$cntPerfils = 0;

	//Busco tots els perfils disponibles a la taula perfils
	$cnsPerfilsDispo = "SELECT ID_PERFIL, pl.NOM, pl.NCURT, pl.NHORES FROM perfils
	INNER JOIN perfils_list AS pl ON pl.ID = perfils.ID_PERFIL GROUP BY ID_PERFIL";
	$stmt = $connexio->prepare($cnsPerfilsDispo);
	$stmt->execute();
	$stmt->bind_result($idPerfil, $nomPerf, $nomCurtPerf, $horesPerf);
	$stmt->store_result();
	while ($stmt->fetch()) {
		//Per cada perfil:
		$perfils[$cntPerfils][0] = $idPerfil;
		$perfils[$cntPerfils][1] = $nomCurtPerf;
		$perfils[$cntPerfils][2] = $nomPerf;
		$perfils[$cntPerfils][3] = $horesPerf;

		$i = 0;

		//Busco tots els codis cursos, nom del cursos de l'edició, les hores de l'edició
		// d'aquell perfil i el nombre d'edicions amb aquell perfil
		$cnsPerfils="SELECT CODI, NOM_CURS, p.HORES, Count(GTAF) AS EDICIONS
			FROM (((perfils AS p INNER JOIN curs AS c ON c.CURS = p.CODI AND c.HORES = p.HORES
			AND c.CURS_ESCOLAR = p.CURS_ESCOLAR AND p.CODI_GTAF = c.GTAF) INNER JOIN
			perfils_list AS pl ON pl.ID = p.ID_PERFIL))
			WHERE ID_PERFIL = ? AND c.PUBLIC = 1 AND c.ESTAT != '0' AND c.ESTAT != 'P'
			GROUP BY CODI, HORES, NOM_CURS ORDER BY CODI, HORES, NOM_CURS";
		$stmt2=$connexio2->prepare($cnsPerfils);
		$stmt2->bind_param("d", $idPerfil);
		$stmt2->execute();
		$stmt2->bind_result($codiCurs, $nomCurs, $hores, $edicions);
		while ($stmt2->fetch()) {
			$perfils[$cntPerfils][4][$i]['codi'] = $codiCurs;
			$perfils[$cntPerfils][4][$i]['titol'] = $nomCurs;
			$perfils[$cntPerfils][4][$i]['hores'] = $hores;
			$perfils[$cntPerfils][4][$i]['numEd'] = $edicions;
			$i++;
		}
		$connexio2->closeStmt();

		if ( $idPerfil == 7 ) {
			//Busco tots els codis cursos, nom del cursos de l'edició, les hores de l'edició
			$cnsPerfils="SELECT curs.CURS, NOM_CURS, HORES, COUNT(gtafs_subv.GTAF) AS EDICIONS
			FROM gtafs_subv INNER JOIN curs ON
			curs.ANY = gtafs_subv.ANY AND curs.MES = gtafs_subv.MES AND curs.CURS = gtafs_subv.CURS
			GROUP BY curs.CURS, HORES, NOM_CURS ORDER BY gtafs_subv.ANY DESC, gtafs_subv.MES DESC";
			$stmt2=$connexio2->prepare($cnsPerfils);
			$stmt2->execute();
			$stmt2->bind_result($codiCurs, $nomCurs, $hores, $edicions);
			while ($stmt2->fetch()) {
				$perfils[$cntPerfils][4][$i]['codi'] = $codiCurs;
				$perfils[$cntPerfils][4][$i]['titol'] = $nomCurs;
				$perfils[$cntPerfils][4][$i]['hores'] = $hores;
				$perfils[$cntPerfils][4][$i]['numEd'] = $edicions;
				$i++;
			}
			$connexio2->closeStmt();
		}

		$cntPerfils++;
	}
	$connexio->closeStmt();
	$connexio->desconectarBD();
	$connexio2->desconectarBD();

	// var_dump($perfils);

	for ($i = 0; $i < count($perfils); $i++ ) {
		$idPerfil = $perfils[$i][0];
		$nomCurtPerfil = $perfils[$i][1];
		$nomPerf = $perfils[$i][2];
		$horesPerf = $perfils[$i][3];

		$textNCurt = new Text( $nomCurtPerfil );
		$textNCurt->obtenirNomCurt();
		$nomPerfCurt = $textNCurt->obtenirText();

		$textExperiencia = "";
		if ( $idPerfil == 7 ) $textExperiencia = " i experiència";

		$mostrar .= "<h4 class='requisits'><strong class=".$nomPerfCurt.">".$nomPerf;
		$mostrar .= "</strong> (cal acreditar ".$horesPerf." hores de formació permanent".$textExperiencia.")</h4>";
		$mostrar .= "<ul class='llistes nivell1 fi'>";

		//mostrem els cursos amb el perfil $nomCurtPerfil
		for ($j = 0; $j < count($perfils[$i][4]); $j++ ) {
			$codi = $perfils[$i][4][$j]['codi'];
			$titol = $perfils[$i][4][$j]['titol'];
			$hores = $perfils[$i][4][$j]['hores'];
			$numEd = $perfils[$i][4][$j]['numEd'];
			$mostrar .= mostrarLiniaPerfil($codi, $titol, $hores, $numEd);
		}

		$mostrar .= "</ul>";
	}

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
	  echo mostrarPagina404();
  else
	  echo missatgeError($e->getCode());
}

?>
