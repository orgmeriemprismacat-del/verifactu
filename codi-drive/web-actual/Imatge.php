<?php
/**
   * @class Imatge
   * @brief Conté tota la informació relacionada amb una Imatge
*/
class Imatge {
   private $alt; /**< Text Text alternatiu d'una imatge */
   private $url; /**< Text Enllaç de la ubicació d'una imatge */
   private $versio; /**< Text Versió d'una imatge */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
      * @brief Constructor de la classe.
      * @param $id La id corresponent a la imatge
      * @return La imatge està creada
   */
   public function __construct($id) {
      if ($id=='') {
         throw new Exception('',1017);
      }

      /* Fem la connexió a la BD */
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Es busca el text alternatiu (alt) i l'enllaç de la ubicació de la imatge del registre que la ID de la taula IMATGES correspon a la ID passada per paràmetre */
      $consultaImatge = "SELECT ALT, URL, VERSIO FROM imatges WHERE ID=?";
      $stmt = $connexio->prepare($consultaImatge);
      $stmt->bind_param("d", $id);
      $stmt->execute();

      $stmt->bind_result($alt, $url, $versio);
      $stmt->fetch();

      require_once 'Text.php';
      if ($alt!=null and $alt!='')
         $this->alt = new Text($alt);
      else
         $this->alt = null;

      if ($url!=null and $url!='')
         $this->url = new Text($url);
      else
         $this->url = null;

      if ($versio!=null and $versio!='')
         $this->versio = new Text($versio);
      else
         $this->versio = null;

      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
      * @brief Obtenim el text alternatiu (alt) de la imatge
      * @return El text alternatiu (alt) de la imatge
      * @throws Si la imatge no té un ALT, envia l'excepció «No existeix el text alternatiu (alt) de la imatge»
   */
   public function obtenirAlt() {
      if ($this->alt==null) {
         throw new Exception('',1018);
      }
      return $this->alt->obtenirText();
   }

   /*
      * @brief Obtenim l'enllaç de la ubicació de la imatge
      * @return L'enllaç de la ubicació de la imatge
      * @throws Si la imatge no té un enllaç, envia l'excepció «No existeix l'enllaç de la ubicació de la imatge»
   */
   public function obtenirLink() {
      if ($this->url==null) {
         throw new Exception('',1019);
      }
      return $this->url->obtenirText();
   }

    /*
       * @brief Obtenim la versió de la imatge
       * @return La versió de la imatge
       * @throws Si la imatge no té una versió, envia l'excepció «No existeix la versió de la imatge»
    */
    public function obtenirVersio() {
       if ($this->versio==null) {
          throw new Exception('',1020);
       }
       return $this->versio->obtenirText();
    }
}
?>
