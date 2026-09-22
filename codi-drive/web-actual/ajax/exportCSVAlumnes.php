<?php
include ('../ConnexioBBDD_PreparedStatment.php');

try {
	$csv_file = "alumnes-inscrits.csv";
	$path_file = "./".$csv_file;
	$csv_sep = ";";
	$csv_end = "
	";

	$csv="nom".$csv_sep."cognoms".$csv_sep."email
	";

	//Si el fitxer no existeix, generem el fitxer
	if (!$handle = fopen($path_file, "a+")) {
			echo "Cannot open";
			exit;
	}
	//Omplim el fitxer
	if (fwrite($handle, utf8_decode($csv)) === FALSE) {
			echo "Cannot write to file";
			exit;
	}

	$connexioWeb = new ConnexioBBDDSTMT();
	$connexioWeb->connectarBD();
	$connexioWeb2 = new ConnexioBBDDSTMT();
	$connexioWeb2->connectarBD();

	$cnsUsers = "SELECT DNI FROM inscripcions WHERE
	(UPPER(`INSC CURS`) = '0' OR UPPER(`INSC CURS`) = '1') AND DNI != '' 
	GROUP BY DNI limit 35000 ";
	$cnsLastEmailByUser = "SELECT NOM, COGNOMS, CORREU FROM inscripcions
	WHERE DNI = ? ORDER BY DATA_INSC DESC LIMIT 1";
	if ( $stmt=$connexioWeb->prepare($cnsUsers) ) {
		if ( $stmt2=$connexioWeb2->prepare($cnsLastEmailByUser) ) {
			$stmt2->bind_param("s", $dniUser);
			$stmt->execute();
			$stmt->bind_result( $dni );
			while ( $stmt->fetch() ) {
				$dniUser = $dni;
				// echo $dniUser."<br>";
				$stmt2->execute();
				$stmt2->bind_result($nom, $cog, $email );
				$stmt2->fetch();
				$csv = $nom.$csv_sep.$cog.$csv_sep.$email.$csv_end;
				// echo $csv;
				// Omplim el fitxer
				if (fwrite($handle, utf8_decode($csv)) === FALSE) {
						echo "Cannot write to file";
						exit;
				}
			}
			$connexioWeb2->closeStmt();
			$connexioWeb->closeStmt();
		}
		else {
			echo "error";
		}
	}
	else {
		echo "error";
		//
	}

	fclose($handle);
	$connexioWeb2->desconectarBD();
	$connexioWeb->desconectarBD();
	echo "fet";

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
}

echo $mostrar;

 ?>
