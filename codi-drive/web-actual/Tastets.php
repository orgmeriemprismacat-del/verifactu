<?php

/**
* @class Tastets
* @brief Conté tota la informació relacionada amb unles trobades en línia
*/

class Tastets {
	private $imgPortada; /* Imatge imatge de la portada de la pàgina de les trobades en línia */
	private $dispositiu; /** string Dispositiu amb el que s'accedeix a la web */

	/* #################################    FUNCIONS CONSTRUCTORS    ################################# */

	public function __construct( $dispositiu ) {
		$this->dispositiu = $dispositiu;

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		//Obtinc la id de la imatge de la portada
		$cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
		DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
		$stmtParam = $connexio->prepare($cnsParams);
		$stmtParam->bind_param("s", $tipus);
		$tipus = "id-img-tastets";
		$stmtParam->execute();
		$stmtParam->bind_result($idImg);
		$stmtParam->fetch();
		$connexio->closeStmt();

		$connexio->desconectarBD();

		require_once 'Imatge.php';
		if ($idImg!=null and $idImg!='')
			$this->imgPortada = new Imatge($idImg);
		else
			$this->imgPortada = null;
	}

	/* ################################# FUNCIONS CONSULTAR ATRIBUTS ################################# */

	/*
   * @brief Retorna la imatge de la portada de la pàgina de trobades
   * @return Retorna l'objecte Imatge de la imatge de la portada de la pàgina de trobades
   */
	private function __obtenirImgPortada() {
		if ($this->imgPortada==null) {
         throw new Exception('',2200);
      }
      return $this->imgPortada;
	}

	/* #################################  FUNCIONS MOSTRAR ELEMENTS  ################################# */
	/*
   * @brief Retorna la pàgina de trobades
   * @return Retorna la pàgina de trobades
   */
	public function retornarPaginaTastets() {
      $pagina = "<div class='container'><div class='row'><div class='col-12'>";
		$pagina .= $this->__mostrarSeccio1();
		$pagina .= $this->__mostrarSeccio2();
		$pagina .= $this->__mostrarSeccio3();
		$pagina .= $this->__mostrarSeccio4();
		$pagina .= $this->__modalSubscription();
		$pagina .= $this->__modalSubscriptionOK();
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
		// $titol = "Tastets en línia";
		$titol = "Tastets";
		$tag = "<div class='nou mixt text-white ml-2 py-1' style='background: var(--bg-free) !important; font-size: 1.1rem;'>GRATUÏT</div>";
		$subtitol = "Càpsules d'aprenentatge a l'abast de tothom";

		$mostrar = "<div class='titol my-4'>
			<h1 class='m-0 d-flex flex-wrap align-items-sm-center'>".$titol.$tag."</h1>
			<div class='d-flex flex-column flex-sm-row align-items-left'>
				<div class='subtitol flex-grow-1 py-2'>".$subtitol."</div>
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

		$containerBanner = "<div class='info-banner mb-4'>
			<picture>
			<source type='image/webp' class='w-100 border-radius-2 banner-img'
				data-srcset='".$linkImgWebP."' alt='".$altImg."'>
			<source type='image/jpeg' class='w-100 border-radius-2 banner-img'
				data-srcset='".$linkImg."' alt='".$altImg."'>
			<img role='img' class='w-100 border-radius-2 banner-img lazyload'
				data-src='".$linkImg."' alt='".$altImg."'>
			</picture>
		</div>";

		return $containerBanner;
	}

	/*
   * @brief Retorna el contingut de la secció 3
   * @return Retorna el contingut de la secció 3
   */
	private function __mostrarSeccio3() {
		$text = "<p class='text-justify'>Els tastets són versions reduïdes de cursos de PrisMa a les quals us podeu inscriure en qualsevol moment. Ofereixen de forma completament <strong>gratuïta</strong>, <strong>asíncrona</strong> i <strong>autònoma</strong> una part dels continguts dels cursos per convidar-vos a obrir les portes a nous coneixements.</p>";
		return $text;
	}

	/*
   * @brief Retorna el contingut de la secció 4
   * @return Retorna el contingut de la secció 4
   */
	private function __mostrarSeccio4() {
		$cntTastets = $this->__mostrarTastets();

		$mostrar = "<div class='cnt-tastets'>
			".$cntTastets."
		</div>";

		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de tots els tastets
   * @return Retorna el contingut de tots els tastets
   */
	private function __mostrarTastets() {
		$mostrar = "";

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		//Es busca totes les trobades en línia de l'últim any
		$cns = "SELECT TITOL, SHORT_DESC, ID_URL, ID_IMG_SMALL, CURS_ORIG
		FROM reptes WHERE ESTAT = ? ORDER BY TITOL";
		$stmt = $connexio->prepare($cns);
		$stmt->bind_param("d", $estat);
		$estat = 1;
		$stmt->execute();
		$stmt->bind_result($titol, $short_desc, $idUrl, $idImg, $cursOrig);
		require_once 'Tastet.php';
		while ($stmt->fetch()) {
			$trobada = new Tastet($idUrl, $this->dispositiu);
			$mostrar .= $trobada->mostrarBlocTastet();
		}
		$connexio->closeStmt();

		$connexio->desconectarBD();

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
   * @brief Retorna un modal per poder subscriure'ns al mailing de cursos i avisar-nos 30 minuts abans
   * @return Retorna un modal per poder subscriure'ns al mailing de cursos i avisar-nos 30 minuts abans
   */
   private function __modalSubscription() {
      $modal = "<div class='modal fade in' id='modalSubscription' tabindex='-1' role='dialog' aria-labelledby='modalSubscription' style='padding-right: 17px; display: none;' aria-modal='true'>
			<div class='modal-dialog modal-dialog-centered modal-notify modal-success justify-content-center text-center ' role='document'>
				<div class='modal-content w-100 border-0'>
					<div class='modal-body p-5' id='modalSubscritionBody'>
						<button role='button' class='close' data-dismiss='modal' aria-label='Close' style=''><span aria-hidden='true'>×</span></button>
						<div class='text-left'>
							<div class='d-flex flex-row mb-3'>
								<label><input type='checkbox' class='mailing' id='mailing-all' value='1'>
									<span class='checkmark'></span>
									<span>Avisa'm 30 minuts abans de cada xerrada</span>
								</label>
							</div>
							<div class='d-flex flex-row mb-4'>
								<label><input type='checkbox' class='mailing' id='mailing-course' value='1'>
									<span class='checkmark'></span>
									<span>Vull rebre més informació de cursos i serveis de l'Associació PrisMa</span>
								</label>
							</div>
						</div>
						<div class='d-flex flex-lg-row flex-column algin-items-center justify-content-center cnt-mailing'>
							<div class='col-12 col-lg-9 p-0 h-100'>
								<div class='form-group field-wrap position-relative h-100 mb-0'>
									<label class=''>
										<span class='camp'>Correu electrònic</span>
										<span class='req'>*</span>
									</label>
									<input type='email' class='form-control email-mailing h-100' id='email-mailing' name='email-mailing'>
									<span id='correu_erroni' class='text-center text-white position-absolute'></span></div>
							</div>
							<div class='col-12 col-lg-3 p-0'>
								<a role='button' class='btn boto-blau text-white negreta500 w-100 h-100 m-0' id='send-subscrition'>Enviar</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>";

   	return $modal;
   }

	/*
   * @brief Retorna un modal per poder subscriure'ns al mailing de cursos i avisar-nos 30 minuts abans
   * @return Retorna un modal per poder subscriure'ns al mailing de cursos i avisar-nos 30 minuts abans
   */
   private function __modalSubscriptionOK() {
      $modal = "<div class='modal fade in' id='modalSubscriptionOK' tabindex='-1' role='dialog' aria-labelledby='modalSubscriptionOK' style='padding-right: 17px; display: none;' aria-modal='true'>
			<div class='modal-dialog modal-dialog-centered modal-notify modal-success justify-content-center text-center ' role='document'>
				<div class='modal-content w-100 border-0'>
					<div class='modal-header border-0 text-white background-prisma'><p class='modal-title modal-title-success text-white float-left' id='modalSuccessTitle'>Sol·licitud enviada</p><button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button></div>
					<div class='modal-body pb-0' id='modalSubscriptionOKBody'>
					</div>
					<div class='modal-footer justify-content-center text-center border-0'><a role='button' class='btn boto-blau text-white negreta500' id='close-sucess' aria-label='Close' data-dismiss='modal'>Tanca</a></div>
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

	/*********************************** FUNCIONS MODIFICAR ATRIBUTS ################################# */
}

?>
