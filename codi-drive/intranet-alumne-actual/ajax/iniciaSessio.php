<?php
	include ('../ConnexioIntranet.php');
	include ('../ConnexioWeb.php');
	include ('../Text.php');
	include ('../Usuari.php');
	include ('../IntranetAlumne.php');
	include ('../inc/missatgesError.php');
	require('../../config.php');

	session_start();

	try {
		if ( isloggedin() ) {

			$usuari = $USER->username;
			$passHash = $USER->password;
			// $password = $SESSION['password'];
			$idUser = $USER->id;
			$nom = $USER->firstname;
			$cognoms = $USER->lastname;
			$email = $USER->email;
			$city = $USER->city;
			$urlPicture = "https://www.prisma.cat/campus/user/pix.php".$USER->picture."/f1.jpg";

			echo $usuari."<br />";
			echo $passHash."<br />";
			echo $nom."<br />";
			echo $cognoms."<br />";
			echo $idUser."<br />";
			echo $email."<br />";
			echo $city."<br />";
			echo $USER->picture."<br />";

			$rols = "1";

			//Buscar a la BD, si existeix el usuari $usuari i assignar-li el seu rol.

			//Si el usuari és un developer
			if ( $usuari == '77922662' ) $rols .= "|3";
			//Si el usuari és un admin
			if ( $usuari == '77922662' ) $rols .= "|1";

			$objUsuari = new Usuari();
			$objUsuari->setUsuari($usuari);
			$objUsuari->setHashPass($passHash);
			$objUsuari->setPass($password);
			$objUsuari->setNom($nom);
			$objUsuari->setCognoms($cognoms);
			$objUsuari->setIdUser($idUser);
			$objUsuari->setEmail($email);
			$objUsuari->setCity($city);
			$objUsuari->setIdPicture($USER->picture);
			$objUsuari->setRols($rols);

			// if ( $esUnUserProva == 1 )
			// 	$objIntranet = new IntranetAlumneProva( serialize($objUsuari) );
			// else
			// $objIntranet = new IntranetAlumne( serialize($objUsuari) );

			// $_SESSION['usuari'] = serialize($objUsuari);
			// $_SESSION['intranet'] = serialize($objIntranet);

			$_SESSION['usuariMdl'] = $usuari;
			$_SESSION['objUsuariMdl'] = serialize($objUsuari);

			$objIntranet = new IntranetAlumne();
			$objIntranet->setUsuari(serialize($objUsuari));

			$_SESSION['objIntranetAlumne'] = serialize($objIntranet);

			print_r($_SESSION);

			echo "success";
		}
		else {
		}
	}
	catch(Exception $e) {
	   echo missatgeError($e->getCode());
	}
?>
