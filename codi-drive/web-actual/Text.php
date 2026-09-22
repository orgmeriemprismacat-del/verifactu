<?php
/**
   * @class Text
   * @brief Conté el text amb el que es vol treballar
*/
class Text {

   private $text; /**< string Correspon al text amb el que es vol treballar */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @param $text El text amb el qual es vol crear l'objecte
   * @return El Text amb el text $text assignat
	 */
   public function __construct($text) {
      if ($text=='')
         throw new Exception('',1008);
      $text = str_replace("’","'",$text);
      $this->text = $text;
   }

   /*
   * @brief Obtenir el text corresponent a la taula Textos corresponent al text
   * @return El text text corresponent a la taula Textos corresponent al text
   */
   public function searchTextBD() {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();
		$cns = "SELECT DESCRIPCIO FROM textos WHERE NOM LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY DATAI";
		$stmt = $connexio->prepare($cns);
		$stmt->bind_param("s", $text);
      $text = $this->text;
		$stmt->execute();
		$stmt->bind_result($textResultant);
		$stmt->fetch();
		$connexio->closeStmt();

		$connexio->desconectarBD();

      $this->text = $textResultant;

   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtenir el text
   * @return El text
   */
   public function obtenirText() {
      return $this->text;
   }

   /*
   * @brief Obtenir el text per utilitzar-ho en codi HTML. De manera que els texts que calen cursives s'hafegirà el tag <em></em>
   * @return El text per utilitzar-ho en codi HTML. De manera que les paraules que calen cursives s'hafegirà el tag <em></em>
   */
   public function obtenirTextHTML() {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

      $cns = "SELECT PARAULA FROM cursives";
   	$stmt=$connexio->prepare($cns);
   	$stmt->execute();
   	$stmt->bind_result($cursiva);
   	while ($stmt->fetch()) {
         $this->text = $this->replace($cursiva, '<em>'.$cursiva.'</em>');
         $this->text = $this->replace(mb_strtolower($cursiva), '<em>'.mb_strtolower($cursiva).'</em>');
         $this->text = $this->replace(mb_strtoupper($cursiva), '<em>'.mb_strtoupper($cursiva).'</em>');
      }
   	$connexio->closeStmt();

      $connexio->desconectarBD();
      return $this->text;
   }

   /*
   * @brief Obtens el text en minuscules
   * @return El text convertit en minuscules
   */
   public function convertirMin() {
      return mb_strtolower($this->text);
   }

   /*
   * @brief Obtens el text en majúscules
   * @return El text convertit en majúscules
   */
   public function convertirMaj() {
      return mb_strtoupper($this->text);
   }

   /*
   * @brief Obtens el primer caracter del text en majúscules
   * @return Converteix a majúscules el primer caracter de la cadena
   */
   public function convertirMajPrimLletra() {
      return ucfirst($this->text);
   }

   /*
   * @brief Obtens el primer caracter de cada paraula del text en majúscules
   * @return Converteix a majúscules el primer caracter de cada paraula de la cadena
   */
   public function convertirMajPrimParaula() {
      return ucwords($this->text);
   }

   /*
   * @brief Obtens el dia de la setmana de la data entrada
   * @return Converteix la data en format 2019-07-01 a Dissabte
   */
   public function obtenirDiaSetmana() {
      $dies = array("Diumenge","Dilluns","Dimarts","Dimecres","Dijous","Divendres","Dissabte");
      $diaSetmana = $dies[date('N',strtotime($this->text))];
      return $diaSetmana;
   }

   /*
   * @brief Obtens la data en format llarg
   * @return Converteix la data en format 2019-07-01 a 1 de juliol del 2019
   */
   public function convertirDataLlarga() {
      $parts_data = explode(' ',$this->text);
      $parts_data = explode('-',$parts_data[0]);

      $d = "de ";
      if (($parts_data[1] == '04') or ($parts_data[1] == '08') or ($parts_data[1] == '10'))
         $d = "d'";

      if ($parts_data[1] == '01') $data_llarga = 'gener';
      else if ($parts_data[1] == '02') $data_llarga = 'febrer';
      else if ($parts_data[1] == '03') $data_llarga = 'març';
      else if ($parts_data[1] == '04') $data_llarga = 'abril';
      else if ($parts_data[1] == '05') $data_llarga = 'maig';
      else if ($parts_data[1] == '06') $data_llarga = 'juny';
      else if ($parts_data[1] == '07') $data_llarga = 'juliol';
      else if ($parts_data[1] == '08') $data_llarga = 'agost';
      else if ($parts_data[1] == '09') $data_llarga = 'setembre';
      else if ($parts_data[1] == '10') $data_llarga = 'octubre';
      else if ($parts_data[1] == '11') $data_llarga = 'novembre';
      else if ($parts_data[1] == '12') $data_llarga = 'desembre';

      $data = intval($parts_data[2])." ".$d.$data_llarga." del ".$parts_data[0];

      return $data;
   }

   /*
   * @brief Obtens el mes en format llarg
   * @return Converteix el numero mes en format mes llarg. Ex: 01 -> gener
   */
   public function obtenirMesLlarg()
   {
      if ($this->text == '01') $data_llarga = 'gener';
      else if ($this->text == '02') $data_llarga = 'febrer';
      else if ($this->text == '03') $data_llarga = 'març';
      else if ($this->text == '04') $data_llarga = 'abril';
      else if ($this->text == '05') $data_llarga = 'maig';
      else if ($this->text == '06') $data_llarga = 'juny';
      else if ($this->text == '07') $data_llarga = 'juliol';
      else if ($this->text == '08') $data_llarga = 'agost';
      else if ($this->text == '09') $data_llarga = 'setembre';
      else if ($this->text == '10') $data_llarga = 'octubre';
      else if ($this->text == '11') $data_llarga = 'novembre';
      else if ($this->text == '12') $data_llarga = 'desembre';

      return $data_llarga;
   }

   /*
   * @brief Obtens el mes en format llarg i amb el de corresponent
   * @return Converteix el numero mes en format mes llarg amb de. Ex: 01 -> de gener
   */
   public function obtenirDeMesLlarg()
   {
      $d = "de ";
      if (($this->text == '04') or ($this->text == '08') or ($this->text == '10'))
         $d = "d'";

      $data_llarga = $d;
      $data_llarga .= $this->obtenirMesLlarg();
      return $data_llarga;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Afegir en el text actual, un text al final de tot.
   * @param $text_afegir El text a afegir.
   * @return El text que hi havia amb el text $text_afegir al final de tot
   * @throws Si el $text_afegir és null o buit, envia l'excepció «El text passat per parametre no es pot afegir»
   */
   public function afegirFinal($text_afegir) {
      if ($text_afegir==null or $text_afegir=='')
      	throw new Exception('',1009);
      $this->text = $this->text.$text_afegir;

      return $this->text;
   }

   /*
   * @brief Canvia tots elsements $text1 per $text2
   * @param $text1 El text que es vol canviar.
   * @param $text2 El text per el qual es vol canviar.
   * @return S'ha modificat en wl text que hi havia, tos els caracters iguals al $text1 per $text2
   */
   public function replace($text1, $text2) {
      $text = str_replace($text1,$text2,$this->text);
      return $text;
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

      $this->text = str_replace(array('Ñ', 'ñ', 'Ç', 'ç'), array('N', 'n', 'C', 'c'), $this->text);
   }

   /*
   * @brief Neteja els accents, reemplaça espais per guins i retorna en minuscula
   * @return Canvia el text subtituin els elements que tenen un accent o un caracter
   especial i els reemplaça per el seu equivalent sense accent, després reemplaça
    els ' i ' per un espai, i després torna a reemplaçar els espais per guions.
   Finalment, ho transforma tot en minuscula
   */
   public function obtenirNomCurt() {
      $this->netejarAccents();
      $this->text=$this->replace(' i ', ' ');
      $this->text=$this->replace(' ', '_');
      $this->text=$this->replace('.', '');
      $this->text=$this->replace("'", '');
      $this->text=$this->replace("(", '');
      $this->text=$this->replace(")", '');
      $this->text=$this->convertirMin();
   }

   /*
    * @brief Neteja els espais dels laterals de la dreta i de l'esquerra
    * @return Neteja els espais dels laterals de la dreta i de l'esquerra
   */
   public function netejaEspaisLaterals() {
      $this->text = ltrim($this->text, " ");
		$this->text = rtrim($this->text, " ");
   }

   /*
    * @brief Arregla la paraula.
    * @return Arregla la paraula (Eliminar espais a l'esquerra, Eliminar espais de la dreta,
    * Convertir la paraula en minúscules) segons el parametre.
   */
   public function arreglarParaulaBD($tipus) {
      $this->netejaEspaisLaterals();
      if ($tipus == 'bd') {
         $this->text = $this->replace("'","''");
      }
      else if ($tipus == 'noms') {
   		$this->text = mb_convert_case($this->text, MB_CASE_TITLE, "UTF-8");
   		$this->text = preg_replace('!\s+!', ' ', $this->text);
   		$this->text = $this->replace('I ', 'i ');
   		$this->text = $this->replace("La ","la ");
   		$this->text = $this->replace("El ","el ");
   		$this->text = $this->replace("De ","de ");
   		$this->text = $this->replace("Los ","los ");
   		$this->text = $this->replace("Les ","les ");
   		$this->text = $this->replace("Las ","las ");
   		$this->text = $this->replace("D'","d'");
   		$this->text = $this->replace("L'","l'");
   		$this->text = $this->replace("Del ","del ");
   		$this->text = $this->replace("Dels ","dels ");
   		$this->text = $this->replace("De La ","de la ");
      }
      else if ($tipus == 'text_firt_maj') {
         $this->text = $this->convertirMin();
         $this->text = $this->convertirMajPrimLletra();
      }
      else if ($tipus == 'text_maj') {
         $this->text = $this->convertirMaj();
      }
      else if ($tipus == 'text_min') {
         $this->text = $this->convertirMin();
      }
      else if ($tipus == 'text') {
         $this->text = $this->convertirMajPrimLletra();
      }
      else if ($tipus == 'email') {
         $this->text = $this->convertirMin();
         $this->text = $this->replace("'","''",$this->text);
      }
   }

}
?>
