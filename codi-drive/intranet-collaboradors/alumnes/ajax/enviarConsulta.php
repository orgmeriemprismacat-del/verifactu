<?php
try {
   $shortname = $_GET['shortname']; //puc saber el codi, edicio i l'aula
   $firstname = $_GET['firstname'];
   $lastname = $_GET['lastname'];
   $emailAlumne = $_GET['emailAlumne'];
   $missatge = $_GET['missatge'];
   $nomTutor = $_GET['nomTut'];
   $emailTutor = $_GET['emailTut'];

   if ($shortname=='' OR $firstname=='' OR $lastname=='' OR $emailAlumne=='' OR
      $missatge=='' OR $nomTutor=='' OR $emailTutor=='') {
      $mostrar = "Hi ha hagut algun problema. Prova-ho més tard. ";
      $mostrar .= "Si encara tens el problema, contacta amb <strong>suport@prisma.cat</strong>.";
   }
   else {
     include ('../../ConnexioWeb.php');
     include ('../../Text.php');
     include ('../../MailSMTPComvive.php');

     $conWeb = new ConnexioWeb();
     $conWeb->connectarBD();

     $sqlTitle="SELECT NOM_CURS, NOM, CURS, AULA FROM curs INNER JOIN aula ON curs.ID_AULA = aula.ID_AULA INNER JOIN mesos ON MES=num WHERE id_Curs=? and aula = ?";
   	if ( $stmt = $conWeb->prepare( $sqlTitle ) ) {
   		$stmt->bind_param('ss', $shortname1, $aula2);
      $shortname1 = substr($shortname, 0, -1);
      $aula2 = substr($shortname, -1);
   		$stmt->execute();
   		$stmt->store_result();
   		$rowcount = $stmt->num_rows();
   		if ( $stmt->num_rows() > 0 ) {
   			$stmt->bind_result($titol, $nomEdicio, $codi, $aula);
   			$stmt->fetch();
   			$conWeb->closeStmt();
   		}
         else
            echo "No existeix l'aula!<br />";
   	}
   	else {
   		throw new Exception('', 11201);
   	}
    $textEd = new Text( $nomEdicio );
    $textEd->setMaj();
    $edicio = $textEd->get();


    $nomAlumne = $firstname.' '.$lastname;

    $data = new DateTime();
    $timestamp = $data->getTimestamp();
    $nomAlumne = str_replace(":","",$nomAlumne);

    $subjAlumn = "Consulta tutoria del curs «".$titol."» núm. ".$timestamp;
    $subjTutor = "TUTORIA ".$codi." ".$edicio." ".$aula.": ".$nomAlumne." núm. ".$timestamp;

    $missatgeAlumn  = "<p>El tutor/a <strong>".$nomTutor."</strong> ha rebut correctament la consulta que has realitzat.</p>
    <p>La teva consulta:</p>
    <div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
    ".$missatge."
    </div>
    <p>Atentament</p>";
    $missatgeTutor  = "<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
    <p><strong>Nom:</strong> ".$nomAlumne."</p>
    <p><strong>Adreça electrònica:</strong> ".$emailAlumne."</p>
    <p><strong>Consulta: </strong><br />
    ".$missatge."</p></div>";
    $cnsAutentificacio = "SELECT VALOR FROM params WHERE TIPUS = 'autentificacioNoReply' AND
    DATAI <= CURRENT_TIMESTAMP AND (DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)";
    if ( $stmt = $conWeb->prepare( $cnsAutentificacio ) ) {
      $stmt->execute();
      $stmt->bind_result($valor);
      $stmt->fetch();
      $conWeb->closeStmt();
    }
    else {
      throw new Exception('', 11202);
    }
    $conWeb->desconectarBD();

    $autentificacioNoReply = explode('|',$valor);
    $usernameNoReply = $autentificacioNoReply[0];
    $passwordNoReply = $autentificacioNoReply[1];
    $nameUserNoReply = $autentificacioNoReply[2];

    $depart = "Departament de Formació";

    $nomFromHead = $nameUserNoReply;
    $correuFromHead = $usernameNoReply;

    $nomReplyHead = "No-Reply PrisMa";
    $correuReplyHead = "no-reply@prisma.cat";
    $nomTo = $nomAlumne;
    $correuTo = $emailAlumne;
    // $correuTo = "meriem.prisma.cat@gmail.com";

    $mailAlumne = new MailSMTPComvive($usernameNoReply, $passwordNoReply, $nomFromHead, $correuFromHead,
    								$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
    								$subjAlumn, $missatgeAlumn, $qui, $depart);
    $nomFromHead = $nameUserNoReply;
    $correuFromHead = $usernameNoReply;

    $nomReplyHead = $nomAlumne;
    $correuReplyHead = $emailAlumne;

    $nomTo = $nomTutor;
    $correuTo = $emailTutor;
    // $correuTo = "meriem.prisma.cat@gmail.com";

    $mailAlumne = new MailSMTPComvive($usernameNoReply, $passwordNoReply, $nomFromHead, $correuFromHead,
    								$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
    								$subjTutor, $missatgeTutor, $qui, $depart);
     $mostrar = 'ok';
   }

   echo $mostrar;

}
catch (Exception $e) {
  echo $e->getCode();
}

?>
