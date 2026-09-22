<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Mail.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
	$correu = $_GET['correu'];
	$comprovaSpam = $_GET['comprova'];

	$mostrar='ok';
	$error=false;
	if ($comprovaSpam!='' || $correu=='') $error=true;

	// echo "c".$comprovaSpam."correu".$correu;
	if ($error)
		$mostrar="Hi ha hagut algun problema. Prova-ho més tard. Si encara tens el problema, contacta amb suport@prisma.cat";
	else {
		// afegim sol·licitud de subscripció
		$connexio = new ConnexioBBDDSTMT();
		$connexio->connectarBD();
		// $result = mysqli_query ($connexio, "INSERT INTO subscriptors (correu, data) VALUES ('".$correu."', CURRENT_DATE)");
		$cns = "INSERT INTO subscriptors (CORREU, DATA) VALUES (?, CURRENT_DATE)";
		$stmt=$connexio->prepare($cns);
		$stmt->bind_param("s", $correu);
		$stmt->execute();
		$stmt->fetch();
		$connexio->closeStmt();
		$connexio->desconectarBD();

		$data = new DateTime();
		$timestamp = $data->getTimestamp();

		$prisma = new Mail();
		$subject="[Sol·licitud de subscripció] núm.".$timestamp;
		$prisma->addSubject($subject);
		$prisma->addHeaders("Atenció a l'usuari", "atencio.usuari@prisma.cat", $correu);
		// $prisma->addTo('PrisMa Secretaria <suport.informatic@prisma.cat>');
		$prisma->addTo('PrisMa Atenció Usuari<atencio.usuari@prisma.cat>');
		$missatge ="<p>".$correu."</p>";
		$prisma->addMissatge($missatge);
		$prisma->sendMessage();

		$alumne = new Mail();
		$subject="Confirmació subscripció butlletí PrisMa";
		$alumne->addSubject($subject);
		$alumne->addHeaders("Atenció a l'usuari", "atencio.usuari@prisma.cat", "atencio.usuari@prisma.cat");
		$alumne->addTo($correu);
		$missatge ="
		<p>Hola,</p><p>Hem rebut una sol&middot;licitud de subscripció al nostre butlletí electrònic per a l'adreça de correu electrònic <strong>".$correu."</strong>.</p>
		<p>Pots confirmar-la <a href=https://www.prisma.cat/mailing/subscripcio.php?mail=".$correu." target=_blank>fent clic aquí</a> o bé visitant l'enllaç https://www.prisma.cat/mailing/subscripcio.php?mail=".$correu.".</p>
		<p>Si la subscripció no va adreçada a tu, simplement ignora aquest missatge.</p>
		<p>Equip PrisMa</p>";
		$missPostDiv='';
		$alumne->addMissatge($missatge);
		$alumne->sendMessage();

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
