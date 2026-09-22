<?php

/**
* @class DescompteGrup
* @brief Conté tota la informació relacionada amb una Descompte per a grups o centres escolars.
*/

class Descomptes {
	/*********************************** FUNCIONS CONSTRUCTORS ***********************************/

	public function __construct( $dispositiu ) {
		$this->dispositiu = $dispositiu;
	}

	/*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

	/*
		* @brief Obtinc el nombre d'hores que hi ha disponbile en els cursos
	*/
	private function __getHoresCursos() {
		require_once 'ConnexioBBDD_PreparedStatment.php';
		$connexio = new ConnexioBBDDSTMT();
		$connexio->connectarBD();

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
		   if ($hores!=0) {
		      $vectHores[] = $hores;
		   }
		}
		$connexio->closeStmt();

		sort($vectHores, SORT_NUMERIC);

		$connexio->desconectarBD();

		return $vectHores;
	}
	/*
		* @brief Obtinc el idPreu dels cursos amb hores $hores
	*/
	private function __getIdPreu($hores) {
		require_once 'ConnexioBBDD_PreparedStatment.php';
    $connexio = new ConnexioBBDDSTMT();
    $connexio->connectarBD();

		$cnsExsiteixCurs = "SELECT ID_PREU FROM curs WHERE
			DATAI+7>CURRENT_DATE AND PUBLIC=1 AND HORES=?  AND id_preu != 14
			AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
			ORDER BY ANY, MES LIMIT 1";
		$stmtEx=$connexio->prepare($cnsExsiteixCurs);
		$stmtEx->bind_param("d", $hores);
		$stmtEx->execute();
		$stmtEx->store_result();
		if ( $stmtEx->num_rows() > 0 ) {
			 $stmtEx->bind_result($idPreu);
			 $stmtEx->fetch();
			 $connexio->closeStmt();
		 }
		 $connexio->desconectarBD();

		 return $idPreu;
	}

	private function __getPreuCar($idPreu) {
		require_once 'ConnexioBBDD_PreparedStatment.php';
    $connexio = new ConnexioBBDDSTMT();
    $connexio->connectarBD();

		$cnsPreu = "SELECT IMPORT FROM preu WHERE ID=?  AND id_preu != 14 AND
			DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
		$stmtPreu=$connexio->prepare($cnsPreu);
		$stmtPreu->bind_param("d", $idPreu);
		$stmtPreu->execute();
		$stmtPreu->bind_result($preu);
		$stmtPreu->fetch();

		if ( $preu > 0 ) {
				$objPreu = new Numero($preu);
				$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();
		}
		else {
				$preuFormatCorrecte = 0;
		}
		$connexio->desconectarBD();

		return $preuFormatCorrecte;
	}

	private function __getPreuDescompte($idPreu, $type) {
		require_once 'ConnexioBBDD_PreparedStatment.php';
    $connexio = new ConnexioBBDDSTMT();
    $connexio->connectarBD();

		$cnsPreu = "SELECT PREU FROM descomptes WHERE ID_PREU=? AND
			TIPUS = ? AND id_preu != 14
			DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
		$stmtPreu=$connexio->prepare($cnsPreu);
		$stmtPreu->bind_param("dd", $idPreu, $type);
		$stmtPreu->execute();
		$stmtPreu->bind_result($preu);
		$stmtPreu->fetch();
		if ( $preu > 0 ) {

				$objPreu = new Numero($preu);
				$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();
		}
		else {
			$preuFormatCorrecte = 0;
		}
		$connexio->desconectarBD();

		return $preuFormatCorrecte;
	}



	/* #################################  FUNCIONS MOSTRAR ELEMENTS  ################################# */
	/*
   * @brief Retorna la pàgina inicial
   * @return Retorna el contingut inicial
   */
	public function mostrarPagina() {
		$pagina = "<div class='container'><div class='row'><div class='col-12'>";
		$pagina .= $this->__getTitle();
		$pagina .= $this->__getBanner();
		$pagina .= $this->__getContentPage();
		$pagina .= "</div></div></div>";
		return $pagina;
   }

	/**
   * @brief Retorna la informació del titol
   * @return Retorna la informació del titol
   */
	private function __getTitle() {
		$container_titol = "<div class='titol my-4'>
			<h1>Descomptes PrisMa</h1>
			<div class='d-flex flex-column flex-sm-row align-items-left'>
				<div class='subtitol flex-grow-1 py-2'>Gaudiu dels descomptes que ofereix PrisMa</div>
			</div>
		</div>";
		return $container_titol;
	}

	/**
   * @brief Retorna la imatge allargada del regal
   * @return Retorna la imatge allargada del regal
   */
	public function __getBanner() {
		$baner = new Imatge(574);
		$versio = $baner->obtenirVersio();
		$altImg = $baner->obtenirAlt();
		$linkImg = $baner->obtenirLink();

		$linkImgWebP = substr($linkImg, 0, -4).".webp?ver=".$versio;
		$linkImg = $linkImg."?ver=".$versio;

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

	public function __getContentPage() {
		$cntDescAlumnPrisma = "<h2>Alumnes PrisMa".$icon."</h2>".$this->__getSection(1);
		$cntDescCarnetJove = "<h2>Carnet jove".$icon."</h2>".$this->__getSection(2);
		$cntDescSocials = "<h2>Socials".$icon."</h2>".$this->__getSection(3);
		$cntDescUsoc = "<h2>Unió Sindical Obrera de Catalunya".$icon."</h2>".$this->__getSection(4);
		$cntDescGrups = "<h2>Grups i centres escolars".$icon."</h2>".$this->__getSection(5);

		//Preparem la pàgina
		$page = "<div class='separacio-peu border border-radius-2 bg-white px-4 py-2'>
			<div id='cntPage' class='cntPage'>
				<p>Des de l’Associació PrisMa oferim diferents tipus de descomptes (no acumulables):</p>
				".$cntDescAlumnPrisma."
				".$cntDescCarnetJove."
				".$cntDescSocials."
				".$cntDescUsoc."
				".$cntDescGrups."
			</div>
		</div>";

		return $page;
	}

	public function __getSection( $type ) {
		//Buscar descomptes
		if ( $type == 1 ) {
			//Alumnes PrisMa
			$cnt = "<p>Les persones que esteu fent o hàgiu fet un curs a PrisMa gaudireu d’un descompte en el preu dels cursos:</p>";
			$table = $this->calcTableDesc( 1 );
			$cnt .= $table;
			$cnt .= "<p>En el moment de fer la inscripció, en posar el número de DNI en el formulari es recalcularà el preu i s’aplicarà el descompte automàticament.</p>";
		}
		else if ( $type == 2 ) {
			//Carnet jove
			$cnt = "<p>Les persones que tingueu un Carnet Jove vigent gaudireu d’un descompte en el preu dels cursos:</p>";
			$table = $this->calcTableDesc( 2 );
			$cnt .= $table;
			$cnt .= "<p>En el moment de fer la inscripció, després de posar el número de DNI haureu de marcar en el formulari que teniu el Carnet Jove i automàticament es recalcularà el preu i s’aplicarà el descompte.</p>";
		}
		else if ( $type == 3 ) {
			//Socials
			$cnt = "<p>Oferim un descompte del 20 % sobre el preu del curs a les persones que tingueu el carnet de família nombrosa, de família monoparental, o de discapacitat del 33 % o més.</p>";
			$table = $this->calcTableDesc( 5 );
			$cnt .= $table;
			$cnt .= "<p>En el moment de fer la inscripció caldrà que adjunteu un document que justifiqui el descompte (carnet, resolució, etc.) i esperar a la validació des de PrisMa per rebre les dades per fer el pagament amb el descompte.</p>";
		}
		else if ( $type == 4 ) {
			//USOC
			$cnt = "<p>Oferim un descompte del 20 % sobre el preu del curs a les persones que estigueu afiliades a la Unió Sindical Obrera de Catalunya (USOC).</p>";
			$table = $this->calcTableDesc( 4 );
			$cnt .= $table;
			$cnt .= "<p>En el moment de fer la inscripció caldrà marcar en el formulari l'opció de l’afiliació a la USOC. Les vostres dades s’enviaran a la USOC per tal que ho validi, i un cop fet això rebreu les instruccions per fer el pagament amb el descompte.</p>";
		}
		else if ( $type == 5 ) {
			$table = $this->calcTableDescGroups();

			$cnt = "<p>Si tres persones o més us inscriviu com a grup en un dels nostres cursos en línia, se us aplicarà un descompte segons el nombre d’integrants del grup:</p>";
			$cnt .= $table;
			$cnt .= "<p>A més, si sou 15 alumnes o més podreu gaudir d’una aula exclusiva per al vostre grup.</p>";
			$cnt .= "<p>Més informació i formulari d’inscripció grupal a
			<a href='https://www.prisma.cat/descompte-curs-grup' target='_self' title='Descompte per a grups!'>https://www.prisma.cat/descompte-curs-grup</a>.</p>";
		}

		return $cnt;
	}

	/*
	* Retorna una taula amb els descomptes del tipus de descompte $type
	* @param $tyoe => és el tupus que correspon a la taula de descomptes
	*/
	private function calcTableDesc( $type ) {
		$hores = $this->__getHoresCursos();

		$table = "<table id='taula-descomptes' class='table table-striped table-hover w-100 mb-2'>
			<thead>
				<tr>
					<th>Hores</th>
					<th>Preu original</th>
					<th>Preu amb descompte</th>
				</tr>
			</thead>
			<tbody>";

		for ( $i = 0; $ i < count($hores); $i++ ) {
			$idPreu = $this->__getIdPreu( $hores[$i] );
			$preuCar = $this->__getPreuCar($idPreu);
			$preuDescompte = $this->__getPreuDescompte($idPreu, $type);

			$table. = "<tr>
				<td>".$hores[$i]."</td>
				<td>".$preuCar." €</td>
				<td>".$preuDescompte." €</td>
			</tr>";
		}

		$table .= "</tbody></table>";
	}

	private function calcTableDescGroups() {
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

		   if ($hores!=0  && $hores!=50 && $hres != 70) {
		      $vectHores[] = $hores;
		      $vectDiesOberts[] = $diesOberts;
		   }
		}
		$connexio->closeStmt();

		sort($vectHores, SORT_NUMERIC);

		$thSenseDescompte = "<td scope='col'>Sense descompte</td>";
		for ($i=0; $i<count($vectHores); $i++) {
			 $connexio2 = new ConnexioBBDDSTMT();
			 $connexio2->connectarBD();

			 $thHores .= "<th scope='col'>".$vectHores[$i]." h</th>";

			 $cnsExsiteixCurs = "SELECT ID_PREU FROM curs WHERE
				 DATAI+?>CURRENT_DATE AND PUBLIC=1 AND HORES=?  AND id_preu != 14
				 AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
				 ORDER BY ANY, MES LIMIT 1";
			 $stmtEx=$connexio->prepare($cnsExsiteixCurs);
			 $stmtEx->bind_param("dd", $diesObertsI, $horesI);
			 $diesObertsI = $vectDiesOberts[$i];
			 $horesI = $vectHores[$i];
			 $stmtEx->execute();
			 $stmtEx->store_result();
			 if ( $stmtEx->num_rows() > 0 ) {
					$stmtEx->bind_result($idPreu);
					$stmtEx->fetch();
					$connexio->closeStmt();

					$cnsPreu = "SELECT IMPORT FROM preu WHERE ID=?  AND id_preu != 14  AND
						DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
					$stmtPreu=$connexio2->prepare($cnsPreu);
					$stmtPreu->bind_param("d", $idPreu);
					$stmtPreu->execute();
					$stmtPreu->bind_result($preu);
					$stmtPreu->fetch();
					$connexio2->closeStmt();
					if ( $preu > 0 ) {

							$objPreu = new Numero($preu);
							$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();
					$thSenseDescompte .= "<td scope='col'>".$preu." €</td>";
					$idsPreus[] = $idPreu;
				}

					$margleLG = "ml-lg-2 mr-lg-2";
					if ($i==0) $margleLG = "ml-lg-0 mr-lg-2";
					else if ($i==count($vectHores)-1) $margleLG = "ml-lg-2 mr-lg-0";
					if ( $i%2 == 0 ) $texthores.="<div class='d-flex flex-column flex-sm-row w-100'>";
					$texthores.="<button id='hores-".$horesI."' onclick='mostraCursos(".$horesI.")' ";
					$texthores.="class='mesinfo position-relative negreta500 border-0 border-radius-2 w-100 ";
					$texthores.="flex-shrink-1 px-2 py-2 mr-0 mr-sm-2 ".$margleLG." ml-sm-0 mb-4'>";
					$texthores.=$horesI." hores</button>";
					if ( $i%2 != 0 ) $texthores.="</div>";
			 }
			 $connexio->closeStmt();

			 $connexio2->desconectarBD();
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
				if ( $preu > 0 ) {

					$objPreu = new Numero($preu);
					$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();

					$trTrams .= "<td>".$preuFormatCorrecte." €</td>";
				}
			}

			$trTrams .= "</tr>";
		}

		$connexio->desconectarBD();

		//Es prepara la taula dels descomptes
		$table = "<table id='taula-descomptes' class='table table-striped table-hover w-100 mb-2'>
				<thead>
					<tr>
						<th scope='col'></th>".$thHores."
					</tr>
				</thead>
				<tbody>
					<tr>".$thSenseDescompte."</tr>".$trTrams."
				</tbody>
			</table>";

		return $table;
	}
}

?>
