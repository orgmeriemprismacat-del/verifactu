<?php

function missatgeError( $codiError ) {
	$error = "<div class='container no-trobat'>";
	$error .= "<img src='https://www.prisma.cat/img/error_404.png' title='Error ".$codiError."'>";
	$error .= "<h1 class='text-centrat'>Error ".$codiError."</h1>";
	$error .= "<p class='text-centrat'>Refresca la pàgina. Si segueixes tenint el mateix error, contacte amb nosaltres a partir del nostre <a href='https://www.prisma.cat/contacte' title='Contacta amb PrisMa'>formulari de contacte</a> indicant l'error per poder-te ajudar més ràpidament.</p>";
	$error .= "</div>";

	$linkPage = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	$date = new DateTime();
	$codiTime = $date->getTimestamp();

	require_once '../Mail.php';
	$mailError = new Mail();
	$mailError->addHeaders('Suport Informatic PrisMa', 'suport.informatic@prisma.cat', 'suport.informatic@prisma.cat');
	$mailError->addSubject("Error Web: ".$codiError." - ".$codiTime);
	$mailError->addTo('suport.informatic@prisma.cat');
	$mailError->addMissatge("<p><strong>Error</strong> ".$codiError."</p> <p><strong>Url</strong>: ".$linkPage."</p>");
	$mailError->sendMessage();
	if ( strpos($linkPage, "documents") !== false ) {
		$mailError->addTo('webmaster@prisma.cat');
		$mailError->sendMessage();
	}

	$csv_file = "error-log-web.txt";
	$path_file = "../logsWeb/".$csv_file;

	$errors = $date->format('d/m/Y H:i:s')."; Error. ".$codiError."; ".$linkPage."\n";;

	//Si el fitxer no existeix, generem el fitxer
	if (!$handleError = fopen($path_file, "a+")) {
		 echo "Cannot open file log-web.txt";
		 exit;
	}
	//Omplim el fitxer
	if (fwrite($handleError, utf8_decode($errors)) === FALSE) {
		 echo "Cannot write to file";
		 exit;
	}
	fclose($handleError);

	echo $error;
}

function missatgeErrorCursNoDisponbile() {
	$msgError = "El curs no està disponible actualment!";
	$msgVisit = "Visita la pàgina dels <a href='https://www.prisma.cat/cursos' title='Cursos en línia que ofereix PrisMa'>cursos en línia</a> per veure el llistat dels cursos que ofereix PrisMa.</p>";
	echo missatgeErrorNoDisponible($msgError, $msgVisit);
}

function missatgeErrorTallerNoDisponbile() {
	$msgError = "El taller no està disponible actualment!";
	$msgVisit = "Si t'interessa quan tornarà a estar disponible, posa't en <a href='https://www.prisma.cat/contacte' title='Contacte amb PrisMa'>contacte amb nosaltres</a>!</p>";
	echo missatgeErrorNoDisponible($msgError, $msgVisit);
}

function missatgeErrorTutorNoDisponbile() {
	$msgError = "El tutor no està disponible!";
	$msgVisit = "Visita la pàgina de l'<a href='https://www.prisma.cat/docents' title='Tutors dels cursos on-line de PrisMa'>equip docent</a> per visualitzar el llistat dels tutors dels cursos en línia que ofereix PrisMa.</p>";
	echo missatgeErrorNoDisponible($msgError, $msgVisit);
}

function missatgeErrorNoDisponible($msgError, $msgVisit) {
	$error = "<div class='container no-trobat'><img title='".$msgError."' ";
	$error .= "src='https://www.prisma.cat/img/error_404.png'>";
	$error .= "<h1 class='text-centrat'>".$msgError."</h1><p class='text-centrat'>";
	$error .= "Pot ser que la pàgina que estàs sol·licitant hagi deixat d'existir o bé que estigui pendent d'actualització.</p>";
	$error .= "<p class='text-centrat'>".$msgVisit."</p></div>";

	$linkPage = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	$date = new DateTime();
	$codiTime = $date->getTimestamp();

	$csv_file = "error-log-web.txt";
	$path_file = "../logsWeb/".$csv_file;

	$errors = $date->format('d/m/Y H:i:s')."; Warning. ".utf8_encode($msgError)."; ".$linkPage."\n";;

	//Si el fitxer no existeix, generem el fitxer
	if (!$handleError = fopen($path_file, "a+")) {
		 echo "Cannot open file log-web.txt";
		 exit;
	}
	//Omplim el fitxer
	if (fwrite($handleError, utf8_decode($errors)) === FALSE) {
		 echo "Cannot write to file";
		 exit;
	}
	fclose($handleError);

	return $error;
}

function missatgeErrorPagament($msgError) {
	$error = "<div class='container no-trobat'><img title='".$msgError."' ";
	$error .= "src='https://www.prisma.cat/img/error_404.png'>";
	$error .= "<h1 class='text-centrat'>".$msgError."</h1><p class='text-centrat'>";
	$error .= "Hi ha hagut un error amb la pàgina que estàs sol·licitant.</p>";
	$error .= "<p class='text-centrat'>Torna a la pàgina del pagament d'inscripció per tornar-ho a intentar.</p></div>";

	$linkPage = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	$date = new DateTime();
	$codiTime = $date->getTimestamp();

	$csv_file = "error-log-web.txt";
	$path_file = "../logsWeb/".$csv_file;

	$errors = $date->format('d/m/Y H:i:s')."; Error. ".utf8_encode($msgError)."; ".$linkPage."\n";;

	//Si el fitxer no existeix, generem el fitxer
	if (!$handleError = fopen($path_file, "a+")) {
		 echo "Cannot open file log-web.txt";
		 exit;
	}
	//Omplim el fitxer
	if (fwrite($handleError, utf8_decode($errors)) === FALSE) {
		 echo "Cannot write to file";
		 exit;
	}
	fclose($handleError);

	return $error;
}

function mostrarPagina404() {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$consultaId404 = "SELECT ID FROM amigable WHERE URL=?";
	$consultaIdCnt404 = "SELECT ID_CONTINGUT FROM pagina WHERE ID_URL=? AND ESTAT=1";
	$consultaCnt404 = "SELECT CONTINGUT FROM contingut WHERE ID=?";

	$stmtId404 = $connexio->prepare($consultaId404);
	$stmtId404->bind_param("s", $url);
	$url='/404';
	$stmtId404->execute();
	$stmtId404->bind_result($idUrl);
	$stmtId404->fetch();
	$connexio->closeStmt();

	$stmtIdCnt404 = $connexio->prepare($consultaIdCnt404);
	$stmtIdCnt404->bind_param("d", $idUrl);
	$stmtIdCnt404->execute();
	$stmtIdCnt404->bind_result($idCnt);
	$stmtIdCnt404->fetch();
	$connexio->closeStmt();

	$stmtCnt404 = $connexio->prepare($consultaCnt404);
	$stmtCnt404->bind_param("d", $idCnt);
	$stmtCnt404->execute();
	$stmtCnt404->bind_result($cnt404);
	$stmtCnt404->fetch();
	$connexio->closeStmt();

	$connexio->desconectarBD();

	$linkPage = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	$date = new DateTime();
	$codiTime = $date->getTimestamp();

	$csv_file = "error-log-web.txt";
	$path_file = "../logsWeb/".$csv_file;

	$errors = $date->format('d/m/Y H:i:s')."; Error. 404; ".$linkPage."\n";

	//Si el fitxer no existeix, generem el fitxer
	if (!$handleError = fopen($path_file, "a+")) {
		 echo "Cannot open file log-web.txt";
		 exit;
	}
	//Omplim el fitxer
	if (fwrite($handleError, utf8_decode($errors)) === FALSE) {
		 echo "Cannot write to file";
		 exit;
	}
	fclose($handleError);

	return $cnt404;
}

function mostrarPagina302() {
	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$consultaId302 = "SELECT ID FROM amigable WHERE URL=?";
	$consultaIdCnt302 = "SELECT ID_CONTINGUT FROM pagina WHERE ID_URL=? AND ESTAT=1";
	$consultaCnt302 = "SELECT CONTINGUT FROM contingut WHERE ID=?";

	$stmtId302 = $connexio->prepare($consultaId302);
	$stmtId302->bind_param("s", $url);
	$url='/302';
	$stmtId302->execute();
	$stmtId302->bind_result($idUrl);
	$stmtId302->fetch();
	$connexio->closeStmt();

	$stmtIdCnt302 = $connexio->prepare($consultaIdCnt302);
	$stmtIdCnt302->bind_param("d", $idUrl);
	$stmtIdCnt302->execute();
	$stmtIdCnt302->bind_result($idCnt);
	$stmtIdCnt302->fetch();
	$connexio->closeStmt();

	$stmtCnt302 = $connexio->prepare($consultaCnt302);
	$stmtCnt302->bind_param("d", $idCnt);
	$stmtCnt302->execute();
	$stmtCnt302->bind_result($cnt302);
	$stmtCnt302->fetch();
	$connexio->closeStmt();

	$connexio->desconectarBD();

	$linkPage = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

	$date = new DateTime();
	$codiTime = $date->getTimestamp();

	$csv_file = "error-log-web.txt";
	$path_file = "../logsWeb/".$csv_file;

	$errors = $date->format('d/m/Y H:i:s')."; Error. 302; ".$linkPage."\n";

	//Si el fitxer no existeix, generem el fitxer
	if (!$handleError = fopen($path_file, "a+")) {
		 echo "Cannot open file log-web.txt";
		 exit;
	}
	//Omplim el fitxer
	if (fwrite($handleError, utf8_decode($errors)) === FALSE) {
		 echo "Cannot write to file";
		 exit;
	}
	fclose($handleError);

	return $cnt302;
}
?>
