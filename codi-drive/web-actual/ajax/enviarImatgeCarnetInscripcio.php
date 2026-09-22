<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Numero.php");
include("../Text.php");
include("../Date.php");
include("../Mail.php");
include("../MailSMTP.php");
include("../MailSMTPComvive.php");
include("../MailSMTPFile.php");

try {
	$codiCurs = $_POST['codiCurs'];
	$edicio = $_POST['edicio'];
	$any = $_POST['any'];
	$documentacio = $_POST['documentacio'];
	// $id = time();
	$textDocumentacio = new Text( $_POST['documentacio'] );
	$textDocumentacio->arreglarParaulaBD('text_maj');
	$documentacio = $textDocumentacio->obtenirText();

	$nomImg = $any.$codiCurs.$edicio."-".$documentacio;

	$namefile = explode('.', $_FILES['file']['name']);
	$extNameFile = $namefile[count($namefile)-1];
	$nomImg = $nomImg.".".$extNameFile;

	if (move_uploaded_file($_FILES["file"]["tmp_name"], "carnets/".$nomImg)) {
	  echo "carnets/".$nomImg;
	}
	else {
	  echo 0;
	}

	$subjectMailInsc = "Inscripció ".$codiCurs." ".$edicio." - ".$documentacio." + O";
	$msgHtml = "<img src='https://www.prisma.cat/ajax/carnets/".$nomImg."'  style='width: 500px'/>";

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	//buscar el username i el password d'autentificació de prisma
	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	$stmt=$connexio->prepare($cnsParam);
	$stmt->bind_param("s", $tipusParam);
	$tipusParam = 'autentificacioInscripcio';
	$stmt->execute();
	$stmt->bind_result($valor);
	$stmt->fetch();
	$autentificacioInscripcio = explode('|',$valor);
	$username = $autentificacioInscripcio[0];
	$password = $autentificacioInscripcio[1];
	$nameUser = $autentificacioInscripcio[2];

	$nomFromHead = $nameUser;
	$correuFromHead = $username;
	$nomReplyHead = $nomCognoms;
	$correuReplyHead = $email;

	$connexio->closeStmt();

	$subject = "Inscripció al curs ".$titolCurs;
	$nomTo = "PrisMa Secretaria";
	$correuTo = "resguard.secretaria@prisma.cat";
	$correuTo = 'meriem.prisma.cat@gmail.com';


	$mailAlumne = new MailSMTPFile($username, $password, $nomFromHead, $correuFromHead,
		$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
		$subjectMailInsc, $msgHtml);

	$connexio->desconectarBD();
}
catch(Exception $e) {
	if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}

?>
