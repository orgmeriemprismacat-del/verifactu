<?php
	/**
		* @class Date
		* @brief Conté totes les operacions que pots realitzar amb una data
	*/
	class Date
	{
		private $date;
		private $nomMesos;

		/*********************************** FUNCIONS CONSTRUCTORS ***********************************/
	   /*
	   * @brief Constructor de la classe.
	   * @return Crees un Date buit
	   */
		public function __construct( $text )	{
			if ($text=='')
	         throw new Exception('',7000);

	      $this->date = $text;

			$this->nomMesos = [
				"01" 	=> "gener",
				"02" 	=> "febrer",
				"03" 	=> "març",
				"04" 	=> "abril",
				"05" 	=> "maig",
				"06" 	=> "juny",
				"07" 	=> "juliol",
				"08" 	=> "agost",
				"09" 	=> "setembre",
				"10" 	=> "octubre",
				"11" 	=> "novembre",
				"12" 	=> "desembre",
			];
		}

		/*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
		/*
	   * @brief Obtenir la data
	   * @return La data sencera
	   */
	   public function get() {
	      return $this->date;
	   }

		/*
	   * @brief Obtens el dia de la setmana de la data entrada
	   * @return Obtens el dia de la setmana "Dissabte" a partir de la data en format "2019-07-01"
	   */
	   public function getDiaSetmana() {
	      $dies = array("Diumenge","Dilluns","Dimarts","Dimecres","Dijous","Divendres","Dissabte");
	      $diaSetmana = $dies[date('N',strtotime($this->date))];
	      return $diaSetmana;
	   }

		/**
	   * @brief Mostra el nom del mes de la data introduida
	   * @return Mostra el nom del mes de la data introduida, ex. 'gener'
	   */
		public function getNomMes() {
			$date = new DateTime( $this->date );
			$mes = date_format($date, 'm');

			return $this->nomMesos[$mes];
		}

		/**
	   * @brief Mostra Mostra el nom del mes de la data introduida amb l'article de
	   * @return Mostra Mostra el nom del mes de la data introduida amb l'article de
		* o d' segons si el nom del mes comença per vocal o no. ex. 'de gener'
	   */
		public function getNomMesArticle() {
			$date = new DateTime( $this->date );
			$mes = date_format($date, 'm');

			$nomMes = "de ";
	      if ( ($mes == '04') or ($mes == '08') or ($mes == '10') )
	         $nomMes = "d'";

			$nomMes .= $this->getNomMes();

			return $nomMes;
		}

		/*
	   * @brief Obtens la data en format llarg
	   * @return Converteix la data en format 1 de juliol del 2019
	   */
		public function getDataLlarga() {
			$date = new DateTime( $this->date );
			$any = date_format($date, 'Y');
			$mes = date_format($date, 'm');
			$dia = date_format($date, 'd');

	      $data = intval( $dia )." ".$this->getNomMesArticle()." del ".$any;

	      return $data;
		}

		/*
	   * @brief Obtens la data en format DD/MM/YYYY
	   * @return Converteix la data en format dia/mes/any (DD/MM/YYY)
	   */
		public function getDataFomatDDMMYYYY() {
			$date = new DateTime( $this->date );
			$any = date_format($date, 'Y');
			$mes = date_format($date, 'm');
			$dia = date_format($date, 'd');

	      $data = $dia."/".$mes."/".$any;

	      return $data;
		}

		/*
	   * @brief Obtens la data en format DD/MM/YYYY HH:MM:SS si el text creat té el format
		YYYY-MM-DD HH:MM:SS or YYYY-MM-DD HH:MM or YYYY-MM-DD
	   * @return Converteix la data en format dia/mes/any (DD/MM/YYY HH:MM:SS)
	   */
		public function getDataFomatDDMMYYYY_HHMMSS() {
			$date = new DateTime( $this->date );
			$data = date_format($date, 'd/m/Y H:i:s');
			if ( date_format($date, 'H:i:s') == "00:00:00" ) {
				$data = date_format($date, 'd/m/Y');
			}

	      return $data;
		}

		/*
		* @brief Obtens la data en format YYYY-MM-DD HH:MM:SS si el text creat té el format
		DD/MM/YYYY HH:MM:SS or DD/MM/YYYY HH:MM or DD/MM/YYYY
		* @return Converteix la data en format any/mes/dia (YYYY-MM-DD HH:MM:SS)
		*/
		public function getDataFomatYYYYMMDD_HHMMSS() {
			$vectDataHora = explode(' ',$this->date);
			$data = "";
			if ( count($vectDataHora) > 0 ) {
				$vectData = explode('/',$vectDataHora[0]);
				if ( count($vectData) > 0 && count($vectData) == 3 &&
				strlen($vectData[0]) == 2 && strlen($vectData[1]) == 2 && strlen($vectData[2]) == 4) {
					$data .= $vectData[2]."-".$vectData[1]."-".$vectData[0];
				}
				if ( count($vectDataHora) > 1) {
					$vectHora = explode(':', $vectDataHora[1]);
					$data .= " ";
					if ( count($vectHora) > 0 && (count($vectHora)==2 or count($vectHora)==3) &&
					strlen($vectHora[0]) == 2 && strlen($vectHora[1]) == 2 && strlen($vectHora[2]) == 2) {
						$data .= $vectHora[0].":".$vectHora[1].":";
						if ( count($vectHora)==3 )
							$data .= $vectHora[2];
					}
				}
			}
			else {
				throw new Exception('', 4145);
			}

	      return $data;
		}

		/*
		* @brief Obtens l'article l' o l segons el dia de la data
		* @return Si el dia és 1 o 11, obtens l'article l'. Altrament retorna l
		*/
		public function getArticlel() {
			$dia = $this->getDia();

			$textl = "l ";
			if ( $dia == '1' or $dia == '11' ) {
				$textl = " l'";
			}

			return $textl;
		}

		/*
		* @brief Obtens el pronom de «l'» o «del» segons el dia de la data
		* @return Si el dia és 1 o 11, obtens el pronom de «l'». Altrament retorna «del»
		*/
		public function getPronomDel() {
			return "de".$this->getArticlel();
		}

		/*
		* @brief Obtens el pronom de «l'» o «el» segons el dia de la data
		* @return Si el dia és 1 o 11, obtens el pronom de «l'». Altrament retorna «el»
		*/
		public function getPronomEl() {
			$dia = $this->getDia();

			$textl = "el ";
			if ( $dia == '1' or $dia == '11' ) {
				$textl = "l'";
			}
			return $textl;
		}

		/*
		* @brief Obtens el pronom «a l'» o «al» segons el dia de la data
		* @return Si el dia és 1 o 11, obtens el pronom «a l'». Altrament retorna «al»
		*/
		public function getPronomAl() {
			return "a".$this->getArticlel();
		}

		/**
	   * @brief Mostra l'any de la data introduïda.
	   * @return Mostra l'any de la data introduïda.
	   */
		public function getAny() {
			$date = new DateTime( $this->date );
			$any = date_format($date, 'Y');
			return $any;
		}

		/**
	   * @brief Mostra el mes de la data introduïda.
	   * @return Mostra el mes de la data introduïda.
	   */
		public function getMes() {
			$date = new DateTime( $this->date );
			$any = date_format($date, 'm');
			return $any;
		}

		/**
	   * @brief Mostra el dia de la data introduïda.
	   * @return Mostra el dia de la data introduïda.
	   */
		public function getDia() {
			$date = new DateTime( $this->date );
			$any = date_format($date, 'd');
			return $any;
		}

		/**
	   * @brief Mostra XXX
	   * @return Mostra XXX
	   */
		public function getXXX() {
			return $this->XXX;
		}

		/*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

		/**
	   * @brief Afegeix $d dies a la data
	   */
		function addDays( $d ) {
			$data = new DateTime($this->date);
			$this->date = $data->add(new DateInterval('P'.$d.'D'))->format('Y-m-d');
		}
		/**
	   * @brief Afegeix XXX
	   * @return Afegeix XXX
	   */
		function addMonths( $m ) {
			$data = new DateTime($this->date);
			$this->date = $data->add(new DateInterval('P'.$m.'M'))->format('Y-m-d');
		}

		/**
	   * @brief Afegeix XXX
	   * @return Afegeix XXX
	   */
		function setXXX( $XXX ) {
			if ($XXX != null && $XXX != '')
				$this->xxx = new Text($XXX);
		}
	}

?>
