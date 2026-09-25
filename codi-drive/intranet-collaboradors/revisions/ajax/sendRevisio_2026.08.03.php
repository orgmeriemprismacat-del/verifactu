<?php

$shortname = $_REQUEST['shortname'];
$user = $_REQUEST['user'];
$existeixIncidencia = $_REQUEST['incidencies'];

include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../../MailSMTPComvive.php');
include ('../../MailSMTPComviveBBCC.php');

try {
  $conWeb = new ConnexioWeb();
  $conWeb->connectarBD();

  $updRevisio = "UPDATE revisio_tutor SET finalitzat = CURRENT_DATE WHERE codic = ? ";
  $updCursos = "UPDATE cursos SET data_revisio = CURRENT_DATE WHERE id_Curs = ?";
  $cnsInfoAula = "SELECT ID_AULA FROM curs WHERE id_curs = ?";
  $updAula = "UPDATE aula SET data_revisio = CURRENT_DATE WHERE id_aula = ? and aula = ?";
  $cnsInfoTut = "SELECT NOM, COGNOMS, MAIL_PRISMA FROM personal WHERE dni LIKE ?";
  // $cnsAutentificacio = "SELECT VALOR FROM params WHERE TIPUS = 'autentificacioCoordinacio' AND
  // DATAI <= CURRENT_TIMESTAMP AND (DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)";
  $cnsAutentificacio = "SELECT VALOR FROM params WHERE TIPUS = 'autentificacioConsultes' AND
  DATAI <= CURRENT_TIMESTAMP AND (DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)";

  if ( $stmt = $conWeb->prepare( $updRevisio ) ) {
    $stmt->bind_param('s', $shortname);
    $stmt->execute();
    $conWeb->closeStmt();
  }
  else {
    throw new Exception('', 11113);
  }
  if ( $stmt = $conWeb->prepare( $updCursos ) ) {
    $stmt->bind_param('s', $shortname);
    $stmt->execute();
    $conWeb->closeStmt();
  }
  else {
    throw new Exception('', 11114);
  }

  $idCurs = substr($shortname, 0, strlen($shortname) - 1);
  $aula = substr($shortname, strlen($shortname) - 1, strlen($shortname));

  if ( $stmt = $conWeb->prepare( $cnsInfoAula ) ) {
    $stmt->bind_param('s', $idCurs);
    $stmt->execute();
    $stmt->bind_result($idAula);
    $stmt->fetch();
    $conWeb->closeStmt();
  }
  else {
    throw new Exception('', 11115);
  }

  if ( $stmt = $conWeb->prepare( $updAula ) ) {
    $stmt->bind_param('ds', $idAula, $aula);
    $stmt->execute();
    $conWeb->closeStmt();
  }
  else {
    throw new Exception('', 11116);
  }

  if ( $stmt = $conWeb->prepare( $cnsInfoTut ) ) {
    $stmt->bind_param('s', $dni);
    $dni = $user."%";
    $stmt->execute();
    $stmt->store_result();
    if ( $stmt->num_rows() > 0 ) {
       $stmt->bind_result($nomTut, $cognomTut, $mailPrisma);
       $stmt->fetch();
    }
    else {
      $dni = "%".$user."%";
      $stmt->execute();
      $stmt->bind_result($nomTut, $cognomTut, $mailPrisma);
      $stmt->fetch();
    }
    $conWeb->closeStmt();
  }
  else {
    throw new Exception('', 11117);
  }

  if ( $stmt = $conWeb->prepare( $cnsAutentificacio ) ) {
    $stmt->execute();
    $stmt->bind_result($valor);
    $stmt->fetch();
    $conWeb->closeStmt();
  }
  else {
    throw new Exception('', 11118);
  }

  $conWeb->desconectarBD();

  $autentificacioCoordinacio = explode('|',$valor);
  $usernameCoord = $autentificacioCoordinacio[0];
  $passwordCoord = $autentificacioCoordinacio[1];
  $nameUserCoord = $autentificacioCoordinacio[2];

  $nomFromHead = $nameUserCoord;
  $correuFromHead = $usernameCoord;

  $nomReplyHead = $nomTut." ".$cognomTut;
  $correuReplyHead = $mailPrisma;

  $nomTo = "Tutoria PrisMa";
  $correuTo = "tutoria@prisma.cat";
  // $correuTo = "tutoria.prisma.cat@gmail.com";
  $subject = "Revisió: ".$shortname;
  $missatge = "<p><strong>".$nomTut." ".$cognomTut."</strong> ha revisat el curs ".$shortname.".</p>";

  $mailCoord = new MailSMTPComvive($usernameCoord, $passwordCoord, $nomFromHead, $correuFromHead,
  								$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
  								$subject, $missatge, $qui, $depart);

  if ( $existeixIncidencia == "1" ) {
  	$nomReplyHead = $nomTut." ".$cognomTut;
  	$correuReplyHead = $mailPrisma;
  	$nomTo = "Suport PrisMa";
  	$correuTo = "suport@prisma.cat";

  	$subject = "Incidències del curs: ".$shortname;
  	$missatge = "<p><strong>".$nomTut." ".$cognomTut."</strong> ha revisat el curs ".$shortname." i ha trobat incidències.</p>
  	<p>Pots consultar les revisions a la Intranet clicant <a style='font-weight: bold; text-decoration: none'
  	href='https://old.prisma.cat/revisions.php'>aquí</a>.</p>";

  	$mailIncidencies = new MailSMTPComviveBBCC($usernameCoord, $passwordCoord, $nomFromHead, $correuFromHead,
  									$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
  									$subject, $missatge, $qui, $depart);

  	$mailIncidencies->addBCC("suport.informatic@prisma.cat", "Suport Informàtic PrisMa");
  	$mailIncidencies->addBCC("consultes@prisma.cat", "Consultes PrisMa");
  	$mailIncidencies->addBCC("tutoria@prisma.cat", "Tutoria PrisMa");
  	$mailIncidencies->send();
  }
}
catch (Exception $e) {
  echo missatgeError( $e->getCode() );
}

?>
