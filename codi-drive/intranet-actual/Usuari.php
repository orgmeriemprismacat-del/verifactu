<?php

	/**
		* @class Usuari
		* @brief Conté tota la informació d'un usuari
	*/
	class Usuari
	{
		private $usuari;
		private $hashPasword;
		private $nom;
		private $cognoms;
		private $departament;
		private $rols;
		private $menuExt;

		/*********************************** FUNCIONS CONSTRUCTORS ***********************************/
	   /*
	   * @brief Constructor de la classe.
	   * @return Crees un Usuari buit
	   */
		public function __construct()	{
			$this->usuari = null;
			$this->hashPasword = null;
			$this->nom = null;
			$this->cognoms = null;
			$this->departament = null;
			$this->rols = null;
			$this->menuExt = null;
		}

		/*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
		/**
	   * @brief Mostra l'usuari
	   * @return Mostra l'usuari
	   */
		function getUsuari() {
			return $this->usuari;
		}

		/**
	   * @brief Mostra el password amb codificació hash
	   * @return Mostra el password amb codificació hash
	   */
		function getHashPass() {
			return $this->hashPasword;
		}

		/**
	   * @brief Mostra el menuExt
	   * @return Mostra el menuExt
	   */
		function getMenuExt() {
			return $this->menuExt;
		}

		/**
	   * @brief Mostra el nom
	   * @return Mostra el nom
	   */
		function getNom() {
			return $this->nom;
		}

		/**
	   * @brief Mostra els cognoms
	   * @return Mostra els cognoms
	   */
		function getCognom() {
			return $this->cognoms;
		}

		/**
	   * @brief Mostra el departament
	   * @return Mostra el departament
	   */
		function getDepart() {
			return $this->departament;
		}

		/**
	   * @brief Mostra l'array dels rols de l'usuari
	   * @return Mostra l'array dels rols de l'usuari
	   */
		function getRols() {
			return $this->rols;
		}

		/**
	   * @brief Mostra si l'usuari té algun rol igual a $rolsPage
	   * @return Mostra si l'usuari té algun rol igual a $rolsPage
	   */
		function tePermisVisualitzacio($rolsPage) {
			$i=0;
			$tePermis = 0;
			$vectRols = $this->rols;
			$vectRolsPage = explode('|',$rolsPage);
			while ($i<count($vectRolsPage) && !$tePermis) {
				$j = 0;
				while ($j<count($vectRols) && !$tePermis) {
					if ($vectRols[$j]==$vectRolsPage[$i]) $tePermis=1;
					$j++;
				}
				$i++;
			}
			return $tePermis;
		}

		/*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
		/**
	   * @brief Afegeix l'usuari
	   * @return Afegeix l'usuari
	   */
		function setUsuari( $nom ) {
			if ($nom != null && $nom != '')
				$this->usuari = new Text($nom);
		}

		/**
	   * @brief Afegeix el password amb codificació hash
	   * @return Afegeix el password amb codificació hash
	   */
		function setHashPass( $password ) {
			if ($password != null && $password != '')
				$this->hashPasword = new Text($password);
		}

		/**
	   * @brief Afegeix el nom
	   * @return Afegeix el nom
	   */
		function setMenuExt( $menuExt ) {
			$this->menuExt = $menuExt;
		}

		/**
	   * @brief Afegeix el nom
	   * @return Afegeix el nom
	   */
		function setNom( $nom ) {
			if ($nom != null && $nom != '')
				$this->nom = new Text($nom);
		}

		/**
	   * @brief Afegeix el cognoms
	   * @return Afegeix el cognoms
	   */
		function setCognoms( $cognoms ) {
			if ($cognoms != null && $cognoms != '')
				$this->cognoms = new Text($cognoms);
		}

		/**
	   * @brief Afegeix el departament
	   * @return Afegeix el departament
	   */
		function setDepartament( $departament ) {
			if ($departament != null && $departament != '')
				$this->departament = new Text($departament);
		}

		/**
	   * @brief Afegeix els rols de l'usuari
	   * @return Afegeix els rols de l'usuari
	   */
		function setRols( $rols ) {
			if ($rols != null && $rols != '') {
				$vectRols = explode('|',$rols);
				for ($i=0; $i<count($vectRols); $i++) {
					$j = 0; $trobat = false;
					if ( $this->rols == null ) $numRols = 0;
					else $numRols = count($this->rols);
					while ($j < $numRols && !$trobat) {
						if ( $this->rols[$j] == $vectRols[$i] ) $trobat=true;
						$j++;
					}
					if ( !$trobat )
						$this->rols[] =  $vectRols[$i];
				}
			}
		}
	}

?>
