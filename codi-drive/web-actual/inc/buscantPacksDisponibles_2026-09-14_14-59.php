<?php

$edicions=''; $perfils=''; $temes=''; $nivells=''; $altres='';

$i=0; $qualsevolEdicio=false;
while ($i<count($filtres[0]) && !$qualsevolEdicio) {
	$edicio = $filtres[0][$i];
	$ed=explode('|',$edicio);

	if ($i>0) $edicions .= " OR ";
	$edicions .= "(ANY=".$ed[2]." AND MES='".$ed[1]."')" ;

	if ($ed[1] == '08' && $ed[2] =='2025')
		$edicions .= "AND CODI_CURS!='SDA'" ;
	if ($ed[1] == '07' && $ed[2] =='2025')
		$edicions .= " OR ( ANY = 2025 AND MES = '08' AND HORES = 15 )" ;

	if ($ed[1]=='00') $qualsevolEdicio=true;

	$i++;
}

if ( $qualsevolEdicio ) {
	$ed=explode('|',$filtres[0][1]);
	$edicions="(ANY>".$ed[2]." or (any=".$ed[2]." AND mes>='".$ed[1]."'))";
}

$objOrd = explode('|', $filtres[4]);
$ordre = $objOrd[1]." ".$objOrd[2];

for ( $i=0; $i < count($filtres[1]); $i++ ) {
	$p=explode('|',$filtres[1][$i]);
	$perfilsFiltres[$i]=$p[0];
}
for ( $i=0; $i < count($filtres[2]); $i++ ) {
	$t=explode('|',$filtres[2][$i]);
	$temesFiltres[$i]=$t[0];
}
for ( $i=0; $i < count($filtres[3]); $i++ ) {
	$n=explode('|',$filtres[3][$i]);
	$nivellsFiltres[$i]=$n[0];
}
for ( $i=0; $i < count($filtres[5]); $i++ ) {
	$n=explode('|',$filtres[5][$i]);
	$altresFiltres[$i]=$n[0];
}

if ( $altresFiltres[0] == 0 )
	$inclouCursosEstiu = 0;
else
	$inclouCursosEstiu = 1;

$filtres = "(".$edicions.") ".$hores;
if ( $filtres != '' ) $filtres = $filtres." AND ";

$posicio=0;

$connexio = new ConnexioBBDDSTMT();
$connexio->connectarBD();
$connexio2 = new ConnexioBBDDSTMT();
$connexio2->connectarBD();

$cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
				DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY VALOR";
$stmtParam = $connexio->prepare($cnsParams);
$stmtParam->bind_param("s", $tipusPar);
$tipusPar = 'DATAI_MOSTRAR_TOTS_CURSOS';
$stmtParam->execute();
$stmtParam->bind_result($dataMostrarTotsCursos);
$stmtParam->fetch();

$connexio->closeStmt();

$date1 = new DateTime("now");
$date2 = new DateTime($dataMostrarTotsCursos);
if ( $date1 >= $date2 ) {
	$mostrarTotsCursos = 1;
}
else {
	$mostrarTotsCursos = 0;
}

/* Busco les edicions disponibles */
$cnsEd = "SELECT c.MES, c.ANY FROM curs AS c
	INNER JOIN aula AS a ON c.ID_AULA=a.ID_AULA
	INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
	INNER JOIN honoraris AS h ON r.ID_HONO=h.ID
	WHERE c.PUBLIC=1 AND c.CURS <> 'PROVA'
	AND c.CURS NOT LIKE '%0%' AND c.CURS NOT LIKE '%JOR%'
	AND c.ESTAT <> '0' AND r.ACTIU = 1
	AND ( a.ID_CUHO IN (17, 13) OR ( a.ID_CUHO <> 17 AND a.AULA = 'A'
	OR ( a.ID_CUHO <> 17 AND h.DNI_TUTOR = 'GENERIC' )
	AND h.DNI_TUTOR <> 'GENERIC' AND h.perfil = 'tutor' AND h.ORDRE_TUTOR = 1))
	AND c.CURS = ? AND c.ANY >= ? GROUP BY c.ANY, c.MES LIMIT 1";
$stmtEd = $connexio2->prepare($cnsEd);
$stmtEd->bind_param("sd", $cursEd, $anyNow);

$anyNow = date('Y');

$connexio2->closeStmt();

$posicioPack = 0; $llistatCursosPacks = [];
$cnsPack="SELECT i.ID_PACK FROM info_pack as i INNER JOIN packs as p ON p.ID_PACK = i.ID_PACK
	INNER JOIN filtres as f ON i.ID_PACK = f.ID_INFO INNER JOIN curs as c ON p.ID_CURS=c.ID_CURS
	WHERE f.DATAI<=CURRENT_TIME AND (f.DATAF IS NULL OR CURRENT_TIME<=f.DATAF)
	AND ".$filtres." i.ESTAT=1 AND c.PUBLIC=1 AND p.PUBLIC=1 AND (c.ESTAT!='0' AND c.ESTAT!='T')
	GROUP BY i.ID_PACK ORDER BY ".$ordre;

if ( $stmt = $connexio->prepare($cnsPack)) {
	$stmt->execute();
	$stmt->bind_result($idPack);
	while ($stmt->fetch()) {
		$pack = new Pack($idPack, $dispositiu);

		if ( $pack->obtenirEstat()==1 && ( $qualsevolEdicio || (!$qualsevolEdicio && $pack->inscripcionsObertes($ed[2], $ed[1])) ) ) {
			// echo "**********************<br />";
			// echo "idpack ".$idPack."<br />";

			$nivells = $pack->obtenirIdNivells();
			$i=0; $trobatN=0;
			if ( count($nivellsFiltres)==0 ) $trobatN=1;
			while( $i<count($nivells) and $trobatN==0 ) {
				$j=0;
				while ($j<count($nivellsFiltres) && !$trobatN) {
					if ($nivells[$i]==$nivellsFiltres[$j])
						$trobatN=1;
					$j++;
				}
				$i++;
			}
			// echo "--------------------<br />";

			$temes = $pack->obtenirIdTemes();
			$i=0; $trobatT=0;
			if ( count($temesFiltres)==0 ) $trobatT=1;
			while( $i<count($temes) and $trobatT==0 ) {
				$j=0;
				while ($j<count($temesFiltres) && !$trobatT) {
					if ($temes[$i]==$temesFiltres[$j])
						$trobatT=1;
					$j++;
				}
				$i++;
			}
			// echo "####################<br />";

			$perfils = $pack->obtenirIdPerfils();
			$i=0; $trobatP=0;
			if ( count($perfilsFiltres)==0 ) $trobatP=1;
			while( $i<count($perfils) and !$trobatP ) {
				// echo "PERFILS DEL PACK ".$i." ".$perfils[$i]."<br />";
				$j=0;
				while ($j<count($perfilsFiltres) && !$trobatP) {
					// echo "PERFILS FILTRES ".$j." ".$perfilsFiltres[$j]."<br />";
					if ($perfils[$i]==$perfilsFiltres[$j])
						$trobatP=1;
					$j++;
				}
				$i++;
			}

			$cursPertany=false;
			if ($trobatT==1 && $trobatN==1 && $trobatP==1) $cursPertany=true;

			if ( $cdd == 1  && !$pack->esCDD() ) $cursPertany = false;
			if ( $nousCursos==1 && !$pack->__esPackNou() ) $cursPertany = false;

			if ( $cursPertany ) {
				$llistatCursosPacks[$posicioPack] = $pack;
				$posicioPack++;
			}
		}
	}
	$connexio->closeStmt();

}

$connexio->desconectarBD();
$connexio2->desconectarBD();
?>
