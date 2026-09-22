<?php
require_once 'Caroussel.php';

/**
   * @class CarousselTastets
   * @brief Conté  els slides dels perfils professionals de la home
*/
class CarousselTastets extends Caroussel {

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /**
   * @brief Constructor de la classe.
   * @return S'ha creat el slideshow
   */
   public function __construct($dispositiu) {
      $this->cns = [
         "getAll" => "SELECT TITOL, CODI_CURS, SHORT_DESC, PRESENTACIO,
   		ID_URL, ID_IMG_LARGE, ID_IMG_SMALL, CURS_ORIG
   		FROM reptes WHERE ESTAT = 1",
         "getTextos" => "SELECT NOM, DESCRIPCIO FROM textos WHERE NOM = ? AND
         DATAI <= CURRENT_TIME AND (DATAF IS NULL OR DATAF >= CURRENT_TIME)"
      ];

      $this->nElements=0;

      require_once 'ConnexioBBDD_PreparedStatment.php';
		$connexio = new ConnexioBBDDSTMT();
    	$connexio->connectarBD();

		$stmt = $connexio->prepare( $this->cns['getAll'] );
		$stmt->execute();
		$stmt->bind_result($titol, $codiCurs, $shortDesc, $intro,
		$idUrl, $idImgLarge, $idImgSmall, $cursOrig);
      require_once 'Tastet.php';
		while ( $stmt->fetch() ) {
         $this->llista[$this->nElements] = new Tastet($idUrl, $dispositiu);
         $this->nElements++;
      }
		$connexio->closeStmt();

      $connexio->desconectarBD();

      // echo $this->nElements;
      // var_dump($this->llista);

   }

   /*********************************** FUNCIONS ASSIGNAR ATRIBUTS ***********************************/

   public function setinfo( $id, $titol, $ariaLabelGeneral, $ariaLabelUnic ) {
      $this->id = $id;
      $this->titol = $titol;
      $this->ariaLabel = $ariaLabelGeneral;
      $this->ariaLabelUnic = $ariaLabelUnic;
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   protected function __vistaElBody( $curs ) {
      require_once 'Tastet.php';
      return $curs->mostrarTastetComCurs();
   }
}
?>
