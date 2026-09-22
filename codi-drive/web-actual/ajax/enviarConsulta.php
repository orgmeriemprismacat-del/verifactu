<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Mail.php");
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

		$alumne = new Mail();
		$subject="[Consulta enviada] Tiquet núm. ".$timestamp;
		$alumne->addSubject($subject);
		$alumne->addHeaders('Secretaria PrisMa', 'no-reply@prisma.cat', 'secretaria@prisma.cat');
		$alumne->addTo($apartats[1][1]);

		$consulta = str_replace("\'","'",$apartats[3][1]);

		$missPreDiv ="<p>Nou tiquet (".$timestamp.") enviat correctament.</p>";
		$missPreDiv.="<p>La teva consulta:</p>";
		$missPostDiv ="<p>En 24-48 hores laborables ens posarem en contacte amb tu a trav&eacute;s d'aquest correu electr&ograve;nic.";
		$missPostDiv.="<p>Gràcies per contactar amb PrisMa.</p>";
		$alumne->addMissatgeTiquet($missPreDiv, $consulta, $missPostDiv);
		$alumne->sendMessage();

		$prisma = new Mail();
		$prisma->addSubject($subject);
		$prisma->addHeaders($apartats[0][1], $apartats[1][1], $apartats[1][1]);
		$prisma->addTo('PrisMa Secretaria <secretaria@prisma.cat>');

		$nom = str_replace("\'","'",$apartats[0][1]);
		$email = str_replace("\'","'",$apartats[1][1]);
		$telf = str_replace("\'","'",$apartats[2][1]);
		$consulta = str_replace("\'","'",$apartats[3][1]);

		$missPreDiv = "<p>Nou tiquet (".$timestamp.") rebut correctament.</p>";
		$missatge ="<p><strong>Nom:</strong> ".$nom."</p>";
		$missatge.="<p><strong>Adre&ccedil;a electr&ograve;nica:</strong> ".$email."</p>";
		$missatge.="<p><strong>Tel&egrave;fon:</strong> ".$telf."</p>";
		$missatge.="<p><strong>Missatge: </strong><br />";
		$missatge.=$consulta."</p>";
		$missPostDiv='';
		$prisma->addMissatgeTiquet($missPreDiv, $missatge, $missPostDiv);
		$prisma->sendMessage();
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
