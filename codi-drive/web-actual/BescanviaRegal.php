<?php
/**
* @class BescanviaRegal
* @brief Conté tota la informació relacionada amb una BescanviaRegal.
*/
class BescanviaRegal {
	private $nomCurs; /** Curs Nom del Curs del regal */
	private $codiCurs; /** Text Codi del curs del regal */
	private $codiRegal; /** Text Codi del regal del curs del regal */
	private $modalitat; /** Número Modalitat del regal. 1 si el regal és un curs en general, 2 si el regal és un curs en concret */
	private $hores; /** Número Hores del curs */
	private $dispositiu; /**< string Mobil si el dispositiu és mobil, altrament ordindador */

	/*********************************** FUNCIONS CONSTRUCTORS ***********************************/

   public function __construct($dispositiu) {
      $this->modalitat = 0;
      $this->codiCurs = null;
      $this->codiRegal = null;
      $this->nomCurs = null;
      $this->hores = 0;
		$this->dispositiu = $dispositiu;
   }

	/*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

	/*
   * @brief Obtens el codi del curs
   * @return Si el regal té un codi curs, retorna el codi del curs del curs. Altrament, null.
   * @throws Si el regal no té un codi curs, envia l'excepció «1903»
   */
   private function obtenirCodiCurs() {
      if ($this->codiCurs==null)
         throw new Exception('',1903);
      return $this->codiCurs;
   }

	/*
   * @brief Obtens el codi del regal del curs
   * @return Si el regal té un codi regal, retorna el codi del regal del curs. Altrament, null.
   * @throws Si el regal no té un codi regal, envia l'excepció «1904»
   */
   private function obtenirCodiRegal() {
      if ($this->codiRegal==null)
         throw new Exception('',1904);
      return $this->codiRegal;
   }

	/*
   * @brief Obtens la modalitat del bescanvia
   * @return Si la modalitat és 1 o 2, retorna la modalitat. Altrament, null.
   * @throws Si la modalitat és 0, envia l'excepció «1905»
   */
   private function obtenirModalitat() {
      if ($this->codiCurs==null)
         throw new Exception('',1905);
      return $this->codiCurs;
   }

	/*
   * @brief Obtens el curs del regal
   * @return Si el regal té un curs, retorna el curs. Altrament, null.
   * @throws Si el regal no té un curs, envia l'excepció «1906»
   */
   private function obtenirCurs() {
      if ($this->codiRegal==null)
         throw new Exception('',1906);
      return $this->codiRegal;
   }

	/*
   * @brief Obtens la modalitat del bescanvia
   * @return Si la modalitat és 1 o 2, retorna la modalitat. Altrament, null.
   * @throws Si la modalitat és 0, envia l'excepció «1907»
   */
   private function obtenirHores() {
      if ($this->hores==null)
         throw new Exception('',1907);
      return $this->hores;
   }

	/*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Mostra la pàgina del bescanvia
   * @return Retorna el contingut de la pàgina del bescanvia
   */
   public function mostrarPaginaBescanviaRegal() {
      $mostrar="<div class='container'><div class='row'><div class='col-12'>";
      $mostrar.=$this->__mostrarTitol();
      $mostrar.=$this->__mostrarBanner();
      $mostrar.=$this->__mostrarContingutInici();
      $mostrar.=$this->__modalCercantCursos();
      $mostrar.=$this->__modalLoading();
      $mostrar.=$this->__modalError();
      $mostrar.="</div></div></div>";
      return $mostrar;
   }

	/**
   * @brief Mostra la informació del titol
   * @return Mostra la informació del titol
   */
   private function __mostrarTitol() {
      $mostrar = "<div class='titol my-4'>
			<div class='d-flex flex-column flex-sm-row align-items-start align-items-sm-center py-2'>
				<h1 class='m-0'>Bescanvia la teva targeta regal!</h1>
			</div>
			<div class='d-flex flex-column flex-sm-row align-items-left'>
				<div class='subtitol flex-grow-1 py-2'>Vols bescanviar la teva targeta regal?</div>
				<button role='button' class='mesinfo border-radius-2 text-center position-relative
				flex-shrink-1 py-1 px-2 negreta500' title='Bescanvia la teva targeta regala'
				onclick=\"location.href='https://www.prisma.cat/regal'\">
				Vols regalar un curs?</button>
			</div>
		</div>";
      return $mostrar;
   }

	/**
   * @brief Mostra la imatge allargada del regal
   * @return Mostra la imatge allargada del regal
   */
   private function __mostrarBanner() {
      $altImg="Bescanvia la targeta regal!";
      $linkImg="https://www.prisma.cat/img/portades/bescanvia-curs.jpg";
      $linkImgWeb=substr($linkImg, 0, -4).".webp";

      $mostrar = "<div class='info-banner mb-4'><picture>";
      $mostrar .= "<source type='image/webp' class='w-100 border-radius-2 banner-img' ";
      $mostrar .= "data-srcset=\"".$linkImgWeb."\" alt=\"".$altImg."\"/>";
      $mostrar .= "<source type='image/jpeg' class='w-100 border-radius-2 banner-img' ";
      $mostrar .= "data-srcset=\"".$linkImg."\" alt=\"".$altImg."\"/>";
      $mostrar .= "<img role='img' class='w-100 border-radius-2 banner-img lazyload' ";
      $mostrar .= "data-src=\"".$linkImg."\"  alt=\"".$altImg."\"/>";
      $mostrar .= "</picture></div>";

      return $mostrar;
   }

	/**
   * @brief Mostra el contingut de la pàgina regala un curs
   * @return Mostra el contingut de la pàgina regala un curs
   */
   private function __mostrarContingutInici() {
		$mostrar = "<div class='separacio-peu border border-radius-2 bg-white px-4 py-2 pb-5'>
			<div id='cntPage' class='cntPage pt-2'>
				<p class='text-center'>
					Si tens un val regal per a un curs PrisMa, pots bescanviar-lo
					inserint, a continuació, el codi que trobaràs a la targeta.
				</p>
				<div class='d-flex flex-column align-items-center justify-content-center'>
					<div class='d-flex flex-row align-items-center justify-content-center w-100'>
						<div class='cnt-besc'>
							<div class='form-group field-wrap position-relative'>
								<label class='position-absolute mb-0'>
									<span class='camp'>Codi regal</span>
									<span class='req font-weight-bold'>*</span>
								</label>
								<input type='text' class='form-control' id='codiRegal' name='codiRegal' value=''>
								<span id='codiRegal_erroni' class='d-flex justify-content-center
									align-items-center px-2 position-absolute text-center text-white'></span>
							</div>
						</div>
					</div>
					<div class='d-flex flex-column flex-md-row align-items-center justify-content-center'>
						<button id='bescanviaCodi' class='boto-blau position-relative
							text-white border-0 border-radius-2 w-100 px-4 py-2 my-2'>
							Bescanvia la targeta regal</button>
					</div>
				</div>
			</div>
		</div>";
		return $mostrar;
	}

	/*
   * @brief Mostra el codi del regal del registre de la bd que té com a codi regal $codiRegal
   * @return Retorna el codi del regal del registre de la bd que té com a codi regal $codiRegal
   */
	public function buscarCursRegalat($codiRegal) {
		require_once 'ConnexioBBDD_PreparedStatment.php';
		$connexio = new ConnexioBBDDSTMT();
		$connexio->connectarBD();

		$cns = "SELECT CCURS FROM regal WHERE CODI LIKE ?";
      $stmt = $connexio->prepare($cns);
      $stmt->bind_param("s", $codiRegal);
      $stmt->execute();
      $stmt->bind_result($codiCurs);
		$stmt->fetch();
      $connexio->closeStmt();

		$connexio->desconectarBD();

		return $codiCurs;
	}

	/*
   * @brief Comprova si el $codiRegal es pot utilitzar.
   * @return Retorna true si el codi regal es pot utilitzar.
	Si el regal no està pagat, es mostra un avís indicant que el regal no està pagat.
	Si el regal està pagat i ja ha sigut utilitzat, es mostra un aví indicant que el regal ja ha estat utilitzat
   */
	public function codiRegalValid($codiRegal) {
		$mostrar = '';

		require_once 'ConnexioBBDD_PreparedStatment.php';
		$connexio = new ConnexioBBDDSTMT();
		$connexio->connectarBD();

		$cns = "SELECT FACT_REL, USAT FROM regal WHERE CODI LIKE ?";
      $stmt = $connexio->prepare($cns);
      $stmt->bind_param("s", $codiRegal);
      $stmt->execute();
		$stmt->store_result();
		if ( $stmt->num_rows() > 0 ) {
	      $stmt->bind_result($factura, $usat);
			$stmt->fetch();

			if ($factura==0) {
				$mostrar .= "<p>El codi <strong>".strtoupper($codiRegal)."</strong>
				està reservat perquè està pendent de finalitzar la comanda.</p>";
				$mostrar .= "<p>Per a qualsevol incidència, pots trucar al telèfon
				<span class='font-weight-bold'>972 21 75 65</span> o contacta amb nosaltres
				a través del <a class='font-weight-bold' href='https://www.prisma.cat/contacte'
				title='Contacta amb PrisMa'>formulari de contacte</a>.</p>";
			}
			else {
				if ($usat!=0) {
					$mostrar .= "<p>El codi <strong>".strtoupper($codiRegal)."</strong>
					ja ha estat utilitzat.</p>";
					$mostrar .= "<p>Per a qualsevol incidència, pots trucar al telèfon
					<span class='font-weight-bold'>972 21 75 65</span> o contacta amb nosaltres
					a través del <a class='font-weight-bold' href='https://www.prisma.cat/contacte'
					title='Contacta amb PrisMa'>formulari de contacte</a>.</p>";
				}
			}
		}
		else {
			$mostrar .= "<p>El codi <strong>".strtoupper($codiRegal)."</strong>
			no existeix.</p>";
			$mostrar .= "<p>Per a qualsevol incidència, pots trucar al telèfon
			<span class='font-weight-bold'>972 21 75 65</span> o contacta amb nosaltres
			a través del <a class='font-weight-bold' href='https://www.prisma.cat/contacte'
			title='Contacta amb PrisMa'>formulari de contacte</a>.</p>";
		}
		$connexio->closeStmt();
		$connexio->desconectarBD();

		return $mostrar;
	}

	/**
   * @brief Mostra el cursos que tenen ek mateix nombre d'hores que el curs $codiCurs segons la modalitat
   * @return Mostra el cursos que tenen ek mateix nombre d'hores que el curs $codiCurs segons la modalitat
		Si la modalitat és 1, indicarà que s'escolleixi el curs que es vol realitzar i
		es mostraran els cursos.
		Si la modalitat és 2, indicarà que el curs que li han regalat és $codiCurs i que si vol,
		pot canviar el curs, es mostraran els cursos i tindrà un botó per tornar «Enrere»
		$inici ens indicarà si és la primera vegada que es carrega o bé d'un canvi de curs
   */
   public function mostrarCursos($modalitat, $codiCurs, $inici) {
		 if (intval($codiCurs)!=0 && $modalitat==1) {
			//el codiCurs és un nombre d'hores
			//modalitat és 1, per tant, han regalat un curs en general.
			$hores = $codiCurs;
			$mostrar = "<p>
				T'han regalat un <span class='font-weight-bold'>curs de ".$hores." hores</span>.
			</p>
			<p>
				A continuació tens un llistat amb els cursos que disposem de ".$hores." hores.
				Tria el curs que vols realitzar.
			</p>";
		}
		 else {
			//el codiCurs és un curs
			//modalitat és 2, per tant, han regalat un curs en concret.

			// Es busca les hores i el id_preu de la pròxima edició oberta del curs
			$horesNom = $this->__buscarHoresNomCurs($codiCurs);
			$vectHoresNom = explode('|', $horesNom);
			$hores = $vectHoresNom[0];
			$nomCurs = $vectHoresNom[1];

			//buscar el nom i les hores del curs amb codi del curs $codiCurs
			if ($inici) {
				$mostrar = "<p>
					T'han regalat el curs <span class='font-weight-bold'>".$nomCurs."</span>.
				</p>";
			}
			else {
				$mostrar .= "<p>
				Havies tirat el curs <span class='font-weight-bold'>".$nomCurs."</span>.
				</p>";
			}
			$mostrar .= "<p>
			A continuació tens un llistat amb els cursos que disposem de <span class='font-weight-bold'>
			".$hores." hores</span>. Tria el curs que vols realitzar.
			</p>";
		}
		 $mostrar .= "<p>
			En cas que vulguis fer un curs de diferents hores, contacta amb nosaltres
			a través del <a class='font-weight-bold' href='https://www.prisma.cat/contacte'
			title='Contacta amb PrisMa'>formulari de contacte</a>.
		</p>";

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) AND VALOR LIKE ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("ss", $tipus, $valorInd);
      $tipus='dies-inscriu-cursos';
      $valorInd='%'.$hores.'%';
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $stmtParam->fetch();
      $valors = explode('|',$valor);
      $diesOberts = $valors[1];
      $connexio->closeStmt();

      require_once 'Curs.php';

		$mostrar .= "<div id='cnt-cursos' class='d-flex flex-wrap justify-content-center'>";
      $cnsExsiteixCurs = "SELECT CURS FROM curs WHERE
       DATAI+?>CURRENT_DATE AND PUBLIC=1 AND HORES=?
       AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
       GROUP BY CURS ORDER BY NOM_CURS";
      $stmtEx=$connexio->prepare($cnsExsiteixCurs);
      $stmtEx->bind_param("dd", $diesOberts, $hores);
      $stmtEx->execute();
      $stmtEx->store_result();
      if ( $stmtEx->num_rows() > 0 ) {
         $stmtEx->bind_result($codiCurs);
         while ($stmtEx->fetch()) {
            $curs = new Curs($codiCurs, $this->dispositiu);
            $mostrar .= $curs->mostrarCursBescanvia();
         }
      }
      $connexio->desconectarBD();
		$mostrar .= "</div>";


		if (!$inici) {
			$mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
				<button id='enrere' class='boto-blau position-relative
					text-white border-0 border-radius-2 w-100 px-4 py-2 my-2'>
					Enrere</button>
			</div>";
		}
		return $mostrar;
	}

	/**
   * @brief Mostra el nom del curs i les hores corresponent a la pròxima edició del curs amb codi $codiCurs
   * @return Mostra el nom del curs i les hores corresponent a la pròxima edició del curs amb codi $codiCurs
   */
	private function __buscarHoresNomCurs($codiCurs) {
		require_once 'ConnexioBBDD_PreparedStatment.php';
		$connexio = new ConnexioBBDDSTMT();
		$connexio->connectarBD();

		$consultaHoresPreu = "SELECT HORES, NOM_CURS FROM curs WHERE
			( (DATAI+8>CURRENT_DATE AND (HORES=30 OR HORES=40 OR HORES=60)) OR
			(DATAI+15>CURRENT_DATE AND (HORES=100)) ) AND CURS LIKE ? AND PUBLIC=1
			AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
			ORDER BY ANY, MES LIMIT 1";
		$stmtHoresPreu=$connexio->prepare($consultaHoresPreu);
		$stmtHoresPreu->bind_param("s", $codiCurs);
		$stmtHoresPreu->execute();
		$stmtHoresPreu->store_result();

		if ( $stmtHoresPreu->num_rows() > 0 ) {
			$stmtHoresPreu->bind_result($hores, $nomCurs);
			$stmtHoresPreu->fetch();
			$connexio->closeStmt();
	  }
	  else {
			$cnsHoresLastEd="SELECT HORES
			FROM curs WHERE CURS LIKE ? AND PUBLIC=1
			AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
			ORDER BY ANY DESC, MES DESC LIMIT 1";
			$stmtLastEd = $connexio->prepare($cnsHoresLastEd);
			$stmtLastEd->bind_param("s", $codiCurs);
			$stmtLastEd->execute();
			$stmtLastEd->bind_result($hores);
			$stmtLastEd->fetch();
			$connexio->closeStmt();

			$cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
			DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
			$stmtParam = $connexio->prepare($cnsParams);
			$stmtParam->bind_param("ss", $tipus, $orderBy);
			$tipus='dies-inscriu-cursos';
			$orderBy='VALOR';
			$stmtParam->execute();
			$stmtParam->bind_result($valor);
			$diesOberts=0;
			 while ($stmtParam->fetch()) {
				 $valors = explode('|',$valor);
				 if (count($valors) != 2)
					 $diesOberts=0;
				 else if (intval($valors[0])>0 && $valors[0]==$hores) //si el valor és un numero i les hores son iguals al curs
					 $diesOberts = $valors[1];
				 else if (intval($valors[0])<=0 && $valors[0]==$codiCurs) //si el valor no és un numero i el codi és igual al curs
					 $diesOberts = $valors[1];
			 }
			 $connexio->closeStmt();
			 // echo "diesOB:".$diesOberts;

			 $consultaHoresPreu = "SELECT HORES, NOM_CURS FROM curs WHERE
				 DATAI+?>CURRENT_DATE AND CURS LIKE ? AND PUBLIC=1
				 AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
				 ORDER BY ANY, MES LIMIT 1";
			 $stmtHoresPreu=$connexio->prepare($consultaHoresPreu);
			 $stmtHoresPreu->bind_param("ds", $diesOberts, $codiCurs);
			 $stmtHoresPreu->execute();
			 $stmtHoresPreu->store_result();
			 if ( $stmtHoresPreu->num_rows() > 0 ) {
				 $this->estat=1;
				 $stmtHoresPreu->bind_result($hores, $nomCurs);
				 $stmtHoresPreu->fetch();
			 }
			 $connexio->closeStmt();
	  }
	  $connexio->desconectarBD();
		return $hores."|".$nomCurs;
	}

	/**
	* @brief Mostra el formulari amb les dades de la persona a qui li vols regalar el curs.
	* @return Mostra el formulari del curs $codiCurs amb les dades de la persona a qui li vols regalar el curs
				on $origen es el valor de a qui li vols regalar, $desti es el valor de la persona
				que et regala el curs, $dedicatoria es el valor de la dedicatoria del curs,
				$hores es el nombre d'hores de la pròxima edició del curs amb codi $codiCurs,
				$preu és el preu corresponent del regal de la pròxima edició del curs amb codi $codiCurs
	*/
	public function mostrarFormulari($modalitat, $codiCurs, $inici, $midaPantalla) {
		/* Es busca les hores i el id_preu de la pròxima edició oberta del curs */
		$horesNom = $this->__buscarHoresNomCurs($codiCurs);
		$vectHoresNom = explode('|', $horesNom);
		$hores = $vectHoresNom[0];
		$nomCurs = $vectHoresNom[1];

		$this->hores = $hores;
		$this->codiCurs = $codiCurs;
		$this->nomCurs = $nomCurs;

		if ($inici) {
			$mostrar .= "<p>
				<span class='font-weight-bold'>Felicitats!</span> T'han regalat el
				curs <span class='font-weight-bold nom-curs'>".$nomCurs."</span> de
				<span class='font-weight-bold'>".$hores." hores</span>.
			</p>";
         $mostrar .= $this->alertaInformacio($codiCurs);
		}
		else {
				$mostrar .= "<p>Has escollit canviar el curs que t'havien regalat
					pel curs <span class='font-weight-bold nom-curs'>".$nomCurs."</span> de
					<span class='font-weight-bold'>".$hores." hores</span>.
				</p>";
            $mostrar .= $this->alertaInformacio($codiCurs);
		}
		$mostrar .= "<p>
			Per inscriure-t'hi, insereix les teves dades al formulari següent i
			tria la en què vols realitzar el curs.
		</p>
		<p>
			D'altra banda, si el vols canviar per un altre del mateix nombre
			d'hores, clica el botó «Canvia el curs».
		</p>
		<p>
			En cas que vulguis fer un curs de diferents hores, contacta amb nosaltres
			a través del <a class='font-weight-bold' href='https://www.prisma.cat/contacte'
			title='Contacta amb PrisMa'>formulari de contacte</a>.
		</p>";

		require_once 'Curs.php';
		$curs = new Curs($codiCurs, $this->dispositiu);
		$mostrar .= $curs->mostrarCursBescanviaInscripcio($midaPantalla);

		$mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<button id='canviCurs' class='mesinfo position-relative
				negreta500 border-0 border-radius-2 w-100 px-4 py-2 my-2'>
				Canvia el curs</button>
		</div>";
		$mostrar .= $this->__mostrarDadesPersonals();
      $mostrar .= $this->__mostrarDadesCurriculars();
      $mostrar .= $this->__mostrarDadesCurs();
      $mostrar .= $this->__mostrarFinal();
		return $mostrar;
	}

	/*
   * @brief Mostra l'apartat de dades personals de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades personals de la pàgina d'inscripció d'un curs
   */
   private function __mostrarDadesPersonals() {
     $mostrar = "<div class='form-dades'>";

		$mostrar .= "<h3>Dades personals</h3>";
		$mostrar .= "<div class='d-flex flex-column algin-items-center justify-content-center'>";

      $mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0'>
						<span class='camp'>Nom</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='nom' name='nom' autofocus>
					<span id='nom_cognom_erroni' class='d-flex justify-content-center
						align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0'>
						<span class='camp'>Cognoms</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='cog' name='cog' >
					<span id='cognom_erroni' class='d-flex justify-content-center
						align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
		</div>";
      $mostrar .= "<div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
	      <div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
		      <div class='form-group field-wrap position-relative'>
		         <div id='doc' class='select d-flex flex-column justify-content-center
						w-100 position-relative m-0'>
			         <span class='element-selected font-weight-normal w-100'>NIF/NIE</span>
			         <ul class='select-list position-absolute' style='display: none;'>
				         <li class='border-bottom m-0' id='doc-dni'><a href='#'>NIF/NIE</a></li>
				         <li class='border-bottom m-0' id='doc-passaport'><a href='#'>Altres</a></li>
			         </ul>
						<i class='fa triangle-inferior fa-angle-down position-absolute'></i>
					</div>
		      </div>
			</div>
	      <div class='col-12 col-md-3 pl-0 pr-0 pr-md-2' id='input_doc'>
		      <div class='form-group field-wrap position-relative'>
			      <label class='position-absolute mb-0'>
						<span class='camp'>DNI amb lletra</span>
				      <span class='req font-weight-bold'>*</span>
					</label>
			      <input type='text' class='form-control' id='nif' name='nif'>
			      <span id='dni_erroni' class='d-flex justify-content-center
						align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
	      <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
	      	<div class='form-group field-wrap position-relative'>
			      <label class='position-absolute mb-0'>
						<span class='camp'>Telèfon de contacte</span>
				      <span class='req font-weight-bold'>*</span>
					</label>
			      <input type='tel' class='form-control' id='telf' name='telf'
						pattern='[6-9]{1}[0-9]{8}' maxlength='9' >
			      <span id='telf_erroni' class='d-flex justify-content-center
						align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
      </div>";
      $mostrar .= $this->__modalCorreuValid();

      $mostrar .= "<div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
	      <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
		      <div class='form-group field-wrap position-relative'>
			      <label class='position-absolute mb-0'>
						<span class='camp'>Correu electrònic</span>
				      <span class='req font-weight-bold'>*</span>
					</label>
			      <input type='email' class='form-control' id='email' name='email' >
			      <span id='correu_erroni' class='d-flex justify-content-center
						align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
	      <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
		      <div class='form-group field-wrap position-relative'>
			      <label class='position-absolute mb-0'>
						<span class='camp'>Confirmaci&oacute del correu electrònic</span>
						<span class='req font-weight-bold'>*</span>
					</label>
			      <input type='email' class='form-control' id='email_conf' name='email_conf' >
			      <span id='correu_conf_erroni' class='d-flex justify-content-center
						align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
      </div>";

      $mostrar .= "<div class='d-flex flex-row algin-items-center justify-content-center'>
	      <div class='col-12 pr-0 pl-0'>
		      <div class='form-group field-wrap position-relative'>
			      <label class='position-absolute mb-0'>
						<span class='camp'>Adreça (carrer, número...)</span>
						<span class='req font-weight-bold'>*</span>
					</label>
			      <input type='text' class='form-control' id='adreca' name='adreca' maxlength='150' >
			      <span id='adreca_erroni' class='d-flex justify-content-center
						align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
      </div>";

      $mostrar .= "<div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
         <div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
	         <div class='form-group field-wrap position-relative' id='cp_box'>
		         <label class='position-absolute mb-0'>
						<span class='camp'>Codi postal</span>
						<span class='req font-weight-bold'>*</span>
					</label>
		         <input type='text' class='form-control' id='cp' name='cp' maxlength='5' >
		         <span id='cp_erroni' class='d-flex justify-content-center
						align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
         <div class='col-12 col-md-9 pl-0 pr-0 pr-md-2'>
	         <div class='form-group field-wrap cnt-poble' id='poble_box'>
		         <label class='position-absolute mb-0'>
						<span class='camp'>Poblaci&oacute</span>
						<span class='req font-weight-bold'>*</span>
					</label>
		         <input type='text' class='form-control' id='poble' name='poble' maxlength='50'>
		         <span id='poble_erroni' class='d-flex justify-content-center
						align-items-center px-2 position-absolute text-center text-white'></span>
		         <ul id='llistat_poblacions' style='display: none'
		         	class='select-list position-absolute' role='listbox'></ul>
	         </div>
			</div>
      </div>";

      $mostrar .= "</div>";

      $mostrar .= "</div>";
   	return $mostrar;
   }

   /*
   * @brief Mostra l'apartat de dades curriculars de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades curriculars de la pàgina d'inscripció d'un curs
   */
   private function __mostrarDadesCurriculars() {
		$mostrar = "<div class='form-dades'>";

	  $mostrar .= "<h3>Dades curriculars</h3>";

		$mostrar .= "<div class='d-flex flex-column algin-items-center justify-content-center'>";

		$mostrar .= "<div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<div id='perfil' class='select d-flex flex-column justify-content-center
						w-100 position-relative m-0'>
						<span class='element-selected font-weight-normal w-100'>Estic treballant a</span>
						<ul class='select-list position-absolute' style='display: none;'>
							<li class='border-bottom m-0' id='perfil-edInfantil03'>
								<a href='#'>Ed. Infantil (0-3)</a>
							</li>
							<li class='border-bottom m-0' id='perfil-edInfantil35'>
								<a href='#'>Ed. Infantil (3-5)</a>
							</li>
							<li class='border-bottom m-0' id='perfil-edPrimaria'>
								<a href='#'>Ed. Primària</a>
							</li>
							<li class='border-bottom m-0' id='perfil-edEspecial'>
								<a href='#'>Ed. Especial</a>
							</li>
							<li class='border-bottom m-0' id='perfil-edSecundaria'>
								<a href='#'>Ed. Secundària (Cicles Formatius, ESO, Batxillerat)</a>
							</li>
							<li class='border-bottom m-0' id='perfil-consultaPrivada'>
								<a href='#'>Consulta privada</a>
							</li>
							<li class='border-bottom m-0' id='perfil-noEsticTreballant'>
								<a href='#'>No estic treballant</a>
							</li>
							<li class='border-bottom m-0' id='perfil-altres'>
								<a href='#'>Altres</a>
							</li>
						</ul>
						<i class='fa triangle-inferior fa-angle-down position-absolute'></i>
					</div>
					<span id='perfil_erroni' class='select_erroni d-flex
						justify-content-center align-items-center px-2 position-absolute
						text-center text-white'></span>
				</div>
			</div>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<div id='titulacio' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
						<span class='element-selected font-weight-normal w-100'>Tinc la titulaci&oacute de</span>
						<ul class='select-list position-absolute' style='display: none;'>
							<li class='border-bottom m-0' id='titulacio-TEI'><a href='#'>TEI</a></li>
							<li class='border-bottom m-0' id='titulacio-edSecundaria'><a href='#'>Prof. Ed. Secundària</a></li>
							<li class='border-bottom m-0' id='titulacio-audicioLlenguatge'><a href='#'>Audici&oacute i Llenguatge</a></li>
							<li class='border-bottom m-0' id='titulacio-treballSocial'><a href='#'>Treball Social</a></li>
							<li class='border-bottom m-0' id='titulacio-psicologia'><a href='#'>Psicologia</a></li>
							<li class='border-bottom m-0' id='titulacio-psicopedagogia'><a href='#'>Psicopedagogia</a></li>
							<li class='border-bottom m-0' id='titulacio-pedagogia'><a href='#'>Pedagogia</a></li>
							<li class='border-bottom m-0' id='titulacio-logopedia'><a href='#'>Logopèdia</a></li>
							<li class='border-bottom m-0' id='titulacio-edInfantil'><a href='#'>Ed. Infantil</a></li>
							<li class='border-bottom m-0' id='titulacio-edPrimaria'><a href='#'>Ed. Primària</a></li>
							<li class='border-bottom m-0' id='titulacio-edEspecial'><a href='#'>Ed. Especial</a></li>
							<li class='border-bottom m-0' id='titulacio-edMusical'><a href='#'>Ed. Musical</a></li>
							<li class='border-bottom m-0' id='titulacio-edFisica'><a href='#'>Ed. F&iacutesica</a></li>
							<li class='border-bottom m-0' id='titulacio-edSocial'><a href='#'>Ed. Social</a></li>
							<li class='border-bottom m-0' id='titulacio-llEstrangeres'><a href='#'>Ll. Estrangeres</a></li>
							<li class='border-bottom m-0' id='titulacio-altres'><a href='#'>Altres</a></li>
							<li class='border-bottom m-0' id='titulacio-estudiant' class='estudiant'><a href='#'>Encara no tinc cap titulaci&oacute, s&oacutec estudiant de</a></li>
						</ul>
						<i class='fa triangle-inferior fa-angle-down position-absolute'></i>
					</div>
					<span id='titulacio_erroni' class='select_erroni d-flex
						justify-content-center align-items-center px-2 position-absolute
						text-center text-white' ></span>
				</div>
			</div>
		</div>
		<div id='perfils-altres'></div>
		<div id='titulacions-altres'></div>
		<div id='titulacions-secundaria'></div>
		<div id='titulacions-estudiant'></div>
		<div>
			<div class='form-group field-wrap position-relative'>
				<label class='position-absolute mb-0'>
					<span class='camp'>També tinc la titulaci&oacute de</span>
				</label>
				<input type='text' class='form-control' id='titol_de' name='titol_de' maxlength='150' >
			</div>
		</div>";

		$mostrar .= "</div>";

		$mostrar .= "</div>";
      return $mostrar;
   }

	/*
   * @brief Mostra l'apartat de dades del curs de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades del curs de la pàgina d'inscripció d'un curs
   */
   private function __mostrarDadesCurs() {
      $mostrar = "<div class='form-dades'>";

      $mostrar .= "<h3>Dades del curs</h3>";

      $mostrar .= "<div id='dates_curs'>
			<div class='d-flex flex-row algin-items-center justify-content-center'>
				<div class='col-12 pl-0 pr-0'>
					<div class='form-group field-wrap position-relative'>
						<div id='dates' class='select d-flex flex-column
							justify-content-center w-100 position-relative m-0'>
							<span class='element-selected font-weight-normal w-100'>
								Durant quines dates vols realitzar el curs? Tria l'edició
							</span>
							<ul class='select-list position-absolute'
							style='display: none;'>".$this->__buscarEdicions()."</ul>
							<i class='fa triangle-inferior fa-angle-down position-absolute'></i>
						</div>
						<span id='dates_erroni' class='select_erroni d-flex
							justify-content-center align-items-center px-2 position-absolute
							text-center text-white'></span>
					</div>
				</div>
			</div>
		</div>";

      $mostrar .= "<div id='missInformatiuEdicioRec'></div>";
      $mostrar .= "<div id='missInformatiuEdicioPerf'></div>";

      $mostrar .= "<div id='txtHint_mailing'></div>";

      $mostrar .= "</div>";
   	return $mostrar;
   }

   /*
   * @brief Retorna una alerta ssi existeix a la BD que ha d'apareixer aquesta alerta
   * @return Reviso si el curs té una alerta per posar a la inscripció.
   Si el curs disposa d'una alerta, es torna una alerta en un contenidor amb una
   estetica amb una exclamació i el missatge que existeix al base de dades
   */
   private function alertaInformacio($codi) {
      $textCodiCurs = new Text($codi);
      $codiCurs = $textCodiCurs->convertirMaj();

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      // Cercar paramatre X per veure si el curs actual té una alerta d'inscripció
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("sss", $tipus, $valor, $orderBy);
      $tipus = 'alerta-info';
      $valor = $codiCurs.'%';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->store_result();
      // Si té una alerta d'inscripció
      if ( $stmtParam->num_rows() > 0 ) {
         $stmtParam->bind_result($valorRes);
         $stmtParam->fetch();
         $arrValors = explode('|',$valorRes);
         $mostrar = $this->__mostrarAlerta( $arrValors[1] );
      }
      else {
         $mostrar = '';
      }
      $connexio->closeStmt();

      return $mostrar;
   }

   /*
   * @brief Retorna una alerta amb el missatge $missatgeAlerta
   * @return Retorna una alerta en un contenidor amb una estetica amb una exclamació i el missatge $missatgeAlerta
   */
   private function __mostrarAlerta( $missatgeAlerta ) {
      $mostrar = "<div style='background: #e8ecf5 !important; border: none'
      class='prisma-contact border-radius-2 px-3 pb-1 pt-3 my-2'>";
      $mostrar .= $missatgeAlerta;
      $mostrar .= "</div>";

      return $mostrar;
   }

	/*
   * @brief Mostra el llistat d'edicions disponibles del curs en el que es vol inscriure
   * @return Retorna el llistat d'edicions disponibles del curs en el que es vol inscriure
   */
   private function __buscarEdicions() {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $mostrar='';

      $codi = $this->obtenirCodiCurs();
      $hores = $this->obtenirHores();

      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);

      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='limit-editions';
      $orderBy='DATAI';
      $stmtParam->execute();
      $stmtParam->bind_result($limitEd);
      $stmtParam->fetch();

      $tipus='dies-inscriu-cursos';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $diesOberts=0;
      while ($stmtParam->fetch()) {
         $valors = explode('|',$valor);
			// echo $valors[0]." ".$valors[1]." ".$hores." ".$codi."<br />";
         if (count($valors) != 2)
            throw new Exception('',1304);
         else if (intval($valors[0])>0 && $valors[0]== $hores) //si el valor és un numero i les hores son iguals al curs
            $diesOberts = $valors[1];
         else if (intval($valors[0])<=0 && $valors[0]== $codi) //si el valor no és un numero i el codi és igual al curs
            $diesOberts = $valors[1];
      }
      $connexio->closeStmt();

      $cnsEd = "SELECT DATAI, DATAF, ANY, MES, c.ESTAT FROM curs AS c INNER JOIN aula AS a ON
         c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
         INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE c.CURS=? AND PUBLIC=1
         AND c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1 AND
         (DATEDIFF(DATAI + ?,CURRENT_DATE)>0) AND c.CURS NOT LIKE '%JOR%' AND
         (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
         OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
         AND ORDRE_TUTOR is not NULL)) ORDER BY ANY, MES LIMIT ?";
      $stmtEd = $connexio->prepare($cnsEd);
      $stmtEd->bind_param("sds", $codi, $diesOberts, $limitEd);
      $stmtEd->execute();
      $stmtEd->bind_result($datai, $dataf, $any, $mesDesc, $estatEd);
      require_once 'Edicio.php';
      while ( $stmtEd->fetch() ) {
         if ($estatEd!='T') {
            $textDataI = new Text($datai);
            $textDataF = new Text($dataf);
            $dataFL=$textDataF->convertirDataLlarga();

            $partsDataI = explode('-',$datai);
            $partsDataF = explode('-',$dataf);
            if ( $partsDataI[0] == $partsDataF[0]) {
               if ( $partsDataI[1] == $partsDataF[1])
                  $dataIL = intval($partsDataI[2]);
               else {
                  $textMesLlarg = new Text($partsDataI[1]);
                  $dataIL = intval($partsDataI[2])." ".$textMesLlarg->obtenirDeMesLlarg();
               }
            }
            else
               $dataIL=$textDataI->convertirDataLlarga();
            $mostrar .= "<li class='border-bottom m-0' id='dates-".$any."-".$mesDesc."'>";
            $mostrar .= "<a href='#'>Del dia ".$dataIL." al ".$dataFL."</a></li>";
         }
      }

      $connexio->closeStmt();
      $connexio->desconectarBD();

      return $mostrar;
   }

	/*
   * @brief Mostra l'apartat de dades finals de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades finals de la pàgina d'inscripció d'un curs
   */
   private function __mostrarFinal() {
      $mostrar = "<div class='form-dades'>";

      $mostrar .= "<h3>Tens algun comentari?</h3>";

      $mostrar .= "<div>
			<div class='form-group field-wrap position-relative'>
				<label class='position-absolute mb-0'>
					<span class='camp'>Comentaris</span>
				</label>
				<textarea class='form-control' id='comentaris' ></textarea>
			</div>
		</div>
      <div class='d-flex cnt_enviar_dades border-0 justify-content-center'>
	      <button id='form_enviar_dades' class='boto-blau position-relative
				text-white border-0 border-radius-2 px-4 py-2 my-2'>Enviar dades</button>
      </div>";

      $mostrar .= "</div>";
   	return $mostrar;
   }

	/*
   * @brief Mostra un modal de càrrega "buscant"
   * @return Retorna un modal de càrrega "buscant"
   */
   private function __modalCercantCursos() {
      $mostrar = "<div class='modal carrega' id='modalCercantCursos' tabindex='-1' role='dialog'
      aria-labelledby='modalCercantCursos' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div class='loading-wrapper'>
                     <div class='loading-text'>Cercant cursos...</div>
                     <div class='loading-content'></div>
                  </div>
               </div>
            </div>
         </div>
    	</div>";

   	return $mostrar;
   }

   /*
   * @brief Mostra un modal de càrrega "Espera un moment"
   * @return Retorna un modal de càrrega "Espera un moment"
   */
   private function __modalLoading() {
      $mostrar = "<div class='modal carrega' id='modalLoading' tabindex='-1' role='dialog'
      aria-labelledby='modalLoading' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div class='loading-wrapper'>
                     <div class='loading-text'>Espera un moment...</div>
                     <div class='loading-content'></div>
                  </div>
               </div>
            </div>
         </div>
    	</div>";

   	return $mostrar;
   }

   /*
   * @brief Mostra un modal d'avis
   * @return Retorna un modal d'avis
   */
   private function __modalCorreuValid() {
      $mostrar = "<div class='modal fade in' id='modalCorreuValid' tabindex='-1'
		role='dialog' aria-labelledby='modalCorreuValidTitle' aria-hidden='true'>
   		<div class='modal-dialog modal-dialog-centered modal-notify modal-warning' role='document'>
   			<div class='modal-content border-0'>
   				<div class='modal-header bg-warning justify-content-center'>
   					<p class='modal-title modal-title-warning font-weight-bold m-0'
							id='modalCorreuValidTitle'>Avís</p>
   					<button role='button' class='close' data-dismiss='modal' aria-label='Close'>
							<span aria-hidden='true' class='text-white'>×</span></button>
   				</div>
   				<div class='modal-body text-center' id='modalCorreuValidBody'></div>
   				<div class='modal-footer justify-content-center text-center border-0 pt-0 mb-2'>
   					<button role='button' data-dismiss='modal' class='btn btn-warning
							border-0 border-radius-2 text-center negreta500 m-0 mr-3'>D'acord</button>
   				</div>
   			</div>
   		</div>
   	</div>";

   	return $mostrar;
   }

   /*
   * @brief Mostra un modal d'error
   * @return Retorna un modal d'error
   */
   private function __modalError() {
      $mostrar = "<div class='modal fade in' id='modalErrors' tabindex='-1'
		role='dialog' aria-labelledby='modalErrorsTitle' aria-hidden='true'>
   		<div class='modal-dialog modal-dialog-centered modal-notify modal-danger' role='document'>
   			<div class='modal-content w-100'>
   				<div class='modal-header border-0 bg-danger text-white'>
   					<p class='modal-title modal-title-danger text-white' id='modalErrorsTitle'>Errors</p>
   					<button role='button' class='close' data-dismiss='modal' aria-label='Close'>
							<span aria-hidden='true' class='text-white'>×</span></button>
   				</div>
   				<div class='modal-body' id='modalErrorsBody'></div>
   				<div class='modal-footer justify-content-center text-center'>
   					<a role='button' class='btn btn-danger waves-effect waves-light'
							aria-label='Close' data-dismiss='modal'>Tanca</a>
   				</div>
   			</div>
   		</div>
   	</div>";

   	return $mostrar;
   }
}
