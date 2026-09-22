<?php
/**
   * @class Docent
   * @brief Conté tota la informació relacionada amb un Docent
*/
class Docent {
   protected $nom; /**< Text Nom del docent */
   protected $cognoms; /**< Text Cognoms del docent */
   protected $dni; /**< Text DNI/NIE del docent */
   protected $img_small; /**< Imatge La imatge petita del docent. Si no n'hi ha, valdrà null */
   protected $url; /**< Url L'enllaç del docent. Si no n'hi ha, valdrà null  */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe. Crees el docent a partir de la url amigable
   * @param $url L'objecte url amb l'enllaç amigable corresponent al docent
   * @return El docent està creat
   */
   public function __construct($dni) {
      if ($dni==null or $dni=='') {
         throw new Exception('',301);
      }

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Es busca nom, els cognoms, imatge petita i url del registre que el DNI de la taula PERSONAL correspon al DNI passat per paràmetre */
      //$consDoc = "SELECT NOM, COGNOMS, ID_IMG_SMALL, ID_URL, ID_URL_AUT FROM personal WHERE DNI=?";
	  $consDoc = "SELECT NOM, COGNOMS, ID_IMG_SMALL, ID_URL FROM personal WHERE DNI=?";
      $stmtDoc = $connexio->prepare($consDoc);
      $stmtDoc->bind_param("s", $dni);
      $stmtDoc->execute();
      //$stmtDoc->bind_result($nom, $cognoms, $id_img_small, $id_url, $id_url_aut);
	  $stmtDoc->bind_result($nom, $cognoms, $id_img_small, $id_url);
      $stmtDoc->fetch();

      require_once 'Text.php';
      if ($nom!=null and $nom!='')
         $this->nom = new Text($nom);
      else
         $this->nom = null;

      if ($cognoms!=null and $cognoms!='')
         $this->cognoms = new Text($cognoms);
      else
         $this->cognoms = null;

      require_once 'Imatge.php';
      if ($id_img_small!=null and $id_img_small!='')
         $this->img_small = new Imatge($id_img_small);
      else
         $this->img_small = null;

      require_once 'Url.php';
      if ($id_url!=null and $id_url!='')
         $this->url = new Url($id_url);
      else
         $this->url = null;

	  /*if ($id_url_aut!=null and $id_url_aut!='')
         $this->url_aut = new Url($id_url_aut);
      else
         $this->url_aut = null;*/

      if ($dni!=null and $dni!='')
         $this->dni = new Text($dni);
      else
         $this->dni = null;

      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
   /**
   * @brief Obtenir nom complet
   * @return El nom i els cognoms del docent
   * @throws No existeixen el nom o els cognoms del docent
   */
   public function obtenirNomComplet() {
      $nomComplet = new Text($this->obtenirNom()->obtenirText());
      $nomComplet->afegirFinal(" ".$this->obtenirCognoms()->obtenirText());
      return $nomComplet;
   }

   /**
   * @brief Obtens la url del docent
   * @return L'objecte url de l'enllaç amigable del docent
   * @throws No existeix la url del docent
   */
   public function obtenirUrl() {
      if ($this->url==null) {
         throw new Exception('',305);
      }
      return $this->url;
   }

   /**
   * @brief Obtenir DNI del docent
   * @return DNI/NIE del docent
   * @throws No existeixen el DNI/NIE del docent
   */
   public function obtenirDni() {
      if ($this->dni==null) {
         throw new Exception('',306);
      }
      return $this->dni;
   }

   /**
   * @brief Obtens el nom del docent
   * @return El nom del docent
   * @throws No existeix el nom del docent
   */
   protected function obtenirNom() {
      if ($this->nom==null) {
         throw new Exception('',332);
      }
      return $this->nom;
   }

   /**
   * @brief Obtens els cognoms del docent
   * @return Els cognoms del docent
   * @throws No existeixen els cognoms del docent
   */
   protected function obtenirCognoms() {
      if ($this->cognoms==null) {
         throw new Exception('',303);
      }
      return $this->cognoms;
   }

   /**
   * @brief Obtens la imatge petita del docent
   * @return La imatge petita del docent
   * @throws No existeix la imatge petita del docent
   */
   protected function obtenirImatgePetita() {
      if ($this->img_small==null) {
         throw new Exception('',304);
      }
      return $this->img_small;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
   /**
   * @brief Mostra la informació d'un docent (nosaltres)
   * @return El codi per mostrar el docent amb el format que tenim a la pàgina Nosaltres
   */
   public function mostrarDocents() {
      $menysEspai='';
      /* si el docent és la Marta Fdez. de la Reguera reduim l'espaiat del text perquè hi càpiga el nom correctament en pantalla mòbil */
      if ($this->obtenirDni()->obtenirText().length > 30)
         $menysEspai = " min-espaiat";

      $urlLink="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
      $urlTarget=$this->obtenirUrl()->obtenirTarget();
      $urlTitle=$this->obtenirUrl()->obtenirTitle();
      $imgLink=$this->obtenirImatgePetita()->obtenirLink()."?ver=".$this->obtenirImatgePetita()->obtenirVersio();
      $imgAlt=$this->obtenirImatgePetita()->obtenirAlt();
      $mostrar="<div class='col-12 col-sm-4 col-md-3 col-lg-2'>
         <div class='prisma-single-docent'>
            <div class='prisma-docent-overlay'>
               <a role='link' target='".$urlTarget."' title='Breu currículum ".$urlTitle."' href='".$urlLink."'>
               <img role='img' class='prisma-docent-image-small w-100' src='https://www.prisma.cat".$imgLink."' alt='".$imgAlt."'>
               </a>
            </div>
            <div class='docent-name".$menysEspai."'>
               <a role='link' class='color-text' href='".$urlLink."' target='".$urlTarget."' title='Breu currículum ".$urlTitle."'>
               <h2>".$this->obtenirNomComplet()->obtenirText()."</h2>
               </a>
            </div>
         </div>
      </div>";

      return $mostrar;
   }
}
?>
