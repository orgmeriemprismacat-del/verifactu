<?php
/**
   * @class Perfil
   * @brief Correspon a un Perfil en concret
*/
class Perfil {
   private $id; /**< Int Correspon al id d'un Perfil */
   private $nom_curt; /**< Text Correspon al nom curt d'un Perfil */
   private $nom_llarg; /**< Text Correspon al nom llarg d'un Perfil */
   private $nom_dept; /**< Text Correspon al nom llarg que li posa el Departament a un Perfil */
   private $hores; /**< Numero El nombres d'hores necessàries que ha de tenir una persona per poder acreditar el perfil */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @param $id La id del perfil
   * @return Es crea el perfil amb el nom_curt corresponent al nom curt del PERFIL_LIST,
      nom_llarg corresponent al nom llarg de la taula PERFIL_LIST,
      nom_dept corresponent al nom del departament de la taula PERFIL_LIST i
      el numero d'hores corresponent al numero d'hores de la taula PERFIL_LIST
   */
   public function __construct($id) {
      if ($id=='') {
         throw new Exception('',401);
      }

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consultaTutor = "SELECT NOM, NDEPT, NCURT, NHORES FROM perfils_list WHERE ID=?";
      $sentencia = $connexio->prepare($consultaTutor);
      $sentencia->bind_param("d", $id);
      $sentencia->execute();
      $sentencia->bind_result($nom, $ndept, $ncurt, $nhores);
      $sentencia->fetch();

      require_once 'Text.php';
      $this->id = $id;
      if ($ncurt!=null and $ncurt!='')
         $this->nom_curt = new Text($ncurt);
      else
         $this->nom_curt = null;

      if ($nom!=null and $nom!='')
         $this->nom_llarg = new Text($nom);
      else
         $this->nom_llarg = null;

      if ($ndept!=null and $ndept!='')
         $this->nom_dept = new Text($ndept);
      else
         $this->nom_dept = null;

      require_once 'Numero.php';
      if ($nhores!=null and $nhores!='')
         $this->hores = new Numero($nhores);
      else
         $this->hores = null;

      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens el id del perfil
   * @return El id del perfil
   */
   public function obtenirIdPerfil() {
      if ($this->id==null) {
         throw new Exception('',401);
      }
      return $this->id;
   }

   /*
   * @brief Obtens el nom curt del perfil
   * @return El nom curt del perfil
   * @throws Si el perfil no té un nom curt, envia l'excepció «No existeix el nom curt»
   */
   public function obtenirNomCurt() {
      if ($this->nom_curt==null) {
         throw new Exception('',402);
      }
      return $this->nom_curt;
   }

   /*
   * @brief Obtens el nom llarg del perfil
   * @return El nom llarg del perfil
   * @throws Si el perfil no té un nom llarg, envia l'excepció «No existeix el nom llarg»
   */
   public function obtenirNomLlarg() {
      if ($this->nom_llarg==null) {
         throw new Exception('',403);
      }
      return $this->nom_llarg;
   }

   /*
   * @brief Obtens el nom del departament del perfil
   * @return El nom del departament del perfil
   * @throws Si el perfil no té un nom del departament, envia l'excepció «No existeix el nom del departament»
   */
   public function obtenirNomDepartament() {
      if ($this->nom_dept==null) {
         throw new Exception('',404);
      }
      return $this->nom_dept;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
}
?>
