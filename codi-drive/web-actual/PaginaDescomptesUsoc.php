<?php

/**
* @class DescompteGrup
* @brief Conté tota la informació relacionada amb una Descompte per a grups o centres escolars.
*/

class PaginaDescomptes {
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
			DATAI+7>CURRENT_DATE AND PUBLIC=1 AND HORES=?
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

		$cnsPreu = "SELECT IMPORT FROM preu WHERE ID=? AND
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
			TIPUS = ? AND
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
			<h1 class='barra'>Descomptes per a afiliats de la USOC</h1>
			<div class='d-flex flex-column flex-sm-row align-items-left'>
				<!--<div class='subtitol flex-grow-1 py-2'></div>-->
			</div>
		</div>";
		return $container_titol;
	}

	public function __getContentPage() {
		//Preparem la pàgina

		$page ="<p>L’<strong>Associació PrisMa</strong> i la <strong>FEUSOC</strong> (Federació d’Ensenyament de la Unió Sindical Obrera de Catalunya) han signat un acord per oferir un <strong>descompte del 20 %</strong> en els cursos de PrisMa, reconeguts com a <strong>formació permanent del professorat</strong> pel Departament d'Educació de la Generalitat de Catalunya:</p>";
		$page .="<ul>";
		$page .="<li>Cursos 100% en línia, asíncrons i al ritme de cadascú.</li>";
		$page .="<li>Tutorització personalitzada.</li>";
		$page .="<li>Accés il·limitat als continguts.</li>";
		$page .="</ul>";
		$page .="<p>En el moment de fer la inscripció, caldrà <span class='font-weight-bold'>marcar en el formulari</span> l'opció de l’<strong>afiliació a la USOC</strong>. Les dades personals s’enviaran al sindicat i, un cop validades, rebreu les instruccions per fer el pagament amb el descompte.</p>";

		$page .= $this->__getSection();

		$page .="<p>Podeu consultar els nostres cursos i les edicions disponibles a <a href='https://www.prisma.cat/cursos' target='_self' title='Cursos de PrisMa' class='font-weight-bold'>www.prisma.cat/cursos</a>.";

		$page .="<p><a href='https://web.feusoc.cat/' target='_blank' title='FEUSOC'><img src='https://usoc.cat/web/wp-content/uploads/logo_ensenyament_transparent-720x380-1.png' style='max-width: 150px;'></a></p>";

		return $page;
	}

	public function __getSection() {

			$table = $this->calcTableDesc();
			$cnt .= "<div class='d-flex justify-content-center align-items-center my-3'>".$table."</div>";

		return $cnt;
	}

	/*
	* Retorna una taula amb els descomptes del tipus de descompte $type
	* @param $tyoe => és el tupus que correspon a la taula de descomptes
	*/
	private function calcTableDesc() {
		$hores = $this->__getHoresCursos();
		$type = 4;
		$table = "<table id='taula-descomptes' class='table table-striped table-hover w-100 mb-2'>
			<thead>
				<tr>
					<th>Curs</th>
					<th>Preu original</th>
					<th>Preu amb descompte</th>
				</tr>
			</thead>
			<tbody>";

		for ( $i = 0; $i < count($hores); $i++ ) {
			$idPreu = $this->__getIdPreu( $hores[$i] );
			$preuCar = $this->__getPreuCar($idPreu);
			$preuDescompte = $this->__getPreuDescompte($idPreu, $type);

			if ($preuCar>0) {
			
				$table .= "<tr>
					<td>".$hores[$i]." h</td>
					<td>".$preuCar." €</td>
					<td>".$preuDescompte." €</td>
				</tr>";
			}
		}

		$table .= "</tbody></table>";
		return $table;
	}
}

?>
