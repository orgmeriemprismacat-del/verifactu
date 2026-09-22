<?php
session_start();
	include ('../ConnexioIntranet.php');
	include ('../ConnexioWeb.php');
	include ('../Text.php');
	include ('../Usuari.php');
	include ('../Intranet.php');
	include ('../Date.php');
	include ('../inc/missatgesError.php');

	try {
		$_SESSION['usuari'] 		= unserialize($_SESSION['usuari']);
		$_SESSION['intranet'] 	= unserialize($_SESSION['intranet']);
		$rols 						= $_SESSION['usuari']->getRols();
		$urlAct 						= $_GET['url'];

		$_SESSION['intranet']->setUrl($urlAct);

		/***********************************   CONSULTES SQL  ***********************************/

		$cnsApartatUrl	= "SELECT ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR
						   	FROM apartats WHERE URL = ? ORDER BY ORDRE";
		$cnsApartatId	= "SELECT ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR
						   	FROM apartats WHERE ID = ? ORDER BY ORDRE";

		/*********************************** FI CONSULTES SQL ***********************************/

		$connexioIntra = new ConnexioIntranet();
		$connexioIntra->connectarBD();

		if ( $stmtIntra=$connexioIntra->prepare($cnsApartatUrl) ) {
			$stmtIntra->bind_param("s", $urlAct);
			$stmtIntra->execute();
			$stmtIntra->bind_result($icona, $nom1, $nivell1, $url1, $nivellPare1, $rols1);
			$stmtIntra->fetch();
			$connexioIntra->closeStmt();
		}
		else {
			throw new Exception('', 8000);
		}

		$breadcrump = "<li class='breadcrumb-item fw-bold'>".$nom1."</li>";
		$nivells = $nivell1;

		while ($nivells>1) {
			if ( $stmtIntra=$connexioIntra->prepare($cnsApartatId) ) {
				$stmtIntra->bind_param("d", $nivellPare1);
				$stmtIntra->execute();
				$stmtIntra->bind_result($icona, $nom1, $nivell1, $url1, $nivellPare1, $rols1);
				$stmtIntra->fetch();
				$connexioIntra->closeStmt();
			}
			else {
				throw new Exception('', 8001);
			}

			$breadcrump = "<li class='breadcrumb-item'>".$nom1."</li>".$breadcrump;
			$nivells = $nivell1;
		}
		$connexioIntra->desconectarBD();

		$mostrar = "<div class='p-3'>
			<nav class='breadcrump d-flex align-items-center pb-2 border-bottom' aria-label='breadcrumb' style=\"--bs-breadcrumb-divider: '/';\">
				<button type='button' class='btn-menu d-flex text-white border-0 bg-pris align-items-center justify-content-center me-3 rounded-circle'
					data-toggle='collapse' aria-expanded='false' aria-label='Obre/tanca el menú'>
					<i class='material-icons'>more_vert</i>
				</button>
				<i class='material-icons me-3'>payment</i>
				<ol class='breadcrumb mb-0'>".$breadcrump."</ol>
			</nav>
		</div>
		<div id='content-page' class='px-3 py-2'>";
		if (!$_SESSION['usuari']->tePermisVisualitzacio($rols1)) {
			$mostrar .= "<p>No tens permisos per visualitzar aquesta pàgina.</p>";
		}
		else {
			$mostrar .= $_SESSION['intranet']->mostrarPage($_SESSION['usuari']);
		}
		$mostrar .= "</div>";

		$_SESSION['usuari'] = serialize($_SESSION['usuari']);
		$_SESSION['intranet'] = serialize($_SESSION['intranet']);

		echo $mostrar;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());
	}

 ?>
