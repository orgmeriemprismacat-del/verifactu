<?php

$edicions=''; $hores=''; $temes=''; $nivells='';

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

$inclouCursosEstiu = 1;

$filtres = "(".$edicions.") ".$hores;
if ($filtres!='') $filtres=$filtres." AND ";

$posicio=0;

$connexio = new ConnexioBBDDSTMT();
$connexio->connectarBD();

$cns="SELECT CODI_CURS FROM informacio as i INNER JOIN curs as c ON CODI_CURS=CURS
	INNER JOIN filtres as f ON i.ID = f.ID_INFO
	WHERE f.DATAI<=CURRENT_TIME AND (f.DATAF IS NULL OR CURRENT_TIME<=f.DATAF) AND ".$filtres."
	i.ESTAT=1 AND i.TIPUS_CURS != 'T' AND PUBLIC=1 AND (c.ESTAT!='0' AND c.ESTAT!='T') AND i.TIPUS_CURS != 'C'
	GROUP BY CODI_CURS ORDER BY ".$ordre;

if ( $stmt = $connexio->prepare($cns) ) {
	$stmt->execute();
	$stmt->bind_result($codiCurs);
	while ($stmt->fetch()) {
		$curs = new Curs($codiCurs, $dispositiu);
		if ( $curs->obtenirEstat()==1 &&
			$curs->__esCursPerNomesEstiu()==1 &&
			( $qualsevolEdicio || (!$qualsevolEdicio && $curs->inscripcionsObertesEstiu($ed[2], $ed[1])) )
		) {
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

			if ( !$curs->__esCursPerNomesEstiu() ) $cursPertany=false;
			if ( $curs->__esCursNou() ) $cursPertany=false;

			if ( $cdd == 1 && !$curs->esCDD() ) $cursPertany = false;
			if ( $mixtos == 1 && !$curs->cursEsMixt() ) $cursPertany = false;
			if ( $subvencio == 1 && !$curs->esCursSubvencionat() ) $cursPertany = false;

			if ( $cursPertany) {
				$llistatCursos[$posicio] = $curs;
				$posicio++;
			}
		}
	}
	$connexio->closeStmt();
}
$connexio->desconectarBD();

?>
