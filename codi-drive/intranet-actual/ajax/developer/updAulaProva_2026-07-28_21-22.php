<?php

include ('../../ConnexioIntranet.php');
include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../Date.php');
include ('../../Usuari.php');
include ('../../Intranet.php');
include ('../../inc/missatgesError.php');

try {

	/* Per cada curs que existeix on any >= 2026
		agafa el seu id_aula, gtaf i data de valoració.
		Per el seu id_aula -> fes un update del gtaf per el recuperrat i  la data de valroació per la recuperada.
	*/

	$buscarCursos = "SELECT ID_AULA, GTAF, DATA_VALORACIO FROM curs WHERE ANY>=2020";
	$updGtaf = "UPDATE aula SET GTAF_AULA = ?, DATA_VALORACIO_AULA = ? WHERE ID_AULA = ?";

	$conWeb = new ConnexioWeb();
   $conWeb->connectarBD();
	$conWeb2 = new ConnexioWeb();
   $conWeb2->connectarBD();

	if ( $stmt=$conWeb->prepare( $buscarCursos ) ) {
		if ( $stmt2=$conWeb2->prepare( $updGtaf ) ) {
			$stmt->execute();
			$stmt->bind_result($idAula, $gtaf, $dataValoracioGtaf);
			while ( $stmt->fetch() ) {
				$stmt2->bind_param("ssd", $gtaf, $dataValoracioGtaf, $idAula);
				echo $updGtaf."<br />".$gtaf." ".$dataValoracioGtaf." ".$idAula."<br />";
				$stmt2->execute();
			}
			$conWeb2->closeStmt();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',9002);
		}
	}
	else {
		throw new Exception('',9001);
	}
   $conWeb2->desconectarBD();
   $conWeb->desconectarBD();

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
}

?>
