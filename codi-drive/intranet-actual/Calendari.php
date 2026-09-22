<?php

/**
	* @class Calendari
	* @brief Conté totes les funcionalitats del calendari
*/
class Calendari
{
	private $client;

	/*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @return Crees un Usuari buit
   */
	public function __construct()	{
		$this->client 	= null;
	}

	/*
   * @brief Afegir Client de Google $client
   * @return Afegir Client de Google $client
   */
	public function setClient() {
		$this->client = new Google_Client();
		$this->client->setApplicationName('Google Calendar API');
		$this->client->setScopes(Google_Service_Calendar::CALENDAR_READONLY);
		$this->client->setAuthConfig('../fitxers/auth/client_secret_1012851982408-eljcsv49bs1hfl5l64s3inah23pg7caf.apps.googleusercontent.com.json');
		$this->client->setRedirectUri('https://' . $_SERVER['HTTP_HOST'] .'/intranet/auth/authGoogleScopes.php');
		$this->client->setAccessType('offline');
		$this->client->setApprovalPrompt('force');
	}

	public function isAccessTokenExpired() {
		return $this->client->isAccessTokenExpired();
	}
	public function createAuthUrl() {
		return $this->client->createAuthUrl();
	}
	public function getAccessToken() {
		return $this->client->getAccessToken();
	}
	public function fetchAccessTokenWithAuthCode($authCode) {
		return $this->client->fetchAccessTokenWithAuthCode($authCode);
	}
	public function setAccessToken($accessToken) {
		return $this->client->setAccessToken($accessToken);
	}
}

?>
