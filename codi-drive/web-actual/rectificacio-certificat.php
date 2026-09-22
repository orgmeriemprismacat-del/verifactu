<?php

include ('ConnexioBBDD_PreparedStatment.php');
include ('MailSMTP.php');
include ('Text.php');

$missatge = "<p>Benvolgut/benvolguda,</p>

<p>T'enviem aquest missatge rectificatiu per informar-te que, a causa d'un error, la data en la qual tindràs disponible el certificat del curs «Tutoria Eficaç: Tècniques i Recursos Pràctics» no és el 10 de setembre, sinó el <strong>24 de setembre</strong>.</p>

<p>Et recordem que en cas que necessitis el certificat amb urgència, et podem enviar el certificat digital de PrisMa per correu electrònic.</p>

<p>Disculpa les molèsties que t'hàgim pogut ocasionar.</p>

<p>Salutacions ben cordials,</p>";

$conWeb = new ConnexioBBDDSTMT();
$conWeb->connectarBD();
$conWeb2 = new ConnexioBBDDSTMT();
$conWeb2->connectarBD();

$consultaCertificats = "SELECT NOM, COGNOMS, CORREU FROM inscripcions WHERE ANY = 2021 AND MES LIKE '08' AND CURS LIKE 'TUT' AND CERTIFICAT LIKE 'PUJAT' ORDER BY COGNOMS, NOM";

if ( $stmt = $conWeb->prepare( $consultaCertificats ) ) {
	$stmt->execute();
	$stmt->store_result();
	$numRows = $stmt->num_rows();
	$i = 0;
	$stmt->bind_result($nom, $cognoms, $email);
	while( $stmt->fetch() ) {
		echo "Processing (".$i."/".$numRows.")...".$email."<br />";

		//buscar el username i el password d'autentificació de prisma
		$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
						AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
		$stmt2=$conWeb2->prepare($cnsParam);
		$stmt2->bind_param("s", $tipusParam);
		$tipusParam = 'msgRectificacio';
		$stmt2->execute();
		$stmt2->bind_result($valor);
		$stmt2->fetch();

		$autentificacioInscripcio = explode('|',$valor);
		$username = $autentificacioInscripcio[0];
		$password = $autentificacioInscripcio[1];
		$nameUser = $autentificacioInscripcio[2];
		$conWeb2->closeStmt();

		$subject = "Rectificació data certificat PrisMa";

		$nomFromHead = $nameUser;
		$correuFromHead = $username;
		$nomReplyHead = $nameUser;
		$correuReplyHead = $username;
		$nomTo = $nom." ".$cognoms;
		$correuTo = $email;

		$mailAlumne = new MailSMTP($username, $password, $nomFromHead, $correuFromHead,
											$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
											$subject, $missatge);
		$i++;
	}
}

$conWeb->closeStmt();
$conWeb->desconectarBD();


?>
