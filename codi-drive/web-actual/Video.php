<?php
/**
   * @class Video
   * @brief Conté tota la informació relacionada amb un Video
*/
class Video {
   private $codi; /**< Text Codi d'un video de Youtube ex: a63Wfstcvy4 */
   private $caratula; /**< Imatge Caratula d'un video de Youtube */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
      * @brief Constructor de la classe.
      * @param $id La id corresponent al vídeo
      * @return El vídeo està creat amb la informació del codi i de la durada obtinguda de la taula VIDEOS
   */
   public function __construct($id) {
      if ($id=='') {
         throw new Exception('',901);
      }

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Es busca el codi del registre que la ID de la taula VIDEOS correspon a la ID
      passada per paràmetre */
      $consultaVideo = "SELECT CODI, CARATULA FROM videos WHERE ID=?";
      $sentencia = $connexio->prepare($consultaVideo);
      $sentencia->bind_param("d", $id);
      $sentencia->execute();
      $sentencia->bind_result($codi, $caratula);
      $sentencia->fetch();
      require_once 'Text.php';
      if ($codi!=null and $codi!='')
         $this->codi = new Text($codi);
      else
         $this->codi = null;
      require_once 'Imatge.php';
      if ($caratula!=null and $caratula!='')
         $this->caratula = new Imatge($caratula);
      else
         $this->caratula = null;

      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
   /*
      * @brief Obtenim el codi del vídeo
      * @return El codi del vídeo
      * @throws Si el video no té un codi, envia l'excepció «No existeix el codi del vídeo»
   */
   private function __obtenirCodi() {
      if ($this->codi==null) {
         throw new Exception('',902);
      }
      return $this->codi;
   }

   /*
      * @brief Obtenim la caratula del vídeo
      * @return La caratula del vídeo
      * @throws Si el video no té una caratula, envia l'excepció «No existeix la caratula del vídeo»
   */
   private function __obtenirCaratula() {
      if ($this->caratula==null) {
         throw new Exception('',903);
      }
      return $this->caratula;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
   /*
      * @brief Mostra la informació d'un vídeo a la pestanya Descripció d'un curs
      * @return El codi per mostrar el vídeo amb el format que tenim a la pestanya Descripció d'un curs
   */
   public function mostrarVideo() {
      $mostrar = "<div class='video-responsive position-relative mt-0 mb-0 w-100'>";
      $mostrar .= $this->__mostrarVideoIframe();
      $mostrar .= "</div>";

      return $mostrar;
   }

    /*
      * @brief Mostra la informació d'un vídeo amb xat
      * @return El codi per mostrar el vídeo amb el format que tenim a la pestanya Descripció d'un curs
   */
   public function mostrarVideoXat() {
      $mostrar = "<div class='d-flex flex-column flex-md-row'>";
      $mostrar .= "<div class='video-responsive position-relative w-75 m-0'>";
      $mostrar .= $this->__mostrarVideoIframe();
	  $mostrar .= "</div><div class='video-responsive position-relative w-25'>";
	  $mostrar .= $this->__mostrarXatIframe();
      $mostrar .= "</div></div>";

      return $mostrar;
   }

   /*
      * @brief Mostra la informació d'un vídeo a la pestanya Descripció d'un curs
      * @return El codi per mostrar el vídeo amb el format que tenim a la pestanya Descripció d'un curs
   */
   public function mostrarVideoInfo() {
      $mostrar = "<div id='video-presentacio' class='video-responsive video-info position-relative w-100'>";
      $mostrar .= "<img class='position-absolute w-100' src='".$this->__obtenirCaratula()->obtenirLink()."?ver=".$this->__obtenirCaratula()->obtenirVersio()."' ";
      $mostrar .= "alt=\"".$this->__obtenirCaratula()->obtenirAlt()."\">";
      $mostrar .= "<button class='youtube-btn position-absolute' aria-label='Botó de reproducció'>";
      $mostrar .= "<svg class='svg-btn-ytb' version='1.1' viewBox='0 0 68 48'><path class='youtube-btn-bg' ";
      $mostrar .= "d='M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,";
      $mostrar .= "1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,";
      $mostrar .= "2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-0.13,27.1-1.55c2.93-0.78,";
      $mostrar .= "4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z'></path>";
      $mostrar .= "<path class='youtube-btn-triangle' d='M 45,24 27,14 27,34'></path></svg></button>";
      $mostrar .= $this->__mostrarVideoIframe();
      $mostrar .= "</div>";

      return $mostrar;
   }

   /*
      * @brief Mostra la informació d'un vídeo a la info des del mòbil
      * @return El codi per mostrar el vídeo amb el format que tenim a la info des d'un mòbil
   */
 /*  public function mostrarVideoInfoMbl() {
      $mostrar = "<div id='video-presentacio' class='video-responsive video-info position-relative'>";
      $mostrar .= "<img class='position-absolute w-100' src='".$this->__obtenirCaratula()->obtenirLink()."' ";
      $mostrar .= "alt=\"".$this->__obtenirCaratula()->obtenirAlt()."\">";
      $mostrar .= "<button class='youtube-btn position-absolute' aria-label='Botó de reproducció'>";
      $mostrar .= "<svg class='svg-btn-ytb' version='1.1' viewBox='0 0 68 48'><path class='youtube-btn-bg' ";
      $mostrar .= "d='M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,";
      $mostrar .= "1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,";
      $mostrar .= "2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-0.13,27.1-1.55c2.93-0.78,";
      $mostrar .= "4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z'></path>";
      $mostrar .= "<path class='youtube-btn-triangle' d='M 45,24 27,14 27,34'></path></svg></button>";
      $mostrar .= $this->__mostrarVideoIframeMbl();
      $mostrar .= "</div>";

      return $mostrar;
   }
*/
   /*
      * @brief Mostra la informació d'un vídeo a la pestanya de Veure un tastet
      * @return El codi per mostrar el vídeo amb el format que tenim al modal de veure un tastet d'un curs
   */
   public function mostrarVideoTastet() {
      $mostrar = "<div class='video-responsive video-tastet position-relative'>";
      $mostrar .= "<img class='position-absolute w-100' src='".$this->__obtenirCaratula()->obtenirLink()."' ";
      $mostrar .= "alt=\"".$this->__obtenirCaratula()->obtenirAlt()."\">";
      $mostrar .= "<button class='youtube-btn position-absolute' aria-label='Botó de reproducció'>";
      $mostrar .= "<svg class='svg-btn-ytb' version='1.1' viewBox='0 0 68 48'><path class='youtube-btn-bg' ";
      $mostrar .= "d='M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,";
      $mostrar .= "1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,";
      $mostrar .= "2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-0.13,27.1-1.55c2.93-0.78,";
      $mostrar .= "4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z'></path>";
      $mostrar .= "<path class='youtube-btn-triangle' d='M 45,24 27,14 27,34'></path></svg></button>";
      $mostrar .= $this->__mostrarVideoIframe();
      $mostrar .= "</div>";

      return $mostrar;
   }

   /*
      * @brief Mostra el Iframe d'un vídeo
      * @return El codi per mostrar el vídeo
   */
   private function __mostrarVideoIframe() {
      $mostrar .= "<iframe class='position-absolute w-100 border-0' ";
      $mostrar .= "title='Video de presentació del curs' src='https://www.youtube.com/embed/";
      $mostrar .= $this->__obtenirCodi()->obtenirText()."?autoplay=0'";
      $mostrar .= "border='0' allow='accelerometer; autoplay; encrypted-media; gyroscope; ";
      $mostrar .= "picture-in-picture' allowfullscreen='allowfullscreen'></iframe>";

      return $mostrar;
   }

    /*
      * @brief Mostra el Iframe d'un xat
      * @return El codi per mostrar el xat
   */
   private function __mostrarXatIframe() {
      $mostrar .= "<iframe class='position-absolute w-100 border-0' ";
      $mostrar .= "title='Xat del vídeo' src='https://www.youtube.com/live_chat?v=";
      $mostrar .= $this->__obtenirCodi()->obtenirText()."&amp;embed_domain=www.prisma.cat'";
      $mostrar .= "border='0' allow='accelerometer; autoplay; encrypted-media; gyroscope; ";
      $mostrar .= "picture-in-picture' allowfullscreen='allowfullscreen'></iframe>";

      return $mostrar;
   }

   /*
      * @brief Mostra el Iframe d'un vídeo per al mòbil
      * @return El codi per mostrar el vídeo per al mòbil
   */
 /*  private function __mostrarVideoIframeMbl() {
      $mostrar .= "<iframe class='position-absolute w-100 border-0' ";
      $mostrar .= "title='Video de presentació del curs' src='https://www.youtube.com/embed/";
      $mostrar .= $this->__obtenirCodi()->obtenirText()."?autoplay=1&mute=1&loop=1&playlist=".$this->__obtenirCodi()->obtenirText()."'";
      $mostrar .= "border='0' allow='accelerometer; encrypted-media; gyroscope; ";
      $mostrar .= "picture-in-picture' allowfullscreen='allowfullscreen'></iframe>";

      return $mostrar;
   }*/
}
?>
