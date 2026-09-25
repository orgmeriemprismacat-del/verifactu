<?php
try {
	$shortname = $_REQUEST['shortname'];
	$idCurs = substr($shortname, 0, strlen($shortname) - 1);

	include ('../../ConnexioWeb.php');
	$sqlTitle="SELECT NOM_CURS FROM curs WHERE id_Curs=?";

  $conWeb = new ConnexioWeb();
  $conWeb->connectarBD();

	if ( $stmt = $conWeb->prepare( $sqlTitle ) ) {
		$stmt->bind_param('s', $idCurs);
		$stmt->execute();
		$stmt->store_result();
		$rowcount = $stmt->num_rows();
		if ( $stmt->num_rows() > 0 ) {
			$stmt->bind_result($nomCurs);
			$stmt->fetch();
			$conWeb->closeStmt();
		}
	}
	else {
		throw new Exception('', 11200);
	}

	$conWeb->desconectarBD();
	echo $nomCurs;
}
catch (Exception $e) {
 echo missatgeError( $e->getCode() );
}
?>
