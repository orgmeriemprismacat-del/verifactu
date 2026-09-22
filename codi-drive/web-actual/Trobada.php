<?php

/**
* @class Trobada
* @brief Conté tota la informació relacionada amb una Trobada
*/

class Trobada {
	private $anyActiu; /* Any actiu de les trobades en línia */
	private $id; /* Int identificador de la trobada en línia */
	private $titol; /** Text Títol de la trobada */
	private $ponents; /** array[Tutor] Diferents Ponents de la trobada */
	private $dataInici; /** Text La data d'inici de la trobada */
	private $codiDesc; /** array[Text] Conté el  odi del descompte de la trobada i la durada d'aquest descompte */
	private $cursDesc; /** array[Text] Codi del curs sobre el qual es fa el descompte, el percentatge del descompte i l'edició on s'aplicarà aquest descompte */
	private $shortDesc; /** array[Text] Diferents descripcions */
	private $intro; /** array[Text] Diferents texts introductoris */
	private $objectius; /** array[Text] Diferents llistats d'objectius  */
	private $cursosRel; /** array[Curs] Llistats de cursos relacionats */
	private $url; /** Url La URL de la trobada */
	private $img; /** Imatge La imatge de la trobada */
	private $videoDirecte; /** Video El video des del qual es farà el directe de la trobada*/
	private $videos; /** array[Video] Els videos que queden com a resultat del directe de la trobada */
	private $documents; /** array[Text] Els documents relacionats amb la trobada */
	private $dispositiu; /** string Dispositiu amb el que s'accedeix a la web */
	private $estat; /** int Indica l'estat de la trobada en línia */

	/* #################################    FUNCIONS CONSTRUCTORS    ################################# */

	public function __construct( $idUrl, $dispositiu ) {
		$this->anyActiu = date("Y");

		require_once 'ConnexioBBDD_PreparedStatment.php';
		$connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();
		$cns = "SELECT ID, TITOL, DNI_PONENT, DATA_INICI, CODI_DESCOMPTE, CURS_DESCOMPTE, SHORT_DESC,
		DESCRIPCIO, OBJECTIUS, CURSOS_REL, ID_URL, ID_IMG, ID_VIDEO_DIRECTE, ID_VIDEOS, DOCUMENTS
		FROM trobades WHERE ESTAT = ? AND ID_URL = ? ORDER BY DATA_INICI";
		$stmt = $connexio->prepare($cns);
		$stmt->bind_param("dd", $estat, $idUrl);
		$estat = 1;
		$stmt->execute();
		$stmt->bind_result($id, $titol, $dniPonent, $dataInici, $codiDesc, $cursDesc, $shortDesc,
		$intro, $objectius, $cursosRel, $idUrl, $idImg, $idVideoDirecte, $idVideos, $docs);
		$stmt->fetch();
		$connexio->closeStmt();

		//Es busca l'últim any en que es va fer trobades en línia
		$cns = "SELECT DATA_INICI FROM trobades WHERE ESTAT = 1 ORDER BY DATA_INICI DESC LIMIT 1";
		$stmt = $connexio->prepare($cns);
		$stmt->execute();
		if ( $stmt->num_rows() > 0 ) {
			$stmt->bind_result($dataInici);
			$stmt->fetch();
			$dataI = new DateTime($datai);
			$this->anyActiu = $dataI->format('Y');
		}
		$connexio->closeStmt();

		$connexio->desconectarBD();

		$this->id = $id;

		if ( $titol != null AND $titol != '' )
			$this->titol = new Text($titol);
		else
			$this->titol= null;

		require_once 'Tutor.php';
		if ( $dniPonent != null AND $dniPonent != '' ) {
			$dniPonents = explode('|',$dniPonent);

			$connexio = new ConnexioBBDDSTMT();
	      $connexio->connectarBD();
			$cns = "SELECT ID_URL FROM personal WHERE DNI = ?";
			$stmt = $connexio->prepare($cns);
			$stmt->bind_param("s", $dni);
			foreach ($dniPonents as $valor) {
		      $dni = $valor;
				$stmt->execute();
				$stmt->bind_result($idUrlPonent);
				$stmt->fetch();

				$urlDoc = new Url($idUrlPonent);
				$this->ponents[] = new Tutor($urlDoc, $dispositiu);
			}
			$connexio->closeStmt();

			$connexio->desconectarBD();
		}
		else
			$this->ponents = [];

		if ( $dataInici != null AND $dataInici != '' )
			$this->dataInici = new Text($dataInici);
		else
			$this->dataInici = null;

		if ( $codiDesc != null AND $codiDesc != '' ) {
			$elements = explode('|',$codiDesc);
			foreach ($elements as $element)  {
				$this->codiDesc[] = $element;
			}
		}
		else
			$this->codiDesc = [];

		if ( $cursDesc != null AND $cursDesc != '' ) {
			$elements = explode('|',$cursDesc);
			foreach ($elements as $element)  {
				$this->cursDesc[] = $element;
			}
		}
		else
			$this->cursDesc = [];

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

		if ( $objectius != null AND $objectius != '' ) {
			$obj = explode('|',$objectius);
			foreach ($obj as $valor)  {
				$objectiu = new Text($valor);
				$objectiu->searchTextBD();
				$this->objectius[] = $objectiu;
			}
		}
		else
			$this->objectius = [];

		if ( $cursosRel != null AND $cursosRel != '' ) {
			$cursos = explode('|',$cursosRel);
			foreach ($cursos as $codi)  {
				require_once 'Curs.php';

				$curs = new Curs($codi, $dispositiu);
				if ( $curs->obtenirEstat() == 1 ) {
					$this->cursosRel[] = $curs;
				}
			}
		}
		else
			$this->cursosRel = [];

		require_once 'Url.php';
		if ( $idUrl != null AND $idUrl != '' )
			$this->url = new Url($idUrl);
		else
			$this->url = null;

		require_once 'Imatge.php';
		if ( $idImg != null AND $idImg != '' )
			$this->img = new Imatge($idImg);
		else
			$this->img = null;
		require_once 'Video.php';

		if ( $idVideoDirecte != null AND $idVideoDirecte != '' )
			$this->videoDirecte = new Video($idVideoDirecte);
		else
			$this->videoDirecte = null;

		if ( $idVideos != null AND $idVideos != '' ) {
			$videos = explode('|',$idVideos);

			foreach ($videos as $valor)  {
				$this->videos[] = new Video($valor);
			}
		}
		else
			$this->videos = [];

		if ( $docs != null AND $docs != '' )
			$this->documents = new Text($docs);
		else
			$this->documents = null;

		$this->dispositiu = $dispositiu;

		/* Calculem l'estat de la trobada en línia.
		ESTAT = 1 Si la trobada encara no ha començat
		ESTAT = 2 Si la trobada s'està emitint
		ESTAT = 3 Si la trobada ha finalitzat
		*/
		$dataI = new DateTime($dataInici);
		$dataAct = new DateTime("now");
		$diff = $dataAct->diff($dataI);

		if ( count($this->videos) == 0 ) { /* Si no tenim video, només podem estar en el estat 1 o 2 */
			if ( $diff->invert==1 ) {
				$this->estat = 2;
			}
			else {
				$this->estat = 1;
			}
		}
		else {
			$this->estat = 3;
		}
	}

	/* ################################# FUNCIONS CONSULTAR ATRIBUTS ################################# */

	/*
   * @brief Obtens el titol de la trobada
   * @return Si la trobada té un titol, retorna el nom de la trobada. Altrament, null.
   * @throws Si la trobada no té un titol, envia l'excepció «No existeix el nom de la trobada»
   */
   private function __obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',2300);
      return $this->titol;
   }

	/*
   * @brief Retorna la imatge de la portada del blocde trobades
   * @return Retorna la imatge de la portada del blocde trobades.
   * @throws Si la trobada no té una imatge de portada, envia l'excepció «No existeix la imatge de portada de la trobada»
   */
	private function __obtenirImgBloc() {
		if ($this->img==null) {
         throw new Exception('',2301);
      }
      return $this->img;
	}

	/*
   * @brief Retorna la data d'inici de la transmissió de la trobada
   * @return Retorna la data d'inici de la transmissió de la trobada
   * @throws Si la trobada no té una data d'inici, envia l'excepció «No existeix la data d'inici de la trobada»
   */
	public function obtenirDataInici() {
		if ($this->dataInici==null) {
         throw new Exception('',2302);
      }
      return $this->dataInici;
	}

	/*
   * @brief Retorna la llista d'elements del codi de descompte de la trobada
   * @return Retorna la llista d'elements del codi de descompte de la trobada
   */
	private function __obtenirCodiDescompte() {
      return $this->codiDesc;
	}

	/*
   * @brief Retorna la llista d'elements de l'edició amb descompte de la trobada
   * @return Retorna la llista d'elements de l'edició amb descompte de la trobada
   */
	private function __obtenirEdicoDescompte() {
      return $this->cursDesc;
	}

	/*
   * @brief Retorna la llista de descripcions curtes de la trobada
   * @return Retorna la llista de descripcions curtes de la trobada
   */
	private function __obtenirShortDesc() {
      return $this->shortDesc;
	}

	/*
   * @brief Retorna la llista de texts introductoris de la trobada
   * @return Retorna la llista de texts introductoris de la trobada
   */
	private function __obtenirTextIntro() {
      return $this->intro;
	}

	/*
   * @brief Retorna la llista d'objectius de la trobada
   * @return Retorna la llista d'objectius de la trobada
   */
	private function __obtenirObjectius() {
      return $this->objectius;
	}

	/*
   * @brief Retorna la llista de cursos relacionats de la trobada
   * @return Retorna la llista de cursos relacionats de la trobada
   */
	private function __obtenirCursosRel() {
      return $this->cursosRel;
	}

	/*
   * @brief Retorna la llista de ponents de la trobada
   * @return Retorna la lliste de ponents de la trobada
   */
	private function __obtenirPonents() {
      return $this->ponents;
	}

	/*
   * @brief Retorna l'enllaç de la trobada
   * @return Retorna l'enllaç de la la trobada
   */
	private function __obtenirUrl() {
		if ($this->url==null) {
         throw new Exception('',2303);
      }
		return $this->url;
	}

	/*
   * @brief Retorna la llista de videos de la trobada
   * @return Retorna la lliste de videos de la trobada
   */
	private function __obtenirVideos() {
      return $this->videos;
	}

	/*
   * @brief Retorna el video en directe de la trobada
   * @return Retorna el video en directe de la trobada
   */
	private function __obtenirVideoEnDirecte() {
      return $this->videoDirecte;
	}

	/*
   * @brief Retorna els documents de la trobada
   * @return Retorna els documents de la trobada
   */
	private function __obtenirDocuments() {
      return $this->documents;
	}

	/*
   * @brief Retorna l'atribut any actiu
   * @return Retorna l'atribut any actiu
   */
	public function obtenirAnyActiu() {
		return $this->anyActiu;
	}

	/*
   * @brief Retorna l'identificador
   * @return Retorna l'identif
   */
	public function obtenirId() {
		return $this->id;
	}

	/*
   * @brief Retorna l'estat
   * @return Retorna l'estat
   */
	public function obtenirEstat() {
		return $this->estat;
	}

	/* #################################  FUNCIONS MOSTRAR ELEMENTS  ################################# */

	public function mostrarBlocTrobada() {
		$altImg = $this->__obtenirImgBloc()->obtenirAlt();
		$linkImg = "https://www.prisma.cat".$this->__obtenirImgBloc()->obtenirLink();
		$linkImgWebP = substr($linkImg, 0, -4).".webp";

		$dataInici = $this->obtenirDataInici()->obtenirText();
		$dataI = new DateTime($dataInici);
		$dataAct = new DateTime("now");

		$day = $dataI->format('d');
		$year = $dataI->format('Y');
		$monthNum = $dataI->format('m');
		$hourMinut = $dataI->format('H:i');

		$objMonth = new Text($monthNum);
		$monthLlarg = $objMonth->obtenirMesLLarg();
		$month3Digits = substr($monthLlarg, 0, 3);
		$objMonthDig = new Text($month3Digits);
		$month = $objMonthDig->convertirMajPrimLletra();

		$shortDescs = "";
		foreach ($this->__obtenirShortDesc() as $descripcio)  {
			$shortDescs .= "<p class='desc-curta mt-4'>".$descripcio->obtenirText()."</p>";
		}

		$textPonents = "<i class='fas fa-user mr-2'></i>"; $cntPonents=0;
		foreach ($this->__obtenirPonents() as $ponent)  {
			if ( $cntPonents > 0 ) {
				if ( $cntPonents == count($this->__obtenirPonents()) -1 )
					$textPonents .= "<span class='mr-1 ml-1'> i </span>";
				else
					$textPonents .= "<span class='mr-1'>, </span>";
			}
			$textPonents .= "<span>".$ponent->obtenirNomComplet()->obtenirText()."</span>";
			$cntPonents++;
		}

		$diff = $dataAct->diff($dataI);

		if ( $this->obtenirEstat() == 2 )
			$enDirecte = "<div class='d-flex flex-sm-row align-items-center my-0 mb-2 ml-0 ml-md-2 text-now'>
				<i class='fas fa-circle mr-1 mb-1'></i>
				<span class='font-weight-bold' style=\"font-size: 1.3rem;font-family: 'Roboto';\">EN DIRECTE</span>
			</div>";

		$linkTrobada = $this->__obtenirUrl()->obtenirLink();

		if ( $this->obtenirEstat() == 1 OR $this->obtenirEstat() == 2 ) {
			$labelInfo = "<label>Més informació</label>";
		}
		else {
			$labelInfo = "<label>Visualitza la trobada</label>";
			$hourMinut = "Conclosa";
		}

		$mostrar = "<div class='cnt-trobada d-flex flex-column flex-md-row border-radius-2 mb-4' onclick='mostrarTrobada(\"".$linkTrobada."\")'>
			<div class='col-12 col-md-7 seccio1'>
				<div class='d-flex flex-column flex-sm-row w-100'>
					<div class='cnt-data mr-4 d-flex flex-column justify-content-center align-items-center'>
						<strong class='day'>".$day."</strong>
						<span class='month mt-1'>".$month."</span>
						<span class='month mt-1'>".$year."</span>
					</div>
					<div class='cnt-titol-info mt-2 mt-sm-0'>
						<div class='d-flex flex-column flex-sm-row justify-content-center justify-content-sm-start align-items-start align-items-sm-center'>
							<h3 class='my-0 mb-2'>".$this->__obtenirTitol()->obtenirTextHTML()."</h3>
							".$enDirecte."
						</div>
					  <div class='d-flex flex-wrap align-items-center info'>
					    ".$textPonents."
					    <i class='fa fa-clock ml-2'></i><span class='ml-2'>".$hourMinut."</span>
					  </div>
					</div>
				</div>
				".$shortDescs."
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
	/*
   * @brief Retorna la pàgina de trobada
   * @return Retorna la pàgina de trobada
   */
	public function retornarPaginaUnaTrobada() {
      $pagina = "<div class='container'><div class='row'><div class='col-12'>";
		$pagina .= $this->__mostrarSeccio1();
		$pagina .= $this->__mostraVideoDirecte();
		$pagina .= "<div id = 'cntPage' class='border border-radius-2 bg-white px-4 py-2'>";
		$pagina .= $this->__mostrarSeccio2();
		$pagina .= $this->__mostrarSeccio3();
		$pagina .= $this->__mostrarSeccio4();
		$pagina .= "</div>";
		$pagina .= $this->__mostrarSeccio5();
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
		$textPonents = ""; $cntPonents=0;
		foreach ($this->__obtenirPonents() as $ponent)  {
			if ($cntPonents > 0 ) {
				if ($cntPonents == count($this->__obtenirPonents())-1 )
					$textPonents .= " i ";
				else {
					$textPonents .= ", ";
				}
			}
			$textPonents .= $ponent->obtenirNomComplet()->obtenirText();
			$cntPonents++;
		}
		$enDirecte = "";
		if ( $this->obtenirEstat() == 2 )
			$enDirecte = "<div class='d-flex flex-sm-row align-items-center my-0 ml-0 ml-sm-2 text-now'>
				<i class='fas fa-circle mr-1 mb-1'></i>
				<span class='font-weight-bold' style=\"font-size: 1.3rem;font-family: 'Roboto';\">EN DIRECTE</span>
			</div>";

		$titol = $this->__obtenirTitol()->obtenirTextHTML();
		$anulada = "";

		if ( $this->id == 11 ) {
			$anulada = "<div class='text-white font-weight-bold ml-2' style='font-size: 1rem; background: #D91313;border-radius: 4px;font-family: Roboto,sans-serif;
            line-height: 18px;padding: 3px 6px; margin-bottom: 5px'>ANUL·LADA</div>";
		}

		$dataInici = $this->obtenirDataInici()->obtenirText();

		$dataI = new DateTime($dataInici);
		$day = $dataI->format('d');

		if ( $day == '1' or $day == '11') {
			$article = "l'";
		}
		else {
			$article = "el ";
		}
		$dataInici = $article.$this->obtenirDataInici()->convertirDataLlarga();


		$subtitol = "Trobada en línia ".$dataInici." amb ".$textPonents.$enDirecte;
		$textButton = "Trobades en línia ";

		$mostrar = "<div class='titol my-4'>
			<div class='d-flex flex-column flex-sm-row align-items-start align-items-sm-end py-2'>
				<h1 class='m-0'>".$titol."</h1>".$anulada."
			</div>
			<div class='d-flex flex-column flex-sm-row align-items-left'>
				<div class='d-flex flex-column flex-sm-row justify-content-center justify-content-sm-start align-items-start align-items-sm-center subtitol flex-grow-1 py-2'>".$subtitol."</div>
				<button role='button' class='mesinfo ml-0 ml-sm-2 border-radius-2 text-center position-relative flex-shrink-1 py-1 px-2 negreta500'
				 title=\"".$titol."\" onclick=\"location.href='https://www.prisma.cat/trobades-en-linia'\">".$textButton."</button>
			</div>
		</div>";
		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de la secció 2
   * @return Retorna el contingut de la secció 2
   */
	private function __mostrarSeccio2() {
		if ( $this->obtenirEstat() == 1 OR $this->obtenirEstat() == 3 ) {
			$mostrar .= $this->__mostraTextIntroductori();
			$mostrar .= $this->__mostraObjectius();
		}
		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de la secció 3
   * @return Retorna el contingut de la secció 3
   */
	private function __mostrarSeccio3() {
		if ( $this->obtenirEstat() == 2 ) {
			$mostrar .= $this->__mostraTextIntroductori();
			$mostrar .= $this->__mostraObjectius();
			$mostrar .= $this->__mostraTextDescompte();
		}
		else {
			$mostrar .= $this->__mostraPonents();
		}

		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de la secció 4
   * @return Retorna el contingut de la secció 4
   */
	private function __mostrarSeccio4() {
		if ( $this->obtenirEstat() == 1 ) {
			$mostrar = $this->__mostraApartatVideoPreDirecte();
		}
		else if ( $this->obtenirEstat() == 2 ) {
			$mostrar .= $this->__mostraPonents();
		}
		else if ( $this->obtenirEstat() == 3 ) {
			$mostrar .= $this->__mostraVideos();
			$mostrar .= $this->__mostraTextDescompte();
			$mostrar .= $this->__mostraDocuments();
		}
		return $mostrar;
	}

	/*
   * @brief Retorna el contingut de la secció 5
   * @return Retorna el contingut de la secció 5
   */
	private function __mostrarSeccio5() {
		$cursosRel = $this->__obtenirCursosRel();
		$mostrar = "<div class='separacio-peu d-flex flex-column'>";
		if ( count($cursosRel) > 0 ) {
			$mostrar .= "<h2 class='h1'>Cursos que et poden interessar</h2><div class='row'>";

			$mostrar .= "<div class='d-flex flex-row flex-wrap'>";
			foreach ($cursosRel as $curs)  {
				$mostrar .= $curs->crearTastet();
				$mostrar .= $curs->mostrarCursRelTrobada();
			}
			$mostrar .= '</div>';
		}
		$mostrar .= "</div></div>";
		return $mostrar;
	}

	/*
   * @brief Afegeix el $email a la taula mailing_trobades amb l'identificador de la trobada pertinent
   * @return Afegeix el $email a la taula mailing_trobades amb l'identificador de la trobada pertinent i enviat = 0
   */
	public function addMailingTrobada( $email ) {
		if ( $email != "" ) {
			require_once 'ConnexioBBDD_PreparedStatment.php';
	      $connexio = new ConnexioBBDDSTMT();
	      $connexio->connectarBD();

			$add = "INSERT INTO mailing_trobades (ID_TROBADA, EMAIL) VALUES (?, ?)";
			$stmt = $connexio->prepare($add);
			$stmt->bind_param("ds", $id, $email);
			$id = $this->obtenirId();
			$stmt->execute();
			$stmt->fetch();
			$connexio->closeStmt();

			$connexio->desconectarBD();

			require_once 'Mail.php';

			$titol = $this->__obtenirTitol();

			$data = new DateTime();
			$timestamp = $data->getTimestamp();

			$subjPrisMa="[Sol·licitud de subscripció de la trobada «".$titol->obtenirText()."»] núm.".$timestamp;
			$msgPrisMa ="<p><strong style='color: #496BAA'>ID Trobada</strong>: ".$id."</p>";
			$msgPrisMa ="<p><strong style='color: #496BAA'>Trobada</strong>: ".$titol->obtenirTextHTML()."</p>";
			$msgPrisMa .="<p><strong style='color: #496BAA'>Correu electrònic</strong>: ".$email."</p>";

			$subjAlumne="Avís trobada «".$titol->obtenirText()."» | PrisMa";
			$msgAlumne ="<p>Hola,</p>
			<p>Hem rebut una sol&middot;licitud d'avís de la propera trobada en línia per a l'adreça de correu electrònic <strong><a href='mailto:".$email."' target='_blank' style='color: #496BAA; text-decoration: none'>".$email."</a></strong>.</p>
			<p>30 minuts abans de la trobada «<strong style='color: #324569'>".$titol->obtenirTextHTML()."</strong>» rebràs un missatge de recordatori en aquesta mateixa adreça.</p>
			<p>Si l'avís no va adreçat a tu, simplement ignora aquest missatge.</p>
			<p>Equip PrisMa</p>";

			$prisma = new Mail();
			$prisma->addSubject($subjPrisMa);
			$prisma->addHeaders("Atenció a l'usuari", "atencio.usuari@prisma.cat", $email);
			$prisma->addTo('PrisMa Atenció Usuari<atencio.usuari@prisma.cat>');
			$prisma->addMissatge($msgPrisMa);
			$prisma->sendMessage();

			$alumne = new Mail();
			$alumne->addSubject($subjAlumne);
			$alumne->addHeaders("Atenció a l'usuari", "atencio.usuari@prisma.cat", "atencio.usuari@prisma.cat");
			$alumne->addTo($email);
			$alumne->addMissatge($msgAlumne);
			$alumne->sendMessage();
		}
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
		}
		return $intro;
	}

	/*
	* @brief Retorna els objectius separats amb llistats
	* @return Retorna els objectius separats amb llistats
	*/
	private function __mostraObjectius() {
		$objectius = '';
		if ( count($this->__obtenirObjectius()) > 0 ) {
			$objectius = '<h2>Objectius</h2>';
			$objectius .= "<ul class='llistes'>";
			foreach ($this->__obtenirObjectius() as $text)  {
				$objectius .= $text->obtenirText();
			}
			$objectius .= '</ul>';
		}
		return $objectius;
	}

	/*
	* @brief Retorna el video en directe amb el format que es necessita
	* @return Retorna el video en directe amb el format que es necessita
	*/
	private function __mostraVideoDirecte() {
		$mostrar = '';
		if ( $this->obtenirEstat() == 2 ) {
			$video = $this->__obtenirVideoEnDirecte();
			if ( $video != null ) {
				$mostrar = $video->mostrarVideoXat();
				$mostrar .= $this->__mostraDocuments();
			}
		}
		return $mostrar;
	}

	/*
	* @brief Retorna el text del descompte
	* @return Retorna el text del descompte
	*/
	private function __mostraTextDescompte() {
		$mostrar = '';

		if ( count($this->__obtenirCodiDescompte()) > 0 && count($this->__obtenirEdicoDescompte()) > 0) {
			$codiCurs = $this->__obtenirEdicoDescompte()[0];
			$percentatge = $this->__obtenirEdicoDescompte()[1];
			$mesDesc = $this->__obtenirEdicoDescompte()[2];

			$textDesc = new Text($mesDesc);
			$mesLletra = $textDesc->obtenirMesLlarg();

			$codiDescompte = $this->__obtenirCodiDescompte()[0];
			$diesCodiDescompte = $this->__obtenirCodiDescompte()[1];

			$dataInici = $this->obtenirDataInici()->obtenirText();

			$dataFi = new DateTime($dataInici);
			$dataFi->add(new DateInterval('P'.$diesCodiDescompte.'D'));

			$objText = new Text( $dataFi->format('Y-m-d') );
			$textDataFi = $objText->convertirDataLlarga();

			$dataAct = new DateTime("now");
			$dataFiAmbHora = $dataFi->format('Y-m-d')." 23:59:59";
			$dataFiCanviHora = new DateTime($dataFiAmbHora);

			$diff = $dataAct->diff($dataFiCanviHora);

			if ( $diff->invert == 0 ) {
				require_once 'Curs.php';

				$objCurs = new Curs($codiCurs);

				$nomCurs = $objCurs->obtenirTitol()->obtenirTextHTML();

				$mostrar = "<p>Gaudeix d'un descompte del <strong>".$percentatge."%</strong>
				en el curs <strong>".$nomCurs."</strong> en l'edició de <strong>".$mesLletra."</strong>
				utilitzant el codi de descompte <strong>".$codiDescompte."</strong>
				fins al <strong>".$textDataFi."</strong>.</p>";
			}
		}

		return $mostrar;
	}

	/*
	* @brief Retorna el text dels documents relacionats
	* @return Retorna el text dels documents relacionats
	*/
	private function __mostraDocuments() {
		$mostrar = '';

		if ( $this->__obtenirDocuments() != null ) {
			$mostrar = $this->__obtenirDocuments()->obtenirText();
		}

		return $mostrar;
	}

	/*
	* @brief Retorna els blocs dels ponents juntament amb el títol
	* @return Retorna els blocs dels ponents juntament amb el títol
	*/
	private function __mostraPonents() {
		$textPonents = '';

		$cntPonents = 0; $blocPonents="";
		foreach ($this->__obtenirPonents() as $ponent)  {
			$blocPonents .= $ponent->mostrarTutorTrobada();
			$cntPonents++;
		}

		if ( $cntPonents > 0 ) {
			$textPonents = "<h2>";
			if ( $cntPonents == 1 ) {
				$textPonents .= "Ponent";
			}
			else {
				$textPonents .= "Ponents";
			}
			$textPonents .= "</h2>";
			$textPonents .= $blocPonents;
		}


		return $textPonents;
	}

	/*
	* @brief Retorna l'apartat del video a l'estat 1
	* @return Retorna l'apartat del video a l'estat 1
	*/
	private function __mostraApartatVideoPreDirecte() {
		$cntBoto = "<div class='d-flex flex-column flex-sm-row align-items-left'>
			<button role='button' id='avis-trobada' class='boto-blau border-radius-2 text-center position-relative flex-shrink-1 py-2 px-3 negreta500 text-white border-0 w-100 mb-0'
				title=\"Avisa'm 30 minuts abans de la trobades en línia\">
					<i class='fas fa-bell mr-2'></i>
					Avisa'm 30 minuts abans de la trobada
			</button>
		</div>";

		if ( $this->id != 11 ) {
			$mostrar .= "<div class='d-flex flex-column justify-content-center align-items-center text-center bg-destacat-blau px-4 py-4 mb-3'>
				<h3 class='mt-0 mb-2'>Aquí visualitzareu la trobada</h3>
				<hr class='my-3 subratllat' />
				<div id='countdown-video-directe' class='countdown col-12 col-md-10 col-lg-8 col-xl-6 d-flex flex-column flex-sm-row justify-content-center align-items-center text-center font-weight-bold p-0'>
					<div class='col-12 col-sm-3 d-flex flex-column justify-content-center align-items-center px-4 my-3'>
						<span class='number'>1476</span>
						<span class='description mt-3'>Dies</span>
					</div>
					<div class='col-12 col-sm-3 d-flex flex-column justify-content-center align-items-center px-4 my-3'>
						<span class='number'>10</span>
						<span class='description mt-3'>Hores</span>
					</div>
					<div class='col-12 col-sm-3 d-flex flex-column justify-content-center align-items-center px-4 my-3'>
						<span class='number'>41</span>
						<span class='description mt-3'>Minuts</span>
					</div>
					<div class='col-12 col-sm-3 d-flex flex-column justify-content-center align-items-center px-4 my-3'>
						<span class='number'>18</span>
						<span class='description mt-3'>Segons</span>
					</div>
				</div>
				".$cntBoto."
			</div>";
		}

		return $mostrar;
	}

	/*
	* @brief Retorna l'apartat del video a l'estat 3
	* @return Retorna l'apartat del video a l'estat 3
	*/
	private function __mostraVideos() {
		$mostrar = '';
		if ( $this->obtenirEstat() == 3 ) {
			$videos = $this->__obtenirVideos();
			foreach ($videos as $video)  {
				$mostrar .= $video->mostrarVideo();
			}
		}
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
									<span>Avisa'm 30 minuts abans de la trobada <strong>".$this->__obtenirTitol()->obtenirTextHTML()."</strong></span>
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
   * @brief Retorna un modal per poder donar una confirmació del mailing
   * @return Retorna un modal per poder donar una confirmació del mailing
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

	/*********************************** FUNCIONS MODIFICAR ATRIBUTS ################################# */
}

?>
