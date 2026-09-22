<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Mail.php");
include("../Text.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$apartats = json_decode($_GET['apartats']);
	$length = $_GET['length'];

	$mostrar='ok';
	$i=0; $error=false;
	while ($i<$length && !$error) {
		if ($apartats[$i][0]!='comprovaSpam' &&
			$apartats[$i][0]!='obs'
			&& $apartats[$i][1]=='') $error=true;
		else if ($apartats[$i][0]=='comprovaSpam' && $apartats[$i][1]!='') $error=true;
		$i++;
	}

	if ($error)
		$mostrar="Hi ha hagut algun problema. Prova-ho més tard. Si encara tens el problema, contacta amb suport@prisma.cat";
	else {
		$data = new DateTime();
		$timestamp = $data->getTimestamp();

		if ( $apartats[6][1] != '' || $apartats[1][1] != '' || $apartats[3][1] != '' ||
				$apartats[4][1] != '' || $apartats[1][1] != '' || $apartats[2][1] != '' || $apartats[0][1] != ''  ) {

			$prisma = new Mail();
			$subject="[Avís edicions reconegudes ".$apartats[6][1]."] Sol·licitud núm. ".$timestamp;
			$prisma->addSubject($subject);
			$prisma->addHeaders('Secretaria PrisMa', 'secretaria@prisma.cat', $apartats[1][1]);
			// $prisma->addHeaders('Secretaria PrisMa', 'suport.informatic@prisma.cat', $apartats[1][1]);
			$prisma->addTo('PrisMa Secretaria <inscripcions.prisma@gmail.com>');
			// $prisma->addTo('PrisMa Secretaria <suport.informatic@prisma.cat>');

			$missPreDiv="<p>Dades de la persona per contactar quan s'obrin les inscripcions del curs <strong>".$apartats[6][1]."</strong></p>";
			$missatge="<p><strong>Nom:</strong> ".$apartats[3][1]."</p>";
			$missatge.="<p><strong>Cognoms:</strong> ".$apartats[4][1]."</p>";
			$missatge.="<p><strong>Adre&ccedil;a electr&ograve;nica:</strong> ".$apartats[1][1]."</p>";
			$missatge.="<p><strong>Telèfon:</strong> ".$apartats[2][1]."</p>";
			$missatge.="<p><strong>observacions:</strong> ".$apartats[0][1]."</p>";
			$missPostDiv="";
			$prisma->addMissatgeTiquet($missPreDiv, $missatge, $missPostDiv);
			$prisma->sendMessage();

			$alumne = new Mail();
			$subject="T'avisarem quan s'obrin les inscripcions de ".$apartats[5][1];
			$alumne->addSubject($subject);
			$alumne->addHeaders('Secretaria PrisMa', 'secretaria@prisma.cat', 'secretaria@prisma.cat');
			// $alumne->addHeaders('Secretaria PrisMa', 'suport.informatic@prisma.cat', 'suport.informatic@prisma.cat');
			$alumne->addTo($apartats[1][1]);
			$missPreDiv="<p>Benvolgut/da,</p>";
			$missatge ="<p>Hem rebut la teva sol&middot;licitud correctament.";
			$missatge.="<p>Quan s'obrin les inscripcions del curs <strong>".$apartats[5][1]."</strong> ens posarem en contacte amb tu a través del correu <strong>".$apartats[1][1]."</strong> i/o del telèfon <strong>".$apartats[2][1]."</strong> que ens has facilitat.</p>";
			$missPostDiv.="<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";
			$missPostDiv.="<p>Salutacions ben cordials,</p>";
			$alumne->addMissatgeTiquet($missPreDiv, $missatge, $missPostDiv);
			$alumne->sendMessage();
		}
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
