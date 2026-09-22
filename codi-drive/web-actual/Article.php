<?php
/**
   * @class Article
   * @brief Conté tota la informació relacionada amb un Article del blog Educat
*/
class Article {

   private $titol; /**< Text Títol de l'article */
   private $autor; /**< Text Autor o autors de l'article */
   private $img; /**< Text Enllaç de la imatge de portada de l'article */
   private $url; /**< Text Enllaç de l'article de l'article */
   private $alt; /**< Text Text alternatiu (ALT) de la imatge de l'article */
   private $title; /**< Text Títol (TITLE) de l'enllaç de l'article */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @param $id La id corresponent a l'article
   * @return L'article està creat
   */
   public function __construct($id, $dispositiu) {
      $this->dispositiu = $dispositiu;

      if ($id=='') {
         throw new Exception('',1001);
      }

      /* Fem la connexió a la BD */
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Es busca el titol, autor o autors, enllaç imatge destacada, enllaç article, etiqueta alt i etiqueta title de la url del registre que la ID de la taula ARTICLES correspon a la ID passada per paràmetre */
      $consultaArticle = "SELECT TITOL, AUTOR, URL_IMG, URL_ARTICLE, ALT, TITLE_URL FROM articles WHERE ID=?";
      $sentencia = $connexio->prepare($consultaArticle);
      $sentencia->bind_param("d", $id);
      $sentencia->execute();
      $sentencia->bind_result($titol, $autor, $url_img, $url_article, $alt, $title_url);
      $sentencia->fetch();

      if ($titol!=null and $titol!='')
         $this->titol = new Text($titol);
      else
         $this->titol = null;

      if ($autor!=null and $autor!='')
         $this->autor = new Text($autor);
      else
         $this->autor = null;

      if ($url_img!=null and $url_img!='')
         $this->img = new Text($url_img);
      else
         $this->img = null;

      if ($url_article!=null and $url_article!='')
         $this->url = new Text($url_article);
      else
         $this->url = null;

      if ($alt!=null and $alt!='')
         $this->alt = new Text($alt);
      else
         $this->alt = null;

      if ($title_url!=null and $title_url!='')
         $this->title = new Text($title_url);
      else
         $this->title = null;

      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtenim el titol de l'article
   * @return El títol de l'article
   * @throws No existeix el títol de l'article
   */
   public function obtenirTitol() {
      if ($this->titol==null) {
         throw new Exception('',1002);
      }
      return $this->titol;
   }

	/*
   * @brief Obtenim l'autor o autors de l'article
   * @return L'autor o autors de l'article
   * @throws No existeix l'autor o autors de l'article
   */
   private function obtenirAutor() {
      if ($this->autor==null) {
         throw new Exception('',1003);
      }
      return $this->autor;
   }

	/*
   * @brief Obtenim l'enllaç de la imatge de portada de l'article
   * @return L'enllaç de la imatge de portada de l'article
   * @throws No existeix l'enllaç de la imatge de l'article
   */
   private function obtenirImatge() {
      if ($this->img==null) {
         throw new Exception('',1004);
      }
      return $this->img;
   }

	/*
   * @brief Obtenim l'enllaç de l'article
   * @return L'enllaç de l'article
   * @throws No existeix l'enllaç de l'article
   */
   private function obtenirLink() {
      if ($this->url==null) {
         throw new Exception('',1005);
      }
      return $this->url;
   }

   /*
   * @brief Obtenim el text alternatiu (alt) de la imatge de portada de l'article
   * @return El text alternatiu (alt) de la imatge de portada de l'article
   * @throws No existeix el text alternatiu (alt) de la imatge de l'article
   */
   private function obtenirAlt() {
      if ($this->alt==null) {
         throw new Exception('',1006);
      }
      return $this->alt;
   }

	/*
   * @brief Obtenim el títol de l'enllaç (title) de l'article
   * @return El el títol de l'enllaç (title) de l'article
   * @throws No existeix el títol de l'enllaç (title) de l'article
   */
   public function obtenirTitle() {
      if ($this->title==null) {
         throw new Exception('',1007);
      }
      return $this->title;
   }

   /*
   * @brief Consulta si es visualitza des d'un mòbil
   * @return Obtens TRUE si es visualitza des d'un mòbil, altramanet retorna FALSE
   */

   private function __isMobile() {
   if ($this->dispositiu == "mobil")
     return true;
   else
     return false;
  }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Mostra la informació d'un article a la pestanya Descripció d'un curs
   * @return El codi per mostrar l'article amb el format que tenim a la pestanya Descripció d'un curs
	*/
   public function mostrarArticle() {
      $mostrar = "<div class='article-relacionat'>";
      $mostrar .= "<p>I, relacionat amb el curs, us recomanem aquest article del blog de PrisMa <em>Educat</em>:<br /><br />";
      $link = $this->obtenirLink()->obtenirText();
      $title = $this->obtenirTitle()->obtenirText();
      $title = str_replace("'","&#39;",$title);
      $img = $this->obtenirImatge()->obtenirText();
      $titol = $this->obtenirTitol()->obtenirText();
      $autor = $this->obtenirAutor()->obtenirText();

      $mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-start'>
      <a href='".$link."' title='".$title."' target='_blank' rel='noopener'>
      <img src='".$img."' alt='Article relacionat' class='mr-0 mr-md-3' /></a>
      <div class='d-flex flex-column mt-4 mt-md-0'>
      <a href='".$link."' title='".$title."' target='_blank' rel='noopener' class='font-weight-bold mb-2'>".$titol."</a>";
      $mostrar .= "<span>".$autor."</span></div></div>";

      $mostrar .= "</div>";

      return $mostrar;
   }
   /*
   * @brief Mostra la informació d'un article a la pestanya Descripció d'un curs
   * @return El codi per mostrar l'article amb el format que tenim a la pestanya Descripció d'un curs
	*/
   public function mostrarCntArticle() {
      $link = $this->obtenirLink()->obtenirText();
      $title = $this->obtenirTitle()->obtenirText();
      $title = str_replace("'","&#39;",$title);
      $img = $this->obtenirImatge()->obtenirText();
      $titol = $this->obtenirTitol()->obtenirText();
      $autor = $this->obtenirAutor()->obtenirText();

      $mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-start mt-3'>
      <a href='".$link."' title='".$title."' target='_blank' rel='noopener'>
      <img src='".$img."' alt='Article relacionat' class='mr-0 mr-md-3' /></a>
      <div class='d-flex flex-column mt-4 mt-md-0'>
      <a href='".$link."' title='".$title."' target='_blank' rel='noopener' class='font-weight-bold mb-2'>".$titol."</a>";
      $mostrar .= "<span>".$autor."</span></div></div>";

      return $mostrar;
   }
}
?>
