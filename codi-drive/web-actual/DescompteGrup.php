<?php
// ini_set(‘display_errors’, 1);
// ini_set(‘display_startup_errors’, 1);
//
// error_reporting(E_ALL);
/**
* @class DescompteGrup
* @brief Conté tota la informació relacionada amb una Descompte per a grups o centres escolars.
*/

class DescompteGrup {
	private $curs; /** Curs Curs del descompte per grups marcat */
	private $hores; /** Numero Les hores del descompte per grup marcat ex: 40 */
   private $dispositiu; /** string Dispositiu amb el que s'accedeix a la web */
   private $nomCurs; /** Text Nom del curs que s'aplicarà el descompte */
   private $preuSenseDescompte; /** Numero Preu del curs sense descomptes */
   private $preus; /** Array Llista  on cada element és un array que conté el nombre mínim de participants, el nombre màxim de participnts i el preu amb descompte que s'aplica en aquest tram */
	private $dadesGrup; /** Array Llista  on cada element és un array que conté les dades de l'alumne */
	private $dates; /** String Les dates de l'edició que es vol inscriure */
	private $edicio; /** Array Llistat de dos posicions on la primera posicio indica l'any i la segona posicio el mes de l'edició escollida  */
	private $conegut; /** String Com has conegut a PrisMa */

	/*********************************** FUNCIONS CONSTRUCTORS ***********************************/

	public function __construct( $dispositiu ) {
		$this->dispositiu = $dispositiu;
		$this->curs = null;
		$this->hores = null;
		$this->nomCurs = null;
		$this->preuSenseDescompte = 0;
		$this->preus = [];
		$this->dadesGrup = [];
		$this->dates = null;
		$this->conegut = null;
		$this->edicio = [];
	}

	/*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

	/*
   * @brief Obtens el nom del curs
   * @return Si existeix el nom del curs, retorna un Text amb el nom del curs. Altrament, null.
   * @throws Si no existeix el nom del curs, envia l'excepció 2100
   */
	private function obtenirNomCurs() {
      if ($this->nomCurs==null)
         throw new Exception('',2100);
      return $this->nomCurs;
   }

	/*
   * @brief Obtens el codi del curs
   * @return Si existeix el codi del curs, retorna un Text amb el codi del curs. Altrament, null.
   * @throws Si no existeix el codi del curs, envia l'excepció 2101
   */
	private function obtenirCodiCurs() {
      if ($this->curs==null)
         throw new Exception('',2101);
      return $this->curs;
   }

	/*
   * @brief Obtens les hores del curs
   * @return Si existeixen les hores del curs, retorna un Numero amb les hores del curs. Altrament, null.
   * @throws Si no existeixen les hores del curs, envia l'excepció 2102
   */
	private function obtenirHores() {
      if ($this->hores==null)
         throw new Exception('',2102);
      return $this->hores;
   }

	/*
   * @brief Obtens el preu sense descompte
   * @return Si existeix el preu sense decomptes del curs, retorna un Numero amb el preu. Altrament, null.
   * @throws Si no existeix el preu sense decomptes del curs, envia l'excepció 2103
   */
	private function obtenirPreuSenseDescompte() {
      if ($this->preuSenseDescompte==null)
         throw new Exception('',2103);
      return $this->preuSenseDescompte;
   }

	/*
   * @brief Obtens les dates de la edició del curs
   * @return Si existeixen les dates de la edició del curs, retorna un Text amb les dates de la edició. Altrament, null.
   * @throws Si no existeix les dates de la edició, envia l'excepció 2104
   */
	private function obtenirDates() {
      if ($this->dates==null)
         throw new Exception('',2104);
      return $this->dates;
   }

	/*
   * @brief Obtens el com ens has conegut el curs
   * @return Si existeix el com ens has conegut del curs, retorna un Text amb el com ens has conegut del curs. Altrament, null.
   * @throws Si no existeix el com ens has conegut del curs, envia l'excepció 2105
   */
	private function obtenirConegut() {
      if ($this->conegut==null)
         throw new Exception('',2105);
      return $this->conegut;
   }

	/*
   * @brief Obtens l'any de l'edicio del curs
   * @return Si existeix l'any de l'edicio del curs, retorna l'any de l'edicio del curs. Altrament, null.
   * @throws Si no existeix l'any de l'edicio del curs, envia l'excepció 2106
   */
	private function obtenirAnyEdicio() {
      if ($this->edicio==null || $this->edicio[0] == null)
         throw new Exception('',2106);
      return $this->edicio[0];
   }

	/*
   * @brief Obtens el mes de l'edició del curs
   * @return Si existeixe el mes de l'edició del curs, retorna el mes de l'edició del curs. Altrament, null.
   * @throws Si no existeix el mes de l'edició del curs, envia l'excepció 2107
   */
	private function obtenirMesEdicio() {
      if ($this->edicio==null || $this->edicio[1] == null)
         throw new Exception('',2107);
      return $this->edicio[1];
   }


	/* #################################  FUNCIONS MOSTRAR ELEMENTS  ################################# */
	/*
   * @brief Retorna la pàgina inicial del descompte per a grups
   * @return Retorna el contingut inicial del descompte per a grups
   */
	public function retornarPaginaInicialDescompte() {
      $pagina = "<div class='container'><div class='row'><div class='col-12'>";
		$pagina .= $this->__obtenirTitol();
		$pagina .= $this->__obtenirBanner();
		$pagina .= $this->__obtenirContingutInici();
		$pagina .= $this->__modalCercantCursos();
		$pagina .= $this->__modalLoading();
		$pagina .= $this->__modalError();
      $pagina .= "</div></div></div>";
      return $pagina;
   }

	/**
   * @brief Retorna la informació del titol
   * @return Retorna la informació del titol
   */
	private function __obtenirTitol() {
		$container_titol = "<div class='titol my-4'>
			<h1>Descompte per a grups i centres escolars</h1>
			<div class='d-flex flex-column flex-sm-row align-items-left'>
				<div class='subtitol flex-grow-1 py-2'>Gaudiu de diferents descomptes a partir de tres persones!</div>
			</div>
		</div>";
		return $container_titol;
	}

	/**
   * @brief Retorna la imatge allargada del regal
   * @return Retorna la imatge allargada del regal
   */
	public function __obtenirBanner() {
		$altImg = "Descompte per a grups o centres escolars!";
		$linkImg = "https://www.prisma.cat/img/portades/descompte-grups.jpg";
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

	/**
   * @brief Retorna el contingut de la pàgina d'inici
   * @return Retorna el contingut de la pàgina d'inici
   */
	public function __obtenirContingutInici() {
		$infoTextDescompte = "
		<p>Des de l’Associació PrisMa oferim diferents <strong>descomptes per a grups i centres escolars</strong>.</p>

		<p>Així, si tres persones o més us inscriviu com a grup en un dels nostres <strong>cursos en línia</strong>, se us aplicarà un descompte segons el nombre d’integrants del grup:</p>
		";

		//Es calcula el nombre de grups de participants per aplicar descomptes per a grup
		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		//Es busca el nº de descomptes diferents per nombre de participants
		$cnsGrupsPart="SELECT NUM_ALUMN_MIN, NUM_ALUMN_MAX FROM descomptes_grup
		WHERE DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)
		GROUP BY NUM_ALUMN_MIN, NUM_ALUMN_MAX";
		$stmtGrupsPart = $connexio->prepare($cnsGrupsPart);
		$stmtGrupsPart->execute();
		$stmtGrupsPart->bind_result($numAlumnMin, $numAlumnMax);
		while ($stmtGrupsPart->fetch()) {
			$numeroParticipants[] = [$numAlumnMin, $numAlumnMax];
		}
		$connexio->closeStmt();

		//Es preapara el contenidor dels cursos
		$containerCursos = "";

		// Es calculen les hores disponibles
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='dies-inscriu-cursos';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $diesObets=0;
      while ($stmtParam->fetch()) {
         $valors = explode('|',$valor);

         $hores = intval($valors[0]);
         $diesOberts = $valors[1];

         if ($hores!=0 && $hores != 10) {
            $vectHores[] = $hores;
            $vectDiesOberts[] = $diesOberts;
         }
      }
      $connexio->closeStmt();

      $connexio2 = new ConnexioBBDDSTMT();
      $connexio2->connectarBD();
      $cnsExsiteixCurs = "SELECT ID_PREU FROM curs WHERE
        DATEDIFF(DATE_ADD(DATAI, INTERVAL ? DAY),CURRENT_DATE)>0 AND PUBLIC=1 AND HORES=?
        AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
        AND HORES != 10 AND ESTAT != '0' AND PUBLIC = 1
        ORDER BY ANY, MES LIMIT 1";
      $stmtEx=$connexio->prepare($cnsExsiteixCurs);
      $stmtEx->bind_param("dd", $diesObertsI, $horesI);

      $i = 0; $horesNoExisteixen = 0;
      while ( $i < count($vectHores) ) {
         $diesObertsI = $vectDiesOberts[$i];
         $horesI = $vectHores[$i];
         $stmtEx->execute();
         $stmtEx->store_result();
         if ( $stmtEx->num_rows() <= 0 ) {
            // echo "elimino hores ".$vectHores[$i]." ja que no hi ha cap curs d'aquestes hores<br>";
            array_splice( $vectHores , $i, 1);
         }
         else $i++;
      }

      sort($vectHores, SORT_NUMERIC);

      $numCategHores = count($vectHores);

      if ( $numCategHores > 5 ) {
         $numDivisible = round( $numCategHores / 2, 0, PHP_ROUND_HALF_UP);
      }

      $cnsPreu = "SELECT IMPORT FROM preu WHERE ID=? AND
        DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
      $stmtPreu=$connexio2->prepare($cnsPreu);
      $stmtPreu->bind_param("d", $idPreu);

		$thSenseDescompte = "<td scope='col'>Sense descompte</td>";
      $thHores = ''; $texthores = '';
      for ($i=0; $i<$numCategHores; $i++) {
			$thHores .= "<th scope='col'>".$vectHores[$i]." h</th>";

         $diesObertsI = $vectDiesOberts[$i];
         $horesI = $vectHores[$i];
         $stmtEx->execute();
         $stmtEx->store_result();
         if ( $stmtEx->num_rows() > 0 ) {
            $stmtEx->bind_result($idPreu);
            $stmtEx->fetch();

            $stmtPreu->execute();
            $stmtPreu->bind_result($preu);
            $stmtPreu->fetch();

            $objPreu = new Numero($preu);
      		$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();

				$thSenseDescompte .= "<td scope='col'>".$preu." €</td>";
				$idsPreus[] = $idPreu;

            $margleLG = "ml-lg-2 mr-lg-2";
            if ( $i==0 || ( $numCategHores > 5 && ($i == 0 || $i == $numDivisible || $i == $numDivisible*2)) ) $margleLG = "ml-lg-0 mr-lg-2";
            else if ( $i==$numCategHores-1 ||  ( $numCategHores > 5 && ( $i == ($numDivisible-1) || $i == (($numDivisible*2)-1) || $i == (($numDivisible*3)-1) || $i == count($vectHores)-1 ) ) ) $margleLG = "ml-lg-2 mr-lg-0";
            if ( $numCategHores > 5 && ($i == 0 || $i == $numDivisible || $i == $numDivisible*2) ) $texthores.="<div class='d-flex flex-column flex-sm-row w-100'>";
            $texthores.="<button id='hores-".$horesI."' onclick='mostraCursos(".$horesI.")' ";
            $texthores.="class='mesinfo position-relative font-weight-bold border-0 border-radius-2 w-100 ";
            $texthores.="flex-shrink-1 px-2 py-2 mr-0 mr-sm-2 ".$margleLG." ml-sm-0 mb-4'>";
            $texthores.=$horesI." hores</button>";

            if ( $numCategHores > 5 && ( $i == ($numDivisible-1) || $i == (($numDivisible*2)-1) || $i == (($numDivisible*3)-1) || $i == count($vectHores)-1 ) ) {
               $texthores.="</div>";
            }
         }
      }
      $connexio2->closeStmt();
      $connexio2->desconectarBD();
      $connexio->closeStmt();

		$containerCursos .= "<p>A més, si sou <strong>15 alumnes o més</strong> podreu gaudir d’una <strong>aula exclusiva</strong> per al vostre grup.</p>";
		$containerCursos .= "<p>Tots els nostres cursos permeten flexibilitat horària i de connexió (de manera que cada membre del grup podrà participar-hi al seu propi ritme) i estan reconeguts pel Departament d'Educació com a formació permanent del professorat.</p>";

		if ($texthores!='') {
         if ( $numCategHores <= 5 ) $flexRow = 'flex-md-row';
         else $flexRow = 'flex-xl-row';

			$containerCursos .= "<p>Trieu el nombre d'hores corresponent al curs que voleu realitzar:</p>";
	      $containerCursos .= "<div id='cnt-hores' class='d-flex flex-column ".$flexRow."'>".$texthores."</div>";
         $containerCursos .= "<div id='cnt-cursos' class='d-flex flex-wrap'></div>";
      }

		$trTrams = "";
		for ($i=0; $i<count($numeroParticipants); $i++) {
			$trTrams .= "<tr>";

			//primera columna
			$trTrams .= "<td>";
			if ( $numeroParticipants[$i][1] == null or $numeroParticipants[$i][1] == '' ) {
				$trTrams .= ">".$numeroParticipants[$i][0];
			}
			else {
				$trTrams .= "".$numeroParticipants[$i][0]."-".$numeroParticipants[$i][1];
			}
			$trTrams .= " persones</td>";
			//fi de la primera columna

			for ($j=0; $j<count($idsPreus); $j++) {
				if ($numeroParticipants[$i][1]=='' or $numeroParticipants[$i][1]==null) {
					$cnsPreu = "SELECT preu FROM descomptes_grup WHERE ID_PREU=? AND NUM_ALUMN_MIN=? AND NUM_ALUMN_MAX IS NULL AND
					  DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
					$stmtPreu=$connexio->prepare($cnsPreu);
					$stmtPreu->bind_param("dd", $idPreu, $numAlumnMin);
				}
				else {
					$cnsPreu = "SELECT preu FROM descomptes_grup WHERE ID_PREU=? AND NUM_ALUMN_MIN=? AND NUM_ALUMN_MAX=? AND
					  DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
					$stmtPreu=$connexio->prepare($cnsPreu);
					$stmtPreu->bind_param("ddd", $idPreu, $numAlumnMin, $numAlumnMax);

				}
				$idPreu = $idsPreus[$j];
				$numAlumnMin = $numeroParticipants[$i][0];
				$numAlumnMax = $numeroParticipants[$i][1];
				$stmtPreu->execute();
				$stmtPreu->bind_result($preu);
				$stmtPreu->fetch();
				$connexio->closeStmt();

				$objPreu = new Numero($preu);
				$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();

				$trTrams .= "<td>".$preuFormatCorrecte." €</td>";
			}

			$trTrams .= "</tr>";
		}

		$connexio->desconectarBD();

		$tableDescomptes = "<div class='table-descomptes'>
			<table id='taula-descomptes' class='table table-striped table-hover w-100 mb-2'>
				<thead>
					<tr>
						<th scope='col'></th>".$thHores."
					</tr>
				</thead>
				<tbody>
					<tr>".$thSenseDescompte."</tr>".$trTrams."
				</tbody>
			</table>
			<p class='text-center mb-4'>Preus per persona. Descomptes no acumulables a altres promocions.</p>
		</div>";

      $infoDescompteParticipants = '';
		//Preparem la informació de la pàgina
		$infoPagina = $infoTextDescompte.$infoDescompteParticipants.$tableDescomptes.$containerCursos;

		//Preparem la pàgina
		$pagina = "<div class='separacio-peu border border-radius-2 bg-white px-4 py-2'>
			<div id='cntPage' class='cntPage'>
				<h2>Informació</h2>
				".$infoPagina."
			</div>
		</div>";

		return $pagina;
	}

	/**
   * @brief Mostra els cursos de $hores hores
   * @return Mostra els cursos de $hores hores
   */
	public function mostraCursos($hores) {
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

		require_once "Curs.php";

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
						if ( $curs->obtenirEstat() == 1 )
            	$mostrar .= $curs->mostrarCursDescompteGrup();
         }
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();

		return $mostrar;
	}

	/**
   * @brief Mostra el formulari d'inscripció per a grups
   * @return Mostra el curs que has triat, el preu segons el nombre de participants
	* i dos botons "Grup" i "Centre escolar". Si $tipusInsc == "grup", el botó "Grup",
	* té la classe marcat. Si $tipusInsc == "centre-escolar", el botó "Centre escolar"
	* té la classe marcat.
   */
   public function mostraFormulariInscripcionsGrup($codi, $tipusInsc) {
		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		/* Es busca les hores i el id_preu de la pròxima edició oberta del curs */
		if (intval($codi)==0) {

         $consultaHoresPreu = "SELECT HORES, NOM_CURS, ID_PREU FROM curs WHERE
            DATAI+8>CURRENT_DATE AND CURS LIKE ? AND PUBLIC=1
            AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
            ORDER BY ANY, MES LIMIT 1";
         $stmtHoresPreu=$connexio->prepare($consultaHoresPreu);
         $stmtHoresPreu->bind_param("s", $codi);
         $stmtHoresPreu->execute();
         $stmtHoresPreu->store_result();

         if ( $stmtHoresPreu->num_rows() > 0 ) {
            $stmtHoresPreu->bind_result($hores, $nomCurs, $idPreu);
            $stmtHoresPreu->fetch();
            $connexio->closeStmt();
        }
        else {
            $cnsHoresLastEd="SELECT HORES
            FROM curs WHERE CURS LIKE ? AND PUBLIC=1
            AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
            ORDER BY ANY DESC, MES DESC LIMIT 1";
            $stmtLastEd = $connexio->prepare($cnsHoresLastEd);
            $stmtLastEd->bind_param("s", $codi);
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
            $diesObets=0;
             while ($stmtParam->fetch()) {
                $valors = explode('|',$valor);
                if (count($valors) != 2)
                   $diesObets=0;
                else if (intval($valors[0])>0 && $valors[0]==$hores) //si el valor és un numero i les hores son iguals al curs
                   $diesObets = $valors[1];
                else if (intval($valors[0])<=0 && $valors[0]==$codi) //si el valor no és un numero i el codi és igual al curs
                   $diesObets = $valors[1];
             }
             $connexio->closeStmt();
             // echo "diesOB:".$diesObets;

             $consultaHoresPreu = "SELECT HORES, NOM_CURS, ID_PREU FROM curs WHERE
                DATAI+?>CURRENT_DATE AND CURS LIKE ? AND PUBLIC=1
                AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
                ORDER BY ANY, MES LIMIT 1";
             $stmtHoresPreu=$connexio->prepare($consultaHoresPreu);
             $stmtHoresPreu->bind_param("ds", $diesObets, $codi);
             $stmtHoresPreu->execute();
             $stmtHoresPreu->store_result();
             if ( $stmtHoresPreu->num_rows() > 0 ) {
                $this->estat=1;
                $stmtHoresPreu->bind_result($hores, $nomCurs, $idPreu);
                $stmtHoresPreu->fetch();
             }
             $connexio->closeStmt();
        }
      }
      else {
         $hores = $codi;

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

         $cnsExsiteixCurs = "SELECT NOM_CURS, ID_PREU FROM curs WHERE
           DATAI+?>CURRENT_DATE AND PUBLIC=1 AND HORES=?
           AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
           ORDER BY ANY, MES LIMIT 1";
         $stmtEx=$connexio->prepare($cnsExsiteixCurs);
         $stmtEx->bind_param("dd", $diesOberts, $hores);
         $stmtEx->execute();
         $stmtEx->bind_result($nomCurs, $idPreu);
         $stmtEx->fetch();
         $connexio->closeStmt();
      }

		$this->curs = new Text($codi);
		$this->hores = new Numero($hores);
		$this->nomCurs = new Text($nomCurs);

		$infoCurs = "<p>Has triat que vols realitzar el curs <strong>".$nomCurs."</strong>.</p>";
		$infoPreu = "<p>El preu per alumne varia depenent del nombre de persones del grup i de les hores del curs.</p>";

		/* Es calcula l'import sense descompte  */
		$thSenseDescompte = "<td scope='col'>Sense descompte</td>";

		$cnsPreu = "SELECT IMPORT FROM preu WHERE ID=? AND
		  DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
		$stmtPreu=$connexio->prepare($cnsPreu);
		$stmtPreu->bind_param("d", $idPreu);
		$stmtPreu->execute();
		$stmtPreu->bind_result($preu);
		$stmtPreu->fetch();
		$connexio->closeStmt();

		$objPreu = new Numero($preu);
		$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();

		$this->preuSenseDescompte = new Numero($preu);

		$thSenseDescompte .= "<td scope='col'>".$preu." €</td>";

		//Es busca el nº de descomptes diferents per nombre de participants
		$cnsGrupsPart="SELECT NUM_ALUMN_MIN, NUM_ALUMN_MAX FROM descomptes_grup
		WHERE DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)
		GROUP BY NUM_ALUMN_MIN, NUM_ALUMN_MAX";
		$stmtGrupsPart = $connexio->prepare($cnsGrupsPart);
		$stmtGrupsPart->execute();
		$stmtGrupsPart->bind_result($numAlumnMin, $numAlumnMax);
		while ($stmtGrupsPart->fetch()) {
			$numeroParticipants[] = [$numAlumnMin, $numAlumnMax];
		}
		$connexio->closeStmt();

		/* Es calcula els imports per cada tram de participants  */
		$trTrams = "";
		for ($i=0; $i<count($numeroParticipants); $i++) {
			$trTrams .= "<tr>";

			//primera columna
			$trTrams .= "<td>";
			if ( $numeroParticipants[$i][1] == null or $numeroParticipants[$i][1] == '' ) {
				$trTrams .= ">".$numeroParticipants[$i][0];
			}
			else {
				$trTrams .= "".$numeroParticipants[$i][0]."-".$numeroParticipants[$i][1];
			}
			$trTrams .= " persones</td>";
			//fi de la primera columna

			if ($numeroParticipants[$i][1]=='' or $numeroParticipants[$i][1]==null) {
				$cnsPreu = "SELECT preu FROM descomptes_grup WHERE ID_PREU=? AND NUM_ALUMN_MIN=? AND NUM_ALUMN_MAX IS NULL AND
				  DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
				$stmtPreu=$connexio->prepare($cnsPreu);
				$stmtPreu->bind_param("dd", $idPreu, $numAlumnMin);
			}
			else {
				$cnsPreu = "SELECT preu FROM descomptes_grup WHERE ID_PREU=? AND NUM_ALUMN_MIN=? AND NUM_ALUMN_MAX=? AND
				  DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
				$stmtPreu=$connexio->prepare($cnsPreu);
				$stmtPreu->bind_param("ddd", $idPreu, $numAlumnMin, $numAlumnMax);

			}
			$numAlumnMin = $numeroParticipants[$i][0];
			$numAlumnMax = $numeroParticipants[$i][1];
			$stmtPreu->execute();
			$stmtPreu->bind_result($preu);
			$stmtPreu->fetch();
			$connexio->closeStmt();

			$objPreu = new Numero($preu);
			$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();

			$trTrams .= "<td>".$preuFormatCorrecte." €</td>";

			$trTrams .= "</tr>";

			$this->preus[$i] = [$numeroParticipants[$i][0], $numeroParticipants[$i][1], $preu];

		}

		//Es prepara la taula dels descomptes
		$tableDescomptes = "<div class='table-descomptes'><table id='taula-descomptes' class='table table-striped table-hover w-100 mb-4'>
			<tbody>
				<tr>".$thSenseDescompte."</tr>".$trTrams."
			</tbody>
		</table></div>";

		$clasGrup = $clasCentre = '';
		if ( $tipusInsc != '' ) {
			$mostrar .= " marcat";
			if ( $tipusInsc == 'grup' ) $clasGrup .= " marcat";
			if ( $tipusInsc == 'centre-escolar' ) $clasCentre .= " marcat";
		}
		$containerTipusInsc = "<p>Indica si la inscripció és d'un grup o un centre escolar.</p>
		<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100 botons'>
			<button id='grup' class='tipusInsc mesinfo position-relative
				negreta500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2 mr-0
				mr-sm-2 ml-lg-0 mr-lg-2 ml-sm-0 mb-4'>
				Grup
			</button>
			<button id='centre-escolar' class='tipusInsc mesinfo position-relative
				negreta500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2 mr-0
				mr-sm-2 ml-lg-0 mr-lg-2 ml-sm-0 mb-4'>
				Centre escolar
			</button>
		</div>";

		$containerSequencia = "<div class='d-flex flex-column align-items-center justify-content-center'>
			<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100 botons'>
				<button id='enrereForm' class='boto-disable position-relative border-0
					border-radius-2 w-100 mr-0 mr-md-2 px-4 py-2 my-2'>
					<i class='fas fa-arrow-left mr-2'></i>Vull triar un altre curs
				</button>
				<button id='continuaForm' class='inscripcio boto-blau position-relative text-white
					border-0 border-radius-2 w-100  px-4 py-2 my-2'>
					Inscripció grupal <i class='fas fa-arrow-right ml-2'></i>
				</button>
			</div>
		</div>";

		//Preparem la informació de la pàgina
		$infoPagina = $infoCurs.$infoPreu.$tableDescomptes.$this->alertaInformacio().$containerTipusInsc.$containerSequencia;

		//Preparem la pàgina
		$pagina = "<h2>Formulari d'inscripció per a grups</h2>".$infoPagina;

		return $pagina;
	}
	/**
   * @brief Mostra el formulari de dades de contacte
   * @return Si tipusInsc, mostra el input del nom del centre. Mostra el señect
	* NIF/NIE, eL input del dni, l'input de telefon, l'input d'email, l'input de
	* confirmació d'email, l'input d'adreça, l'input codi postal, l'input de població
   */
	public function mostraFormulariDadesContacte($codi, $tipusInsc, $nomCentre,
	$nom, $cognoms, $dni, $telefon, $email, $adreca, $cp, $poblacio) {
		$formNomCentre = "";
		if ( $tipusInsc == 'centre-escolar' ) {
			$formNomCentre = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
				<div class='col-12 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap position-relative w-100'>
						<label class='position-absolute mb-0'>
							<span class='camp'>Nom del centre</span>
							<span class='req font-weight-bold'>*</span>
						</label>
						<input type='text' class='form-control w-100' id='nom_centre' name='nom_centre' autofocus='' value=\"".$nomCentre."\">
						<span id='nom_centre_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
					</div>
				</div>
			</div>";
		}

		$classNomActive = '';
		if ( $nom != '' ) $classNomActive = 'active';
		if ( $cognoms != '' ) $classCogActive = 'active';
		if ( $dni != '' ) $classDniActive = 'active';
		if ( $telefon != '' ) $classTelActive = 'active';
		if ( $email != '' ) $classEmailActive = 'active';
		if ( $adreca != '' ) $classAdrecaActive = 'active';
		if ( $cp != '' ) $classCpActive = 'active';
		if ( $poblacio != '' ) $classPobleActive = 'active';

		$formNomCognoms = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0 ".$classNomActive."'>
						<span class='camp'>Nom</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='nom' name='nom' value=\"".$nom."\">
					<span id='nom_cognom_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0 ".$classCogActive."'>
						<span class='camp'>Cognoms</span>
						<span class='req font-weight-bold'>*</span>
					</label>
      			<input type='text' class='form-control' id='cog' name='cog' value=\"".$cognoms."\">
					<span id='cognom_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
		</div>";
		if ( $tipusInsc == 'centre-escolar' ) {
			$formSelectDniTel = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
				<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2' id='input_doc'>
					<div class='form-group field-wrap position-relative'>
						<label class='position-absolute mb-0 ".$classDniActive."'>
							<span class='camp'>CIF</span>
							<span class='req font-weight-bold'>*</span>
						</label>
						<input type='text' class='form-control' id='nif' name='nif' value=\"".$dni."\">
						<span id='dni_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
					</div>
				</div>
				<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap position-relative'>
						<label class='position-absolute mb-0 ".$classTelActive."'>
							<span class='camp'>Telèfon de contacte</span>
							<span class='req font-weight-bold'>*</span>
						</label>
						<input type='tel' class='form-control' id='telf' name='telf' pattern='[6-9]{1}[0-9]{8}' maxlength='9'  value=\"".$telefon."\">
						<span id='telf_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
					</div>
				</div>
			</div>";
		}
		else {
			$formSelectDniTel = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
				<div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap position-relative'>
						<div id='doc' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
							<span class='element-selected font-weight-normal w-100'>NIF/NIE</span>
							<ul class='select-list position-absolute ' style='display: none;'>
								<li class='border-bottom m-0' id='doc-dni'><a href='#'>NIF/NIE</a></li>
								<li class='border-bottom m-0' id='doc-passaport'><a href='#'>Altres</a></li>
							</ul>
							<i class='fa triangle-inferior fa-angle-down position-absolute'></i>
						</div>
					</div>
				</div>
				<div class='col-12 col-md-3 pl-0 pr-0 pr-md-2' id='input_doc'>
					<div class='form-group field-wrap position-relative'>
						<label class='position-absolute mb-0 ".$classDniActive."'>
							<span class='camp'>DNI amb lletra</span>
							<span class='req font-weight-bold'>*</span>
						</label>
						<input type='text' class='form-control' id='nif' name='nif' value=\"".$dni."\">
						<span id='dni_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
					</div>
				</div>
				<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap position-relative'>
						<label class='position-absolute mb-0 ".$classTelActive."'>
							<span class='camp'>Telèfon de contacte</span>
							<span class='req font-weight-bold'>*</span>
						</label>
						<input type='tel' class='form-control' id='telf' name='telf' pattern='[6-9]{1}[0-9]{8}' maxlength='9'  value=\"".$telefon."\">
						<span id='telf_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
					</div>
				</div>
			</div>";

		}

		$formCorreus = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0 ".$classEmailActive."'>
						<span class='camp'>Correu electrònic</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='email' class='form-control' id='email' name='email'  value=\"".$email."\">
					<span id='correu_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0 ".$classEmailActive."'>
						<span class='camp'>Confirmaci&oacute del correu electrònic</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='email' class='form-control' id='email_conf' name='email_conf'  value=\"".$email."\">
					<span id='correu_conf_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
		</div>".$this->__modalCorreuValid();

		$formAdreca = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0 ".$classAdrecaActive."'>
						<span class='camp'>Adreça (carrer, número...)</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='adreca' name='adreca' maxlength='150'  value=\"".$adreca."\">
					<span id='adreca_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
		</div>";

		$formCpPoble = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
				<div class='cnt-cp form-group field-wrap position-relative' id='cp_box'>
					<label class='position-absolute mb-0 ".$classCpActive."'>
						<span class='camp'>Codi postal</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='cp' name='cp' maxlength='5'  value=\"".$cp."\">
					<span id='cp_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
			<div class='col-12 col-md-9 pl-0 pr-0 pr-md-2'>
				<div class='cnt-poble form-group field-wrap' id='poble_box'>
					<label class='position-absolute mb-0 ".$classPobleActive."'>
						<span class='camp'>Poblaci&oacute</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='poble' name='poble' maxlength='50' value=\"".$poblacio."\">
					<span id='poble_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
					<ul id='llistat_poblacions' style='display: none'
					class='select-list position-absolute' role='listbox'></ul>
				</div>
			</div>
		</div>";

		$containerSequencia = "<div class='d-flex flex-column align-items-center justify-content-center'>
			<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100 botons'>
				<button id='enrereForm' class='boto-disable position-relative border-0
					border-radius-2 w-100 mr-0 mr-md-2 px-4 py-2 my-2'>
					<i class='fas fa-arrow-left mr-2'></i>Torna enrere
				</button>
				<button id='continuaForm' class='boto-blau position-relative text-white
					border-0 border-radius-2 w-100  px-4 py-2 my-2'>
					Continua<i class='fas fa-arrow-right ml-2'></i>
				</button>
			</div>
		</div>";

		$formulari = "<div class='d-flex flex-column align-items-center justify-content-center'>";
		if ( $tipusInsc == 'centre-escolar' )
			$formulari .= $formNomCentre.$formSelectDniTel.$formNomCognoms.$formCorreus.$formAdreca.$formCpPoble;
		else
			$formulari .= $formNomCognoms.$formSelectDniTel.$formCorreus.$formAdreca.$formCpPoble;
		$formulari .= "</div>".$containerSequencia;


		$pagina = "<h2>Dades de contacte</h2>
		<p>Emplena el formulari amb les dades de la persona de contacte.</p>".$formulari;

		return $pagina;
	}

	/**
   * @brief S'afegeix un alumne a les dades del gruo
   * @return S'afegeix un array amb els elements $nom, $cog, $dni, $tel, $email,
	* $adreca, $cp, $poble, $perfil, $titulacio a l'últim registre del llistat dadesGrup
   */
	public function afegirDadesAlumne($nom, $cog, $dni, $tel,
	$email, $adreca, $cp, $poble, $perfil, $titulacio, $comentaris){
		$this->dadesGrup[] = [$nom, $cog, $dni, $tel, $email, $adreca, $cp, $poble, $perfil, $titulacio, $comentaris];
	}

   /*
   * @brief Retorna una alerta ssi existeix a la BD que ha d'apareixer aquesta alerta
   * @return Reviso si el curs té una alerta per posar a la inscripció.
   Si el curs disposa d'una alerta, es torna una alerta en un contenidor amb una
   estetica amb una exclamació i el missatge que existeix al base de dades
   */
   private function alertaInformacio() {
      $textCodiCurs = new Text($this->obtenirCodiCurs()->obtenirText());
      $codiCurs = $textCodiCurs->convertirMaj();

      echo "codi".$codiCurs;

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
   * @brief Retorna una alerta ssi existeix a la BD que ha d'apareixer aquesta alerta
   * @return Reviso si el curs té una alerta per posar a la inscripció.
   Si el curs disposa d'una alerta, es torna una alerta en un contenidor amb una
   estetica amb una exclamació i el missatge que existeix al base de dades
   */
   private function alertaInscripcio() {
      $textCodiCurs = new Text($this->obtenirCodiCurs()->obtenirText());
      $codiCurs = $textCodiCurs->convertirMaj();

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      // Cercar paramatre X per veure si el curs actual té una alerta d'inscripció
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("sss", $tipus, $valor, $orderBy);
      $tipus = 'alerta-inscripcio';
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
      class='prisma-contact border-radius-2 px-3 pb-1 pt-3 my-3'>";
      $mostrar .= $missatgeAlerta;
      $mostrar .= "</div>";

      return $mostrar;
   }

	/**
   * @brief Mostra el formulari de dades del curs i del grup
   * @return Mostra l'apartat dades del curs i dades del grup. A l'apartat de
	* dades del curs, mostra el nom del curs corresponent al codi curs $codi
	* juntament amb el total d'alumnes $numAlumnes. A l'apartat de dades del curs
	* mostra un botó i la taula d'alumnes inscrit segons les dades del grup.
	* Per cada element de dadesGrup, es mostra un tr  a la taula amb un td per cada
	* element de dadesGrup[i];
   */
	public function mostraFormulariDadesCursIGrup($codi, $numAlumnes, $dadesContacte) {
		$this->dadesContacte = $dadesContacte;

      require_once 'Curs.php';
      $curs = new Curs($codi, 'ordinador');
      $this->curs = $curs->obtenirCodi();
      $this->nomCurs = $curs->obtenirTitol();
      $this->hores = $curs->obtenirHores();

		$dadesCurs = "<h2>Dades del curs</h2>
		<p>Nom del curs: <strong>".$this->obtenirNomCurs()->obtenirText()."</strong></p>
		<p>Total alumnes: <span id='num-alumnes' class='font-weight-bold'>".$numAlumnes."</span></p>
      ".$this->alertaInscripcio()."
		<div class='form-dades' id='dates_curs'>
		   <div class='d-flex flex-row align-items-center justify-content-center'>
		      <div class='col-12 pl-0 pr-0 pr-md-2'>
		         <div class='form-group field-wrap position-relative'>
		            <div id='dates' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
		               <span class='element-selected font-weight-normal w-100'>
		               Durant quines dates voleu realitzar el curs? Tria l'edició</span>
		               <ul class='select-list position-absolute' style='display: none;'>".$this->__buscarEdicions()."
		               </ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i>
		            </div>
		            <span id='dates_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
		         </div>
		      </div>
		   </div>
		</div>
		<div class='' id='missInformatiuEdicioRec'></div>
		<div class='' id='missInformatiuEdicioPerf'></div>
		<div class='form-dades' id='txtHint_conegut'>
		   <div class='d-flex flex-row align-items-center justify-content-center'>
		      <div class='col-12 pl-0 pr-0 pr-md-2'>
		         <div class='form-group field-wrap position-relative'>
		            <div id='comConegut' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
		               <span class='element-selected font-weight-normal w-100'>
		               Com heu conegut aquest curs? Tria una opció</span>
		               <ul id='listComConegut' class='select-list position-absolute' style='display: none;'>
		               </ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i>
		            </div>
		            <span id='conegut_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
		         </div>
		      </div>
		   </div>
		</div>
		<div class='' id='comHasConegut_altres'></div>";

		if ( $this->dadesGrup==[] ) {
			$alumnesInscrits = "<p>No hi ha alumnes inscrits en el grup.</p>";
		}
		else {
			$dadesAlumnesInscrits = "";
			for ($i = 0; $i < count($this->dadesGrup); $i++) {
				$dadesAlumnesInscrits .= "<tr>";
				for ($j = 0; $j < count($this->dadesGrup[$i])-3; $j++) {
					$dadesAlumnesInscrits .= "<td>".$this->dadesGrup[$i][$j]."</td>";
				}
				$dadesAlumnesInscrits .= "</tr>";
			}

			$alumnesInscrits = "<table class='table table-striped table-hover w-100 mb-4'>
				<thead>
					<tr>
						<th scope='col'>Nom</th>
						<th scope='col'>Cognoms</th>
						<th scope='col'>Dni</th>
						<th scope='col'>Telèfon</th>
						<th scope='col'>Correu electrònic</th>
						<th scope='col'>Adreça</th>
						<th scope='col'>Codi postal</th>
						<th scope='col'>Població</th>
					</tr>
				</thead>
				<tbody>
					".$dadesAlumnesInscrits."
				</tbody>
			</table>";
		}

		$dadesGrup = "<h2>Dades del grup</h2>
		<p>A partir del botó «Afegeix un alumne» per anar afegint els diferents alumnes i omplir les dades de l'alumne. </p>
		<div class='d-flex flex-column flex-lg-row justify-content-start'>
			<button id='afegeix-alumne' class='mesinfo position-relative negreta500
			border-0 border-radius-2 flex-shrink-1 px-2 py-2 mr-0 mr-sm-2 ml-lg-0
			mr-lg-2 ml-sm-0 mb-4'><i class='fas fa-plus mr-2'></i>Afegeix un alumne</button>
		</div>".$alumnesInscrits.$this->__modalAfegirAlumne();

		$containerSequencia = "<div class='d-flex flex-column align-items-center justify-content-center'>
			<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100 botons'>
				<button id='enrereForm' class='boto-disable position-relative border-0
					border-radius-2 w-100 mr-0 mr-md-2 px-4 py-2 my-2'>
					<i class='fas fa-arrow-left mr-2'></i>Torna enrere
				</button>
				<button id='continuaForm' class='boto-blau position-relative text-white
					border-0 border-radius-2 w-100  px-4 py-2 my-2'>
					Continua<i class='fas fa-arrow-right ml-2'></i>
				</button>
			</div>
		</div>";

		$pagina = $dadesGrup.$dadesCurs.$containerSequencia;

		return $pagina;
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

		$codi = $this->obtenirCodiCurs()->obtenirText();
      $hores = $this->obtenirHores()->obtenirNumero();

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
      $diesObets=0;
      while ($stmtParam->fetch()) {
         $valors = explode('|',$valor);
         if (count($valors) != 2)
            throw new Exception('',1304);
         else if (intval($valors[0])>0 && $valors[0]== $hores) //si el valor és un numero i les hores son iguals al curs
            $diesObets = $valors[1];
         else if (intval($valors[0])<=0 && $valors[0]== $codi) //si el valor no és un numero i el codi és igual al curs
            $diesObets = $valors[1];
      }
      $connexio->closeStmt();

      $cnsEd = "SELECT DATAI, DATAF, ANY, MES, c.ESTAT FROM curs AS c INNER JOIN aula AS a ON
         c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
         INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE c.CURS=? AND PUBLIC=1
         AND c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1 AND
         (DATEDIFF(DATAI + ?,CURRENT_DATE)>0) AND c.CURS NOT LIKE '%JOR%' AND
         (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
         OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
         AND ORDRE_TUTOR is not NULL)) GROUP BY ANY, MES ORDER BY ANY, MES LIMIT 6";
      $stmtEd = $connexio->prepare($cnsEd);
      $stmtEd->bind_param("sd", $codi, $diesObets);
      $stmtEd->execute();
      // $stmtEd->bind_result($datai, $dataf, $cursEscolar, $fiss, $gtaf, $dataRes, $any, $mesDesc, $curs, $dni, $hores, $estatEd);
      $stmtEd->bind_result($datai, $dataf, $any, $mesDesc, $estatEd);
      require_once 'Edicio.php'; $cnt = 0;
      while ( $stmtEd->fetch() && $cnt < $limitEd){
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
			$cnt++;
      }

      $connexio->closeStmt();
      $connexio->desconectarBD();

      return $mostrar;
   }

	/**
	* @brief Mostra el resum de les dades
	* @return Mostra el resum de les dades
	*/
	public function mostraResumDades($codi, $numAlumnes, $tipusInsc, $dates, $any, $edicio, $conegut) {
		$this->dates = new Text($dates);
		$this->edicio = [intval($any), $edicio];
		$this->conegut = new Text($conegut);

		$preuSenseDescompte = $this->obtenirPreuSenseDescompte()->obtenirNumero();
		$objTotalPreuSenseDescompte = new Numero(floatval( floatval($preuSenseDescompte) * intval($numAlumnes) ));
		$totalPreuSenseDescompte = $objTotalPreuSenseDescompte->mostrarNumeroDecimalsSense0();

		$i = 0; $trobat = false; $numAlumnes = count($this->dadesGrup);
		while ( $i < count($this->preus) && !$trobat ) {
			if ( 	intval($numAlumnes) >= floatval($this->preus[$i][0]) &&
					( $this->preus[$i][1] == null || intval($numAlumnes) <= floatval($this->preus[$i][1] ) ) )
				$trobat = true;
			else
				$i++;
		}

		$objTotalPreuDescompte =  new Numero(floatval( floatval($this->preus[$i][2]) * intval($numAlumnes) ));
		$totalPreuDescompte = $objTotalPreuDescompte->mostrarNumeroDecimalsSense0();

		$dadesCurs = "<h3>Dades del curs</h3>
			<p>Nom del curs: <strong>".$this->obtenirNomCurs()->obtenirText()."</strong></p>
			<p>Total alumnes: ".$numAlumnes."</p>
			<p>Preu total: <span class='tatxat'>".$totalPreuSenseDescompte." euros</span> <span class='font-weight-bold preu_descompte'>".$totalPreuDescompte." euros</span></p>
			<p>Dates: <strong>".$dates."</strong></p>
			<p>Com has conegut el curs: <strong>".$conegut."</strong></p>
         ".$this->alertaInscripcio()."
		";

		$pos = 1;

		$dadesNomCognoms = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label  class='position-absolute mb-0 active'>
						<span class='camp'>Nom</span>
					</label>
					<input type='text' class='form-control' id='nom' name='nom' disabled value=\"".$this->dadesContacte[0][$pos]."\">
				</div>
			</div>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0 active'>
						<span class='camp'>Cognoms</span>
					</label>
					<input type='text' class='form-control' id='cog' name='cog' disabled value=\"".$this->dadesContacte[0][$pos+1]."\">
				</div>
			</div>
		</div>";

		$dadesDniTel = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2' id='input_doc'>
				<div class='form-group field-wrap position-relative'>
					<label class='active'><span class='fons'></span><span class='camp'>DNI amb lletra</span></label>
					<input type='text' class='form-control' id='nif' name='nif' maxlength='150' disabled value=\"".$this->dadesContacte[0][$pos+2]."\">
				</div>
			</div>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative w-100'>
					<label class='position-absolute mb-0 active'><span class='camp'>Telèfon de contacte</span></label>
					<input type='tel' class='form-control' id='telf' name='telf' pattern='[6-9]{1}[0-9]{8}' maxlength='9' disabled value=\"".$this->dadesContacte[0][$pos+3]."\">
				</div>
			</div>
		</div>";


		$dadesInici = "";
		if ( $tipusInsc == 'centre-escolar' ) {
			$dadesNomCentre = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
				<div class='col-12 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap position-relative w-100'>
						<label class='position-absolute mb-0 active'><span class='camp'>Nom del centre</span></label>
						<input type='text' class='form-control w-100' id='desti' name='desti' autofocus='' disabled value=\"".$this->dadesContacte[0][0]."\">
					</div>
				</div>
			</div>";

			$dadesDniTel = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
				<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2' id='input_doc'>
					<div class='form-group field-wrap position-relative'>
						<label class='active'><span class='fons'></span><span class='camp'>CIF</span></label>
						<input type='text' class='form-control' id='nif' name='nif' maxlength='150' disabled value=\"".$this->dadesContacte[0][$pos+2]."\">
					</div>
				</div>
				<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap position-relative w-100'>
						<label class='position-absolute mb-0 active'><span class='camp'>Telèfon de contacte</span></label>
						<input type='tel' class='form-control' id='telf' name='telf' pattern='[6-9]{1}[0-9]{8}' maxlength='9' disabled value=\"".$this->dadesContacte[0][$pos+3]."\">
					</div>
				</div>
			</div>";


			$dadesInici = $dadesNomCentre.$dadesDniTel.$dadesNomCognoms;
		}
		else
			$dadesInici = $dadesNomCognoms.$dadesDniTel;


		$dadesContacte = "<h3>Dades de contacte</h3>
		<div class='d-flex flex-column align-items-center justify-content-center'>
			".$dadesInici."
			<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
				<div class='col-12 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap position-relative w-100'>
						<label class='position-absolute mb-0 active'><span class='camp'>Correu electrònic</span></label>
						<input type='email' class='form-control' id='email' name='email' disabled value=\"".$this->dadesContacte[0][$pos+4]."\">
					</div>
				</div>
			</div>

			<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
				<div class='col-12 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap position-relative w-100'>
						<label class='position-absolute mb-0 active'><span class='camp'>Adreça (carrer, número,...)</span></label>
						<input type='text' class='form-control' id='adreca' name='adreca' maxlength='150' disabled value=\"".$this->dadesContacte[0][$pos+5]."\">
					</div>
				</div>
			</div>
			<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
				<div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap position-relative' id='cp_box'>
						<label class='active'><span class='fons'></span><span class='camp'>Codi postal</span></label>
						<input type='text' class='form-control' id='cp' name='cp' maxlength='5' disabled value=\"".$this->dadesContacte[0][$pos+6]."\">
					</div>
				</div>
				<div class='col-12 col-md-9 pl-0 pr-0 pr-md-2'>
					<div class='form-group field-wrap' id='poble_box'>
						<label class='active'><span class='camp'>Població</span></label>
						<input type='text' class='form-control' id='poble' name='poble' maxlength='50' disabled value=\"".$this->dadesContacte[0][$pos+7]."\">
					</div>
				</div>
			</div>
		</div>";

		if ( $this->dadesGrup==[] ) {
			$alumnesInscrits = "<p>No hi ha alumnes inscrits en el grup.</p>";
		}
		else {
			$dadesAlumnesInscrits = "";
			for ($i = 0; $i < count($this->dadesGrup); $i++) {
				$dadesAlumnesInscrits .= "<tr>";
				for ($j = 0; $j < count($this->dadesGrup[$i])-3; $j++) {
					$dadesAlumnesInscrits .= "<td>".$this->dadesGrup[$i][$j]."</td>";
				}
				$dadesAlumnesInscrits .= "</tr>";
			}

			$alumnesInscrits = "<table class='table table-striped table-hover w-100 mb-4'>
				<thead>
					<tr>
						<th scope='col'>Nom</th>
						<th scope='col'>Cognoms</th>
						<th scope='col'>Dni</th>
						<th scope='col'>Telèfon</th>
						<th scope='col'>Correu electrònic</th>
						<th scope='col'>Adreça</th>
						<th scope='col'>Codi postal</th>
						<th scope='col'>Població</th>
					</tr>
				</thead>
				<tbody>
					".$dadesAlumnesInscrits."
				</tbody>
			</table>";
		}

		$dadesGrup = "<h3>Dades del grup</h3>".$alumnesInscrits;

		$rebreMailing = "<div class='' id='txtHint_mailing'></div>";

		$comentari = "<h3>Tens algun comentari?</h3>
		<div class='form-group field-wrap position-relative'>
	      <label>
				<span class='camp'>Comentaris</span>
			</label>
	      <textarea class='form-control' id='comentaris'></textarea>
      </div>";

		$containerSequencia = "<div class='d-flex flex-column align-items-center justify-content-center'>
			<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100 botons'>
				<button id='enrereForm' class='boto-disable position-relative border-0
					border-radius-2 w-100 mr-0 mr-md-2 px-4 py-2 my-2'>
					<i class='fas fa-arrow-left mr-2'></i>Torna enrere
				</button>
				<button id='continuaForm' class='boto-blau position-relative text-white
					border-0 border-radius-2 w-100  px-4 py-2 my-2'>
					Envia dades<i class='fas fa-arrow-right ml-2'></i>
				</button>
			</div>
		</div>";

		$pagina = "<h2>Resum de les dades introduïdes</h2>";
		$pagina .= $dadesCurs.$dadesContacte.$dadesGrup.$rebreMailing.$comentari.$containerSequencia;

		return $pagina;
	}

	/**
	* @brief Envia les dades
	* @return S'envia un email a la persona de contacte. Per cada registre de les
	* dades del grup, s'insereix a la bd l'alumne i s'envia un email a cada alumne
	*/
	public function enviarDades($comentaris, $mailing, $tipusInsc) {

		$numAlumnes = count($this->dadesGrup);
		$dataInsc = date('d')."-".date('m')."-".date('Y')." ".date('H').":".date('i');

		/* ######## Càlcul del preu amb descompte segons el nombre de participants ######## */
		$i = 0; $trobat = false;
		while ( $i < count($this->preus) && !$trobat ) {
			if (  intval($numAlumnes) >= floatval($this->preus[$i][0]) &&
				   ( $this->preus[$i][1] == null || intval($numAlumnes) <= floatval($this->preus[$i][1] ) ) )
				$trobat = true;
			else
				$i++;
		}

		/* ############# Obtinc les dades del curs per la inscripció ############# */
		$textTitolCurs = $this->obtenirNomCurs();
		$textCodiCurs = $this->obtenirCodiCurs();
		$textDates = $this->obtenirDates();
		$numAny = new Numero($this->obtenirAnyEdicio());
		$textEdicio = new Text($this->obtenirMesEdicio());
		$textConegut = $this->obtenirConegut();
		$numPreuSenseDescompte = $this->obtenirPreuSenseDescompte();
		$numPreuDescompte = new Numero( floatval($this->preus[$i][2]) );

		$textCodiCurs->arreglarParaulaBD('text_maj');
		$textTitolCurs->arreglarParaulaBD('text_no_mod');
		$textDates->arreglarParaulaBD('text_min');
		$textConegut->arreglarParaulaBD('text_no_mod');

		$titolCurs = $textTitolCurs->obtenirText();
		$codiCurs = $textCodiCurs->obtenirText();
		$edicio = $textEdicio->obtenirText();
		$any = $numAny->obtenirNumero();
		$datesRealitzacio = $textDates->obtenirText();
		$conegut = $textConegut->obtenirText();

		$connexio = new ConnexioBBDDSTMT();
		$connexio->connectarBD();
      // echo "Obtinc les dades del curs per la inscripció";

		/* ######################################################################### */

		$cnsDatesCurs = "SELECT DATAI, DATAF, HORES, DATA_RESOL FROM curs WHERE CURS=? AND ANY=? AND MES=?";
		$stmt=$connexio->prepare($cnsDatesCurs);
		$stmt->bind_param("sds", $codiCurs, $any, $edicio);
		$stmt->execute();
		$stmt->bind_result($datai, $dataf, $hores, $data_resol);
		$stmt->fetch();
		$connexio->closeStmt();

		/* ######################################################################### */

		$objDataI = new Date($datai);
		$objDataF = new Date($dataf);

		if ( $objDataI->getAny() != $objDataF->getAny() ) {
			//De l'1 de desembre de 2021 al 15 de febrer de 2022
			//De l'1 de desembre de 2021 a l'11 de febrer de 2022
			//Del 2 de desembre de 2021 al 15 de febrer de 2022
			//Del 2 de desembre de 2021 a l'11 de febrer de 2022
			$textDates = $objDataI->getPronomDel()."".$objDataI->getDataLlarga()."
			".$objDataF->getPronomAl()."".$objDataF->getDataLlarga()."";
		}
		else {
			if ( $objDataI->getMes() != $objDataF->getMes() ) {
				//Del 4 d'abril a l'11 de maig de 2022
				$textDates = $objDataI->getPronomDel()."".intval($objDataI->getDia())."
				".$objDataI->getNomMesArticle()."
				".$objDataF->getPronomAl()."".$objDataF->getDataLlarga()."";
			}
			else {
				//Del 4 al 31 de juliol de 2022
				$textDates = $objDataI->getPronomDel()."".intval($objDataI->getDia())."
				".$objDataF->getPronomAl()."".$objDataF->getDataLlarga()."";
			}
		}
		$datesRealitzacio = $textDates;

		/* ######################################################################### */

		$preuSenseDescompte = $numPreuSenseDescompte->obtenirNumero();
		$preuDescompte = $numPreuDescompte->obtenirNumero();
		$numTotalPreuSenseDescompte = new Numero(floatval( floatval($preuSenseDescompte) * intval($numAlumnes) ));
		$numTotalPreuDescompte =  new Numero(floatval( floatval($preuDescompte) * intval($numAlumnes) ));
		$totalPreuSenseDescompte = $numTotalPreuSenseDescompte->mostrarNumeroDecimalsSense0();
		$totalPreuDescompte = $numTotalPreuDescompte->mostrarNumeroDecimalsSense0();

		/* ######################################################################### */

		//buscar el username i el password d'autentificació de prisma
		$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
						AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
		$stmt=$connexio->prepare($cnsParam);
		$stmt->bind_param("s", $tipusParam);

		$tipusParam = 'autentificacioInscripcioCopiaInscripcions';
		$stmt->execute();
		$stmt->bind_result($valor);
		$stmt->fetch();
		$autentificacioInscripcio = explode('|',$valor);
		$usernameInsc = $autentificacioInscripcio[0];
		$passwordInsc = $autentificacioInscripcio[1];
		$nameUserInsc = $autentificacioInscripcio[2];

		$tipusParam = 'autentificacioInscripcio';
		$stmt->execute();
		$stmt->bind_result($valor);
		$stmt->fetch();
		$autentificacioInscripcio = explode('|',$valor);
		$username = $autentificacioInscripcio[0];
		$password = $autentificacioInscripcio[1];
		$nameUser = $autentificacioInscripcio[2];

		$connexio->closeStmt();

      // echo "autentificacioInscripcioCopiaInscripcions<br />";
		/* ######################################################################### */

		/* ############# Obtinc les dades de contacte per la inscripció ############# */
		$pos = 0; $nomCentre = '';
      if ( $tipusInsc == 'centre-escolar' ) {
   		$objCentreEscolar = new Text( $this->dadesContacte[0][$pos] );
   		$objCentreEscolar->arreglarParaulaBD('noms');
         $nomCentre = $objCentreEscolar->obtenirText();
      }
		$pos = 1;
		$nomPersonaContacte = $this->dadesContacte[0][$pos];
		$cogPersonaContacte = $this->dadesContacte[0][$pos+1];
		$dniPersonaContacte = $this->dadesContacte[0][$pos+2];
		$emailPersonaContacte = $this->dadesContacte[0][$pos+4];
		$telefonPersonaContacte = $this->dadesContacte[0][$pos+3];
		$adrecaPersonaContacte = $this->dadesContacte[0][$pos+5];
		$cpPersonaContacte = $this->dadesContacte[0][$pos+6];
		$poblacioPersonaContacte = $this->dadesContacte[0][$pos+7];

		$objNomPersonaContacte = new Text( $nomPersonaContacte );
		$objCogPersonaContacte = new Text( $cogPersonaContacte );
		$objDniPersonaContacte = new Text( $dniPersonaContacte );
		$objEmailPersonaContacte = new Text( $emailPersonaContacte );
		$objTelefonPersonaContacte = new Numero( $telefonPersonaContacte );
		$objAdrecaPersonaContacte = new Text( $adrecaPersonaContacte );
		$objCpPersonaContacte = new Text( $cpPersonaContacte );
		$objPoblacioPersonaContacte = new Text( $poblacioPersonaContacte );

		$objNomPersonaContacte->arreglarParaulaBD('noms');
		$objCogPersonaContacte->arreglarParaulaBD('noms');
		$objDniPersonaContacte->arreglarParaulaBD('text_maj');
		$objEmailPersonaContacte->arreglarParaulaBD('email');
		$objAdrecaPersonaContacte->arreglarParaulaBD('text');
		$objCpPersonaContacte->arreglarParaulaBD('text_maj');
		$objPoblacioPersonaContacte->arreglarParaulaBD('noms');

		$nomPersonaContacte = $objNomPersonaContacte->obtenirText();
		$cogPersonaContacte = $objCogPersonaContacte->obtenirText();
		$nomCogPersonaContacte = $nomPersonaContacte." ".$cogPersonaContacte;
		$dniPersonaContacte = $objDniPersonaContacte->obtenirText();
		$emailPersonaContacte = $objEmailPersonaContacte->obtenirText();
		$telefonPersonaContacte = $objTelefonPersonaContacte->obtenirNumero();
		$adrecaPersonaContacte = $objAdrecaPersonaContacte->obtenirText();
		$cpPersonaContacte = $objCpPersonaContacte->obtenirText();
		$poblacioPersonaContacte = $objPoblacioPersonaContacte->obtenirText();

      // echo "Obtinc les dades de contacte per la inscripció <br />";
		$cnsIdPag = "SELECT IDPAG FROM inscripcions ORDER BY IDPAG DESC LIMIT 1";
		$stmt=$connexio->prepare($cnsIdPag);
		$stmt->execute();
		$stmt->bind_result($idPag);
		$stmt->fetch();
		$connexio->closeStmt();
		$idPag = $idPag+1;

		$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
						AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
		$stmt=$connexio->prepare($cnsParam);
		$stmt->bind_param("s", $tipusParam);
		$tipusParam = 'keyEncriptar';
		$stmt->execute();
		$stmt->bind_result($keyEncr);
		$stmt->fetch();
		$connexio->closeStmt();

		$cipher = "AES-128-CBC";

		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
		$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
		$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

		$urlIdPag = "https://www.prisma.cat/pagaments/".$hashIdPag;

		$insertBD = "INSERT INTO respGrups (NOM, COGNOMS, DNI, TELEFON, CORREU,
						 ADRECA, CODI_POSTAL, POBLACIO, IDPAG)
						 VALUES (?,?,?,?,?,?,?,?,?)";
	   $stmt=$connexio->prepare($insertBD);
 		$stmt->bind_param("sssdssssd", $nomPersonaContacte, $cogPersonaContacte,
		$dniPersonaContacte, $telefonPersonaContacte, $emailPersonaContacte,
		$adrecaPersonaContacte, $cpPersonaContacte, $poblacioPersonaContacte, $idPag);
 		$stmt->execute();
 		$stmt->fetch();
 		$connexio->closeStmt();

		/* ############# Preparo la part del missatge de dades de contacte ############# */
      if ($mailing=='Registred') $constMailing = 'Ja està subscrit';
      else if ($mailing=='Yes') $constMailing = 'Sí';
      else $constMailing = 'No';

      // echo " Preparo la part del missatge de dades de contacte <br />";
		require_once 'Template.php';
      $templates = new Template();

      $msgDadesContacte = $templates->getTemplate_Inscripcions_Centres_MissatgeDadesContacte( $tipusInsc );
      $names_template = array("[CENTRE_ESCOLAR]", "[NOM]", "[COGNOMS]", "[DNI]", "[EMAIL]",
         "[TELEFON]", "[ADRECA]", "[CP]", "[POBLACIO]", "[MAILING]", "[COMENTARIS]");
      $names_function   = array( $nomCentre, $nomPersonaContacte,
         $cogPersonaContacte, $dniPersonaContacte, $emailPersonaContacte,
         $telefonPersonaContacte, $adrecaPersonaContacte, $cpPersonaContacte,
         $poblacioPersonaContacte, $constMailing, $comentaris);
      $msgPersContInsc = str_replace($names_template, $names_function, $msgDadesContacte);

      // echo "Preparo la part del missatge de dades de curs <br />";
		/* ############# Preparo la part del missatge de dades de curs ############# */
      $msgDadesCurs = $templates->getTemplate_Inscripcions_Centres_MissatgeDadesCurs();
      $names_template = array("[TITOL]", "[DATAI_DATAF]", "[CONEGUT]",
         "[PREU_ORIG]", "[PAY_DESC_ALUMNE]", "[IDPAG_PAY]");
      $names_function   = array($titolCurs, $datesRealitzacio, $conegut,
         $totalPreuSenseDescompte, $totalPreuDescompte, $idPag);
      $msgCursInsc = str_replace($names_template, $names_function, $msgDadesCurs);

      // echo "Preparo la part del missatge de dades de alumnes <br />";
		/* ############# Preparo la part del missatge de dades de alumnes ############# */

      $msgDadesAlumnes = '';
		for ($i = 0; $i < $numAlumnes; $i++) {
         $msgDades = $templates->getTemplate_Inscripcions_Centres_MissatgeDadesUnAlumne();
         $names_template = array("[NUM]", "[NOM]", "[COGNOMS]", "[DNI]", "[EMAIL]",
            "[TELEFON]", "[ADRECA]", "[CP]", "[POBLACIO]", "[PERFIL]", "[TITULACIO]", "[COMENTARIS]");
         $names_function   = array(($i+1), $this->dadesGrup[$i][0], $this->dadesGrup[$i][1],
            $this->dadesGrup[$i][2], $this->dadesGrup[$i][4], $this->dadesGrup[$i][3],
            $this->dadesGrup[$i][5], $this->dadesGrup[$i][6], $this->dadesGrup[$i][7],
            $this->dadesGrup[$i][8], $this->dadesGrup[$i][9], $this->dadesGrup[$i][10]);
         $msgDadesAlumnes .= str_replace($names_template, $names_function, $msgDades);
		}

      $msg = $templates->getTemplate_Inscripcions_Centres_MissatgeDadesAlumnes();
      $names_template = array("[DADES_ALUMNES]", "[NUM_ALUMN]");
      $names_function   = array($msgDadesAlumnes, $numAlumnes);
      $msgPersAlumnInsc = str_replace($names_template, $names_function, $msg);

		/* ############# Preparo el subjtect del missatge a enviar  ############# */
		$subjectMailPersContInsc = "Inscripció grupal ".$codiCurs." ".$edicio." - ".$dniPersonaContacte;
		if ($comentaris != '') $subjectMailPersContInsc .= " + O";
      // echo "Preparo el subjtect del missatge a enviar <br />";

		/* ############# Preparo el missatge curt que rebrà PrisMa ############# */
		$msgPersonaContactCurt = $msgPersContInsc.$msgCursInsc.$msgPersAlumnInsc;
      // echo " Preparo el missatge curt que rebrà PrisMa <br />";

		/* ############# Envio el missatge curt que rep PrisMa ############# */
		$nomFromHead = 'Inscripcions PrisMa';
		$correuFromHead = 'inscripcions@prisma.cat';
		$nomReplyHead = $nomCogPersonaContacte;
		$correuReplyHead = $emailPersonaContacte;

		$nomTo = 'Inscripcions PrisMa';
		$correuTo = 'inscripcions@prisma.cat';
      // $correuTo = 'meriem.prisma.cat@gmail.com';

		$mailInscripcions = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
											$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
											$subjectMailPersContInsc, $msgPersonaContactCurt);
                                 // echo "Envio el missatge curt que rep PrisMa <br />";

		/* #############      Preparo el missatge llarg que     ############# */
		/* ############# hauria de rebre la persona de contacte ############# */
		/* ######################################################################### */
		$cnsDatesCurs = "SELECT DATAI, DATAF, HORES, DATA_RESOL FROM curs WHERE CURS=? AND ANY=? AND MES=?";
		$stmt=$connexio->prepare($cnsDatesCurs);
		$stmt->bind_param("sds", $codiCurs, $any, $edicio);
		$stmt->execute();
		$stmt->bind_result($datai, $dataf, $hores, $data_resol);
		$stmt->fetch();
		$connexio->closeStmt();

		/* ######################################################################### */
		$dadesAlumnesInscrits = "";
		for ($i = 0; $i < count($this->dadesGrup); $i++) {
			if ( $i%2 != 0 ) $background = "background: #FCFCFC";
			else $background = "background: #F7F7F7";
			$dadesAlumnesInscrits .= "<tr style='".$background."'>";
			$dadesAlumnesInscrits .= "<td style='padding: 8px; line-height: 1.43; border-top: 1px solid #ddd; text-align: center;'>".$this->dadesGrup[$i][0]." ".$this->dadesGrup[$i][1]."</td>";
			$dadesAlumnesInscrits .= "<td style='padding: 8px; line-height: 1.43; border-top: 1px solid #ddd; text-align: center;'>".$this->dadesGrup[$i][2]."</td>";
			$dadesAlumnesInscrits .= "<td style='padding: 8px; line-height: 1.43; border-top: 1px solid #ddd; text-align: center;'>".$this->dadesGrup[$i][4]."</td>";
			$dadesAlumnesInscrits .= "</tr>";
		}

		$textAlumnes = "<p>Les persones que faran el curs són:</p>
		<table style='border-collapse: collapse; width: 100%;'>
			<thead>
				<tr style='background: #EAEEF6;'>
					<th scope='col' style='padding: 8px; line-height: 1.43; text-align: center;'>Nom i cognoms</th>
					<th scope='col' style='padding: 8px; line-height: 1.43; text-align: center;'>Dni</th>
					<th scope='col' style='padding: 8px; line-height: 1.43; text-align: center;'>Correu electrònic</th>
				</tr>
			</thead>
			<tbody>".$dadesAlumnesInscrits."</tbody>
		</table>";

      $msg = $templates->getTemplate_Inscripcions_Pagaments_MissatgeTextManeresPagar();
   	$names_template = array("[URL_PAGAMENT]", "[TITOL]", "[CODI_CURS]", "[MES]");
   	$names_function   = array($urlIdPag, $titolCurs, $codiCurs, $edicio);
   	$textManeresPagar = str_replace($names_template, $names_function, $msg);
      // echo "getTemplate_Inscripcions_Pagaments_MissatgeTextManeresPagar <br />";

		/* ######################################################################### */

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio2 = new ConnexioBBDDSTMT();
      $connexio2->connectarBD();

      // echo $codiCurs;

      $cnsParams2="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam2 = $connexio2->prepare($cnsParams2);
      $stmtParam2->bind_param("sss", $tipus, $valor, $orderBy);
      $tipus = 'alerta-confirmació-inscripcio';
      $valor = $codiCurs.'%';
      $orderBy='VALOR';
      $stmtParam2->execute();
      $stmtParam2->store_result();
      // Si té una alerta d'inscripció
      if ( $stmtParam2->num_rows() > 0 ) {
         $stmtParam2->bind_result($valorRes);
         $stmtParam2->fetch();
         $arrValors = explode('|',$valorRes);
         $textAlertaConfirmacioInscripcio = "<div style='background-color:#e8ecf5;border:1px solid #d7deee;
         border-radius:2px;padding:5px 25px;margin-bottom:20px'>";
         $textAlertaConfirmacioInscripcio .= $arrValors[1];
         $textAlertaConfirmacioInscripcio .= "</div>";
      }
   	else {
   		$textAlertaConfirmacioInscripcio = '';
   	}

		$connexio2->closeStmt();
      $connexio2->desconectarBD();

		/* ######################################################################### */
      $msg = $templates->getTemplate_Dades_RequadreDadesPersonals(1);
      $names_template = array("[NOM_ALUMNE]", "[COG_ALUMNE]", "[DNI_ALUMNE]", "[EMAIL_ALUMNE]",
         "[TEL_ALUMNE]", "[ADRECA_ALUMNE]", "[CP_ALUMNE]", "[POBLACIO_ALUMNE]");
      $names_function   = array(
         $nomPersonaContacte,
         $cogPersonaContacte,
         $dniPersonaContacte,
         $emailPersonaContacte,
         $telefonPersonaContacte,
         $adrecaPersonaContacte,
         $cpPersonaContacte,
         $poblacioPersonaContacte
      );
      $reqDadesContacte = str_replace($names_template, $names_function, $msg);
      // echo "getTemplate_Dades_RequadreDadesPersonals <br />";

      $msg = $templates->getTemplate_Inscripcions_EnviamentContacteDescompteGrup(
         $data_resol, $titolDocencia, $mailing);
      $names_template = array("[NOM]", "[NUM_ALUMN]", "[TITOL]", "[DATAI_DATAF]", "[HORES]",
         "[PAY_ORIG]", "[PAY_DESC]", "[TEXT_DADES_CONTACTE]", "[TEXT_ALERT_CONF_INSCR]",
         "[TEXT_ALUMNES]", "[TEXT_MANERES_PAGAR]");
      $names_function   = array( $nomPersonaContacte, $numAlumnes, $titolCurs,
         $datesRealitzacio, $hores, $totalPreuSenseDescompte, $totalPreuDescompte,
         $reqDadesContacte, $textAlertaConfirmacioInscripcio, $textAlumnes, $textManeresPagar);
      $msgPersonaContactLlarg = str_replace($names_template, $names_function, $msg);
      // echo "getTemplate_Inscripcions_EnviamentContacteDescompteGrup <br />";

		/* ############# Preparo el subjtect del missatge llarg que ############# */
		/* #############   hauria de rebre la persona de contacte   ############# */
		$subjectMailPersCont= "Inscripció grupal al curs ".$titolCurs;
		$subjectMailPersCont2= "Inscripció grupal al curs ".$titolCurs." ".$dataInsc;

		/* #############         Envio el missatge llarg que        ############# */
		/* #############   hauria de rebre la persona de contacte   ############# */
		$nomFromHead = 'Secretaria PrisMa';
		$correuFromHead = 'secretaria@prisma.cat';
		$nomReplyHead = $nomCogPersonaContacte;
		$correuReplyHead = $emailPersonaContacte;

		$nomTo = "PrisMa Secretaria";
		$correuTo = "resguard.secretaria@prisma.cat";
      // $correuTo = 'meriem.prisma.cat@gmail.com';

		$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
											$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
											$subjectMailPersCont2, $msgPersonaContactLlarg);

		$nomTo = 'Secretaria PrisMa';
		$correuTo = 'inscripcions@prisma.cat';
      // $correuTo = 'meriem.prisma.cat@gmail.com';

		$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
											$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
											$subjectMailPersCont, $msgPersonaContactLlarg);
		// echo "envia missatge llarg de la persona de contacte a inscripcions@prisma.cat<br />";

		/* ############# Preparo el subjtect del missatge llarg que ############# */
		/* #############        hauria de rebre cada alumne        ############# */
		$subjectMailAlumne = "Inscripció al curs ".$titolCurs;
		$subjectMailAlumne2 = "Inscripció al curs ".$titolCurs." ".$dataInsc;;

		for ($i = 0; $i < $numAlumnes; $i++) {
			$nom = $this->dadesGrup[$i][0];
			$cog= $this->dadesGrup[$i][1];
			$dni= $this->dadesGrup[$i][2];
			$tel= $this->dadesGrup[$i][3];
			$email= $this->dadesGrup[$i][4];
			$adreca= $this->dadesGrup[$i][5];
			$cp= $this->dadesGrup[$i][6];
			$poble= $this->dadesGrup[$i][7];
			$perfil= $this->dadesGrup[$i][8];
			$titulacio= $this->dadesGrup[$i][9];
			$comentaris= $this->dadesGrup[$i][10];

         // echo "enviamentMissatges<br />";

			/* #############        Preparo el missatge llarg que      ############# */
			/* #############        hauria de rebre cada alumne        ############# */
			$msgAlumneLlarg = $this->__prepararMsgLlargAlumne($titolCurs, $datesRealitzacio, $codiCurs,
			$any, $edicio, $hores, $nom, $cog, $dni, $tel, $email, $adreca, $cp, $poble,
			$perfil, $titulacio, $comentaris, $nomCogPersonaContacte, $emailPersonaContacte);

			/* #############         Envio el missatge llarg que        ############# */
			/* #############        hauria de rebre cada alumne        ############# */
			$nomFromHead = 'Secretaria PrisMa';
			$correuFromHead = 'secretaria@prisma.cat';
			$nomReplyHead = $nom." ".$cog;
			$correuReplyHead = $email;

			$nomTo = "PrisMa Secretaria";
			$correuTo = "resguard.secretaria@prisma.cat";
         // $correuTo = 'meriem.prisma.cat@gmail.com';

			$subject2 = "Inscripció al curs ".$titolCurs." ".$dataInsc;

			$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
												$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
												$subjectMailAlumne2, $msgAlumneLlarg);
                                    // echo "enviamentMissatgeCopia<br />";

			$nomTo = 'Secretaria PrisMa';
			$correuTo = 'inscripcions@prisma.cat';
         // $correuTo = 'meriem.prisma.cat@gmail.com';

			$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
												$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
												$subjectMailAlumne, $msgAlumneLlarg);
			// echo "envia missatge llarg de cada alumne a inscripcions@prisma.cat<br />";
		}

		/* #############  Inserim les dades de l'alumne a la BD   ############# */
		$pagFraccAlumne = 0;
		$mailingAlumne = 'X';
		$currentDate = 'CURRENT_DATE';
		$perenne = 'X';
		$obsAlumne = "Descompte per grup. Paga ".$dniPersonaContacte;
		$reclamatAlumne = "Paga ".$nomCogPersonaContacte;
		$obsPagAlumne = "Paga ".$nomCogPersonaContacte;

		$insertBD = "INSERT INTO inscripcions (ANY, MES, CURS, DATA_INSC, NOM, COGNOMS,
						 CORREU, DNI, ADRECA, Codi_Postal, POBLACIO, PERFIL, TITULACIO,
						 TELEFON, OBSERVACIONS, COMENTARIS, FRACCIONAT, INSC_MAILING,
						 A_PAGAR, USUARI, IDPAG, PERENNE, CONEGUT, reclamat, pag_observacions, TIPUS_INSC)
						 VALUES (?,?,?,CURRENT_TIME,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?)";

		$cnsPoble = "SELECT ID FROM poblacions WHERE CP=? AND POBLE=?";
		$insertPoblacions = "INSERT INTO poblacions_validar (CP, POBLE) VALUES (?,?)";

		for ($i = 0; $i < $numAlumnes; $i++) {
			$nomAlumnes  = $this->dadesGrup[$i][0];
			$cogAlumnes = $this->dadesGrup[$i][1];
			$dniAlumnes = $this->dadesGrup[$i][2];
			$telAlumnes = $this->dadesGrup[$i][3];
			$emailAlumnes = $this->dadesGrup[$i][4];
			$adrecaAlumnes = $this->dadesGrup[$i][5];
			$cpAlumnes = $this->dadesGrup[$i][6];
			$pobleAlumnes = $this->dadesGrup[$i][7];
			$perfilAlumnes = $this->dadesGrup[$i][8];
			$titulacioAlumnes = $this->dadesGrup[$i][9];
			$comentarisAlumnes= $this->dadesGrup[$i][10];

			$objNom = new Text( $nomAlumnes );
			$objCog = new Text( $cogAlumnes );
			$objDni = new Text( $dniAlumnes );
			$objEmail = new Text( $emailAlumnes );
			$objTelefon = new Numero( $telAlumnes );
			$objAdreca = new Text( $adrecaAlumnes );
			$objCp = new Text( $cpAlumnes );
			$objPoblacio = new Text( $pobleAlumnes );
			$objPerfil = new Text( $perfilAlumnes );
			$objTitulacio = new Text( $titulacioAlumnes );
			if ( $comentarisAlumnes != '' ) $objComentaris = new Text( $comentarisAlumnes );

			$objNom->arreglarParaulaBD('noms');
			$objCog->arreglarParaulaBD('noms');
			$objDni->arreglarParaulaBD('text_maj');
			$objEmail->arreglarParaulaBD('email');
			$objAdreca->arreglarParaulaBD('text');
			$objCp->arreglarParaulaBD('text_maj');
			$objPoblacio->arreglarParaulaBD('noms');
			$objPerfil->arreglarParaulaBD('text');
			$objTitulacio->arreglarParaulaBD('text');

			$nomAlumne = $objNom->obtenirText();
			$cogAlumne = $objCog->obtenirText();
			$nomCogAlumne = $nomAlumne." ".$cogAlumne;
			$dniAlumne = $objDni->obtenirText();
			$emailAlumne = $objEmail->obtenirText();
			$telefonAlumne = $objTelefon->obtenirNumero();
			$adrecaAlumne = $objAdreca->obtenirText();
			$cpAlumne = $objCp->obtenirText();
			$poblacioAlumne = $objPoblacio->obtenirText();
			$perfilAlumne = $objPerfil->obtenirText();
			$titulacioAlumne = $objTitulacio->obtenirText();
			if ( $comentarisAlumnes != '' ) $comentarisAlumne = $objComentaris->obtenirText();
			else $comentarisAlumne = "";

			$usuariAlumne = '';
			$clean = preg_replace('~[^0-9]+~', '', $dniAlumne);
			preg_match_all('!\d+!', $clean, $numero);
			$usuariAlumne = implode(' ', $numero[0]);

			$tipusInsc = 'G';

			$stmt=$connexio->prepare($insertBD);
			$stmt->bind_param("dsssssssssssdssdsdddsssss", $any, $edicio, $codiCurs,
			$nomAlumne, $cogAlumne, $emailAlumne, $dniAlumne, $adrecaAlumne, $cpAlumne,
			$poblacioAlumne, $perfilAlumne, $titulacioAlumne, $telefonAlumne, $obsAlumne,
			$comentarisAlumne, $pagFraccAlumne, $mailingAlumne, $preuDescompte, $usuariAlumne,
			$idPag, $perenne, $conegut, $reclamatAlumne, $obsPagAlumne, $tipusInsc);

			$stmt->execute();
			$stmt->fetch();
			$connexio->closeStmt();

			$stmt=$connexio->prepare($cnsPoble);
			$stmt->bind_param("ds", $cpAlumne, $poblacioAlumne);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() <= 0 ) {
				$connexio->closeStmt();

				$stmt=$connexio->prepare($insertPoblacions);
				$stmt->bind_param("ds", $cpAlumne, $poblacioAlumne);
				$stmt->execute();
				$stmt->fetch();
			}
			$connexio->closeStmt();
		}

		/* ############# Envio el missatge curt que rep PrisMa ############# */
		$nomFromHead = $nameUser;
		$correuFromHead = $username;
		$nomReplyHead = $nomCogPersonaContacte;
		$correuReplyHead = $emailPersonaContacte;

		$nomTo = "Inscripcions PrisMa";
		$correuTo = "inscripcions.prisma@gmail.com";
      // $correuTo = 'meriem.prisma.cat@gmail.com';

		$mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
											$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
											$subjectMailPersContInsc, $msgPersonaContactCurt);
		// echo "envia missatge curt amb les dades de la persona de contacte i les dades de l'alumne a inscripcions.prisma@gmail.com<br />";

		if ($mailing=='Registred') $mailingBD = 'X';
		else if ($mailing=='Yes') $mailingBD = '1';
		else $mailingBD = '0';

		if ($mailingBD == '1') {
			$cnsMailing = "SELECT ID FROM mailing WHERE MAIL=?";
			$stmt=$connexio->prepare($cnsMailing);
			$stmt->bind_param("s", $emailPersonaContacte);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() <= 0 ) {
				$connexio->closeStmt();

				$insertMailing = "INSERT INTO mailing (mail,nom,usuari) VALUES (?,?,?)";
				$stmt=$connexio->prepare($insertMailing);
				$stmt->bind_param("sss", $emailPersonaContacte, $nomPersonaContacte, $usuariAlumne);
				$stmt->execute();
				$stmt->fetch();
			}
			$connexio->closeStmt();
		}

		$stmt=$connexio->prepare($cnsPoble);
		$stmt->bind_param("ds", $cpPersonaContacte, $poblacioPersonaContacte);
		$stmt->execute();
		$stmt->store_result();
		if ( $stmt->num_rows() <= 0 ) {
			$connexio->closeStmt();

			$stmt=$connexio->prepare($insertPoblacions);
			$stmt->bind_param("ds", $cpPersonaContacte, $poblacioPersonaContacte);
			$stmt->execute();
			$stmt->fetch();
		}
		$connexio->closeStmt();

		/* ######################################################################### */

		//buscar el username i el password d'autentificació de prisma
		$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
						AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
		$stmt=$connexio->prepare($cnsParam);
		$stmt->bind_param("s", $tipusParam);
		$tipusParam = 'autentificacioInscripcio';
		$stmt->execute();
		$stmt->bind_result($valor);
		$stmt->fetch();
		$autentificacioInscripcio = explode('|',$valor);
		$username = $autentificacioInscripcio[0];
		$password = $autentificacioInscripcio[1];
		$connexio->closeStmt();

		/* ############# Envio el missatge llarg a la persona de contacte ############# */
		$nomFromHead = $nameUser;
		$correuFromHead = $username;
		$nomReplyHead = $nomCogPersonaContacte;
		$correuReplyHead = $emailPersonaContacte;

		$nomTo = "PrisMa Secretaria";
		$correuTo = "resguard.secretaria@prisma.cat";
      // $correuTo = 'meriem.prisma.cat@gmail.com';

		$mailPersCont = new MailSMTPComvive(	$username, $password, $nomFromHead, $correuFromHead,
												$nomReplyHead, $correuReplyHead, $nomTo,
												$correuTo, $subjectMailPersCont2, $msgPersonaContactLlarg);

		$nomTo = $nomCogPersonaContacte;
		$correuTo = $emailPersonaContacte;

		$mailPersCont = new MailSMTPComvive(	$username, $password, $nomFromHead, $correuFromHead,
												$nomReplyHead, $correuReplyHead, $nomTo,
												$correuTo, $subjectMailPersCont, $msgPersonaContactLlarg);

		// echo "envia missatge dades de contacte a la persona de contacte<br />";

		/* ############# Envio el missatge llarg a cada alumne ############# */
		for ($i = 0; $i < $numAlumnes; $i++) {
			$nom = $this->dadesGrup[$i][0];
			$cog= $this->dadesGrup[$i][1];
			$dni= $this->dadesGrup[$i][2];
			$tel= $this->dadesGrup[$i][3];
			$email= $this->dadesGrup[$i][4];
			$adreca= $this->dadesGrup[$i][5];
			$cp= $this->dadesGrup[$i][6];
			$poble= $this->dadesGrup[$i][7];
			$perfil= $this->dadesGrup[$i][8];
			$titulacio= $this->dadesGrup[$i][9];
			$comentaris= $this->dadesGrup[$i][10];

			/* #############        Preparo el missatge llarg que      ############# */
			/* #############        hauria de rebre cada alumne        ############# */
			$msgAlumneLlarg = $this->__prepararMsgLlargAlumne($titolCurs, $datesRealitzacio, $codiCurs, $any, $edicio, $hores, $nom, $cog, $dni, $tel, $email,
			$adreca, $cp, $poble, $perfil, $titulacio, $comentaris, $nomCogPersonaContacte, $emailPersonaContacte);


			$nomFromHead = $nameUser;
			$correuFromHead = $username;
			$nomReplyHead = $nomCogPersonaContacte;
			$correuReplyHead = $emailPersonaContacte;

			$nomTo = "PrisMa Secretaria";
			$correuTo = "resguard.secretaria@prisma.cat";
         // $correuTo = 'meriem.prisma.cat@gmail.com';

			$mailPersCont = new MailSMTPComvive(	$username, $password, $nomFromHead, $correuFromHead,
													$nomReplyHead, $correuReplyHead, $nomTo,
													$correuTo, $subjectMailAlumne2, $msgAlumneLlarg);

			$nomTo = $nom." ".$cog;
			$correuTo = $email;

			$mailPersCont = new MailSMTPComvive(	$username, $password, $nomFromHead, $correuFromHead,
													$nomReplyHead, $correuReplyHead, $nomTo,
													$correuTo, $subjectMailAlumne, $msgAlumneLlarg);
			// echo "envia missatge dades de cada alumne a la cada alumnes<br />";
		}
		$connexio->desconectarBD();
		return $hashIdPag;
	}

	private function __prepararMsgLlargAlumne($titolCurs, $datesRealitzacio, $codiCurs,
	$any, $mes, $hores, $nom,	$cog, $dni, $tel, $email, $adreca, $cp, $poble, $perfil,
	$titulacio, $comentaris, $nomCogPersonaContacte, $emailPersonaContacte) {
      $templates = new Template();

		$objNom = new Text( $nom );
		$objCog = new Text( $cog );
		$objDni = new Text( $dni );
		$objEmail = new Text( $email );
		$objTelefon = new Numero( $tel );
		$objAdreca = new Text( $adreca );
		$objCp = new Text( $cp );
		$objPoblacio = new Text( $poble );
		$objPerfil = new Text( $perfil );
		$objTitulacio = new Text( $titulacio );
		if ( $comentaris != '' ) $objComentaris = new Text( $comentaris );

		$objNom->arreglarParaulaBD('noms');
		$objCog->arreglarParaulaBD('noms');
		$objDni->arreglarParaulaBD('text_maj');
		$objEmail->arreglarParaulaBD('email');
		$objAdreca->arreglarParaulaBD('text');
		$objCp->arreglarParaulaBD('text_maj');
		$objPoblacio->arreglarParaulaBD('noms');
		$objPerfil->arreglarParaulaBD('text');
		$objTitulacio->arreglarParaulaBD('text');

		$nomAlumne = $objNom->obtenirText();
		$cogAlumne = $objCog->obtenirText();
		$nomCogAlumne = $nomAlumne." ".$cogAlumne;
		$dniAlumne = $objDni->obtenirText();
		$emailAlumne = $objEmail->obtenirText();
		$telefonAlumne = $objTelefon->obtenirNumero();
		$adrecaAlumne = $objAdreca->obtenirText();
		$cpAlumne = $objCp->obtenirText();
		$poblacioAlumne = $objPoblacio->obtenirText();
		$perfilAlumne = $objPerfil->obtenirText();
		$titulacioAlumne = $objTitulacio->obtenirText();
		if ( $comentaris != '' ) $comentarisAlumne = $objComentaris->obtenirText();
		else $comentarisAlumne = "";

		require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

		/* ######################################################################### */

		// $textCursReconegut = "<p>Aquest curs està reconegut pel Departament d'Educació
		// de la Generalitat de Catalunya i té una durada lectiva de <strong>".$hores." hores</strong>.</p>";

		$cnsDatesCurs = "SELECT DATAI, DATAF, HORES, DATA_RESOL, CURS_ESCOLAR FROM curs WHERE CURS=? AND ANY=? AND MES=?";
		$stmt=$connexio->prepare($cnsDatesCurs);
		$stmt->bind_param("sds", $codiCurs, $any, $mes);
		$stmt->execute();
		$stmt->bind_result($datai, $dataf, $hores, $data_resol, $curs_escolar);
		$stmt->fetch();
		$connexio->closeStmt();

		// if ($data_resol==null or $data_resol=='') {
		// 	$textCursReconegut = "<p>Aquest curs té una durada lectiva de <strong>";
		// 	$textCursReconegut .= $hores." hores</strong>.<p>
		// 	<p>PrisMa, com a entitat organitzadora, ha sol·licitat el reconeixement de
		// 	les edicions del curs escolar ".$curs_escolar." al Departament d'Educació. Tan bon
		// 	punt surti la resolució t'avisarem a través del correu electrònic.";
		// }

		// if ( 	strpos( $perfilAlumne, 'Consulta privada:') !== false ||
		// 		strpos( $perfilAlumne, 'No estic treballant:') !== false ||
		// 		strpos( $perfilAlumne, 'Altres:') !== false ) {
		// 	$textCursReconegut .= "Els nostres cursos compten com Formació Permanent
		// 	del Professorat sempre que es realitzin posteriorment a la data d’expedició
		// 	del títol d’accés a la docència (Magisteri o CAP / Màster en Educació Secundària).";
		// }

      /* ######################################################################### */
   	$titolDocencia = 0;
      if ( 	strpos( $perfilAlumne, 'Consulta privada:') !== false ||
				strpos( $perfilAlumne, 'No estic treballant:') !== false ||
				strpos( $perfilAlumne, 'Altres:') !== false )  {
   		$titolDocencia = 1;
   	}

		/* ######################################################################### */
		// $textEstudiant = '';
		// if ( strpos( $titulacioAlumne, 'Encara no tinc cap titulació, sóc estudiant de,') !== false ) {
		// 	$textEstudiant = "<p>En el teu cas, ​atès que encara ​no disposes d'aquesta titulació
		// 	finalitzada, rebràs un certificat de PrisMa que p​odràs fer constar​ com ​a ​
		// 	currículum personal però ​que ​no ​te donarà punts en convocatòries oficials
		// 	del Departament d'Educació. En el cas que tinguis uns estudis universitaris
		// 	finalitzats, si us plau, contacta amb nosaltres perquè rectifiquem la sol·licitud.</p>";
		// }

		/* ######################################################################### */
		$textHasRealitzatCurs='';
		//Buscar si el usuari XXX ha realitzat el curs xxx
		$cnsInsc = "SELECT ANY, MES FROM inscripcions WHERE CURS=? AND DNI=? AND GENERAT=1";
	   $stmt=$connexio->prepare($cnsInsc);
	   $stmt->bind_param("ss", $codiCurs, $dniAlumne);
	   $stmt->execute();
	   $stmt->store_result();
		if ($stmt->num_rows() > 0) {
			$stmt->bind_result($anyReal, $mesReal);
			$stmt->fetch();
		   $connexio->closeStmt();

			$cnsTitol = "SELECT NOM_CURS FROM curs WHERE CURS=? AND ANY=? AND MES=?";
		   $stmt=$connexio->prepare($cnsTitol);
		   $stmt->bind_param("sds", $codiCurs, $anyReal, $mesReal);
		   $stmt->execute();
			$stmt->bind_result($titolReal);
			$stmt->fetch();
			$connexio->closeStmt();

			$objMes = new Text($mesReal);
			$textMesReal = $objMes->obtenirMesLlarg();

			$textHasRealitzatCurs = "<p>Ja has realitzat el curs <strong>".$titolReal."</strong> ";
			$textHasRealitzatCurs .= "en l'edició <strong>".$textMesReal."</strong> ";
			$textHasRealitzatCurs .= "de l'any <strong>".$anyReal."</strong>.</p>";
		}
	   $connexio->closeStmt();

		/* ######################################################################### */
		$textIniciCurs = "";
		//Buscar si el usuari XXX ha realitzat algun curs
		$consAlumnePrisMa = "SELECT ID FROM inscripcions WHERE DNI=? AND
                     ((A_PAGAR>0 AND PAGAMENT>0) OR
                     (A_PAGAR=0 AND OBSERVACIONS LIKE '%CURS REGAL%')) AND
                     UPPER(`INSC CURS`)!=?";
		$stmtPrisMa = $connexio->prepare($consAlumnePrisMa);
		$stmtPrisMa->bind_param("ss", $dniAlumne, $inscurs);
		$inscurs='D';
		$stmtPrisMa->execute();
		$stmtPrisMa->store_result();
		if ($stmtPrisMa->num_rows() > 0)
		   $alumnePrisma = true;
		else
		   $alumnePrisma = false;

		$connexio->closeStmt();


		// //Busquem la data d'inici del curs
		// if ( $datai <= date('Y-m-d') ) { /*ha començat el curs*/
		// 	$textIniciCurs = "<p>En un període de 24 hores laborals podràs accedir al
		// 	curs amb les teves claus.</p>";
		// 	if ( !$alumnePrisma )
		// 		$textIniciCurs = "<p>En un període de 24 hores laborals rebràs un correu
		// 		electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
		// }
		// else {
		// 	$textIniciCurs = "<p>Uns dies abans de l'inici del curs podràs accedir a
		// 	l'apartat general de l'aula amb les teves claus.</p>";
		// 	if ( !$alumnePrisma )
		// 		$textIniciCurs = "<p>Uns dies abans de l'inici del curs rebràs un correu
		// 		electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
		// }
		/* ######################################################################### */

		// $textDadesAlumnes = "<p>Les dades que ens han facilitat per fer el curs són les següents:</p>
		// <div style='background-color:rgb(232,236,245);border:1px solid rgb(215,222,238);
		// border-top-left-radius:2px;border-top-right-radius:2px;border-bottom-right-radius:2px;
      //    border-bottom-left-radius:2px;padding:25px'>
		// 	<p><strong>Nom:</strong> ".$nomCogAlumne."</p>
		// 	<p><strong>Document d’identificació:</strong> ".$dniAlumne."</p>
		// 	<p><strong>Correu electrònic:</strong> ".$emailAlumne."</p>
		// 	<p><strong>Telèfon:</strong> ".$telefonAlumne."</p>
		// 	<p><strong>Adreça:</strong> ".$adrecaAlumne."</p>
		// 	<p><strong>CP:</strong> ".$cpAlumne."</p>
		// 	<p><strong>Població:</strong> ".$poblacioAlumne."</p>
		// 	<p><strong>Estic treballant a:</strong> ".$perfilAlumne."</p>
		// 	<p><strong>Titulació/especialitat:</strong> ".$titulacioAlumne."</p>
		// 	<p><strong>Comentaris:</strong> ".$comentarisAlumne."</p>
		// </div>";
      // TEXT_DADES_ALUMNE $textDadesAlumnes

      $msg = $templates->getTemplate_Dades_RequadreDadesPersonalsProfessionals();
   	$names_template = array("[NOM_ALUMNE]", "[COG_ALUMNE]", "[DNI_ALUMNE]",
         "[EMAIL_ALUMNE]", "[TEL_ALUMNE]", "[ADRECA_ALUMNE]", "[CP_ALUMNE]",
         "[POBLACIO_ALUMNE]", "[PERFIL_ALUMNE]", "[TITULACIO_ALUMNE]", "[COMENTARIS_ALUMNE]");
   	$names_function   = array($nomAlumne, $cogAlumne, $dniAlumne, $emailAlumne,
         $telefonAlumne, $adrecaAlumne, $cpAlumne, $poblacioAlumne,
         $perfilAlumne, $titulacioAlumne, $comentarisAlumne);
   	$textDadesAlumnes = str_replace($names_template, $names_function, $msg);

		/* ######################################################################### */

		// $textPagament = "<p>Si tens algun dubte sobre el pagament o sobre la teva
		// disponibilitat per fer el curs en aquestes dates, posa’t en contacte amb
		// <strong>".$nomCogPersonaContacte."</strong> al correu electrònic
		// <strong>".$emailPersonaContacte."</strong>.</p>";


		/* ######################################################################### */

      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("sss", $tipus, $valor, $orderBy);
      $tipus = 'alerta-confirmació-inscripcio';
      $valor = $codiCurs.'%';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->store_result();
      // Si té una alerta d'inscripció
      if ( $stmtParam->num_rows() > 0 ) {
         $stmtParam->bind_result($valorRes);
         $stmtParam->fetch();
         $arrValors = explode('|',$valorRes);
         $textAlertaConfirmacioInscripcio = "<div style='background-color:#e8ecf5;border:1px solid #d7deee;
         border-radius:2px;padding:5px 25px;margin-bottom:20px'>";
         $textAlertaConfirmacioInscripcio .= $arrValors[1];
         $textAlertaConfirmacioInscripcio .= "</div>";
      }
   	else {
   		$textAlertaConfirmacioInscripcio = '';
   	}

		$connexio->closeStmt();

      $connexio->desconectarBD();

		/* ######################################################################### */
      //
		// $msg = "<p>Benvolgut/da ".$nomAlumne.",</p>
		// <p>T’informem que hem rebut una sol·licitud per realitzar el curs en línia
		// <strong>".$titolCurs."</strong>, que es realitza <strong>".$datesRealitzacio."</strong>.</p>
		// ".$textCursReconegut."
		// ".$textEstudiant."
		// ".$textHasRealitzatCurs."
		// ".$textDadesAlumnes."
		// ".$textIniciCurs."
		// ".$textPagament."
		// <p>Si vols modificar alguna dada de la inscripció, posa’t en contacte amb secretaria@prisma.cat.</p>
      // ".$textAlertaConfirmacioInscripcio."
		// <p>Atentament,</p>";

      $msg = $templates->getTemplate_Inscripcions_EnviamentAlumnesDescompteGrup(
         $alumnePrisma, $data_resol, $titolDocencia, $titulacioAlumne);
      $names_template = array("[NOM_ALUMNE]", "[TITOL]", "[DATAI_DATAF]",
         "[HORES]", "[CURS_ESCOLAR]", "[TEXT_HAS_REALITZAT_CURS]", "[TEXT_DADES_ALUMNE]",
         "[NOM_COG_CONTACTE]", "[EMAIL_CONTACTE]", "[TEXT_ALERT_CONF_INSCR]"
      );
      $names_function   = array($nomAlumne, $titolCurs, $datesRealitzacio,
         $hores, $curs_escolar, $textHasRealitzatCurs, $textDadesAlumnes,
         $nomCogPersonaContacte, $emailPersonaContacte, $textAlertaConfirmacioInscripcio
      );
      $msg = str_replace($names_template, $names_function, $msg);

		return $msg;
	}

	/*
   * @brief Retorna un modal de càrrega "buscant"
   * @return Retorna un modal de càrrega "buscant"
   */
   private function __modalCercantCursos() {
      $modal = "<div class='modal carrega' id='modalCercantCursos' tabindex='-1' role='dialog'
      aria-labelledby='modalCercantCursos' style='display: none' aria-modal='true'>
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
   * @brief Mostra un modal d'avis
   * @return Retorna un modal d'avis
   */
   private function __modalCorreuValid() {
      $mostrar = "<div class='modal fade in' id='modalCorreuValid' tabindex='-1' role='dialog' aria-labelledby='modalCorreuValidTitle' aria-hidden='true'>
   		<div class='modal-dialog modal-dialog-centered modal-notify modal-warning' role='document'>
   			<div class='modal-content border-0'>
   				<div class='modal-header bg-warning justify-content-center'>
   					<p class='modal-title modal-title-warning font-weight-bold m-0' id='modalCorreuValidTitle'>Avís</p>
   					<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button>
   				</div>
   				<div class='modal-body text-center' id='modalCorreuValidBody'></div>
   				<div class='modal-footer justify-content-center text-center border-0 pt-0 mb-2'>
   					<button role='button' data-dismiss='modal' class='btn btn-warning border-0 border-radius-2 text-center negreta500 m-0 mr-3'>D'acord</button>
   				</div>
   			</div>
   		</div>
   	</div>";

   	return $mostrar;
   }

	/*
   * @brief Mostra un modal d'afegir alumne
   * @return Retorna un modal d'afegir alumne
   */
   private function __modalAfegirAlumne() {
		$dadesPersonals = "";
		$formNomCognoms = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0'>
						<span class='camp'>Nom</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='nom' name='nom' value=\"".$nom."\">
					<span id='nom_cognom_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label>
						<span class='camp'>Cognoms</span>
						<span class='req font-weight-bold'>*</span>
					</label>
      			<input type='text' class='form-control' id='cog' name='cog' value=\"".$cognoms."\">
					<span id='cognom_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
		</div>";
		$formSelectDniTel = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<div id='doc' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
						<span class='element-selected font-weight-normal w-100'>NIF/NIE</span>
						<ul class='select-list position-absolute ' style='display: none;'>
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
					<input type='text' class='form-control' id='nif' name='nif' value=\"".$dni."\">
					<span id='dni_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0'>
						<span class='camp'>Telèfon de contacte</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='tel' class='form-control' id='telf' name='telf' pattern='[6-9]{1}[0-9]{8}' maxlength='9'  value=\"".$telefon."\">
					<span id='telf_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
		</div>";

		$formCorreus = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0'>
						<span class='camp'>Correu electrònic</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='email' class='form-control' id='email' name='email'  value=\"".$email."\">
					<span id='correu_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
			<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0'>
						<span class='camp'>Confirmaci&oacute del correu electrònic</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='email' class='form-control' id='email_conf' name='email_conf'  value=\"".$email."\">
					<span id='correu_conf_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
		</div>".$this->__modalCorreuValid();

		$formAdreca = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 pl-0 pr-0 pr-md-2'>
				<div class='form-group field-wrap position-relative'>
					<label class='position-absolute mb-0'>
						<span class='camp'>Adreça (carrer, número,...)</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='adreca' name='adreca' maxlength='150'  value=\"".$adreca."\">
					<span id='adreca_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
		</div>";

		$formCpPoble = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
				<div class='cnt-cp form-group field-wrap position-relative' id='cp_box'>
					<label class='position-absolute mb-0'>
						<span class='camp'>Codi postal</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='cp' name='cp' maxlength='5'  value=\"".$cp."\">
					<span id='cp_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
				</div>
			</div>
			<div class='col-12 col-md-9 pl-0 pr-0 pr-md-2'>
				<div class='cnt-poble form-group field-wrap' id='poble_box'>
					<label class='position-absolute mb-0'>
						<span class='camp'>Poblaci&oacute</span>
						<span class='req font-weight-bold'>*</span>
					</label>
					<input type='text' class='form-control' id='poble' name='poble' maxlength='50' value=\"".$poblacio."\">
					<span id='poble_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
					<ul id='llistat_poblacions' style='display: none'
					class='select-list position-absolute' role='listbox'></ul>
				</div>
			</div>
		</div>";

		$dadesPersonals = "<h3>Dades personals</h3>".$formNomCognoms.$formSelectDniTel.$formCorreus.$formAdreca.$formCpPoble;

		$dadesCurriculars = "
		<div class='form-dades'>
			<h3>Dades curriculars</h3>
			<div class='d-flex flex-column align-items-center justify-content-center'>
				<div class='d-flex flex-row align-items-center justify-content-center w-100'>
					<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
						<div class='form-group field-wrap position-relative'>
							<div id='perfil' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
					         <span class='element-selected font-weight-normal w-100'>Estic treballant a</span>
					         <ul class='select-list position-absolute' style='display: none;'>
						         <li class='border-bottom m-0' id='perfil-edInfantil03'><a href='#'>Ed. Infantil (0-3)</a></li>
						         <li class='border-bottom m-0' id='perfil-edInfantil35'><a href='#'>Ed. Infantil (3-5)</a></li>
						         <li class='border-bottom m-0' id='perfil-edPrimaria'><a href='#'>Ed. Primària</a></li>
						         <li class='border-bottom m-0' id='perfil-edEspecial'><a href='#'>Ed. Especial</a></li>
						         <li class='border-bottom m-0' id='perfil-edSecundaria'><a href='#'>Ed. Secundària (Cicles Formatius, ESO, Batxillerat)</a></li>
						         <li class='border-bottom m-0' id='perfil-consultaPrivada'><a href='#'>Consulta privada</a></li>
						         <li class='border-bottom m-0' id='perfil-noEsticTreballant'><a href='#'>No estic treballant</a></li>
						         <li class='border-bottom m-0' id='perfil-altres'><a href='#'>Altres</a></li>
					         </ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i>
				         </div>
							<span id='perfil_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
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
					         </ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i>
         				</div>
							<span id='titulacio_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white' ></span>
						</div>
					</div>
				</div>
				<div class='d-flex flex-column align-items-center justify-content-center w-100' id='perfils-altres'></div>
				<div class='d-flex flex-row align-items-center justify-content-center w-100' id='titulacions-altres'></div>
				<div class='d-flex flex-row align-items-center justify-content-center w-100' id='titulacions-secundaria'></div>
				<div class='d-flex flex-row align-items-center justify-content-center w-100' id='titulacions-estudiant'></div>
				<div class='d-flex flex-row align-items-center justify-content-center w-100'>
					<div class='col-12 pl-0 pr-0 pr-md-2'>
						<div class='form-group field-wrap position-relative'>
							<label>
								<span class='camp'>També tinc la titulaci&oacute de</span>
							</label>
							<input type='text' class='form-control' id='titol_de' name='titol_de' maxlength='150' >
						</div>
					</div>
				</div>
			</div>
		</div>";

		$comentaris = "<h3>Tens algun comentari?</h3>
		<div class='form-group field-wrap position-relative'>
	      <label>
				<span class='camp'>Comentaris</span>
			</label>
	      <textarea class='form-control' id='comentaris'></textarea>
      </div>";

      $mostrar = "<div class='modal fade in' id='modalAfegirAlumne' tabindex='-1' role='dialog' aria-labelledby='modalAfegirAlumneTitle' aria-hidden='true'>
   		<div class='modal-dialog modal-dialog-centered modal-notify modal-info' role='document'>
   			<div class='modal-content border-0'>
   				<div class='modal-body pb-0' id='modalAfegirAlumneBody'>
					<button role='button' class='close' data-dismiss='modal' aria-label='Close'>
						<span aria-hidden='true'>×</span>
					</button>
						".$dadesPersonals.$dadesCurriculars.$comentaris."
					</div>
   				<div class='modal-footer justify-content-center text-center border-0 pt-0 mb-2'>
					<button id='form_afegir_alumne' class='boto-blau text-white font-weight-bold
					text-center border-radius-2 border-0 m-0'>Afegir alumne</button>
   				</div>
   			</div>
   		</div>
   	</div>";

   	return $mostrar;
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


	/*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
}

?>
