<?php

	include ('../ConnexioIntranet.php');
	include ('../ConnexioWeb.php');
	include ('../Text.php');
	include ('../Usuari.php');
	include ('../Intranet.php');
	include ('../Calendari.php');
	include ('../inc/missatgesError.php');

	session_start();

	try {
		require_once '../lib/google-api-php-client/vendor/autoload.php';

		$_SESSION['usuari'] 		= unserialize($_SESSION['usuari']);
		$_SESSION['intranet'] 	= unserialize($_SESSION['intranet']);

		// $clientPath = 'client-'.$_SESSION['usuari']->getUsuari()->get().'.json';
		// if ( !file_exists($clientPath) ){
		// 	$client = new Calendari();
		// 	$client->setClient();
		// }
		// else {
		// 	$serializeClient = json_decode(file_get_contents($clientPath), true);
		// 	$client 	= unserialize($serializeClient);
		// }
			$client = new Calendari();
			$client->setClient();
		$tokenPath = 'token-'.$_SESSION['usuari']->getUsuari()->get().'.json';
		// if ( file_exists($tokenPath) && !$client->isAccessTokenExpired() ) {
		if ( file_exists($tokenPath) ) {
			$accessToken = json_decode(file_get_contents($tokenPath), true);
			$client->setAccessToken($accessToken);
			// file_put_contents($clientPath, json_encode(serialize($client)));
			// $_SESSION['googleClientCalendar'] 	= serialize($client);
		}
		else {
			if ( isset($_GET['code']) ) {
				$authCode = $_GET['code'];
				$accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
				$client->setAccessToken($accessToken);
				if (!file_exists(dirname($tokenPath))) {
					mkdir(dirname($tokenPath), 0700, true);
				}
				$accessNewToken = $client->getAccessToken();
				$accessNewToken['expires_in'] = 186624000;
				file_put_contents($tokenPath, json_encode($accessNewToken));
				// file_put_contents($clientPath, json_encode(serialize($client)));
				// $_SESSION['googleClientCalendar'] 	= serialize($client);
				$_SESSION['googleClientCode'] 	= serialize($authCode);


			}
			else {
				$_SESSION['usuari'] = serialize($_SESSION['usuari']);
				$_SESSION['intranet'] = serialize($_SESSION['intranet']);

				if ( !isset($_GET['cns']) ) {
					$authUrl = $client->createAuthUrl();

					header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
				}
				exit('No code found');
			}
		}

		$_SESSION['usuari'] = serialize($_SESSION['usuari']);
		$_SESSION['intranet'] = serialize($_SESSION['intranet']);

		if ( !isset($_GET['cns']) ) {
			header('Location: ' . filter_var("https://intranet.prisma.catinici/", FILTER_SANITIZE_URL));
		}
		else {
			echo "1";
			// $serializeClient = serialize($client);
			// file_put_contents($clientPath, json_encode($serializeClient));
			// $_SESSION['googleClientCalendar'] 	= serialize($client);

		}
		// $_SESSION['googleClient'] = serialize($client);
	}
	catch(Exception $e) {
	   echo missatgeError($e->getCode());
	}
?>
