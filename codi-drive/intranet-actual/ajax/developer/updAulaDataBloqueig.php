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

	$buscarCursos = "SELECT ID_AULA, DATA_BLOQUEIG FROM curs";
	$updGtaf = "UPDATE aula SET DATA_BLOQUEIG = ? WHERE ID_AULA = ?";

	$conWeb = new ConnexioWeb();
   $conWeb->connectarBD();
	$conWeb2 = new ConnexioWeb();
   $conWeb2->connectarBD();

	if ( $stmt=$conWeb->prepare( $buscarCursos ) ) {
		if ( $stmt2=$conWeb2->prepare( $updGtaf ) ) {
			$stmt->execute();
			$stmt->bind_result($idAula, $dataBloqueig);
			while ( $stmt->fetch() ) {
				$stmt2->bind_param("sd", $dataBloqueig, $idAula);
				echo $updGtaf."<br />".$dataBloqueig." ".$idAula."<br />";
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
