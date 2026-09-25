<?php
require('../../../config.php');
	include ('../../ConnexioIntranetTutor.php');
	include ('../../ConnexioWeb.php');
	include ('../../Text.php');
	include ('../../IntranetTutor.php');
	include ('../../IntranetTutorProva.php');
	include ('../inc/missatgesError.php');

	try {
		$urlAct 						= $_GET['url'];
		$mdlUsername 				= $_GET['mdlUsername'];

		if ( substr( $urlAct, -1 ) != '/' )
			$urlAct = $urlAct."/";

		$objIntranet = new IntranetTutor();

		if ( $username == '77922662' )
			$objIntranet = new IntranetTutorProva();
		$objIntranet->setUrl($urlAct);

		/***********************************   CONSULTES SQL  ***********************************/

		$cnsApartatUrl	= "SELECT ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR
						   				FROM apartats_tutor WHERE URL = ? ORDER BY ORDRE";
		$cnsApartatId	= "SELECT ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR
						   				FROM apartats_tutor WHERE ID = ? ORDER BY ORDRE";

		/*********************************** FI CONSULTES SQL ***********************************/

		$connexioIntra = new ConnexioIntranetTutor();
		$connexioIntra->connectarBD();

		if ( $stmtIntra=$connexioIntra->prepare($cnsApartatUrl) ) {
			$stmtIntra->bind_param("s", $urlAct);
			$stmtIntra->execute();
			$stmtIntra->bind_result($icona, $nom1, $nivell1, $url1, $nivellPare1, $rols1);
			$stmtIntra->fetch();
			$connexioIntra->closeStmt();
		}
		else {
			throw new Exception('', 5002);
		}

		$breadcrump = "<span>".$nom1."</span>";
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

			$breadcrump = "<span>".$nom1."</span><span class='mx-2'>/</span>".$breadcrump;
			$nivells = $nivell1;
		}
		$connexioIntra->desconectarBD();

		$breadcrump = $icona."<span class='mr-2'></span><div>".$breadcrump."</div>";

		$mostrar = "<div class='p-3'>
			<div class='breadcrump d-flex flex-column flex-sm-row align-items-sm-center pb-2 border-bottom'>
				<div class='d-flex flex-wrap flex-row mb-2 mb-sm-0 flex-1-0-auto'>
					<button type='button' class='btn-menu
						d-flex text-white border-0 bg-tutor align-items-center
						justify-content-center mr-3 rounded-circle' data-toggle='collapse'
						aria-expanded='false' aria-label='Obre/tanca el menú'>
						<i class='material-icons'>more_vert</i>
					</button>
					<h1 class='d-flex align-items-center'>".$breadcrump."</h1>
				</div>

				<div class='logout d-flex justify-content-sm-center pointer'>
					Torna al Campús Virtual
					<i class='material-icons ml-2'>logout</i>
				</div>
			</div>
		</div>
		<div id='content-page' class='px-3 py-2'>";

		$i=0;
		$tePermis = 0;
		$vectRols = "4";
		$vectRolsPage = explode('|',$rols1);
		while ($i<count($vectRolsPage) && !$tePermis) {
			$j = 0;
			while ($j<count($vectRols) && !$tePermis) {
				if ($vectRols[$j]==$vectRolsPage[$i]) $tePermis=1;
				$j++;
			}
			$i++;
		}

		$nom = $USER->firstname;
		$idPicture = $USER->picture;
		$idUser = $USER->id;

		if ( !$tePermis ) {
			$mostrar .= "<p>No tens permisos per visualitzar aquesta pàgina.</p>";
		}
		else {
			$mostrar .= $objIntranet->mostrarPage($nom, $idPicture, $idUser, $mdlUsername);
		}
		$mostrar .= "</div>";

		echo $mostrar;

	}
	catch(Exception $e) {
		echo missatgeError($e->getCode());
	}

 ?>
