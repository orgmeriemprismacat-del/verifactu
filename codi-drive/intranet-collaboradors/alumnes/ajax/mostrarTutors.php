<?php
try {
	$shortname = $_GET['shortname'];
	$username = $_GET['username'];

	function url_exists($url = NULL) {
		if( empty( $url ) ){
			return false;
		}

		$ch = curl_init( $url );

		// Set a waite time
		curl_setopt( $ch, CURLOPT_TIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );

		// Set NOBODY true for established a new request type HEAD
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		// Accept redirections
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		// Recieve response with string type, no output type
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		$data = curl_exec( $ch );

		// Obtains de response code
		$httpcode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		// Close session
		curl_close( $ch );

		// Only accept response codes 200 (OK), 301 or 302 (redirections)
		$accepted_response = array( 200, 301, 302 );
		if( in_array( $httpcode, $accepted_response ) ) {
			return true;
		} else {
			return false;
		}
	}

	include ('../../ConnexioMoodle.php');
	include ('../inc/missatgesError.php');

	$conMoodle = new ConnexioMoodle();
  $conMoodle->connectarBD();
	$conMoodle2 = new ConnexioMoodle();
  $conMoodle2->connectarBD();

	$cnsInfoTutor="SELECT firstname, lastname, email, id, picture
		FROM mdl_user WHERE id IN (SELECT userid FROM `mdl_role_assignments` WHERE roleid=3 AND contextid IN
			(SELECT id FROM `mdl_context` WHERE contextlevel=50 AND instanceid IN
				(SELECT id FROM `mdl_course` WHERE shortname LIKE ?)))";
	$cnsMdlFile = "SELECT contextid FROM mdl_files WHERE id = ?";

	if ( $stmt=$conMoodle->prepare( $cnsInfoTutor ) ) {
		$stmt->bind_param("s", $shortname);
		$stmt->execute();
		$stmt->store_result();
		if ( $stmt->num_rows() > 0 ) {
			if ( $stmt->num_rows() > 1 ) {
				$mostrar="<p>Tria el tutor/a al qual vols enviar el missatge:</p>";
			}
			$mostrar.="<div class='cnt-tutors'>";
			$i=0; $className = 'foto';
			$stmt->bind_result($firstname, $lastname, $email, $idUser, $idPicture);
			while ( $stmt->fetch() ) {
				if ( $stmt2=$conMoodle2->prepare( $cnsMdlFile ) ) {
					$stmt2->bind_param("d", $idPicture);
					$stmt2->execute();
					$stmt2->bind_result($contextIdUser);
					$stmt2->fetch();
					$conMoodle2->closeStmt();
				}
				else {
					throw new Exception('', 11204);
				}

				if ( $contextIdUser != '' )
					$urlFoto = "https://campus.prisma.cat/pluginfile.php/".$contextIdUser."/user/icon/f3";
				else {
					$urlFoto = "https://campus.prisma.cat/user/pix.php/".$idUser."/f1.jpg";
					$url_exists = url_exists( $urlImg ) ? "1" : "0";
					if( !$url_exists )
						$urlFoto = "https://www.prisma.cat/campus/intranet-alumnes/img/not-found.png";
				}

				if ( $stmt->num_rows() > 1 ) $className = 'foto-2';
				$mostrar.="<div class='".$className."' id='tutor".$i."'>";
				$mostrar.="<img src='".$urlFoto."' height='100' width='100'>";
				$mostrar.="<p id='nom".$i."'>".htmlspecialchars($firstname." ".$lastname)."</p>";
				$mostrar.="<div id='emailTutor".$i."' class='emailTutor d-none'>".$email."</div></div>";
				$i++;
			}
			$mostrar.="</div>";
		}
		$conMoodle->closeStmt();
	}
	else {
		throw new Exception('', 11203);
	}

	$conMoodle2->desconectarBD();
	$conMoodle->desconectarBD();
	echo $mostrar;
}
catch (Exception $e) {
	echo missatgeError( $e->getCode() );
}
?>
