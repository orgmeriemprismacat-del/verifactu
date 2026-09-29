<?php
require('../../../config.php');

include ('../../ConnexioWeb.php');
include ('../../Text.php');
include ('../inc/missatgesError.php');

try {
	$usuari = $USER->username;

	$connexioWeb = new ConnexioWeb();
	$connexioWeb->connectarBD();

	$cnsInfoInsc = "SELECT ID, NOM, COGNOMS, CORREU, DNI, ADRECA,
	         Codi_Postal, Poblacio, PERFIL, Titulacio, TELEFON,
	         ANY, MES, CURS, `INSC CURS`, A_PAGAR, IDPAG FROM inscripcions
	         WHERE USUARI = ? AND USUARI != 0 AND UPPER(`INSC CURS`) != 'D' ORDER BY DATA_INSC DESC LIMIT ?";

	$cnsSubscrit = "SELECT mail FROM mailing WHERE mail=?";

	$stmt=$connexioWeb->prepare($cnsInfoInsc);
	$stmt->bind_param("dd", $usuari, $limit);
	$limit = '1';
	$stmt->execute();
	$stmt->bind_result($idInsc, $nom, $cog, $email, $dni, $adreca, $cp, $poble, $perfil, $titulacio, $tel, $any, $mes, $curs, $inscrit, $apagar, $idPag );
	$stmt->fetch();
	$connexioWeb->closeStmt();

	$stmt=$connexioWeb->prepare($cnsSubscrit);
	$stmt->bind_param("s", $email);
	$stmt->execute();
	$stmt->store_result();
	if ($stmt->num_rows()<=0) {
		$noSubscribe = 1;
	}
	else {
		$noSubscribe = 0;
	}

	$connexioWeb->closeStmt();
	$connexioWeb->desconectarBD();

	$cntEmail = "";
	if ( $noSubscribe || $email == 'merimari051094@gmail.com' ) {
		$cntEmail = "<div class='cnt-butlleti pt-3 w-100 pt-3'>
			<p class='text-center fs-3 mt'>Estigues al dia dels nostres cursos i serveis!</p>
			<div class='d-flex flex-column justify-content-around flex-md-row w-100'>
				<div class='form-group d-flex field-wrap position-relative mb-0 py-1 px-md-1 w-100 '>
					<input type='text' class='form-control pl-2 mb-0 pb-0' id='email-subsc' name='email-subsc' value='".$email."'>
					<button aria-label='Newsletter' class='news my-0 py-0' id='butlleti-news'>
					<i class='fa fa-envelope' aria-hidden='true'></i>
					</button>
				</div>
			</div>
		</div>";
	}

	$cntSocial = "<div class='cnt-xarxes pt-3 w-100'>
		<p class='text-center fs-3 mt'>Segueix-nos a les xarxes!</p>
		<div class='d-flex flex-column justify-content-around align-items-center w-100'>
			<a class='d-flex align-items-center justify-content-center' aria-label='Facebook' role='link' rel='noopener' title='Facebook de PrisMa' href='https://www.facebook.com/PrisMaFormacio' target='_blank'>
				<i class='fab fa-facebook-f peu' aria-hidden='true'></i>
			</a>
			<a class='d-flex align-items-center justify-content-center' aria-label='Twitter' role='link' rel='noopener' title='Twitter de PrisMa' href='https://twitter.com/PrisMaFormacio' target='_blank'>
				<i class='fab fa-twitter peu' aria-hidden='true'></i>
			</a>
			<a class='d-flex align-items-center justify-content-center' aria-label='Youtube' role='link' rel='noopener' title='Youtube de PrisMa' href='https://www.youtube.com/user/AssociacioPrisMa' target='_blank'>
				<i class='fab fa-youtube peu' aria-hidden='true'></i>
			</a>
			<a class='d-flex align-items-center justify-content-center' aria-label='Instagram' role='link' rel='noopener' title='Instagram de PrisMa' href='https://www.instagram.com/prisma.formacio/' target='_blank'>
				<i class='fab fa-instagram peu' aria-hidden='true'></i>
			</a>
		</div>
	</div>";
	$cntSocial = "<div class='cnt-xarxes pt-3 w-100'>
		<p class='text-center fs-3 mt'>Segueix-nos a les xarxes!</p>
		<div class='d-flex flex-column justify-content-around align-items-center w-100'>
			<a class='d-flex align-items-center justify-content-center' aria-label='Facebook' role='link' rel='noopener' title='Facebook de PrisMa' href='https://www.facebook.com/PrisMaFormacio' target='_blank'>
				<i class='fab fa-facebook-f peu' aria-hidden='true'></i>
			</a>
			<a class='d-flex align-items-center justify-content-center' aria-label='Twitter' role='link' rel='noopener' title='Twitter de PrisMa' href='https://twitter.com/PrisMaFormacio' target='_blank'>
				<i class='fab fa-twitter peu' aria-hidden='true'></i>
			</a>
			<a class='d-flex align-items-center justify-content-center' aria-label='Youtube' role='link' rel='noopener' title='Youtube de PrisMa' href='https://www.youtube.com/user/AssociacioPrisMa' target='_blank'>
				<i class='fab fa-youtube peu' aria-hidden='true'></i>
			</a>
			<a class='d-flex align-items-center justify-content-center' aria-label='Instagram' role='link' rel='noopener' title='Instagram de PrisMa' href='https://www.instagram.com/prisma.educacio/' target='_blank'>
				<i class='fab fa-instagram peu' aria-hidden='true'></i>
			</a>
			<div class='d-flex align-items-center justify-content-center'>
				<a class='d-flex align-items-center justify-content-center' aria-label='Tiktok' role='link' rel='noopener' title='Tiktok de PrisMa' href='https://www.tiktok.com/@prisma.educacio' target='_blank'>
					<i class='fa-brands fa-tiktok peu' aria-hidden='true'></i>
				</a>
				<span id='insta-nou' class='erroni position-relative text-center text-white ml-1'>NOU</span>
			</div>
		</div>
	</div>";

	// $cntReward = "<div class='cnt-ressenya d-flex flex-column justify-content-center align-items-center mt-3'>
   //    <p class='text-center fs-3 mt'>Estigues al dia dels nostres cursos i serveis!</p>
   //    <button role='button' class='boto-blau px-4 d-flex border-0'>Comparteix<i class='material-icons ml-2'>share</i></button>
   // </div>";

	$cntPromocio = "<div class='promocio d-flex flex-column justify-content-center align-items-center w-100 mt-2 pt-2 px-3'>
		".$cntSocial."
		".$cntReward."
	</div>";

	$mostrar .= $cntPromocio;
}
catch(Exception $e) {
	echo missatgeError($e->getCode());
}

echo $mostrar;

 ?>
