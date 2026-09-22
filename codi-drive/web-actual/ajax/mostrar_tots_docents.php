<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Docent.php");
include("../Text.php");
include("../Imatge.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	/* Mostrem tots els docents actius ordenats per cognoms i nom */
	$cnsDoc="SELECT DNI_TUTOR FROM personal AS p INNER JOIN honoraris AS h
		ON h.DNI_TUTOR=p.DNI WHERE DNI_TUTOR!='0' AND DNI_TUTOR!='1' AND DNI_TUTOR!='X'
		AND DNI_TUTOR REGEXP  '^[0123456789XYZ]' AND h.ESTAT=1 AND PERFIL=? AND
		CURS!='PROVA' AND CURS!='JOR' AND CURS NOT LIKE '%0%' AND p.ESTAT = 1
		GROUP BY DNI_TUTOR ORDER BY COGNOMS, NOM";
	$stmt=$connexio->prepare($cnsDoc);
	$stmt->bind_param("s", $perfil);
	$perfil='tutor';
	$stmt->execute();
	$stmt->bind_result($dniTut);
	$posicio=0;
	while ($stmt->fetch()) {
		$listDni[$posicio]=$dniTut;
		$posicio++;
	}
	$connexio->closeStmt();
	$connexio->desconectarBD();

	$mostrar="<h1>Equip docent</h1><div class='row d-flex flex-row flex-wrap'>";

	$num=count($listDni);

	for ($i=0; $i<$num; $i++) {
		$docent = new Docent($listDni[$i]);
		$mostrar.=$docent->mostrarDocents();
	}
	$mostrar.='</div>';
	$mostrar.="<p class='clearfix mb-4 text-left'><a role='link' class='mostrar-tots' href='https://www.prisma.cat/autors/' target='_self' title='Visualitza autors'>
		 Mostra els autors<i class='fas fa-long-arrow-alt-right ml-2'></i></a></p>";
		 
	echo $mostrar;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
