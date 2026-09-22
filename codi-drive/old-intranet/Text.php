<?php
	/**
		* @class Text
		* @brief Conté totes les operacions que pots realitzar amb un string
	*/
	class Text
	{
		private $text;

		/*********************************** FUNCIONS CONSTRUCTORS ***********************************/
	   /*
	   * @brief Constructor de la classe.
	   * @return Crees un Text buit
	   */
		public function __construct($text)	{
			if ($text=='')
	         throw new Exception('',5000);
	      $text = str_replace("’","'",$text);
	      $this->text = $text;
		}

		/*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
		/*
	   * @brief Obtenir el text
	   * @return El text
	   */
	   public function get() {
	      return $this->text;
	   }

		/*
	   * @brief Obtenir el text per utilitzar-ho en codi HTML
	   * @return El text per utilitzar-ho en codi HTML de manera que les paraules que calen cursives s'hafegirà el tag <em></em>
	   */
	   public function getHTML() {
	      require_once 'ConnexioWeb.php';
	      $conWeb = new ConnexioWeb();
	   	$conWeb->connectarBD();

	      $cns = "SELECT PARAULA FROM cursives";
			if ( $stmt=$conWeb->prepare( $cns ) ) {
		   	$stmt->execute();
		   	$stmt->bind_result($cursiva);
		   	while ($stmt->fetch()) {
		         $this->text = $this->replace($cursiva, '<em>'.$cursiva.'</em>');
		      }
		   	$conWeb->closeStmt();
			}
			else {
				throw new Exception('',5001);
			}

	      $conWeb->desconectarBD();
	      return $this->text;
	   }

		/*
	   * @brief Obtens el dia de la setmana de la data entrada
	   * @return Obtens el dia de la setmana "Dissabte" a partir de la data en format "2019-07-01"
	   */
	   public function getDiaSetmana() {
	      $dies = array("Diumenge","Dilluns","Dimarts","Dimecres","Dijous","Divendres","Dissabte");
	      $diaSetmana = $dies[date('N',strtotime($this->text))];
	      return $diaSetmana;
	   }

		/**
	   * @brief Mostra XXX
	   * @return Mostra XXX
	   */
		function getXXX() {
			return $this->XXX;
		}

		/*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
		/*
	   * @brief Converteix el text en minuscules
	   * @return El text convertit en minuscules
	   */
	   public function setMin() {
			$this->text = mb_strtolower($this->text, 'UTF-8');
	   }

	   /*
	   * @brief Converteix el text en majúscules
	   * @return El text convertit en majúscules
	   */
	   public function setMaj() {
	      $this->text = mb_strtoupper($this->text, 'UTF-8');
	   }

	   /*
	   * @brief Converteix el primer caracter del text en majúscules
	   * @return Converteix a majúscules el primer caracter de la cadena
	   */
	   public function setMajPrimLletra() {
	      $this->text = ucfirst($this->text);
	   }

	   /*
	   * @brief Converteix el primer caracter de cada paraula del text en majúscules
	   * @return Converteix a majúscules el primer caracter de cada paraula de la cadena
	   */
	   public function setMajPrimParaula() {
	      $this->text = ucwords($this->text);
	   }

		/*
	   * @brief Afegir en el text actual, un text al final de tot.
	   * @param $text_afegir El text a afegir.
	   * @return El text que hi havia amb el text $text_afegir al final de tot
	   * @throws Si el $text_afegir és null o buit, envia l'excepció «El text passat per parametre no es pot afegir»
	   */
	   public function addLast($text_afegir) {
	      if ($text_afegir==null or $text_afegir=='')
	      	throw new Exception('',5002);
	      $this->text = $this->text.$text_afegir;
	   }

		/*
		* @brief Neteja els accents
		* @return Canvia el text subtituin els elements que tenen un accent o un caracter especial i els reemplaça per el seu equivalent sense accent
		*/
		public function netejarAccents() {
			$this->text = str_replace(
				array('Á', 'À', 'Â', 'Ä', 'á', 'à', 'ä', 'â', 'ª'),
				array('A', 'A', 'A', 'A', 'a', 'a', 'a', 'a', 'a'), $this->text);

			$this->text = str_replace(
				array('É', 'È', 'Ê', 'Ë', 'é', 'è', 'ë', 'ê'),
				array('E', 'E', 'E', 'E', 'e', 'e', 'e', 'e'), $this->text);

			$this->text = str_replace(
				array('Í', 'Ì', 'Ï', 'Î', 'í', 'ì', 'ï', 'î'),
				array('I', 'I', 'I', 'I', 'i', 'i', 'i', 'i'), $this->text);

			$this->text = str_replace(
				array('Ó', 'Ò', 'Ö', 'Ô', 'ó', 'ò', 'ö', 'ô'),
				array('O', 'O', 'O', 'O', 'o', 'o', 'o', 'o'), $this->text);

			$this->text = str_replace(
				array('Ú', 'Ù', 'Û', 'Ü', 'ú', 'ù', 'ü', 'û'),
				array('U', 'U', 'U', 'U', 'u', 'u', 'u', 'u'), $this->text);

			$this->text = str_replace(
				array('Ñ', 'ñ', 'Ç', 'ç'),
				array('N', 'n', 'C', 'c'), $this->text);
		}

		/*
		* @brief Canvia tots elsements $text1 per $text2
		* @param $text1 El text que es vol canviar.
		* @param $text2 El text per el qual es vol canviar.
		* @return S'ha modificat en wl text que hi havia, tos els caracters iguals al $text1 per $text2
		*/
		public function replace($text1, $text2) {
			$this->text = str_replace($text1, $text2, $this->text);
		}

		/**
	   * @brief Neteja els accents, reemplaça espais per guions i retorna en minuscula
	   * @return Transforma el text subtituin els elements que tenen un accent o un caracter
   	* especial i els reemplaça per el seu equivalent sense accent, després reemplaça
    	* els ' i ', els pais i els punts per un guio i l'apostrof el substituieix per res.
   	* Finalment, ho transforma tot en minuscula.
	   */
		public function setNomCurt() {
			$this->netejarAccents();
			$this->replace(' ', '-');
			$this->replace(' i ', '-');
			$this->replace('.', '-');
			$this->replace("'", '');
			$this->setMin();
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
