<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD.php");
include("../Text.php");
include("../Numero.php");

try {
	$codi = $_GET['codi'];
	$dni = $_GET['dni'];

	$connexio = new ConnexioBBDD();
	$connexio->connectarBD();

	$connexio->consultarBD("SELECT ID_PREU FROM cursos as c WHERE (CURS LIKE '".$codi."' AND GTAF IS NOT NULL
		AND GTAF != '' AND ((DATAI + 7 > CURRENT_DATE AND (c.HORES=30 OR c.HORES = 40
		OR c.HORES = 60)) OR (DATAI + 14 > CURRENT_DATE AND (c.HORES=100))) AND (CURS NOT
		LIKE '%JOR%') AND (CURS NOT LIKE '%0%')) ORDER BY MES LIMIT 1");
	$row_preu = $connexio->obtenirResultat();
	$id_preu = $row_preu['ID_PREU'];

	$connexio->consultarBD("SELECT PERCENTATGE, MES FROM descompte WHERE DATAI <= CURRENT_TIMESTAMP AND
	CURRENT_TIMESTAMP <= DATAF ORDER BY DATAI DESC");
	$row_descompte = $connexio->obtenirResultat();
	$num_descomptes = $connexio->obtenirNumRows();

	$mes = $row_descompte['MES'];
	$percentatge = $row_descompte['PERCENTATGE'];

	//HAIG DE MIRAR SI LA PERSONA HA PARTICIPAT EL CURS AMB CODI like CODI0% AND INSC CURS = 1 AND A_PAGAR - PAGAMENT <= 0. SI ÉS QUE SI $num_jornades = 1, ALTRAMENT 0
	$connexio->consultarBD("SELECT ID FROM inscripcions WHERE CURS LIKE '".$codi."0%' AND DNI LIKE '".$dni."' AND INSC_CURS LIKE '1' AND A_PAGAR - PAGAMENT <=0");
	if ($connexio->obtenirNumRows()>0)
		$num_jornades = 1;
	else
		$num_jornades = 0;

	$connexio->consultarBD("SELECT IMPORT, TIPUS FROM preu WHERE NUM = ".$id_preu." AND (TIPUS = 2 OR TIPUS = 3) AND (DATAF is null OR
	(DATAI <=CURRENT_DATE AND CURRENT_DATE<=DATAF)) ORDER BY TIPUS");

	while ($row_preu = $connexio->obtenirResultat()) {
		$import = $row_preu['IMPORT'];
		$tipus = $row_preu['TIPUS'];

		if ($tipus == 2) {
			$preu = $import;
			if ($num_descomptes > 0){
				if ($mes == 'TOTS') {
					$import_descomptat = ($import*$percentatge)/100;
					$preu = floatval($import) - floatval($import_descomptat);
				}
			}
		}
		else {
			if ($num_jornades > 0) {
				$preu = $import;
			}
		}
	}

	$connexio->lliurarConsulta();
	$connexio->desconectarBD();

	$objPreu = new Numero($preu);
	$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();

	echo $preuFormatCorrecte;
}
catch(Exception $e) {
	echo "Url. Missatge: ". $e->getMessage();
}

?>
