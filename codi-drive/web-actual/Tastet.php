<?php

/**
* @class Tastet
* @brief Conté tota la informació relacionada amb una Trobada
*/

class Tastet {
	private $titol; /** Text Títol del tastet */
	private $codiCurs; /** String Codi curs del tastet */
	private $shortDesc; /** array[Text] Diferents descripcions */
	private $intro; /** array[Text] Diferents texts introductoris */
	private $url; /** Url La URL del tastet */
	private $cursOrig; /** Curs Curs original */
	private $imgPortada; /** Imatge La imatge del tastet */
	private $imgCurs; /** Imatge La imatge del tastet */
	private $dispositiu; /** string Dispositiu amb el que s'accedeix a la web */
	private $estat; /** int Indica l'estat del tastet en línia */

	/* #################################    FUNCIONS CONSTRUCTORS    ################################# */

	public function __construct( $idUrl, $dispositiu ) {
		require_once 'ConnexioBBDD_PreparedStatment.php';
		$connexio = new ConnexioBBDDSTMT();
    	$connexio->connectarBD();

		$cns = "SELECT TITOL, CODI_CURS, SHORT_DESC, PRESENTACIO,
		ID_URL, ID_IMG_LARGE, ID_IMG_SMALL, CURS_ORIG
		FROM reptes WHERE ESTAT = ? AND ID_URL = ?";

		$stmt = $connexio->prepare($cns);
		$stmt->bind_param("dd", $estat, $idUrl);
		$estat = 1;
		$stmt->execute();
		$stmt->bind_result($titol, $codiCurs, $shortDesc, $intro,
		$idUrl, $idImgLarge, $idImgSmall, $cursOrig);
		$stmt->fetch();
		$connexio->closeStmt();

		$connexio->desconectarBD();

		if ( $titol != null AND $titol != '' )
			$this->titol = new Text($titol);
		else
			$this->titol= null;

		if ( $codiCurs != null AND $codiCurs != '' ) {
			$this->codiCurs = $codiCurs;
		}
		else
			$this->codiCurs = '';

		if ( $shortDesc != null AND $shortDesc != '' ) {
			$descripcions = explode('|',$shortDesc);
			foreach ($descripcions as $valor)  {
				$descripcio = new Text($valor);
				$descripcio->searchTextBD();
				$this->shortDesc[] = $descripcio;
			}
		}
		else
			$this->shortDesc = [];

		if ( $intro != null AND $intro != '' ) {
			$texts = explode('|',$intro);
			foreach ($texts as $valor)  {
				$paragraf = new Text($valor);
				$paragraf->searchTextBD();
				$this->intro[] = $paragraf;
			}
		}
		else
			$this->intro = [];

		require_once 'Curs.php';
		if ( $cursOrig != null AND $cursOrig != '' ) {
			$cursOrigin = new Curs($cursOrig, $dispositiu);
			if ( $cursOrigin->obtenirEstat() == 1 ) {
				$this->cursOrig = $cursOrigin;
			}
		}
		else
			$this->cursOrig = '';

		require_once 'Url.php';
		if ( $idUrl != null AND $idUrl != '' )
			$this->url = new Url($idUrl);
		else
			$this->url = null;

		require_once 'Imatge.php';
		if ( $idImgLarge != null AND $idImgLarge != '' )
			$this->imgPortada = new Imatge($idImgLarge);
		else
			$this->imgPortada = null;

		if ( $idImgSmall != null AND $idImgSmall != '' )
			$this->imgCurs = new Imatge($idImgSmall);
		else
			$this->imgCurs = null;

		$this->dispositiu = $dispositiu;
		$this->estat = 1;
	}

	/* ################################# FUNCIONS CONSULTAR ATRIBUTS ################################# */

	/*
   * @brief Obtens el titol del tastet
   * @return Si la trobada té un titol, retorna el nom del tastet. Altrament, null.
   * @throws Si la trobada no té un titol, envia l'excepció «No existeix el nom del tastet»
   */
   private function __obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',5300);
      return $this->titol;
   }

	/*
   * @brief Retorna la imatge de la portada del bloc del tastet
   * @return Retorna la imatge de la portada del bloc del tastet.
   * @throws Si la trobada no té una imatge de portada, envia l'excepció «No existeix la imatge de portada del tastet»
   */
	private function __obtenirImgPortada() {
		if ($this->imgPortada==null) {
       throw new Exception('',5301);
    }
    return $this->imgPortada;
	}

	/*
   * @brief Retorna la imatge de la portada del bloc del tastet
   * @return Retorna la imatge de la portada del bloc del tastet.
   * @throws Si la trobada no té una imatge de portada, envia l'excepció «No existeix la imatge de portada del tastet»
   */
	private function __obtenirImgCurs() {
		if ($this->imgCurs==null) {
       throw new Exception('',5302);
    }
    return $this->imgCurs;
	}

	/*
   * @brief Retorna la llista de descripcions curtes del tastet
   * @return Retorna la llista de descripcions curtes del tastet
   */
	private function __obtenirShortDesc() {
      return $this->shortDesc;
	}

	/*
   * @brief Retorna la llista de texts introductoris del tastet
   * @return Retorna la llista de texts introductoris del tastet
   */
	private function __obtenirTextIntro() {
      return $this->intro;
	}

	/*
   * @brief Retorna el curs original del tastet
   * @return Retorna el curs original del tastet
   */
	private function __obtenirCursOrig() {
      return $this->cursOrig;
	}

	/*
   * @brief Retorna l'enllaç del tastet
   * @return Retorna l'enllaç de la la trobada
   */
	private function __obtenirUrl() {
		if ($this->url==null) {
         throw new Exception('',5303);
      }
		return $this->url;
	}

	/*
   * @brief Retorna l'estat
   * @return Retorna l'estat
   */
	public function obtenirEstat() {
		return $this->estat;
	}

	/* #################################  FUNCIONS MOSTRAR ELEMENTS  ################################# */

	public function mostrarBlocTastet() {
		$altImg = $this->__obtenirImgCurs()->obtenirAlt();
		$versioImg = $this->__obtenirImgCurs()->obtenirVersio();
		$linkImg = "https://www.prisma.cat".$this->__obtenirImgCurs()->obtenirLink();
		$linkImgWebP = substr($linkImg, 0, -4).".webp?ver=".$versioImg;

		$shortDescs = "";
		foreach ($this->__obtenirShortDesc() as $descripcio)  {
			$shortDescs .= "<p class='desc-curta mt-4'>".$descripcio->obtenirText()."</p>";
		}

		$labelInfo = "<label>Més informació</label>";

		$nivells.="<i class='fas fa-signal mr-2'></i>";
		$cntNiv = 0;
		while ( $cntNiv < count($this->__obtenirCursOrig()->obtenirNivells()) ) {
			 $niv=$this->__obtenirCursOrig()->obtenirNivell($cntNiv)->obtenirText();
			 if ($cntNiv!=0)
					$nivells .= "<span class='mx-1'>|</span>";
			 if ( $cntNiv == count($this->nivells) - 1)
					 $nivells .= "<span>".$niv."</span>";
			 else
					$nivells .= "<span class='m-0'>".$niv."</span>";
			 $cntNiv++;
		}

		$linkTastet = $this->__obtenirUrl()->obtenirLink();

		$mostrar = "<div class='cnt-tastet d-flex flex-column flex-md-row border-radius-2 mb-4' onclick='mostrarTastet(\"".$linkTastet."\")'>
			<div class='col-12 col-md-7 seccio1'>
				<div class='d-flex flex-column flex-sm-row w-100'>
					<div class='cnt-titol-info mt-2 mt-sm-0'>
						<div class='d-flex flex-column flex-sm-row justify-content-center justify-content-sm-start align-items-start align-items-sm-center'>
							<h3 class='my-0 mb-2'>".$this->__obtenirTitol()->obtenirTextHTML()."</h3>
						</div>
					  <div class='d-flex flex-wrap align-items-center info'>
					    <!--<i class='fa fa-clock mr-2'></i><span class=''>1 setmana</span>-->
							".$nivells."
					  </div>
					</div>
				</div>
				".$shortDescs."
				<p>Aquest curs està relacionat amb el curs <a href='".$this->__obtenirCursOrig()->obtenirUrl()->obtenirLink()."'>
				<strong>".$this->__obtenirCursOrig()->obtenirTitol()->obtenirTextHTML()."</strong></a>.</p>
			</div>
			<div class='col-12 col-md-5 seccio2 px-0 bg-white d-flex align-items-center w-100'>
				<div class='img-overlay h-100' style=''>
					<picture>
						<source type='image/webp' class='w-100 h-100'
							data-srcset='".$linkImgWebP."' alt='".$altImg."'>
						<source type='image/jpeg' class='w-100 h-100'
							data-srcset='".$linkImg."' alt='".$altImg."'>
						<img role='img' class='w-100 h-100 lazyload'
							data-src='".$linkImg."' alt='".$altImg."'>
					</picture>
					<div class='cnt-text-overlay position-absolute'>".$labelInfo."</div>
				</div>
			</div>
		</div>";

		return $mostrar;
	}

	public function mostrarTastetComCurs() {
		$codi = $this->codiCurs;

		$linkImg  ="https://www.prisma.cat".$this->__obtenirImgCurs()->obtenirLink();
		$dscImg = $this->__obtenirImgCurs()->obtenirAlt();
		$titolHTML = $this->__obtenirTitol()->obtenirTextHTML();
		$titol = $this->__obtenirTitol()->obtenirText();
		$linkUrl = "https://www.prisma.cat".$this->__obtenirUrl()->obtenirLink();

		$linkImg .= "?ver=".$versioImg;
      $linkImgOrig = substr($linkImg, 0, -4);
      $linkImgWeb = $linkImgOrig.".webp?ver=".$versioImg;

      $linkImg540JPG=$linkImgOrig."-345.jpg?ver=".$versioImg;
      $linkImg345JPG=$linkImgOrig."-345.jpg?ver=".$versioImg;
      $linkImg210JPG=$linkImgOrig."-210.jpg?ver=".$versioImg;
      $linkImg260JPG=$linkImgOrig."-260.jpg?ver=".$versioImg;
      $linkImg540Webp=$linkImgOrig."-345.webp?ver=".$versioImg;
      $linkImg345Webp=$linkImgOrig."-345.webp?ver=".$versioImg;
      $linkImg210Webp=$linkImgOrig."-210.webp?ver=".$versioImg;
      $linkImg260Webp=$linkImgOrig."-260.webp?ver=".$versioImg;

		// $pictureImg = "<picture>
	   //    <source media='(max-width: 576px)' type='image/webp' data-srcset='".$linkImg540Webp."' srcset='".$linkImg540Webp."'>
	   //    <source media='(max-width: 576px)' type='image/jpeg' data-srcset='".$linkImg540JPG."' srcset='".$linkImg540JPG."'>
	   //    <source media='(max-width: 990px)' type='image/webp' data-srcset='".$linkImg345Webp."' srcset='".$linkImg345Webp."'>
	   //    <source media='(max-width: 990px)' type='image/jpeg' data-srcset='".$linkImg345JPG."' srcset='".$linkImg345JPG."'>
	   //    <source media='(max-width: 1199px)' type='image/webp' data-srcset='".$linkImg210Webp."' srcset='".$linkImg210Webp."'>
	   //    <source media='(max-width: 1199px)' type='image/jpeg' data-srcset='".$linkImg210JPG."' srcset='".$linkImg210JPG."'>
	   //    <source media='(min-width: 1200px)' type='image/webp' data-srcset='".$linkImg260Webp."' srcset='".$linkImg260Webp."'>
	   //    <source media='(min-width: 1200px)' type='image/jpeg' data-srcset='".$linkImg260JPG."' srcset='".$linkImg260JPG."'>
		//
	   //    <source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
	   //    <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
	   //    <img data-src='".$linkImg."' alt=\"".$dscImg."\"
      //    	class='prisma-related-course-image w-100 lazyload' style='object-fit: cover'
		// 		onclick=\"location.href='".$linkUrl."'\" />
      // </picture>";
		$pictureImg = "<picture>
	      <source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
	      <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
	      <img data-src='".$linkImg."' alt=\"".$dscImg."\"
         	class='prisma-related-course-image w-100 lazyload' style='object-fit: cover'
				onclick=\"location.href='".$linkUrl."'\" />
      </picture>";

		$imgCourseOverlay = "<div class='prisma-related-course-overlay position-relative'>".$pictureImg."</div>";

		$headInfoCourse = "<div class='titol flex-1-0-auto'>
			<a role='link' class='color-text' href='".$linkUrl."'
				title=\"Mostra la informació del tastet ".$titol."\">
				<div class='h3 font-weight-bold'>".$titolHTML."</div>
			</a>
		</div>";
		$tagInfoCourse = "";
      $footerInfoCourse .= "<div class='related-course-footer clear-both d-flex flex-row align-items-center justify-content-between py-0'>
			<button class='mesinfo position-relative font-weight-bold border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-3'
			onclick=\"mostraInfoCurs('".$linkUrl."')\">
				Informació del tastet <i class='fas fa-long-arrow-alt-right'></i>
			</button>
		</div>";

		$cntInfoCourse = "<div class='related-course-content px-3 border-radius-2'>
			<div class='espai-titol-etiqueta d-flex flex-column'>
			".$headInfoCourse."
			".$tagInfoCourse."
			</div>
		</div>
		".$footerInfoCourse."";

		$vista = "<div class='col-12 col-sm-6 col-lg-3'>
		<div class='prisma-related-course border-radius-2'>
		".$imgCourseOverlay.$cntInfoCourse."</div></div>";

		return $vista;
	}

	/*
   * @brief Retorna la pàgina de trobada
   * @return Retorna la pàgina de trobada
   */
	public function retornarPaginaUnTastet() {
    	$pagina = "<div class='container'><div class='row'><div class='col-12'>";
		$pagina .= $this->__mostrarSeccio1();
		$pagina .= $this->__mostrarSeccio2();
		$pagina .= "<div id = 'cntPage' class='border border-radius-2 bg-white px-4 py-2'>";
		$pagina .= $this->__mostrarSeccio3();
		$pagina .= $this->__mostrarSeccio4();
		$pagina .= "</div>";
		$pagina .= $this->__mostrarSeccio5();
		$pagina .= $this->__modalLoading();
		$pagina .= $this->__modalError();
      $pagina .= "</div></div></div>";
      return $pagina;
   }

	/*
   * @brief Retorna el contingut de la secció 1
   * @return Retorna el contingut de la secció 1
   */
	private function __mostrarSeccio1() {
		$titol = $this->__obtenirTitol()->obtenirTextHTML();
		$titolCursOrig = $this->__obtenirCursOrig()->obtenirTitol()->obtenirTextHTML();

		$subtitol = "Tastet del curs ".$titolCursOrig;
		$textButton = "<i class='fas fa-long-arrow-alt-left mr-2'></i>Tastets";

		$mostrar = "<div class='titol my-4'>
			<div class='d-flex flex-column flex-sm-row align-items-start align-items-sm-end py-2'>
				<h1 class='m-0'>".$titol."</h1>
			</div>
			<div class='d-flex flex-column flex-sm-row align-items-left'>
				<div class='d-flex flex-column flex-sm-row justify-content-center justify-content-sm-start align-items-start align-items-sm-center subtitol flex-grow-1 py-2'><p class='mb-0'>".$subtitol."</p></div>
				<button role='button' class='mesinfo ml-0 ml-sm-2 border-radius-2 text-center position-relative flex-shrink-1 py-1 px-2 negreta500'
				 title=\"".$titol."\" onclick=\"location.href='https://www.prisma.cat/tastets'\">".$textButton."</button>
			</div>
		</div>";
		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de la secció 2
   * @return Retorna el contingut de la secció 2
   */
	private function __mostrarSeccio2() {
		$altImg = $this->__obtenirImgPortada()->obtenirAlt();
		$versioImg = $this->__obtenirImgPortada()->obtenirVersio();
		$linkImg = "https://www.prisma.cat".$this->__obtenirImgPortada()->obtenirLink();
		$linkImgWebP = substr($linkImg, 0, -4).".webp";

		$linkImg .= "?ver=".$versioImg;
		$linkImgWebP .= "?ver=".$versioImg;

		$containerBanner = "<div class='info-banner mb-4 position-relative'>
			".$this->__etiquetaGratuit()."
			<picture>
			<source type='image/webp' class='w-100 border-radius-2 banner-img'
				data-srcset='".$linkImgWebP."' alt='".$altImg."'>
			<source type='image/jpeg' class='w-100 border-radius-2 banner-img'
				data-srcset='".$linkImg."' alt='".$altImg."'>
			<img role='img' class='w-100 border-radius-2 banner-img lazyload'
				data-src='".$linkImg."' alt='".$altImg."'>
			</picture>
		</div>";

		$mostrar .= $containerBanner;
		return $mostrar;
	}

	private function __etiquetaGratuit() {
		$mostrar = "<div class='etiqPrice'>
       <div class='border'></div>
       <div class='nom'>GRATUÏT!</div>
     </div>";

		 return $mostrar;
	}

	/*
   * @brief Retorna el contingut de la secció 3
   * @return Retorna el contingut de la secció 3
   */
	private function __mostrarSeccio3() {
		$mostrar .= $this->__mostraTextIntroductori();

		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de la secció 4
   * @return Retorna el contingut de la secció 4
   */
	private function __mostrarSeccio4() {
		$mostrar = $this->__mostraBotoInscripcio();
		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de la secció 5
   * @return Retorna el contingut de la secció 5
   */
	private function __mostrarSeccio5() {
		$cursOrig = $this->__obtenirCursOrig();
		$mostrar = "<div class='separacio-peu d-flex flex-column'>";
		if ( $cursOrig->obtenirEstat() == 1 ) {
			$mostrar .= "<h2 class='h1'>Curs original del tastet</h2><div class='row'>";

			$mostrar .= "<div class='d-flex flex-row flex-wrap mx-3'>";
			$mostrar .= $cursOrig->crearTastet();
			$mostrar .= $cursOrig->mostrarCursBescanviaInscripcio(1200);
			$mostrar .= '</div>';
		}
		$mostrar .= "</div></div>";
		return $mostrar;
	}

	/*
	* @brief Retorna el texts introductoris separats amb paragrafs
	* @return Retorna el texts introductoris separats amb paragrafs
	*/
	private function __mostraTextIntroductori() {
		$intro = '';
		if ( count($this->__obtenirTextIntro()) > 0 ) {
			$intro = '<h2>Presentació</h2>';
			foreach ($this->__obtenirTextIntro() as $text)  {
				$intro .= "<p class='text-justify mb-2'>".$text->obtenirText()."</p>";
			}
			$intro .= "<p>
				Com tots els tastets, és <span class='font-weight-bold'>completament gratuït</span> i es pot fer de manera autònoma.
			</p>";
			$intro .= "<p>
				Si us agrada el que hi veieu i teniu més interès en el tema, us animem a fer el curs <a href='".$this->__obtenirCursOrig()->obtenirUrl()->obtenirLink()."'>
				<strong>".$this->__obtenirCursOrig()->obtenirTitol()->obtenirTextHTML()."</strong></a>, que amplia els continguts, compta amb acompanyament tutorial i està reconegut com a formació permanent del professorat.
			</p>";
			$intro .= "<p>
				Som-hi, doncs! Esperem que gaudiu de l'experiència!
			</p>";
		}
		return $intro;
	}

	/*
	* @brief Retorna el boto d'inscripció
	* @return Retorna el boto d'inscripció
	*/
	private function __mostraBotoInscripcio() {
		$titolTastet = $this->__obtenirTitol()->obtenirTextHTML();
		$linkTastet = $this->__obtenirUrl()->obtenirLink();

		$elemLink=explode('/',$linkTastet);
		$extUrl=$elemLink[count($elemLink)-1];

		$urlInsc="https://www.prisma.cat/inscripcions/tastets/".$extUrl;

		$mostrar = "<button role='button' class='inscripcio border-0
		border-radius-2 text-white position-relative text-center negreta500 position-relative text-center border-0 text-white'
		title=\"Inscriu-te al tastet ".$titolTastet."\" onclick=\"location.href='".$urlInsc."'\">
		<i class='fas fa-edit'></i> Inscripció gratuïta al tastet</button>";

		return $mostrar;
	}


	/* #################################  FUNCIONS MOSTRAR MODALS  ################################# */

	/*
   * @brief Retorna un modal de càrrega "Espera un moment"
   * @return Retorna un modal de càrrega "Espera un moment"
   */
   private function __modalLoading() {
      $modal = "<div class='modal carrega' id='modalLoading' tabindex='-1' role='dialog'
      aria-labelledby='modalLoading' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div class='loading-wrapper'>
                     <div class='loading-content'></div>
                  </div>
               </div>
            </div>
         </div>
    	</div>";

   	return $modal;
   }

	/*
   * @brief Retorna un modal d'error
   * @return Retorna un modal d'error
   */
   private function __modalError() {
      $modal = "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' aria-hidden='true'>
   		<div class='modal-dialog modal-dialog-centered modal-notify modal-danger' role='document'>
   			<div class='modal-content w-100'>
   				<div class='modal-header border-0 bg-danger text-white'>
   					<p class='modal-title modal-title-danger text-white float-left' id='modalErrorsTitle'>Errors</p>

   					<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button>
   				</div>
   				<div class='modal-body' id='modalErrorsBody'></div>
   				<div class='modal-footer justify-content-center text-center'>
   					<a role='button' class='btn btn-danger waves-effect waves-light' aria-label='Close' data-dismiss='modal'>Tanca</a>
   				</div>
   			</div>
   		</div>
   	</div>";

   	return $modal;
   }

}

?>
