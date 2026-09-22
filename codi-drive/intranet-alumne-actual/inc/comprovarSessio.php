<?php
	include ('ConnexioIntranet.php'); //BD alumnes
	include ('ConnexioWeb.php'); //BD cursos
	include ('ConnexioMoodle.php'); //BD darrer
	include ('ConnexioMoodleAntic.php'); //BD muser
	include ('IntranetAlumne.php');
	include ('IntranetAlumneProva.php');
	include ('Text.php');
	include ('Usuari.php');
	include ('inc/missatgesError.php');

	session_start();

	$configOk = 0;

	try {
		$objUsuari = unserialize( $_SESSION['objUsuariMdl'] );
		$objIntranetAlumne = unserialize( $_SESSION['objIntranetAlumne'] );
		if ( isloggedin() ) {
			if ( $objUsuari ) {
				$usuari = '';
				$password = '';
				$nom = '';
				$cognoms = '';
				$idUser = '';
				$email = '';
				$city = '';
				$idPicture = '';
				if ( $objUsuari->getUsuari() )
				if ( $objUsuari->getUsuari() )
				 	$usuari = $objUsuari->getUsuari()->get();
				if ( $objUsuari->getPass() )
				 	$password = $objUsuari->getPass()->get();
				if ( $objUsuari->getNom() )
				 	$nom = $objUsuari->getNom()->get();
				if ( $objUsuari->getCognom() )
				 	$cognoms = $objUsuari->getCognom()->get();
				if ( $objUsuari->getIdUser() )
				 	$idUser = $objUsuari->getIdUser();
				if ( $objUsuari->getEmail() )
				 	$email = $objUsuari->getEmail()->get();
				if ( $objUsuari->getCity() )
				 	$city = $objUsuari->getCity()->get();
				if ( $objUsuari->getIdPicture() )
				 	$idPicture = $objUsuari->getIdPicture();

				// echo $usuari."<br>";
				// echo $password."<br>";
				// echo $nom."<br>";
				// echo $cognoms."<br>";
				// echo $idUser."<br>";
				// echo $email."<br>";
				// echo $city."<br>";
				// echo $idPicture."<br>";

				// $_SESSION['usuari'] = serialize($objUsuari);
				// $_SESSION['intranet'] = serialize($objIntranet);
				$_SESSION['objUsuariMdl'] = serialize($objUsuari);
				$_SESSION['objIntranetAlumne'] = serialize($objIntranetAlumne);
				$configOk = 1;
			}
			else {
				$usuari = $USER->username;
				$passHash = $USER->password;
				// $password = $SESSION['password'];
				$idUser = $USER->id;
				$nom = $USER->firstname;
				$cognoms = $USER->lastname;
				$email = $USER->email;
				$city = $USER->city;
				$urlPicture = "https://www.prisma.cat/campus/user/pix.php".$USER->picture."/f1.jpg";

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

				$_SESSION['usuariMdl'] = $usuari;
				$_SESSION['objUsuariMdl'] = serialize($objUsuari);

				$objIntranet = new IntranetAlumne();
				$objIntranet->setUsuari(serialize($objUsuari));

				$_SESSION['objIntranetAlumne'] = serialize($objIntranet);
				$configOk = 1;
			}
		}
		else {
			$configOk = 0;
			session_unset();
		}

	}
	catch(Exception $e) {
	   echo missatgeError($e->getCode());
	}
?>
