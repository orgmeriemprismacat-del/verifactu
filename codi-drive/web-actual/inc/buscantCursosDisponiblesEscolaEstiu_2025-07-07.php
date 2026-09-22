<?php

$edicions=''; $hores=''; $temes=''; $nivells='';

$i=0; $qualsevolEdicio=false;
while ($i<count($filtres[0]) && !$qualsevolEdicio) {
	$edicio = $filtres[0][$i];
	$ed=explode('|',$edicio);
	// echo ($edicio); echo "<br>";

	if ($i>0) $edicions .= " OR ";
	$edicions .= "(ANY=".$ed[2]." AND MES='".$ed[1]."')" ;

	//if ($ed[1] == '08')
		//$edicions .= "AND CODI_CURS!='SDA' AND CODI_CURS!='GED'" ;
	if ($ed[1] == '08')
		$edicions .= "AND CODI_CURS!='SDA'" ;
	/*if ($ed[1] == '07' && $ed[2] =='2025')
		$edicions .= " OR ( ANY = 2025 AND MES = '08' AND HORES = 15 )" ;*/

	if ($ed[1]=='00') $qualsevolEdicio=true;

	$i++;
}

if ($qualsevolEdicio) {
	$ed=explode('|',$filtres[0][1]);
	$edicions="(ANY>".$ed[2]." or (any=".$ed[2]." AND mes>='".$ed[1]."'))";
}

for($i=0; $i<count($filtres[1]); $i++) {
	if ($i>0) $hores .= " OR ";
	$hores .= "HORES=".$filtres[1][$i];
}
if ($hores!='') $hores='AND ('.$hores.')';
$objOrd = explode('|',$filtres[4]);
$ordre = $objOrd[1]." ".$objOrd[2];

for($i=0; $i<count($filtres[2]); $i++) {
	$t=explode('|',$filtres[2][$i]);
	$temesFiltres[$i]=$t[0];
}
for($i=0; $i<count($filtres[3]); $i++) {
	$n=explode('|',$filtres[3][$i]);
	$nivellsFiltres[$i]=$n[0];
}
for($i=0; $i<count($filtres[5]); $i++) {
	$n=explode('|',$filtres[5][$i]);
	$altresFiltres[$i]=$n[0];
}
if ( $altresFiltres[0] == 0 )
	$inclouCursosEstiu = 0;
else
	$inclouCursosEstiu = 1;

$filtres = "(".$edicions.") ".$hores;
if ($filtres!='') $filtres=$filtres." AND ";

$posicio=0;

$connexio = new ConnexioBBDDSTMT();
$connexio->connectarBD();
$connexio2 = new ConnexioBBDDSTMT();
$connexio2->connectarBD();

$mostrarTotsCursos = 1;

/* Busco les edicions disponibles */
$cnsEd = "SELECT MES, ANY FROM curs AS c INNER JOIN aula AS a ON
	c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
	INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE PUBLIC=1 AND
	c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1
	AND c.CURS NOT LIKE '%JOR%' AND
	(a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
	OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
	AND ORDRE_TUTOR=1)) AND c.CURS = ? AND ANY >= ? GROUP BY ANY, MES LIMIT 1";
$stmtEd = $connexio2->prepare($cnsEd);
$stmtEd->bind_param("sd", $cursEd, $anyNow);

$anyNow = date('Y');

$cns="SELECT CODI_CURS FROM informacio as i INNER JOIN curs as c ON CODI_CURS=CURS
	INNER JOIN filtres as f ON i.ID = f.ID_INFO
	WHERE f.DATAI<=CURRENT_TIME AND (f.DATAF IS NULL OR CURRENT_TIME<=f.DATAF) AND
	".$filtres." i.ESTAT=1 AND PUBLIC=1 AND (c.ESTAT!='0' AND c.ESTAT!='T') AND i.TIPUS_CURS != 'T' AND i.TIPUS_CURS != 'C'
	GROUP BY CODI_CURS ORDER BY ".$ordre;

// echo $cns;
if ( $stmt = $connexio->prepare($cns) ) {
	$stmt->execute();
	$stmt->bind_result($codiCurs);
	while ($stmt->fetch()) {
		$cursEd = $codiCurs;
		$stmtEd->execute();
		$stmtEd->bind_result($mesFirstEd, $anyFirstEd);
		$stmtEd->fetch();

		if (
			$mostrarTotsCursos ||
			(
				!( $mesFirstEd == '06' || $mesFirstEd == '07' || $mesFirstEd == '08' ) &&
				!$mostrarTotsCursos
			)
		) {
			// echo "CODICURS".$codiCurs."	"."mesFirstEd:".$mesFirstEd."<br />";
			$curs = new Curs($codiCurs, $dispositiu);
			if (
					$curs->obtenirEstat()==1 &&
					( $qualsevolEdicio || (!$qualsevolEdicio && $curs->inscripcionsObertes($ed[2], $ed[1])) )
			) {
				// echo "ESTAT 1	";
				$nivells = $curs->obtenirNivells();
				$i=0; $trobatN=0;
				if (count($nivellsFiltres)==0) $trobatN=1;
				while($i<count($nivells) and $trobatN==0) {
					$j=0;
					while ($j<count($nivellsFiltres) && !$trobatN) {
						if ($nivells[$i]==$nivellsFiltres[$j])
							$trobatN=1;
						$j++;
					}
					$i++;
				}

				$temes = $curs->obtenirTemes();
				$i=0; $trobatT=0;
				if (count($temesFiltres)==0) $trobatT=1;
				while($i<count($temes) and $trobatT==0) {
					$j=0;
					while ($j<count($temesFiltres) && !$trobatT) {
						if ($temes[$i]==$temesFiltres[$j])
							$trobatT=1;
						$j++;
					}
					$i++;
				}

				$cursPertany=false;
				if ($trobatT==1 && $trobatN==1) $cursPertany=true;

				if ( $packs == 1 ) $cursPertany = false;

				if ( $cdd == 1 && !$curs->esCDD() ) $cursPertany = false;
				if ( $mixtos == 1 && !$curs->cursEsMixt() ) $cursPertany = false;
				if ( $subvencio == 1 && !$curs->esCursSubvencionat() ) $cursPertany = false;

				//si el curs es nomes d'estiu, afegirm aquests cursos
				if ( !$mostrarTotsCursos ) {
					if ( $curs->__esCursPerNomesEstiu() ) {
						if ( $inclouCursosEstiu == 1 ) $cursPertany=true;
						else $cursPertany=false;
					}
				}

				if ( (($nousCursos==1 && $curs->__esCursNou()) || ( $nousCursos==1 && $curs->etiquetaCursAmbDescompte() != '' ) || $nousCursos==0) && $cursPertany) {
					$llistatCursos[$posicio] = $curs;
					$posicio++;
				}
			}
		}
	}
	$connexio->closeStmt();
}
$connexio2->closeStmt();
$connexio->desconectarBD();

?>
