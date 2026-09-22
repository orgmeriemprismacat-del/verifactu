<?php
/**
   * @class Page
   * @brief Conté tota la informació d'una pàgina
*/
class Page {
   private $contingut; /**< string Contingut de la pàgina */
   private $banner; /**< Imatge Banner de la pagina si escau. */
   private $titol; /**< Text Titol de la pagina si escau. */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe. Crees la pàgina a partir de la url
   * @param $url La url corresponent a la pàgina.
   * @return S'ha creat una pàgina amb el contingut corresponent
   */
   public function __construct($url) {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consultaPagina = "SELECT ID FROM amigable WHERE ID=?";
      $stmtPage = $connexio->prepare($consultaPagina);
      $stmtPage->bind_param("d", $id_amigable);
      $id_amigable = $url->obtenirID();
      $stmtPage->execute();
      $stmtPage->store_result();
      if ( $stmtPage->num_rows() <= 0 ) {//no existeix la id_amigable
         $connexio->closeStmt();
         //Mostrar pàgina not found
         $consultaPaginaId = "SELECT ID FROM amigable WHERE URL LIKE '/404'";
         $stmt404 = $connexio->prepare($consultaPaginaId);
         $stmt404->execute();
         $stmt404->bind_result($id);
         $stmt404->fetch();
         $id_amigable = $id;
         $stmt404->store_result();
         if ( $stmt404->num_rows() <= 0 ) {
            throw new Exception('',1011);
         }
         $connexio->closeStmt();
      }

      $cnsPageIdCont = "SELECT ID_CONTINGUT, ID_IMG, TITOL FROM pagina WHERE ID_URL=? AND ESTAT=1";
      $stmtIdCnt = $connexio->prepare($cnsPageIdCont);
      $stmtIdCnt->bind_param("d", $id_amigable);
      $stmtIdCnt->execute();
      $stmtIdCnt->bind_result($id_contingut, $id_img_page, $titol);
      $stmtIdCnt->fetch();
      $connexio->closeStmt();

      $cnsPageCont = "SELECT CONTINGUT FROM contingut WHERE ID=?";
      $stmtPageCnt = $connexio->prepare($cnsPageCont);
      $stmtPageCnt->bind_param("d", $id_contingut);
      $stmtPageCnt->execute();
      $stmtPageCnt->bind_result($contingut);
      $stmtPageCnt->fetch();

      require_once 'Text.php';
      require_once 'Imatge.php';

      $this->contingut = $contingut;

      if ($titol != null || $titol != '' )
        $this->titol = new Text($titol);
      else
        $this->titol = null;

      if ($id_img_page != null || $id_img_page != '' )
        $this->banner = new Imatge($id_img_page);
      else
        $this->banner = null;

      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
   /*
   * @brief Obtenir contingut
   * @return El contingut de la pàgina
   */
   public function getContent() {
     if ( $this->banner == null ) {
       $page = $this->contingut;
     }
     else {
       $page = "<div class='container'><div class='row'><div class='col-12'>";
       // if ( $this->titol != null || $this->titol != '' )
       //    $page .= $this->__getTitle();
       // if ( $this->banner != null || $this->banner != '' )
       //    $page .= $this->__obtenirBanner();
       $page = "<div class='separacio-peu border border-radius-2 bg-white px-4 py-2'>
   			<div id='cntPage' class='cntPage'>
   				".$this->contingut."
   			</div>
   		</div>";
       $page .= "</div></div></div>";
     }
      return $page;
   }

   public function getBanner() {
    $altImg = $this->banner->obtenirAlt();
    $linkImg = "https://www.prisma.cat".$this->banner->obtenirLink();

    $linkImgWeb=substr($linkImg, 0, -4).".webp";

 		$cntBanner = "<div class='info-banner mb-4'>
 			<picture>
 			<source type='image/webp' class='w-100 border-radius-2 banner-img'
 				data-srcset='".$linkImgWebP."' alt='".$altImg."'>
 			<source type='image/jpeg' class='w-100 border-radius-2 banner-img'
 				data-srcset='".$linkImg."' alt='".$altImg."'>
 			<img role='img' class='w-100 border-radius-2 banner-img lazyload'
 				data-src='".$linkImg."' alt='".$altImg."'>
 			</picture>
 		</div>";

     return $cntBanner;
   }

   /**
    * @brief Retorna la informació del titol
    * @return Retorna la informació del titol
    */
 	private function __getTitle() {
 		$cntTitle = "<div class='titol my-4'>
 			<h1>".$this->titol->obtenirText()."</h1>
 		</div>";

 		return $cntTitle;
 	}

}
?>
