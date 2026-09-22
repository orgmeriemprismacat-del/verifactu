<?php
/**
   * @class Url
   * @brief Classe que conté un enllaç i la informació acompanyada sempre amb una Url (name, title)
*/
class Url {
   private $id; /**< Numero ID URL amigable */
   private $enllac; /**< Text L'enllaç amigable */
   private $name; /**< Text El name de l'enllaç */
   private $title; /**< Text El title de l'enllaç */
   private $target; /**< Text El target de l'enllaç */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
      * @brief Constructor de la classe.
      * @param $id La id de la url de l'enllaç
      * @return Es crea la url amb el link corresponent a la url de la taula AMGIABLE, name corresponent
      al name del link, title corresponent al title del link i el target corresponent al target del link
   */
   public function __construct($id) {
      if ($id=='') {
         throw new Exception('',404);
      }

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consultaUrl = "SELECT NAME, TITLE, TARGET, URL FROM amigable WHERE ID=?";
      $sentencia = $connexio->prepare($consultaUrl);
      $sentencia->bind_param("d", $id);
      $sentencia->execute();
      $sentencia->bind_result($name, $title, $target, $url);
      $sentencia->fetch();

      require_once 'Text.php';
      if ($name!=null and $name!='')
         $this->name = new Text($name);
      else
         $this->name = null;

      if ($title!=null and $title!='')
         $this->title = new Text($title);
      else
         $this->title = null;

      if ($target!=null and $target!='')
         $this->target = new Text($target);
      else
         $this->target = null;

      if ($url!=null and $url!='')
         $this->enllac = new Text($url);
      else
         $this->enllac = null;

      require_once 'Numero.php';
      $this->id = new Numero($id);

      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
   /*
      * @brief Obtens el enllaç de la url
      * @return Obtens l'enllaç de la url
      * @throws Si la url no té un enllaç, envia l'excepció «No existeix l'enllaç»
   */
   public function obtenirLink() {
      if ($this->enllac==null) {
         throw new Exception('',1013);
      }

	  return $this->enllac->obtenirText();
   }

   /*
      * @brief Obtens el title de l'enllaç de la url
      * @return Obtens el text del title de l'enllaç
      * @throws Si la url no té un title, envia l'excepció «L'enllaç no té TITLE»
   */
   public function obtenirTitle() {
      if ($this->title==null) {
         throw new Exception('',1014);
      }

      return $this->title->obtenirText();
   }

   /*
      * @brief Obtens el name de l'enllaç de la url
      * @return Obtens el text del name de l'enllaç
      * @throws Si la url no té un name, envia l'excepció «L'enllaç no té NAME»
   */
   public function obtenirName() {
      if ($this->name==null) {
         throw new Exception('',1015);
      }

      return $this->name->obtenirText();
   }

   /*
      * @brief Obtens el target de l'enllaç de la url
      * @return Obtens el text del target de l'enllaç
      * @throws Si la url no té un target, envia l'excepció «L'enllaç no té TARGET»
   */
   public function obtenirTarget() {
      if ($this->target==null) {
         throw new Exception('',1016);
      }

      return $this->target->obtenirText();
   }

   /*
      * @brief Obtens el id de la url
      * @return Obtens el numero de la id de la url
   */
   public function obtenirID() {
      return $this->id->obtenirNumero();
   }
}
?>
