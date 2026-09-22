<?php
/**
   * @class Slider
   * @brief Conté tota la informació relacionada amb un Slideshow.
*/
class Slider {
   private $titol; /**< Text El titol del slider. */
   private $destacats; /**< Text Els destacats del slider */
   private $description; /**< Text La descripció curta del slider */
   private $classe; /**< Text La descripció curta del slider */
   private $boto; /**< Text Si existeix l'opció del botó al slider */
   private $textBoto; /**< Text El text del botó del slider */
   private $nomCampanya; /**< Text El text de la campanya del slider */
   private $img; /**< Imatge La imatge rectangular del slider. Si no n'hi ha, valdrà null */
   private $url; /**< URL L'enllaç del slider. Si no n'hi ha, valdrà null  */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /**
   * @brief Constructor de la classe. Crees el curs a partir de la url
   * @param $codi El codi corresponent al curs.
   * @return S'ha assignat en el curs, el codi de curs $codi, el titol i la descripció curta obtinguta
      a la taula INFORMACIO corresponent al codi_curs $codi i ESTAT = 1, les hores i l'id_preu
      obtingut a la taula CURSOS de la pròxima edició oberta corresponent al curs amb codi_curs
      $codi, els perfils que té la pròxima edició oberta de la taula PERFILS i les ids del video
      i de les imatges de la taula INFORMACIO corresponent al codi_curs $codi i ESTAT = 1
   */
   public function __construct($id_slider) {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Es busca els ids del sliders que estan actius ordenats per ORDRE */
      $cns= "SELECT TITOL, DESTACATS, DESCRIPCIO, CLASS, BOTO, TEXT_BOTO, ID_IMG, ID_URL, CAMPANYA FROM slider WHERE ID=?";
      $stmt=$connexio->prepare($cns);
      $stmt->bind_param("d", $id_slider);
      $stmt->execute();
      $stmt->bind_result($titol, $destacats, $descripcio, $clase, $boto, $textBoto, $idImg, $idUrl, $nomCampanya);
      $stmt->fetch();
      $connexio->closeStmt();

      require_once 'Text.php';
      if ($titol!=null and $titol!='')
         $this->titol = new Text($titol);
      else
         $this->titol = null;
      if ($descripcio!=null and $descripcio!='')
         $this->description = new Text($descripcio);
      else
         $this->description = null;
      if ($destacats!=null and $destacats!='')
         $this->destacats = new Text($destacats);
      else
         $this->destacats = null;
      if ($clase!=null and $clase!='')
         $this->classe = new Text($clase);
      else
         $this->classe = null;
      if ($boto!=null and $boto!='')
         $this->boto = $boto;
      else
         $this->boto = null;
      if ($textBoto!=null and $textBoto!='')
         $this->textBoto = new Text($textBoto);
      else
         $this->textBoto = null;
      if ($nomCampanya!=null and $nomCampanya!='')
         $this->nomCampanya = new Text($nomCampanya);
      else
         $this->nomCampanya = null;
      require_once 'Imatge.php';
      if ($idImg!=null and $idImg!='')
         $this->img = new Imatge($idImg);
      else
         $this->img = null;
      require_once 'Url.php';
      if ($idUrl!=null AND $idUrl!='')
         $this->url = new Url($idUrl);
      else
         $this->url = null;

      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens el titol del slider
   * @return Si el curs té un titol, retorna el nom del slider. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció «No existeix el nom del slider»
   */
   private function __obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',1201);
      return $this->titol;
   }

   /*
   * @brief Obtens els destacats
   * @return Els destacats
   */
   private function __obtenirDestacat() {
      return $this->destacats;
   }

   /*
   * @brief Obtens la descripció curta
   * @return La descripció curta
   */
   private function __obtenirDesc() {
      return $this->description;
   }

   /*
   * @brief Obtens la classe
   * @return La classe
   */
   private function __obtenirClass() {
      return $this->classe;
   }

   /*
   * @brief Obtens el text del botó
   * @return Obtens el text del botó
   */
   private function __obtenirBoto() {
      return $this->boto;
   }

   /*
   * @brief Obtens el text del botó
   * @return Obtens el text del botó
   */
   private function __obtenirTextBoto() {
      return $this->textBoto;
   }

   /*
   * @brief Obtens la imatge
   * @return Si el slider té una imatge, retorna la imatge del slider, és a dir la caratula del
      slider. Altrament, null.
   * @throws Si el slider no té una imatge, envia l'excepció «No existeix la imatge del slider»
   */
   private function __obtenirImg() {
      if ($this->img==null)
         throw new Exception('',1202);
      return $this->img;
   }

   /*
   * @brief Obtens la Url del slider
   * @return Si el slider té una url, retorna l'enllaç amigable. Altrament, null.
   */
   private function __obtenirUrl() {
      return $this->url;
   }

   /**
   * @brief Obtens el slider amb item $numElement
   * @return Obtens el slider amb items $numElement
   */
   public function obtenirSlider($numElement) {
      $imatge = $this->__obtenirImg();
      $url = $this->__obtenirUrl();
      $destacat = $this->__obtenirDestacat();
      $desc = $this->__obtenirDesc();
      $button = $this->__obtenirTextBoto();
      $classe = $this->__obtenirClass();

      $linkImg = "https://www.prisma.cat".$imatge->obtenirLink();
      $dscImg = $imatge->obtenirAlt();

      $linkImgOrig = substr($linkImg, 0, -4);
      $linkImgWeb = $linkImgOrig.".webp";

      $linkImg400JPG=$linkImgOrig."-400.jpg";
      $linkImg800JPG=$linkImgOrig."-800.jpg";
      $linkImg1200JPG=$linkImgOrig."-1200.jpg";
      $linkImg400Webp=$linkImgOrig."-400.webp";
      $linkImg800Webp=$linkImgOrig."-800.webp";
      $linkImg1200Webp=$linkImgOrig."-1200.webp";

      $mostrar = "<div class='carousel-item item-".$numElement." ".$classe->obtenirText();
      if ($numElement==0) $mostrar .= " active";
      $mostrar.= "' role='option' aria-label=\"Slider amb informació d'interès\">";

      $mostrar.= "<picture>
         <source media='(max-width: 576px)' type='image/webp' data-srcset='".$linkImg400Webp."' srcset='".$linkImg400Webp."'>
         <source media='(max-width: 576px)' type='image/jpeg' data-srcset='".$linkImg400JPG."' srcset='".$linkImg400JPG."'>
         <source media='(max-width: 990px)' type='image/webp' data-srcset='".$linkImg800Webp."' srcset='".$linkImg800Webp."'>
         <source media='(max-width: 990px)' type='image/jpeg' data-srcset='".$linkImg800JPG."' srcset='".$linkImg800JPG."'>
         <source media='(max-width: 1400px)' type='image/webp' data-srcset='".$linkImg1200Webp."' srcset='".$linkImg1040Webp."'>
         <source media='(max-width: 1400px)' type='image/jpeg' data-srcset='".$linkImg1200JPG."' srcset='".$linkImg1040JPG."'>
         <source media='(min-width: 1401px)' type='image/webp' data-srcset='".$linkImgWeb."' srcset='".$linkImgWeb."'>
         <source media='(min-width: 1401px)' type='image/jpeg' data-srcset='".$linkImg."' srcset='".$linkImg."'>
         <source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
         <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
         <img data-src='".$linkImg."' alt=\"".$dscImg."\" class='h-100 w-100 img-slider lazyload'>
      </picture>";

      $mostrar.= "<div class='carousel-caption d-flex flex-column justify-content-center align-items-center h-100 w-100'>";
      $mostrar.= "<p class='titol font-weight-bold'>".$this->__obtenirTitol()->obtenirText()."</h3>";
      if ( $destacat!=null )
         $mostrar.= "<div class='destacat d-flex flex-row flex-wrap justify-content-center align-items-center'>".$destacat->obtenirText()."</div>";
      if ($desc!=null)
         $mostrar.= "<div class='description'>".$desc->obtenirText()."</div>";
      if ($this->__obtenirBoto()==1) {
         if ($url!=null) {
            if ($button==null) $textButton = "Més informació";
            else $textButton = $button->obtenirText();

            $linkUrl = $url->obtenirLink();
            $linkUrlTarget = $url->obtenirTarget();

            if ( $this->nomCampanya != null )
               $linkUrl .= "?utm_source=slider&utm_medium=web&utm_campaign=".$this->nomCampanya->obtenirText();

			$trobat = stristr($linkUrl, "://");

			if ($trobat == "https")
	            $mostrar.= "<button enllac='https://www.prisma.cat".$linkUrl."' class='button-slider' ";
    		else
				$mostrar.= "<button enllac='".$linkUrl."' class='button-slider' ";

			$mostrar.=  "target='".$linkUrlTarget."' title=\"".$url->obtenirTitle()."\">".$textButton."</button>";
         }
      }

      $mostrar.= "</div></div>";
      return $mostrar;
   }
}
?>
