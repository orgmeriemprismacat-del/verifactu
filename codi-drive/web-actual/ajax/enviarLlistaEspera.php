<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Mail.php");
include("../MailSMTPComvive.php");
include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$apartats = json_decode($_GET['apartats']);
	$length = $_GET['length'];

	$mostrar='ok';
	$i=0; $error=false;
	while ($i<$length && !$error) {
		if ($apartats[$i][0]!='comprovaSpam' && $apartats[$i][1]=='') $error=true;
		else if ($apartats[$i][0]=='comprovaSpam' && $apartats[$i][1]!='') $error=true;
		$i++;
	}

	if ($error)
		$mostrar.="Hi ha hagut algun problema. Prova-ho més tard. Si encara tens el problema, contacta amb suport@prisma.cat";
	else {
		$data = new DateTime();
		$timestamp = $data->getTimestamp();

		$edicio = new Text($apartats[0][1]);
		
		$connexio = new ConnexioBBDDSTMT();
		$connexio->connectarBD();
		
		$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
		$stmt=$connexio->prepare($cnsParam);
		$stmt->bind_param("s", $tipusParam);
		$tipusParam = 'autentificacioInscripcio';
		$stmt->execute();
		$stmt->bind_result($valor);
		$stmt->fetch();
		$autentificacioInscripcio = explode('|',$valor);
		$usernameInsc = $autentificacioInscripcio[0];
		$passwordInsc = $autentificacioInscripcio[1];
		$nameUserInsc = $autentificacioInscripcio[2];

		$connexio->closeStmt();
		
		$subjectMailInsc="[Avís llista d'espera ".$apartats[6][1]."-".$apartats[0][1]."] Sol·licitud núm. ".$timestamp;
		
		$msgInsc="<p>Dades de la persona per a contactar en quan s'allibera una plaça del curs <strong>".$apartats[5][1]."</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:25px;margin-bottom:20px'>
			<p><strong>Nom:</strong> ".$apartats[3][1]."</p>
			<p><strong>Cognoms:</strong> ".$apartats[4][1]."</p>
			<p><strong>Adre&ccedil;a electr&ograve;nica:</strong> ".$apartats[1][1]."</p>
			<p><strong>Telèfon:</strong> ".$apartats[2][1]."</p>
		</div>";
		
		$nomFromHead = $nameUserInsc;
		$correuFromHead = $usernameInsc;
		$nomReplyHead = $apartats[3][1]." ".$apartats[4][1];
		$correuReplyHead = $apartats[1][1];
		
		$nomTo = 'Secretaria PrisMa';
		$correuTo = 'inscripcions@prisma.cat';
		
		$prisma = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);
		
		$nomTo = 'Secretaria PrisMa';
		$correuTo = 'secretaria@prisma.cat';
		
		$prisma = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);
		
		$nomTo = "PrisMa Secretaria";
		$correuTo = "resguard.secretaria@prisma.cat";
		
		$prisma = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
										$subjectMailInsc, $msgInsc);
		
		$subjectMailInsc="Sol·licitud de plaça de curs ".$apartats[5][1]." a l'edició ".$edicio->obtenirDeMesLlarg()."";
		
		$msgInsc="<p>Benvolgut/da ".$apartats[3][1].",</p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:25px;margin-bottom:20px'>
			<p>Hem rebut la teva sol&middot;licitud correctament.</p>
			<p>Si s'allibera una plaça del curs <strong>".$apartats[5][1]."</strong> de l' <strong>edició ".$edicio->obtenirDeMesLlarg()."</strong> ens posarem en contacte amb tu a través del correu <strong>".$apartats[1][1]."</strong> i/o del telèfon <strong>".$apartats[2][1]."</strong> que ens has facilitat.</p>
		</div>
		<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>
		<p>Salutacions ben cordials,</p>";
		
		$nomFromHead = $nameUserInsc;
		$correuFromHead = $usernameInsc;
		$nomReplyHead = $nameUserInsc;
		$correuReplyHead = $usernameInsc;
		
		$nomTo = $apartats[3][1]." ".$apartats[4][1];
		$correuTo = $apartats[1][1];
		
		$alumne = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
									$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
									$subjectMailInsc, $msgInsc);
	}

	echo $mostrar;
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}


?>
