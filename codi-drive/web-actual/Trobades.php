<?php

/**
* @class Trobades
* @brief Conté tota la informació relacionada amb unles trobades en línia
*/

class Trobades {
	private $anyActiu; /* Any actiu de les trobades en línia */
	private $imgPortada; /* Imatge imatge de la portada de la pàgina de les trobades en línia */
	private $dispositiu; /** string Dispositiu amb el que s'accedeix a la web */

	/* #################################    FUNCIONS CONSTRUCTORS    ################################# */

	public function __construct( $dispositiu ) {
		$this->dispositiu = $dispositiu;
		$this->anyActiu = date("Y");

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		//Es busca l'últim any en que es va fer trobades en línia
		$cns = "SELECT DATA_INICI FROM trobades WHERE ESTAT = 1 ORDER BY DATA_INICI DESC LIMIT 1";
		$stmt = $connexio->prepare($cns);
		$stmt->execute();
      $stmt->store_result();
		if ( $stmt->num_rows() > 0 ) {
			$stmt->bind_result($dataInici);
			$stmt->fetch();
			$dataI = new DateTime($dataInici);
			$this->anyActiu = $dataI->format('Y');
		}
		$connexio->closeStmt();

		//Obtinc la id de la imatge de la portada
		$cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
		DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
		$stmtParam = $connexio->prepare($cnsParams);
		$stmtParam->bind_param("s", $tipus);
		$tipus = "id-img-trobades";
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
   * @brief Retorna l'atribut any actiu
   * @return Retorna l'atribut any actiu
   */
	public function obtenirAnyActiu() {
		return $this->anyActiu;
	}

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
	public function retornarPaginaTrobades() {
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
		$pagina .= $this->__mostrarSeccio5();
      return $pagina;
   }

	/*
   * @brief Retorna el contingut de la secció 1
   * @return Retorna el contingut de la secció 1
   */
	private function __mostrarSeccio1() {
		$titol = "Trobades en línia";
		$subtitol = "Càpsules a l'abast de tothom";
		$textButton = "Trobades en línia 2020";

		// $button = "<button role='button' class='mesinfo border-radius-2 text-center position-relative flex-shrink-1 py-1 px-2 negreta500'
		//  title='".$titol."' onclick=\"location.href='https://www.prisma.cat/trobades-en-linia-2020'\">".$textButton."</button>";

		$mostrar = "<div class='titol my-4'>
			<h1 class='m-0'>".$titol."</h1>
			<div class='d-flex flex-column flex-sm-row align-items-left'>
				<div class='subtitol flex-grow-1 py-2'>".$subtitol."</div>.".$button."
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
		$linkImg = "https://www.prisma.cat".$this->__obtenirImgPortada()->obtenirLink();
		$linkImgWebP = substr($linkImg, 0, -4).".webp";

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
		$text = "<p class='text-justify'>L'aprenentatge permanent és fonamental en la nostra professió docent, per la qual cosa oferim un conjunt de càpsules gravades sobre diferents temàtiques educatives que esperem que us siguin útils per acompanyar els infants i adolescents en el seu desenvolupament.";
		return $text;
	}

	/*
   * @brief Retorna el contingut de la secció 4
   * @return Retorna el contingut de la secció 4
   */
	private function __mostrarSeccio4() {
		$titol = "<h2>Properes trobades</h2>";
		$cntTrobades = $this->__mostrarProperesTrobades();

		$cntBoto = "<div class='d-flex flex-column flex-sm-row align-items-left mb-4'>
			<button role='button' id='avis-trobada' class='mesinfo border-radius-2 text-center position-relative flex-shrink-1 py-1 px-2 negreta500'
				title=\"Avisa'm 30 minuts abans de totes les trobades en línia del ".$this->obtenirAnyActiu()."\">
					<i class='fas fa-bell mr-2'></i>
					Avisa'm 30 minuts abans de cada trobada
			</button>
		</div>";

		/* Si no hi ha cap trobada per començar, no es posa títol i es busca totes les trobades en línia*/
		if ( $cntTrobades == "" ) {
			$titol = "";
			$cntTrobades = $this->__mostrarTotesTrobades();
			$cntBoto = "";
		}

		$mostrar = "<div class='cnt-trobades'>
			".$titol."
			".$cntTrobades."
			".$cntBoto."
		</div>";

		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de les properes trobades
   * @return Retorna el contingut de les properes trobades
   */
	private function __mostrarProperesTrobades() {
		$mostrar = "";

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		//Es busca totes les trobades en línia de l'últim any
		$cns = "SELECT ID_URL FROM trobades WHERE DATA_INICI LIKE ? AND
		DATA_INICI >= CURRENT_DATE AND ESTAT = ? AND (ID_VIDEOS IS NULL OR ID_VIDEOS = '')
		ORDER BY DATA_INICI";
		$stmt = $connexio->prepare($cns);
		$stmt->bind_param("sd", $searchYear, $estat);
		$searchYear = "%".$this->obtenirAnyActiu()."%";
		$estat = 1;
		$stmt->execute();
		$stmt->bind_result($idUrl);
		require_once 'Trobada.php';
		while ($stmt->fetch()) {
			$trobada = new Trobada($idUrl, $this->dispositiu);
			$mostrar .= $trobada->mostrarBlocTrobada();
		}
		$connexio->closeStmt();

		$connexio->desconectarBD();

		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de totes les trobades de l'any corresponent
   * @return Retorna el contingut de totes les trobades de l'any corresponent
   */
	private function __mostrarTotesTrobades() {
		$mostrar = "";

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		//Es busca totes les trobades en línia de l'últim any
		$cns = "SELECT TITOL, DNI_PONENT, DATA_INICI, CODI_DESCOMPTE, SHORT_DESC, ID_URL, ID_IMG, ID_VIDEO_DIRECTE, ID_VIDEOS
		FROM trobades WHERE DATA_INICI LIKE ? AND ESTAT = ? ORDER BY DATA_INICI";
		$stmt = $connexio->prepare($cns);
		$stmt->bind_param("sd", $searchYear, $estat);
		$searchYear = "%".$this->obtenirAnyActiu()."%";
		$estat = 1;
		$stmt->execute();
		$stmt->bind_result($titol, $dniPonent, $dataInici, $desc, $short_desc, $idUrl, $idImg, $idVideoDirecte, $idVideos);
		require_once 'Trobada.php';
		while ($stmt->fetch()) {
			$trobada = new Trobada($idUrl, $this->dispositiu);
			$mostrar .= $trobada->mostrarBlocTrobada();
		}
		$connexio->closeStmt();

		$connexio->desconectarBD();

		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de la secció 5
   * @return Retorna el contingut de la secció 5
   */
	private function __mostrarSeccio5() {
		$mostrar = '';
		if ( $this->__mostrarProperesTrobades() != '' && $this->__mostrarTrobadesConcloses() != '' ) {
			$titol = "<h2>Trobades concloses</h2>";
			$subtitol = "<p class='mb-4'>Podeu visualitzar la trobada en diferit un cop conlosa.</p>";
			$cntTrobades = $this->__mostrarTrobadesConcloses();

			$mostrar = "<div class='bg-destacat'>
			<div class='container'><div class='row'><div class='col-12'>
			<div class='cnt-trobades concloses'>
				".$titol."
				".$subtitol."
				".$cntTrobades."
			</div></div></div></div></div>";
		}
		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de les trobades concloses
   * @return Retorna el contingut de les trobades concloses
   */
	private function __mostrarTrobadesConcloses() {
		$mostrar = "";

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		//Es busca totes les trobades en línia de l'últim any
		$cns = "SELECT ID_URL FROM trobades WHERE DATA_INICI LIKE ? AND ESTAT = ? AND
		DATA_INICI <= CURRENT_TIME AND (ID_VIDEOS IS NOT NULL OR ID_VIDEOS != '')
		ORDER BY DATA_INICI";
		$stmt = $connexio->prepare($cns);
		$stmt->bind_param("sd", $searchYear, $estat);
		$searchYear = "%".$this->obtenirAnyActiu()."%";
		$estat = 1;
		$stmt->execute();
		$stmt->bind_result($idUrl);
		require_once 'Trobada.php';
		while ($stmt->fetch()) {
			$trobada = new Trobada($idUrl, $this->dispositiu);
			$mostrar .= $trobada->mostrarBlocTrobada();

		}
		$connexio->closeStmt();

		$connexio->desconectarBD();

		return $mostrar;
	}

	/*
   * @brief Per cada trobada de l'últim any actiu que encara no s'han realitzat,
	* afegeix el $email a la taula mailing_trobades amb l'identificador de la trobada pertinent
   * @return Per cada trobada de l'últim any actiu que encara no s'han realitzat,
	* afegeix el $email a la taula mailing_trobades amb l'identificador de la trobada pertinent i enviat = 0
   */
	public function addMailingAllTrobades($email) {
		echo "mailingTrobades".$email;

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		//Es busca totes les trobades en línia de l'últim any
		$cns = "SELECT ID_URL FROM trobades WHERE DATA_INICI LIKE ? AND DATA_INICI >= CURRENT_DATE AND ESTAT = 1 ORDER BY DATA_INICI";
		$stmt = $connexio->prepare($cns);
		$stmt->bind_param("s", $searchYear);
		$searchYear = "%".$this->obtenirAnyActiu()."%";
		$stmt->execute();
		$stmt->bind_result($idUrl);
		require_once 'Trobada.php';
		while ($stmt->fetch()) {
			$trobada = new Trobada($idUrl, $this->dispositiu);
			$trobada->addMailingTrobada($email);
		}
		$connexio->closeStmt();

		$connexio->desconectarBD();
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
