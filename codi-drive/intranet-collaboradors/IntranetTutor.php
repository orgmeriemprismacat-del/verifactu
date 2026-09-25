<?php

/**
	* @class Intranet
	* @brief Conté totes les funcionalitats de l'intranet
*/
class IntranetTutor
{
	private $usuari;
	private $url;
	private $firmes;
	private $dades;
	private $cnsBD_Intra;
	private $updBD_Intra;
	private $cnsBD_Web;
	private $updBD_Web;
	private $cnsBD_Moodle;
	private $updBD_Moodle;
	private $cnsBD_MoodleAntic;
	private $updBD_MoodleAntic;

	/*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @return Crees un Usuari buit
   */
	 public function __construct()	{
 		$this->usuari = null;
 		$this->url 		= null;

 		$this->firmes = [];
 		$this->setFirmes();

 		$this->setDades();

 		$this->cnsBD_Intra = [
 			"cnsUrlApartat" 				=> "SELECT URL FROM apartats WHERE ID = ?",
 			"buscarParamType"				=> "SELECT VALOR FROM params WHERE PARAM = ? AND TIPUS = ?
 						AND DATAI <= CURRENT_TIMESTAMP AND
 						(DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)",
 			"buscarParam" 					=> "SELECT VALOR FROM params WHERE PARAM = ?
 				    AND DATAI <= CURRENT_TIMESTAMP AND
 				    (DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)",
 			"buscarParamOrderBy"		=> "SELECT VALOR, TIPUS FROM params WHERE PARAM = ?
 						AND DATAI <= CURRENT_TIMESTAMP AND
 						(DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)
 						ORDER BY ORDERBY",
 			"xx"										=> "",
 		];
 		$this->updBD_Intra = [
 			"insertAccess"					=> "INSERT historicAcces (username, url) VALUES (?, ?)",
 		];
 		$this->cnsBD_Web = [
 			"cnsImatgeWebById"      => "SELECT ALT, URL FROM imatges WHERE ID = ?",
 			"cnsUrlWebById"	        => "SELECT NAME, TITLE, URL FROM amigable WHERE ID = ?",
 			"buscarParam" 	        => "SELECT VALOR FROM params WHERE TIPUS = ?
 						AND DATAI <= CURRENT_TIMESTAMP AND
 						(DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)",
 			"cnsInfoTutorByDni"     => "SELECT NOM, COGNOMS, MAIL_PRISMA,
 						ID_IMG_LARGE, ID_IMG_SMALL, TITULACIO FROM personal WHERE DNI = ?",
 			"cnsInFoCursByIdInsc"   => "SELECT ANY, MES, CURS, Grup, NOM, COGNOMS,
 			 			DNI, CORREU FROM inscripcions WHERE ID = ?",
 			"cnsInFoCursByIdCurs"   => "SELECT NOM_CURS, DATAI, DATAF, GTAF, FISS,
 						CURS_ESCOLAR, HORES, ID_CUHO, CONFIRMAT
 						FROM curs AS c INNER JOIN aula AS a ON c.ID_AULA = a.ID_AULA
 						WHERE ANY = ? AND MES = ? AND CURS = ? AND a.AULA = ? AND PUBLIC = 1",
 			"cnsTutorByIdCuho"      => "SELECT DNI_TUTOR FROM rel_cuho AS r
 						INNER JOIN honoraris AS h ON r.id_hono=h.ID
 						WHERE r.ID_CUHO = ?",
 			"cnsTutorByCursos"   => "SELECT DNI_TUTOR FROM cursos
 						WHERE ANY = ? AND MES = ? AND CURS = ? AND AULA = ?",
 			"cnsPerfil"             => "SELECT ID_PERFIL, NOM, NCURT FROM perfils INNER JOIN
 						perfils_list ON perfils.ID_PERFIL = perfils_list.ID WHERE CODI LIKE ?
 						AND HORES=? AND CURS_ESCOLAR=? AND CODI_GTAF=? GROUP BY ID_PERFIL",
 			"cnsInfoCursWeb"        => "SELECT TITOL, SHORT_DESC, ID_IMG_LARGE, ID_IMG_SMALL, ID_AMIGABLE
 						FROM informacio WHERE CODI_CURS = ? AND ESTAT = 1",
 			"buscarTotsRegistres"   => "SELECT i.ID, i.TIPUS_INSC, i.ANY, i.MES, i.CURS, i.Grup,
 						GENERAT, CERTIFICAT, NOM_CURS, ESTAT, A_PAGAR-PAGAMENT AS pendent,
 						PAGAMENT, IDPAG, `INSC CURS` AS inscrit, FACTURA_RELACIONADA, usuari
 						FROM inscripcions AS i INNER JOIN curs AS c ON
 						( i.ANY=c.ANY AND i.MES=c.MES AND i.CURS=c.CURS )
 						INNER JOIN aula AS a ON c.ID_AULA=a.ID_AULA AND i.Grup = a.AULA
 						WHERE DNI=? AND (`INSC CURS`!='D')
 						ORDER BY i.ANY DESC, i.MES DESC, i.CURS",
 			"cnsInfoInscByUser"     => "SELECT ID, NOM, COGNOMS, CORREU, DNI, ADRECA,
 						Codi_Postal, Poblacio, PERFIL, Titulacio, TELEFON,
 						ANY, MES, CURS, `INSC CURS`, A_PAGAR, IDPAG FROM inscripcions
 						WHERE USUARI = ? AND USUARI != 0 AND UPPER(`INSC CURS`) != 'D' ORDER BY DATA_INSC DESC LIMIT ?",
 			"cnsInscCursosNoAcabats" => "SELECT c.NOM_CURS, c.HORES, p.IMPORT, i.ID,
 						i.A_PAGAR, i.PAGAMENT, i.`INSC CURS`, i.IDPAG, i.TIPUS_INSC
 						FROM curs AS c INNER JOIN inscripcions AS i ON c.ANY = i.ANY AND
 						c.MES = i.MES AND c.CURS = i.CURS INNER JOIN preu AS p ON p.NUM = c.ID_PREU
 						WHERE i.USUARI = ? AND i.USUARI != 0 AND p.TIPUS = 1 AND ( `INSC CURS`='0' OR `INSC CURS`='1' )
 						AND c.DATAF > CURRENT_DATE ORDER BY i.DATA_INSC DESC",
 			"cnsInscCursosAcabats"  => "SELECT c.NOM_CURS, c.HORES, p.IMPORT, i.ID, i.A_PAGAR,
 						i.PAGAMENT, i.`INSC CURS`, i.IDPAG, i.CERTIFICAT, i.PERENNE, i.TIPUS_INSC
 						FROM curs AS c INNER JOIN inscripcions AS i ON c.ANY = i.ANY AND
 						c.MES = i.MES AND c.CURS = i.CURS INNER JOIN preu AS p ON p.NUM = c.ID_PREU
 						WHERE i.USUARI = ? AND i.USUARI != 0 AND p.TIPUS = 1 AND ( `INSC CURS`='0' OR `INSC CURS`='1' )
 						AND c.DATAF <= CURRENT_DATE ORDER BY i.DATA_INSC DESC",
 			"cnsAllCourses"					=> "SELECT CODI_CURS, TITOL FROM informacio WHERE ESTAT = 1 ORDER BY TITOL",
 			"cnsInfoContacte"					=> "SELECT ADRECA, CP, POBLE, FIX, MBL, EMAIL, HORARI, HESTIU, MAPA
          	FROM contacte WHERE ESTAT=1",
 			"cnsInfoFestiusDates"			=> "SELECT ATENCIO FROM festius_text AS ft INNER JOIN
 			      festius_dates AS fd ON ft.ID=fd.ID_FESTIU
 						WHERE DATAI-3<=CURRENT_TIMESTAMP AND (CURRENT_TIMESTAMP<=DATAF OR DATAF
 						LIKE '%0000-00-00%' OR DATAF IS NULL)
 						ORDER BY DATAI DESC LIMIT 1",
 			"cnsAnysDispo"	=> "SELECT ANY FROM curs GROUP BY ANY ORDER BY ANY DESC",
 			"cnsMesosDipo"	=> "SELECT MES FROM curs GROUP BY MES",
 			"cnsAnysJorDispo"	=> "SELECT ANY FROM jornades GROUP BY ANY ORDER BY ANY DESC",
 			"cnsMesosJorDipo"	=> "SELECT MES FROM jornades GROUP BY MES",
 			"cnsAulesById"	=> "SELECT AULA FROM aula WHERE ID_AULA = ?",
 			"cnsInscByAnyMesCursAula"	=> "SELECT NOM, COGNOMS, CORREU, PERFIL, Titulacio,
 						DATA_INSC, `INSC CURS`, DATA_BAIXA, MOTIU_BAIXA
 						FROM inscripcions
 						WHERE ANY = ? AND CURS = ? AND MES = ? AND Grup = ? AND UPPER(`INSC CURS`) != 'D'
 						ORDER BY COGNOMS, NOM",
 			"cnsCursosRelTutoresSIST"	 => "SELECT c.CURS, NOM_CURS, a.ID_AULA, DATAI, DATAF
 						FROM curs AS c INNER JOIN aula AS a ON c.ID_AULA = a.ID_AULA
 						INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
 						INNER JOIN honoraris AS h ON r.ID_HONO=h.ID
 						WHERE PUBLIC=1 AND c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND
 						r.ACTIU=1 AND c.CURS NOT LIKE '%JOR%' AND c.PUBLIC = 1 AND
 						(a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
 						OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor')) AND
 						ANY = ? AND MES = ? AND
 						(DNI_TUTOR LIKE '43674436%' OR DNI_TUTOR LIKE '79302336%')
 						GROUP BY c.CURS
 						ORDER BY c.CURS, AULA",
 			"cnsCursosRelTutoresNEURO"	=> "SELECT c.CURS, NOM_CURS, a.ID_AULA, DATAI, DATAF
 						FROM curs AS c INNER JOIN aula AS a ON c.ID_AULA = a.ID_AULA
 						INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
 						INNER JOIN honoraris AS h ON r.ID_HONO=h.ID
 						WHERE PUBLIC=1 AND c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND
 						r.ACTIU=1 AND c.CURS NOT LIKE '%JOR%' AND c.PUBLIC = 1 AND
 						(a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
 						OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor')) AND
 						ANY = ? AND MES = ? AND
 						(DNI_TUTOR LIKE '33881437%' OR DNI_TUTOR LIKE '78100121%')
 						GROUP BY c.CURS
 						ORDER BY c.CURS, AULA",
 			"cnsCursosRelTutores"		=> "SELECT c.CURS, NOM_CURS, a.ID_AULA, DATAI, DATAF
 						FROM curs AS c INNER JOIN aula AS a ON c.ID_AULA = a.ID_AULA
 						INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
 						INNER JOIN honoraris AS h ON r.ID_HONO=h.ID
 						WHERE PUBLIC=1 AND c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND
 						r.ACTIU=1 AND c.CURS NOT LIKE '%JOR%' AND c.PUBLIC = 1 AND
 						(a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
 						OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor')) AND
 						ANY = ? AND MES = ? AND DNI_TUTOR LIKE ?
 						GROUP BY c.CURS
 						ORDER BY c.CURS, AULA",
 		  "cnsCursosRelTutoreshONO"				=> "SELECT CURS, TITOL FROM honoraris
 						INNER JOIN informacio ON honoraris.CURS = informacio.CODI_CURS
 						WHERE DNI_TUTOR LIKE ? AND honoraris.ESTAT = '1' AND informacio.ESTAT = 1
 						GROUP BY CURS ORDER BY ORDRE_CURS",
			"cnsInfoPersonal"		=> "SELECT NOM, COGNOMS, MAIL_PRISMA, MAIL_PERSONAL
						FROM personal WHERE DNI LIKE ?",
			"cnsCursosCobramentsTutores"		=> "SELECT ANY, c.CURS, i.TITOL, MES,
						ALUMNES, IMPORT, IRPF, APAGAR, GESTIONAT, PAGAT, c.OBSERVACIONS
					  FROM cobraments AS c INNER JOIN informacio AS i ON c.CURS = i.CODI_CURS
					  WHERE DNI_TUTOR LIKE ? AND GESTIONAT IS NOT NULL AND ALUMNES>0 AND ESTAT=1
					  ORDER BY ANY DESC, MES DESC, TITOL",
			"cnsCursosCobramentsTutoresByAny"		=> "SELECT ANY, c.CURS, i.TITOL, MES,
						ALUMNES, IMPORT, IRPF, APAGAR, GESTIONAT, PAGAT, c.OBSERVACIONS
					  FROM cobraments AS c INNER JOIN informacio AS i ON c.CURS = i.CODI_CURS
					  WHERE DNI_TUTOR LIKE ? AND ANY = ? AND GESTIONAT IS NOT NULL AND ALUMNES>0 AND ESTAT=1
					  ORDER BY ANY DESC, MES DESC, TITOL",
			"xxxxx"		=> "",
 		];
 		$this->updBD_Web = [
 			"updPerenneInsc"					=> "UPDATE inscripcions SET PERENNE = ? WHERE ID = ?",
 			"addEmailSubsc"							=> "INSERT INTO subscriptors (CORREU, DATA) VALUES (?, CURRENT_DATE)",
 		];
 		$this->cnsBD_Moodle = [
 			"cnsIdCurs"							=> "SELECT id FROM mdl_course WHERE shortname = ?",
 			"cnsIdUsuari"						=> "SELECT id FROM mdl_user WHERE username = ?",
 			"cnsCursVisble"					=> "SELECT id, visible FROM mdl_course WHERE shortname = ? ",
 			"cnsUserMdle"						=> "SELECT ID FROM mdl_user_enrolments WHERE
 						enrolid = (SELECT e.id FROM `mdl_enrol` AS e
 						WHERE enrol='manual' AND courseid =
 						(SELECT c.id FROM `mdl_course` AS c WHERE shortname = ?))
 						AND userid = (SELECT u.id FROM `mdl_user` AS u
 						WHERE username = ?)",
 			"cnsMdlFile"						=> "SELECT contextid FROM mdl_files WHERE id = ?",
 			"xx"										=> "",
 		];
 		$this->updBD_Moodle = [
 			"deleteMdlUserEnrol" 		=> "DELETE FROM mdl_user_enrolments WHERE ID = ?",
 			"deleteMdlRoleAssig" 		=> "DELETE from mdl_role_assignments WHERE
 						contextid in (SELECT id FROM mdl_context WHERE
 						contextlevel = 50 and instanceid =
 						(SELECT c.id FROM mdl_course AS c WHERE shortname = ?))
 						AND userid = (SELECT u.id FROM mdl_user AS u
 						WHERE username = ?)",
 			"xx"										=> "",
 		];
 		$this->cnsBD_MoodleAntic = [
 			"cnsIdCurs"							=> "SELECT id FROM mdl_course WHERE shortname = ?",
 			"cnsIdUsuari"						=> "SELECT id FROM mdl_user WHERE username = ?",
 			"cnsCursVisble"					=> "SELECT id, visible FROM mdl_course WHERE shortname = ? ",
 			"cnsUserMdle"						=> "SELECT ID FROM mdl_user_enrolments WHERE
 						enrolid = (SELECT e.id FROM mdl_enrol AS e
 						WHERE enrol = 'manual' AND courseid =
 						(SELECT c.id FROM mdl_course AS c WHERE shortname = ?))
 						AND userid = (SELECT u.id FROM mdl_user AS u WHERE username = ?)",
 			"xx"										=> "",
 		];
 		$this->updBD_MoodleAntic = [
 			"deleteMdlUserEnrol" 		=> "DELETE FROM mdl_user_enrolments WHERE ID = ?",
 			"deleteMdlRoleAssig" 		=> "DELETE from mdl_role_assignments WHERE
 						contextid in (SELECT id FROM mdl_context WHERE
 						contextlevel = 50 and instanceid =
 						(SELECT c.id FROM mdl_course AS c WHERE shortname = ?))
 						AND userid = (SELECT u.id FROM mdl_user AS u WHERE username = ?)",
 			"xx"										=> "",
 		];
 	}

	//Assigna firmes
	function setFirmes() {
		$this->firmes['Equip']['qui'] = 'Equip PrisMa';
		$this->firmes['Equip']['dept'] = 'Departament de Formació';
		$this->firmes['Secretaria']['qui'] = 'Pablo';
		$this->firmes['Secretaria']['dept'] = 'Departament de Gestió';
	}

	//Assigna dades importants i generals
	function setDades() {
		$this->dades['colors']['blauPrisma'] = "#496BAA";
		$this->dades['dies']['oberturaAules1'] = 4; //Dies obertura d'aula si el curs comença un dilluns o un dimarts
		$this->dades['dies']['oberturaAules2'] = 2; //Dies obertura d'aula si el curs no comença un dilluns o un dimarts
	}

	/*********************************** FUNCIONS MOSTRAR ***********************************/

	/**
   * @brief Assigna un objecte Usuari $usuari
   * @return Assigna un objecte Usuari $usuari
   */
	function setUsuari( $usuari ) {
		$this->usuari = unserialize($usuari);
	}

	/**
   * @brief Mostra l'estructura de la pàgina principal que pertoca a la url.
   * @return Mostra l'estructura de la pàgina principal que pertoca a la url.
   */
	public function mostrarPage($nom, $idPicture, $idUser, $mdlUsername) {
		if ( $this->url->get() == '/collaboradors/' ) {
			$mostrar = $this->__mostrarPage_Inici($nom, $idPicture, $idUser);
		}
		else if ( $this->url->get() == '/collaboradors/gestio-cobraments/' ) {
			$mostrar = $this->__mostrarPage_Tutors_Gestio_Cobraments( $this->url->get() );
		}
		else if ( $this->url->get() == '/collaboradors/consulta-cobraments/' ) {
			$mostrar = $this->__mostrarPage_Tutors_Consulta_Cobraments( $this->url->get(), $mdlUsername );
		}
		else if ( $this->url->get() == '/collaboradors/consulta-revisions/' ) {
			$mostrar = $this->__mostrarPage_Tutors_Consulta_Revisions( $this->url->get() );
		}
		else if ( $this->url->get() == '/collaboradors/consulta-informes/' ) {
			$mostrar = $this->__mostrarPage_Tutors_Consulta_Informes( $this->url->get() );
		}
		else if ( $this->url->get() == '/collaboradors/consulta-alumnes/' ) {
			$mostrar = $this->__mostrarPage_Tutors_Consulta_Alumnes( $this->url->get() );
		}
		else if ( $this->url->get() == '/collaboradors/contacte/' ) {
			$mostrar = $this->__mostrarPage_Tutors_Contacte( $this->url->get(), $mdlUsername );
		}
		$mostrar .= $this->__mostrarModalLoading();
		$mostrar .= $this->__mostrarModalError();
		$mostrar .= $this->__mostrarModalmodalSuccess();
		$mostrar .= $this->__mostrarModalConsulta("modalSubscriptionMailing", 1, 0, '', '');

		require_once 'ConnexioIntranet.php';

		$conIntra = new ConnexioIntranet();
		$conIntra->connectarBD();
		if ( $stmt=$conIntra->prepare( $this->updBD_Intra["insertAccess"] ) ) {
			$stmt->bind_param("ss", $userIntra, $urlIntra);
			$urlIntra = $this->url->get();
			$userIntra = $this->usuari->getUsuari()->get();
			$stmt->execute();
			$conIntra->closeStmt();
		}

		$conIntra->desconectarBD();

		return $mostrar;
	}

	/* #######################       INTRANET INICI       ####################### */
	public function __mostrarPage_Inici($nom, $idPicture, $idUser) {

		require_once 'ConnexioMoodle.php';

		$conMoodle = new ConnexioMoodle();
		$conMoodle->connectarBD();

		if ( $stmt=$conMoodle->prepare( $this->cnsBD_Moodle["cnsMdlFile"] ) ) {
			$stmt->bind_param("d", $idPicture);
			$stmt->execute();
			$stmt->bind_result($contextIdUser);
			$stmt->fetch();
			$conMoodle->closeStmt();
		}
		else {
			throw new Exception('', 6001);
		}

		$conMoodle->desconectarBD();

		if ( $contextIdUser != '' )
			$urlImg = "https://campus.prisma.cat/pluginfile.php/".$contextIdUser."/user/icon/f3";
		else
			$urlImg = "https://campus.prisma.cat/intranet-alumnes/img/not-found.png";

		$url_exists = $this->url_exists( $urlImg ) ? "1" : "0";
		if( !$url_exists )
			$urlImg = "https://campus.prisma.cat/intranet-alumnes/img/not-found.png";

		$mostrar = "<div class='d-flex flex-column h-100 align-items-center justify-content-center'>
			<div class='img-perfil d-flex flex-column justify-content-center align-items-center mb-4'>
				<div class='position-relative'>
					<img class='w-100' src='".$urlImg."'>
					<a class='edit-img-campus position-absolute d-flex align-items-center justify-content-center' target='_black'
					href='https://campus.prisma.cat/user/edit.php?id=".$idUser."'>
						<i class='material-icons'>edit</i>
					</a>
				</div>
					<p class='mt-3 mb-0 font-weight-bold text-center'>Hola, ".$nom."!</p>
			</div>
		  <div class='cnt-perfil d-flex flex-column justify-content-center align-items-center px-3'>
		    <p class='subtitol font-weight-bold text-center'>Benvingut/da a la teva intranet!</p>
		    <p class='text-center'>Aquí trobaràs les teves funcionalitats que necessitis com a tutor/a.</p>
		    <p class='text-center'>Per a qualsevol consulta o incidència, no dubtis a posar-te en contacte amb nosaltres des de l'apartat de <a href='https://campus.prisma.cat/collaboradors/contacte/'><strong>Contacte</strong></a>.</p>
		  </div>
	  </div>";
		return $mostrar;
	}

	public function __mostrarPage_Tutors_Gestio_Cobraments() {
		//
	}

	public function __mostrarPage_Tutors_Consulta_Cobraments( $url, $mdlUsername ) {
		// Busquem els anys disponibles i formem un llistat a partir del resultat
		$anysDispo = explode('|', "Tots|".$this->buscarAnysDisponibles());

		$any = 'Tots';
		$orderby = 'any';
		$asc = 0;

		$mostrar .= "<div id='cobraments' class='card card-blau mt-0'>
		 <div class='d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header hide'>
			 <p class='title font-weight-bold text-center py-3 mb-0 align-items-center justify-content-center d-flex'>
				 CERCA
				 <i class='material-icons ml-1'>search</i>
			 </p>
		 </div>
		 <div class='card-body px-0'>
			 <div class='d-flex flex-column flex-md-row justify-content-center align-items-center'>
				 ".$this->mostrarSelect('', 'Any', 'active', 'anys-dispo', 'mb-2', $any, '', $anysDispo)."
			 </div>
		 </div>
		 <div class='card-footer d-flex justify-content-center align-items-center'>
			 <button id='cercar-cobraments' role='button' class='boto-blau px-4 d-flex'>
				 Cerca
				 <i class='material-icons ml-1'>search</i>
			 </button>
		 </div>
		</div>
		<div id='resultats-cerca' class='card card-blau mt-5'>
			".$this->mostrarTable_Alumnes_ConsultaCobraments($any, $orderby, $asc, $mdlUsername)."
		</div>";

		return $mostrar;
	}

	public function mostrarTable_Alumnes_ConsultaCobraments($anyCobr, $orderby, $asc, $mdlUsername) {
		if ( $orderby != 'any' && $orderby != 'titol' && $orderby != 'mes' &&
				 $orderby != 'alumnes' && $orderby != 'importBrut' && $orderby != 'irpf' &&
				 $orderby != 'cobrat' && $orderby != 'gestionat' && $orderby != 'pagat' )
			$orderby = 'any';
		if ( $asc != 1 and $asc != 0 )
			$asc = 1;

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		$titolsTableCursos[0] = "ANY";
		$titolsTableCursos[1] = "TITOL";
		$titolsTableCursos[2] = "MES";
		$titolsTableCursos[3] = "ALUMNES";
		$titolsTableCursos[4] = "I. BRUT";
		$titolsTableCursos[5] = "IRPF";
		$titolsTableCursos[6] = "A COBRAR";
		$titolsTableCursos[7] = "GESTIONAT";
		$titolsTableCursos[8] = "PAGAT";
		$titolsTableCursos[9] = "OBSERVACIONS";

		// si és la Carmen Boix, podrà accedir als de la Laura S. i l'Ester
		if ( $mdlUsername == '33881437' )
			$dniTutorSql = "33881437E";
		else if ( $mdlUsername == '77897279' )
			$dniTutorSql = "78100121X";
		else
			$dniTutorSql = "%".$mdlUsername."%";

		if ( $anyCobr == 'tots' || $anyCobr == 'Tots' )
			$cnsCercar = $this->cnsBD_Web["cnsCursosCobramentsTutores"];
		else
			$cnsCercar = $this->cnsBD_Web["cnsCursosCobramentsTutoresByAny"];

		if ( $stmt = $conWeb->prepare( $cnsCercar ) ) {
			if ( $anyCobr == 'tots' || $anyCobr == 'Tots' )
				$stmt->bind_param("s", $dniTutorSql);
			else
				$stmt->bind_param("sd", $dniTutorSql, $anyCobr);
			$stmt->execute();
			$stmt->bind_result($any, $curs, $titol, $mes, $alumnes, $import, $irpf, $apagar, $gestionat, $pagat, $obs);
			$i = 0;
			while ( $stmt->fetch() ) {
				$resultats[$i][0] = $any;
				$resultats[$i][1] = $curs;
				$resultats[$i][2] = $titol;
				$resultats[$i][3] = $mes;
				$resultats[$i][4] = $alumnes;
				$resultats[$i][5] = $import;
				$resultats[$i][6] = $irpf;
				$resultats[$i][7] = $apagar;
				$resultats[$i][8] = $gestionat;
				$resultats[$i][9] = $pagat;
				$resultats[$i][10] = $obs;
				$i++;
			}
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('', xxx);
		}

		if ( $i > 0 ) {
			function cmpAny($a, $b) {
			    if ($a[0] == $b[0]) { //any
						if ($a[3] == $b[3]) { //mes
							if ($a[2] == $b[2]) { //titol
									return 0;
							}
							return ($a[2] < $b[2]) ? -1 : 1;
						}
						return ($a[3] < $b[3]) ? -1 : 1;
			    }
			    return ($a[0] < $b[0]) ? -1 : 1;
			}

			function cmpTitol($a, $b) {
			    if ($a[2] == $b[2]) { //titol
						if ($a[0] == $b[0]) { //any
							if ($a[3] == $b[3]) { //mes
									return 0;
							}
							return ($a[3] < $b[3]) ? -1 : 1;
						}
						return ($a[0] < $b[0]) ? -1 : 1;
			    }
			    return ($a[2] < $b[2]) ? -1 : 1;
			}

			function cmpMes($a, $b) {
			    if ($a[3] == $b[3]) { //mes
						if ($a[0] == $b[0]) { //any
							if ($a[2] == $b[2]) { //titol
									return 0;
							}
							return ($a[2] < $b[2]) ? -1 : 1;
						}
						return ($a[0] < $b[0]) ? -1 : 1;
			    }
			    return ($a[3] < $b[3]) ? -1 : 1;
			}

			function cmpAlumnes($a, $b) {
				if ($a[4] == $b[4]) { //xxxx
					if ($a[0] == $b[0]) { //any
						if ($a[3] == $b[3]) { //mes
							if ($a[2] == $b[2]) { //titol
									return 0;
							}
							return ($a[2] < $b[2]) ? -1 : 1;
						}
						return ($a[3] < $b[3]) ? -1 : 1;
					}
					return ($a[0] < $b[0]) ? -1 : 1;
				}
				return ($a[4] < $b[4]) ? -1 : 1;
			}

			function cmpImportBrut($a, $b) {
				if ($a[5] == $b[5]) { //xxxx
					if ($a[0] == $b[0]) { //any
						if ($a[3] == $b[3]) { //mes
							if ($a[2] == $b[2]) { //titol
									return 0;
							}
							return ($a[2] < $b[2]) ? -1 : 1;
						}
						return ($a[3] < $b[3]) ? -1 : 1;
					}
					return ($a[0] < $b[0]) ? -1 : 1;
				}
				return ($a[5] < $b[5]) ? -1 : 1;
			}

			function cmpIRPF($a, $b) {
				if ($a[6] == $b[6]) { //xxxx
					if ($a[0] == $b[0]) { //any
						if ($a[3] == $b[3]) { //mes
							if ($a[2] == $b[2]) { //titol
									return 0;
							}
							return ($a[2] < $b[2]) ? -1 : 1;
						}
						return ($a[3] < $b[3]) ? -1 : 1;
					}
					return ($a[0] < $b[0]) ? -1 : 1;
				}
				return ($a[6] < $b[6]) ? -1 : 1;
			}

			function cmpCobrat($a, $b) {
				if ($a[7] == $b[7]) { //xxxx
					if ($a[0] == $b[0]) { //any
						if ($a[3] == $b[3]) { //mes
							if ($a[2] == $b[2]) { //titol
									return 0;
							}
							return ($a[2] < $b[2]) ? -1 : 1;
						}
						return ($a[3] < $b[3]) ? -1 : 1;
					}
					return ($a[0] < $b[0]) ? -1 : 1;
				}
				return ($a[7] < $b[7]) ? -1 : 1;
			}

			function cmpGestionat($a, $b) {
				if ($a[8] == $b[8]) { //xxxx
					if ($a[0] == $b[0]) { //any
						if ($a[3] == $b[3]) { //mes
							if ($a[2] == $b[2]) { //titol
									return 0;
							}
							return ($a[2] < $b[2]) ? -1 : 1;
						}
						return ($a[3] < $b[3]) ? -1 : 1;
					}
					return ($a[0] < $b[0]) ? -1 : 1;
				}
				return ($a[8] < $b[8]) ? -1 : 1;
			}

			function cmpPagat($a, $b) {
				if ($a[9] == $b[9]) { //xxxx
					if ($a[0] == $b[0]) { //any
						if ($a[3] == $b[3]) { //mes
							if ($a[2] == $b[2]) { //titol
									return 0;
							}
							return ($a[2] < $b[2]) ? -1 : 1;
						}
						return ($a[3] < $b[3]) ? -1 : 1;
					}
					return ($a[0] < $b[0]) ? -1 : 1;
				}
				return ($a[9] < $b[9]) ? -1 : 1;
			}

			if ($orderby == 'any') usort($resultats, "cmpAny");
			else if ($orderby == 'titol') usort($resultats, "cmpTitol");
			else if ($orderby == 'mes') usort($resultats, "cmpMes");
			else if ($orderby == 'alumnes') usort($resultats, "cmpAlumnes");
			else if ($orderby == 'importBrut') usort($resultats, "cmpImportBrut");
			else if ($orderby == 'irpf') usort($resultats, "cmpIRPF");
			else if ($orderby == 'cobrat') usort($resultats, "cmpCobrat");
			else if ($orderby == 'gestionat') usort($resultats, "cmpGestionat");
			else if ($orderby == 'pagat') usort($resultats, "cmpPagat");

			$conWeb->desconectarBD();

			$classAny = '';
			$classTitol = '';
			$classMes = '';
			$classAlumn = '';
			$classImpBrut = '';
			$classIrpf = '';
			$classCobrat = '';
			$classGestionat = '';
			$classPagat = '';

			if ($asc == 1) { //s'ordena ascendentment
				if ($orderby == 'any') $classAny = ' asc';
				else if ($orderby == 'titol') $classTitol = ' asc';
				else if ($orderby == 'mes') $classMes = ' asc';
				else if ($orderby == 'alumnes') $classAlumn = ' asc';
				else if ($orderby == 'importBrut') $classImpBrut = ' asc';
				else if ($orderby == 'irpf') $classIrpf = ' asc';
				else if ($orderby == 'cobrat') $classCobrat = ' asc';
				else if ($orderby == 'gestionat') $classGestionat = ' asc';
				else if ($orderby == 'pagat') $classPagat = ' asc';
			}
			else { //s'ordena desscendentment
				if ($orderby == 'any') $classAny = ' desc';
				else if ($orderby == 'titol') $classTitol = ' desc';
				else if ($orderby == 'mes') $classMes = ' desc';
				else if ($orderby == 'alumnes') $classAlumn = ' desc';
				else if ($orderby == 'importBrut') $classImpBrut = ' desc';
				else if ($orderby == 'irpf') $classIrpf = ' desc';
				else if ($orderby == 'cobrat') $classCobrat = ' desc';
				else if ($orderby == 'gestionat') $classGestionat = ' desc';
				else if ($orderby == 'pagat') $classPagat = ' desc';
			}

			$apartat = "<table class='table table-striped table-hover table-xl-responsive table-order text-center'>
			<thead>
				<tr>
					<th id='th-any' class='sorting".$classAny."'>".$titolsTableCursos[0]."</th>
					<th id='th-titol' class='sorting".$classTitol."'>".$titolsTableCursos[1]."</th>
					<th id='th-mes' class='sorting".$classMes."'>".$titolsTableCursos[2]."</th>
					<th id='th-alumnes'>".$titolsTableCursos[3]."</th>
					<th id='th-importBrut'>".$titolsTableCursos[4]."</th>
					<th id='th-irpf'>".$titolsTableCursos[5]."</th>
					<th id='th-cobrat' class='sorting".$classCobrat."'>".$titolsTableCursos[6]."</th>
					<th id='th-gestionat' class='sorting".$classGestionat."'>".$titolsTableCursos[7]."</th>
					<th id='th-pagat' class='sorting".$classPagat."'>".$titolsTableCursos[8]."</th>
					<th class='observacions'>".$titolsTableCursos[9]."</th>
				</tr>
			</thead>
			<tbody>";

			require_once 'Date.php';

			if ($asc == 1) {
				$inici = 0;
				$fi = count($resultats);
				for ($i = $inici; $i<$fi; $i++) {
					$dataGestionat = $resultats[$i][8];
					if ( $dataGestionat != '' ) {
						$objDataGestionat = new Date($dataGestionat);
						$dataGestionat = $objDataGestionat->getDataFomatDDMMYYYY_HHMMSS();
					}

					$dataPagat = $resultats[$i][9];
					if ( $dataPagat != '' ) {
						$objDataPagat = new Date($dataPagat);
						$dataPagat = $objDataPagat->getDataFomatDDMMYYYY_HHMMSS();
					}

					$importBrut = $resultats[$i][5]." €";
					$irpf = $resultats[$i][6]." €";
					$aCobrar = $resultats[$i][7]." €";

					$apartat .= "<tr>
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[0], $resultats[$i][0])."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[1], $resultats[$i][2])."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[2], $resultats[$i][3])."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[3], $resultats[$i][4])."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[4], $importBrut)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[5], $irpf)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[6], $aCobrar)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[7], $dataGestionat)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[8], $dataPagat)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[9], $resultats[$i][10])."
					</tr>";
				}
			}
			else {
				$inici = count($resultats);
				$fi = 0;
				for ($i = $inici-1; $i>=$fi; $i--) {
					$dataGestionat = $resultats[$i][8];
					if ( $dataGestionat != '' ) {
						$objDataGestionat = new Date($dataGestionat);
						$dataGestionat = $objDataGestionat->getDataFomatDDMMYYYY_HHMMSS();
					}

					$dataPagat = $resultats[$i][9];
					if ( $dataPagat != '' ) {
						$objDataPagat = new Date($dataPagat);
						$dataPagat = $objDataPagat->getDataFomatDDMMYYYY_HHMMSS();
					}

					$importBrut = $resultats[$i][5]." €";
					$irpf = $resultats[$i][6]." €";
					$aCobrar = $resultats[$i][7]." €";

					$apartat .= "<tr>
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[0], $resultats[$i][0])."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[1], $resultats[$i][2])."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[2], $resultats[$i][3])."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[3], $resultats[$i][4])."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[4], $importBrut)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[5], $irpf)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[6], $aCobrar)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[7], $dataGestionat)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[8], $dataPagat)."
						".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[9], $resultats[$i][10])."
					</tr>";
				}
			}

			$apartat .= "</tbody></table>";

		}
		else {
			$apartat = "<p class='text-center'>No s'han trobat resultats.</p>";
		}

		$conWeb->desconectarBD();

		if ( $anyCobr == 'tots' || $anyCobr == 'Tots' )
			$titolApartat = "RESULTATS DE TOTS ELS COBRAMENTS";
		else
			$titolApartat = "RESULTATS DELS COBRAMENTS DE L'ANY ".$anyCobr;

		$mostrar = "<div class='d-flex flex-column justify-content-center align-items-center
				w-100 text-center border-bottom card-header'>
				<p class='title font-weight-bold text-center py-3 mb-0 align-items-center
				justify-content-center d-flex'>".$titolApartat."</p>
			</div>
			<div class='card-body px-0'>
			".$apartat."
		</div>";

		return $mostrar;
	}


	public function __mostrarPage_Tutors_Consulta_Revisions() {
		//
	}

	public function __mostrarPage_Tutors_Consulta_Informes() {
		//
	}

	public function __mostrarPage_Tutors_Consulta_Alumnes() {
		// Busquem els anys disponibles i formem un llistat a partir del resultat
		$anysDispo = explode('|', $this->buscarAnysDisponibles());
		// Busquem els mesos disponibles i formem un llistat a partir del resultat
		$mesosDispo = explode('|', $this->buscarMesosDisponibles());

		$any = date("Y");
		$mes = date("m");

		$mostrar .= "<div id='cursos' class='card card-blau mt-0'>
		 <div class='d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header hide'>
			 <p class='title font-weight-bold text-center py-3 mb-0 align-items-center justify-content-center d-flex'>
				 CERCA
				 <i class='material-icons ml-1'>search</i>
			 </p>
		 </div>
		 <div class='card-body px-0'>
			 <div class='d-flex flex-column flex-md-row justify-content-center align-items-center'>
				 ".$this->mostrarSelect('', 'Any', 'active', 'anys-dispo', 'mb-2', $any, '', $anysDispo)."
				 ".$this->mostrarSelect('', 'Mes', 'active', 'mesos-dispo', 'mb-2', $mes, '', $mesosDispo)."
			 </div>
		 </div>
		 <div class='card-footer d-flex justify-content-center align-items-center'>
			 <button id='cercar-cursos' role='button' class='boto-blau px-4 d-flex'>
				 Cerca
				 <i class='material-icons ml-1'>search</i>
			 </button>
		 </div>
		</div>
		<div id='resultats-cerca' class='card card-blau mt-5' style='display: none'></div>";
		return $mostrar;
	}

	public function mostrarTable_Alumnes_ConsultaAlumnes($any, $mes, $mdlUsername) {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		// si és la Carmen Boix, podrà accedir als de la Laura S. i l'Ester
		if ( $mdlUsername == '77897279' )
			$cnsCercar = $this->cnsBD_Web["cnsCursosRelTutoresSIST"];
		else if ( $mdlUsername == '33881437' || $mdlUsername == '78100121' )
			$cnsCercar = $this->cnsBD_Web["cnsCursosRelTutoresNEURO"];
		else
			$cnsCercar = $this->cnsBD_Web["cnsCursosRelTutores"];

		if ( $stmt = $conWeb->prepare( $cnsCercar ) ) {
			if ( $mdlUsername == '77897279' || $mdlUsername == '33881437' || $mdlUsername == '78100121' )
				$stmt->bind_param("ds", $any, $mes);
			else
				$stmt->bind_param("dss", $any, $mes, $dniTutor);
			$dniTutor = '%'.$mdlUsername.'%';
			$stmt->execute();
			$stmt->bind_result($codiCurs, $nomCurs, $idAula, $dataI, $dataF);
			$nCurs = 0;
			while ( $stmt->fetch() ) {
				$cursos[$nCurs][0] = $codiCurs;
				$cursos[$nCurs][1] = $nomCurs;
				$cursos[$nCurs][2] = $idAula;
				$cursos[$nCurs][3] = $dataI;
				$cursos[$nCurs][4] = $dataF;
				$nCurs++;
			}
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('', 7001);
		}

		$apartat = "";

		for ( $i=0; $i < $nCurs; $i++ ) {
			$codiCursAct = $cursos[$i][0];
			$titolCursAct = $cursos[$i][1];
			$idAulaCursAct = $cursos[$i][2];
			$dataICursAct = $cursos[$i][3];
			$dataFCursAct = $cursos[$i][4];

			require_once 'Date.php';

			$dateI = new Date($dataICursAct);
			$dataICursAct = $dateI->getDataFomatDDMMYYYY_HHMMSS();
			$dateF = new Date($dataFCursAct);
			$dataFCursAct = $dateF->getDataFomatDDMMYYYY_HHMMSS();

			$titol = "<p class='titol-apartat font-weight-bold'>".$titolCursAct."</p>";
			$apartat .= $titol;

			if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsAulesById"] ) ) {
				$stmt->bind_param("d", $idAulaCursAct);
				$stmt->execute();
				$stmt->bind_result($aulaCursAct);
				$nAula = 0;
				while ( $stmt->fetch() ) {

					$valors = $this->__retornaTableAlumne_Mostrar_Consulta_Alumne($any, $mes, $codiCursAct, $aulaCursAct);

					$alumnes = $valors [0];
					$nInscrits = $valors [1];
					$nPendents = $valors [2];
					$nBaixes = $valors [3];
					$nCanvis = $valors [4];

					$infoAula = "
					<div class='d-flex flex-column flex-md-row justify-content-center align-items-center'>
					  <table class='table table-striped table-hover table-order text-center mr-md-3' style='width: 60%;'>
						  <tbody>
								<tr>
									<th style='min-width: 160px;'>NOM DEL CURS</th>
									<td>".$titolCursAct."</td>
								</tr>
								<tr>
									<th>AULA</th>
									<td>".$aulaCursAct."</td>
								</tr>
								<tr>
									<th>DATA INICI</th>
									<td>".$dataICursAct."</td>
								</tr>
								<tr>
									<th>DATA FI</th>
									<td>".$dataFCursAct."</td>
								</tr>
							</tbody>
						</table>
						<table class='table table-striped table-hover table-order text-center' style='width: 40%;'>
							<tbody>
								<tr>
									<th style='width: 130px;'>INSCRITS</th>
									<td>".$nInscrits."</td>
								</tr>
								<tr>
									<th>PENDENTS</th>
									<td>".$nPendents."</td>
								</tr>
								<tr>
									<th>BAIXES</th>
									<td>".$nBaixes."</td>
								</tr>
								<tr>
									<th>CANVIS</th>
									<td>".$nCanvis."</td>
								</tr>
							</tbody>
						</table>
					</div>";

					$apartat .= $infoAula;
					$apartat .= $alumnes;
				}
				$conWeb->closeStmt();
			}
			else {
				throw new Exception('', 7002);
			}
		}

		if ( $nCurs == 0 ) {
			$apartat = "<p class='text-center'>No s'han trobat resultats.</p>";
		}

		$conWeb->desconectarBD();

		$mostrar = "<div class='d-flex flex-column justify-content-center align-items-center
				w-100 text-center border-bottom card-header'>
				<p class='title font-weight-bold text-center py-3 mb-0 align-items-center
				justify-content-center d-flex'>RESULTATS DE LA CERCA ".$mes."/".$any."</p>
			</div>
			<div class='card-body px-0'>
			".$apartat."
		</div>";

		return $mostrar;
	}

	private function __retornaTableAlumne_Mostrar_Consulta_Alumne($any, $mes, $curs, $aula) {

		$titolsTableAlumnes[0] = "NOM";
		$titolsTableAlumnes[1] = "COGNOMS";
		$titolsTableAlumnes[2] = "<em>E-MAIL</em>";
		$titolsTableAlumnes[3] = "TITULACIÓ";
		$titolsTableAlumnes[4] = "TREBALLA A";
		$titolsTableAlumnes[5] = "DATA INSC.";
		$titolsTableAlumnes[6] = "ESTAT";
		$titolsTableAlumnes[7] = "DATA BAIXA";
		$titolsTableAlumnes[8] = "MOTIU BAIXA";

		$alumnes = "<table class='table table-striped table-hover table-xl-responsive text-center'>
		<thead>
			<tr>
				<th>".$titolsTableAlumnes[0]."</th>
				<th>".$titolsTableAlumnes[1]."</th>
				<th>".$titolsTableAlumnes[2]."</th>
				<th>".$titolsTableAlumnes[3]."</th>
				<th>".$titolsTableAlumnes[4]."</th>
				<th>".$titolsTableAlumnes[5]."</th>
				<th>".$titolsTableAlumnes[6]."</th>
				<th>".$titolsTableAlumnes[7]."</th>
				<th>".$titolsTableAlumnes[8]."</th>
			</tr>
		</thead>
		<tbody>";

		require_once 'Date.php';

		$nInscrits = 0;
		$nPendents = 0;
		$nBaixes = 0;
		$nCanvis = 0;

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInscByAnyMesCursAula"] ) ) {
			$stmt->bind_param("dsss", $any, $curs, $mes, $aula);
			$stmt->execute();
			$stmt->bind_result($nom, $cognoms, $email, $perfil, $treballa, $dataInsc, $inscrit, $dataBaixa, $motiuBaixa);
			$nAula = 0;
			while ( $stmt->fetch() ) {
				$dateInsc = new Date($dataInsc);
				$dataInscFormat = $dateInsc->getDataFomatDDMMYYYY_HHMMSS();

				$labelInscrit = $this->obtenirLabel('', 'lightGray', $inscrit);
				if ( $inscrit == "0" ) {
					$labelInscrit = $this->obtenirLabel('', 'lightOrange', 'NO INSCRIT');
					$nPendents++;
				}
				else if ( $inscrit == "1" ) {
					$labelInscrit = $this->obtenirLabel('', 'lightGreen', 'INSCRIT');
					$nInscrits++;
				}
				else if ( $inscrit == "C" ) {
					$labelInscrit = $this->obtenirLabel('', 'lightGray', 'CANVI');
					$nCanvis++;
				}
				else if ( $inscrit == "X" ) {
					$labelInscrit = $this->obtenirLabel('', 'lightGray', 'BAIXA');
					$nBaixes++;
				}
				else if ( $inscrit == "M" ) {
					$labelInscrit = $this->obtenirLabel('', 'lightGray', 'BAIXA*');
					$nBaixes++;
				}

				if ( $dataBaixa != '' && $dataBaixa != null ) {
					$dateBaixa = new Date($dataBaixa);
					$dataBaixaFormat = $dateBaixa->getDataFomatDDMMYYYY_HHMMSS();
				}
				else {
					$dataBaixaFormat = "-";
				}

				if ( $motiuBaixa == '' || $motiuBaixa == null ) $motiuBaixa = "-";

				$alumnes .= "<tr>
					".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[0], $nom)."
					".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[1], $cognoms)."
					".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[2], $email)."
					".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[3], $treballa)."
					".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[4], $perfil)."
					".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[5], $dataInscFormat)."
					".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[6], $labelInscrit)."
					".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[7], $dataBaixaFormat)."
					".$this->mostrarFilaTaulaResponsive($titolsTableAlumnes[8], $motiuBaixa)."
				</tr>";
			}
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('', 7003);
		}

		$conWeb->desconectarBD();

		$alumnes .= "</tbody></table>";

		$valors[0] = $alumnes;
		$valors[1] = $nInscrits;
		$valors[2] = $nPendents;
		$valors[3] = $nBaixes;
		$valors[4] = $nCanvis;

		return $valors;
	}

	public function __mostrarPage_Tutors_Contacte( $url, $mdlUsername ) {

		$formulari = $this->mostrarFormulariContacteDefecte($mdlUsername);

		$mostrar = "<div id='contacte' class='card card-blau mt-5 d-flex flex-column justify-content-center align-items-center'>
			<div class='d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header'>
				<p class='title font-weight-bold text-center py-3 mb-0 align-items-center
				justify-content-center d-flex'>Deixa'ns la teva consulta</p>
			</div>
			<div class='card-body px-0 w-100 ps2'>
				<div class='d-flex flex-column justify-content-center align-items-center my-2'>
					".$formulari."
				</div>
			</div>
		</div>";
		return $mostrar;
	}

	public function mostrarFormulariContacteDefecte( $mdlUsername ) {
		// Mostrar prioritats
		$cntPrioritats = $this->__apartat_prioritats_contacte();

		// Mostrar departaments
		$cntTipusConsulta = $this->__apartat_departament_contacte();

		// Mostrar cnt rel
		$cntCursRel = $this->__apartat_curs_rel_informatic_contacte( $mdlUsername );

		// Mostrar cnt adds
		$cntAdds = $this->__apartat_adds_informatic_contacte();

		$urlOpt = $this->mostrarInput(0, '', 'URL', '', 'url', 'edit', '');
		$cntURL = "<div id='cntURL' class='d-flex flex-column align-items-center justify-content-center w-100'>
			".$urlOpt."
		</div>";

		$missatge = $this->mostrarTextarea('', "Missatge <span class='req font-weight-bold ml-1'>*</span>", '', 'missatge', 'edit', '', 'text');
		$cntMissatge = "<div id='cntMissatge' class='d-flex flex-column align-items-center justify-content-center w-100'>
			".$missatge."
		</div>";

		$cntBoto = "<div class='cnt-send-info mt-4 d-flex flex-column flex-md-row justify-content-center align-items-center'>
			<button id='envia-consulta' role='button' class='send-info boto-blau px-4 d-flex'>
			Envia consulta <i class='material-icons ml-2'>send</i>
			</button>
		</div>";

		$form = "<div class='d-flex flex-column justify-content-center align-items-center w-100'>
			".$cntPrioritats."
			".$cntTipusConsulta."
			".$cntCursRel."
			".$cntAdds."
			".$cntURL."
			".$cntMissatge."
			".$cntBoto."
		</div>";

		return $form;
	}

	private function __apartat_prioritats_contacte() {
		$prioritats = [
		  ["baixa", "Baixa"],
		  ["normal", "Normal"],
		  ["urgent", "Urgent"],
		  ["critica", "Crítica"],
		];

		$apartatPrioritat = "";

		for ( $i=0; $i<count($prioritats); $i++ ) {
			$apartatPrioritat .= "<div id='".$prioritats[$i][0]."' class='prioritat d-flex flex-column align-items-center justify-content-center w-100 px-2 py-2 mx-1'>
			  <img class='w-100 mb-2' src='https://campus.prisma.cat/intranet-collaboradors/consultes/img/prioritat-".$prioritats[$i][0].".png'>
			  <p class='text-center font-weight-bold mb-0'>".$prioritats[$i][1]."</p>
			</div>";
		}

		$apartat = "<div id='cnt-prioritat' class='d-flex flex-column align-items-center justify-content-center w-100 px-md-2'>
		  <p class='w-100'>Prioritat<span class='font-weight-bold req ml-1'>*</span></p>
		  <div class='d-flex flex-column flex-sm-row  align-items-center justify-content-center w-100 px-1 mb-3'>
				".$apartatPrioritat."
		  </div>
		</div>";
		return $apartat;
	}

	private function __apartat_departament_contacte() {
		$dept = [
			[
				"info",
				"Informàtica",
				[
					["isabel-lopez", "Isabel Lòpez"],
					["meriem-abjil", "Meriem Abjil"]
				],
				[
					["+34646028231", "646 02 82 31", ""]
				]
			],
			[
				"secre",
				"Secretaria",
				[
					["pablo-martori", "Pablo Martori"],
				],
				[
					["+34678123687", "682 64 76 44", ""],
				]
			],
			["coord", "Coordinació", [["jordi-catala", "Jordi Català"]], [["+34638272362", "638 27 23 62", ""]] ],
			["fac", "Facturació", [["adam-carmona", "Adam Carmona"]], [["+34618695033", "618 69 50 33", ""]] ],
		];

		$peoples = "";
		for ( $i=0; $i < count($dept); $i++ ) {
			$peopleOver = "";
			for ( $j=0; $j < count($dept[$i][2]); $j++ ) {
				$peopleOver .= "<div class='people-dept'>
					<img role='img' class='w-100 mx-2' src='https://campus.prisma.cat/intranet-collaboradors/consultes/img/".$dept[$i][2][$j][0].".jpg?ver=1.2' alt='".$dept[2][$j][1]."'>
					<p class='name font-weight-bold mb-0 mt-2'>".$dept[$i][2][$j][1]."</p>
		    </div>";
			}

			$peopleTel = "";
			// for ( $j=0; $j < count($dept[$i][3]); $j++ ) {
			// 	$peopleTel .= "<div class='tel d-flex flex-wrap justify-content-center align-items-center w-100'>
		   //    <i class='fa fa-phone p-2 mr-2' aria-hidden='true'></i>
		   //    <div><a href='tel:".$dept[$i][3][$j][0]."' class='ml-1'>".$dept[$i][3][$j][1]."</a></div>
			// 		<div class='ml-2 font-weight-bold'>".$dept[$i][3][$j][2]."</div>
		   //  </div>";
			// }

			$people = "<div class='people d-flex flex-column h-100 w-100 position-relative justify-content-center align-items-center mx-1 ml-md-0' id='dept-".$dept[$i][0]."'>
				<span class='bg w-100 position-absolute'></span>
				<div class='people-overlay position-relative mt-3 d-flex px-3'>
					".$peopleOver."
				</div>
				<div class='people-name h-100 pt-3 pb-3 px-2'>
					<p class='name font-weight-bold dept'>".$dept[$i][1]."</p>
					".$peopleTel."
				</div>
			  <div id='dept-".$dept[$i][0]."' class='people-selecciona py-2'>SELECCIONA</div>
			</div>";

			if ( $i == 0 || $i == 2 )
				$peoples .= "<div class='d-flex flex-column flex-sm-row mb-3 justify-content-center text-center w-100'>";

			$peoples .= $people;

			if ( $i == 1 || $i == 3 )
				$peoples .= "</div>";
		}

		$apartat = "<div id='cnt-tipusConsulta' class='d-flex flex-column align-items-center justify-content-center w-100 px-md-2'>
		  <p class='w-100'>Tipus de consulta<span class='font-weight-bold req ml-1'>*</span></p>
		  <div class='d-flex flex-column flex-lg-row justify-content-center w-100 text-center'>
				".$peoples."
		  </div>
		</div>";

		return $apartat;
	}

	private function __apartat_curs_rel_informatic_contacte( $mdlUsername ) {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		$llistatCursos = '';
		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web["cnsCursosRelTutoreshONO"] ) ) {
			$stmt->bind_param("s", $dniTutor);
			$dniTutor = '%'.$mdlUsername.'%';
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $codiCurs, $nomCurs);
				while( $stmt->fetch() ) {
					$llistatCursos .= "<li class='border-bottom' id='curs-consulta-".$codiCurs."'>";
					$llistatCursos .= $nomCurs;
					$llistatCursos .= "</li>";
				}
			}
		}
		else {
			throw new Exception('', xxx);
		}
		$conWeb->closeStmt();
		$conWeb->desconectarBD();

		$courses = $this->mostrarSelect2('', 'Curs relacionat', '', 'curs-consulta', 'curs-consulta', '', $llistatCursos);

		if ( $llistatCursos != '' ) {
			$apartat = "<div id='cnt-courses' class='d-flex flex-column align-items-center justify-content-center w-100'>
				".$courses."
			</div>";
		}
		else {
			$apartat = "";
		}

		return $apartat;
	}

	private function __apartat_adds_informatic_contacte() {
		$dispositiu = [];
		$sistemes = [];

		require_once 'ConnexioIntranetTutor.php';
		$conIntra = new ConnexioIntranetTutor();
		$conIntra->connectarBD();

		if ( $stmt = $conIntra->prepare( $this->cnsBD_Intra['buscarParamOrderBy'] ) ) {
			$stmt->bind_param('s', $parametre);

			$parametre = 'dades-apartat-contacte-dispositius';
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor, $tipus );
				$cntParam = 0;
				while( $stmt->fetch() ) {
					$dispositius[$cntParam] = $valor;
					$cntParam++;
					$textDevices .= "<div id='".$tipus."' class='device d-flex flex-column align-items-center justify-content-center col-12 col-sm-4 w-100 px-2'>
						<span class='material-icons mb-2'>".$tipus."</span>
						<p class='text-center mb-0'>".$valor."</p>
					</div>";

				}
			}

			$parametre = 'dades-apartat-contacte-sistemes';
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor, $tipus );
				$cntParam = 0;
				while( $stmt->fetch() ) {
					$sistemes[$cntParam] = $valor;
					$cntParam++;
					$textSystems .= "<div id='".$tipus."' class='device d-flex flex-column align-items-center justify-content-center col-12 col-sm-6 col-md-3 w-100 px-2'>
						<img class='w-100 mb-2' src='https://campus.prisma.cat/intranet-alumnes/img/systems/".$tipus.".png'>
						<p class='text-center mb-0'>".$valor."</p>
					</div>";
				}
			}

			$parametre = 'dades-apartat-contacte-navegadors';
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor, $tipus );
				$cntParam = 0;
				while( $stmt->fetch() ) {
					$navegadors[$cntParam] = $valor;
					$cntParam++;
					$textBrowsers .= "<div id='".$tipus."' class='device d-flex flex-column align-items-center justify-content-center col-12 col-sm-6 col-md-3 w-100 px-2'>
						<img class='w-100 mb-2' src='https://campus.prisma.cat/intranet-alumnes/img/browsers/".$tipus.".png'>
						<p class='text-center mb-0'>".$valor."</p>
					</div>";
				}
			}
		}
		else {
			throw new Exception('', xxxx);
		}

		$conIntra->closeStmt();

		$conIntra->desconectarBD();

		$cntAdds = "<div id='adds'class='hide d-flex flex-column align-items-center justify-content-center w-100 mt-3'>
			<div id='cnt-devices' class='d-flex flex-column align-items-center justify-content-center w-100 px-md-2'>
				<p class='w-100'>El dispositiu que utilitzo és:</p>
				<div class='d-flex flex-row flex-wrap w-100 px-1 mb-3'>
			         ".$textDevices."
				</div>
			</div>
			<div id='cnt-systems' class='d-flex flex-column align-items-center justify-content-center w-100 px-md-2'>
				<p class='w-100'>El sistema operatiu del dispositiu és:</p>
				<div class='d-flex flex-row flex-wrap w-100 px-1 mb-3'>
			         ".$textSystems."
				</div>
			</div>
			<div id='cnt-browsers' class='d-flex flex-column align-items-center justify-content-center w-100 px-md-2'>
				<p class='w-100'>El navegador que faig servir és:</p>
				<div class='d-flex flex-row flex-wrap w-100 px-1 mb-3'>
			         ".$textBrowsers."
				</div>
			</div>
		</div>";

		return  $cntAdds;
	}

	public function enviarMsgConsulta( $username, $typeCons, $prioritat, $courseCons,
	$deviceCons, $systemCons, $browserCons, $url, $message ) {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInfoPersonal"] ) ) {
			$stmt->bind_param("s", $dniTutor);
			$dniTutor = '%'.$username.'%';
			$stmt->execute();
			$stmt->store_result();
			if ($stmt->num_rows() > 0) {
				$stmt->bind_result($nom, $cognoms, $email, $emailPers);
				$stmt->fetch();
			}
			else //no existeix el tutor/a a la taula personal
				throw new Exception('',xxxx);
		}
		else {
			throw new Exception('', xxx);
		}
		$conWeb->closeStmt();
		$conWeb->desconectarBD();

		$order   = array("\r\n", "\n", "\r");
		$replace = '</p>
		<p>';

		$messageNew = str_replace( $order, $replace, $message);

		//Buscar el nom, cognoms i el correu a partir del username

		if ( $typeCons == 'dept-info' ) {
			$this->enviarMsgConsultaSupp( $nom, $cognoms, $email, $prioritat,
				$courseCons, $deviceCons, $systemCons, $browserCons, $url, $messageNew);
		}
		else if ( $typeCons == 'dept-secre' || $typeCons == 'dept-fac' || $typeCons == 'dept-coord') {
			$this->enviarMsgConsultaSecreCoordFact( $typeCons, $nom, $cognoms, $email, $prioritat,
				$courseCons, $url, $messageNew);
		}
	}

	/**
	* @brief Enviar MSG de consulta a secretaria@prisma.cat
	* @return Enviar msg de consultes generals amb els camps nom, cognoms, email, telefon,
	* motiu de consulta, curs relacionat, assumpte i missatge al
	* correu eletrònic de secretaria@prisma.cat i un msg informant a l'alumne que en
	* 24/48h rebrà una resposta a la consulta.
	*/
	public function enviarMsgConsultaSecreCoordFact( $typeCons, $nom, $cognoms, $email, $prioritat,
		$titol, $url, $message) {

		$dadesUsuari = "<p><strong>Dades del tutor/a</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Nom</strong>: ".$nom."</p>
			<p><strong>Cognoms</strong>: ".$cognoms."</p>
			<p><strong><em>E-mail</em></strong>: ".$email."</p>
		</div>";

		$textCURS = "";
		if ( $titol != '' ) $textCURS = "
		<p><strong>Curs relacionat</strong>: ".$titol."</p>";
		$textURL = "";
		if ( $url != '' ) $textURL = "
		<p><strong>URL</strong>: ".$url."</p>";

		$dadesConsulta = "<p><strong>Dades de la consulta</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Prioritat</strong>: ".$prioritat."</p>".$textCURS."".$textURL."
			<p><strong>Missatge</strong>: ".$message."</p>
		</div>";

		if ( $typeCons == 'dept-secre' ) {
			$tipusConsulta = "Secretaria";
			$correuTo = "consultes@prisma.cat";
			$correuReplyHeadAl = "consultes@prisma.cat";
		}
		else if ( $typeCons == 'dept-coord' ) {
			$tipusConsulta = "Coordinació";
			$correuTo = "tutoria@prisma.cat";
			$correuReplyHeadAl = "tutoria@prisma.cat";
		}
		else if ( $typeCons == 'dept-fac' ) {
			$tipusConsulta = "Facturació";
			$correuTo = "facturacio@prisma.cat";
			$correuReplyHeadAl = "facturacio@prisma.cat";
		}

		$missatgePrisMa = "<p>Hola,</p>
			<p>S'ha efectuat una consulta a <strong style='color: #496baa'>".$tipusConsulta."</strong>.</p>
			<p>A continuació trobaràs les dades de la consulta.</p>
			".$dadesUsuari."
			".$dadesConsulta;

		$missatgeAlumnes = "<p>Hola, ".$nom.",</p>
		<p>Hem enregistrat correctament la teva consulta:</p>

		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Prioritat</strong>: ".$prioritat."</p>".$textCURS."".$textURL."
			<p><strong>Missatge</strong>: ".$message."</p>
		</div>

		<p>En 24/48 hores laborables respondrem a la teva consulta.</p>

		<p>Per a qualsevol altra consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

		<p>Salutacions ben cordials,</p>";

		$dateNow = new DateTime('now');
		$dataAct = $dateNow->format('d/m/Y');

		$subjectPrisMa = "Consulta tutor ".$nom." (".mb_strtoupper($prioritat, 'UTF-8').") | ".$dataAct;

		$subjectAlumne = "Consulta tutor ".mb_strtoupper($prioritat, 'UTF-8')." | ".$dataAct;

		/* ########################          SMTP          ######################## */
		$authSecre = $this->getAuthSMTP_AtencioUsuari();
		$userSecre = $authSecre[0];
		$passSecre = $authSecre[1];
		$nameUserSecre = $authSecre[2];

		$nomFromHead = $nameUserSecre;
		$correuFromHead = $userSecre;

		$nomFromHead = $nameUserSecre;
		$correuFromHead = $userSecre;

		require_once "MailSMTPComvive.php";

		$nomTo = $tipusConsulta." PrisMa";
		// $correuTo = "meriem.prisma.cat@gmail.com";
		$nomReplyHead = $nom." ".$cognoms;
		$correuReplyHead = $email;

		$mailSecre = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectPrisMa, $missatgePrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

		$nomTo = $nom." ".$cognoms;
		$correuTo = $email;
		// $correuTo = "merimari051094@gmail.com";
		$nomReplyHead = $tipusConsulta." PrisMa";

		//es notifica a l'alumne que s'ha enviat la proposta de modificació de dades a Secretaria.
		$mailAlumne = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHeadAl, $nomTo, $correuTo,
							$subjectAlumne, $missatgeAlumnes, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);
		//
		if ( $prioritat == 'Crítica' ) {
			$nomTo = $tipusConsulta." PrisMa";
			$correuTo = "pablo.martori@prisma.cat";
			// $correuTo = "meriem.prisma.cat@gmail.com";
			$nomReplyHead = $nom." ".$cognoms;
			$correuReplyHead = $email;

			$mailSecre = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
								$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
								$subjectPrisMa, $missatgePrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);
		}
		echo "OK";
	}

	/**
	* @brief Enviar MSG de consulta a suport.informatic@prisma.cat i a suport@risma.cat
	* @return Enviar msg de consulta amb els camps nom, cognoms, email, telefon,
	* tipus de consulta, curs de consulta, els dispositiu de consulta, els sistema de
	* consulta, el navegador de consulta, l'assumpte i el missatge al
	* correu eletrònic de suport.informatic@prisma.cat i un msg informant a l'alumne que en
	* 24/48h rebrà una resposta a la consulta.
	*/
	public function enviarMsgConsultaSupp( $nom, $cognoms, $email, $prioritat,
	$titol, $deviceCons, $systemCons, $browserCons, $url, $message) {

		echo "nom: ".$nom."<br>";
		echo "cognoms: ".$cognoms."<br>";
		echo "email: ".$email."<br>";
		echo "prioritat: ".$prioritat."<br>";
		echo "courseCons: ".$titol."<br>";
		echo "deviceCons: ".$deviceCons."<br>";
		echo "systemCons: ".$systemCons."<br>";
		echo "browserCons: ".$browserCons."<br>";
		echo "url: ".$url."<br>";
		echo "message: ".$message."<br>";

		$dispositiu = $deviceCons;
		$so = $systemCons;
		$navegador = $browserCons;

		$conIntra = new ConnexioIntranetTutor();
		$conIntra->connectarBD();

		if ( $stmt = $conIntra->prepare( $this->cnsBD_Intra['buscarParamType'] ) ) {
			$stmt->bind_param('ss', $parametre, $tipus);

			$parametre = 'dades-apartat-contacte-dispositius';
			$tipus = $deviceCons;
			$stmt->execute();
			$stmt->bind_result( $valor);
			$stmt->fetch();

			$dispositiu = $valor;

			$parametre = 'dades-apartat-contacte-sistemes';
			$tipus = $systemCons;
			$stmt->execute();
			$stmt->bind_result( $valor);
			$stmt->fetch();
			$so = $valor."<img style='width: 22px; margin-bottom: -7px !important; margin-left: 10px;' src='https://campus.prisma.cat/intranet-alumnes/img/systems/".$tipus.".png'>";

			$parametre = 'dades-apartat-contacte-navegadors';
			$tipus = $browserCons;
			$stmt->execute();
			$stmt->bind_result( $valor);
			$stmt->fetch();
			$navegador = $valor."<img style='width: 22px; margin-bottom: -7px !important; margin-left: 10px;' src='https://campus.prisma.cat/intranet-alumnes/img/browsers/".$tipus.".png'>";
		}
		$conIntra->desconectarBD();

		$dadesUsuari = "<p><strong>Dades del tutor/a:</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Nom</strong>: ".$nom."</p>
			<p><strong>Cognoms</strong>: ".$cognoms."</p>
			<p><strong><em>E-mail</em></strong>: ".$email."</p>
		</div>";

		$textCURS = "";
		if ( $titol != '' ) $textCURS = "
		<p><strong>Curs relacionat</strong>: ".$titol."</p>";
		$textURL = "";
		if ( $url != '' ) $textURL = "
		<p><strong>URL</strong>: ".$url."</p>";

		$dadesBGConsulta = "<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			".$textCURS."<p><strong>Dispositiu</strong>: ".$dispositiu."</p>
			<p><strong>Sistema operatiu</strong>: ".$so."</p>
			<p><strong>Navegador</strong>: ".$navegador."</p>".$textURL."
			<p><strong>Incidència</strong>: ".$message."</p>
		</div>";

		$dadesConsulta = "<p><strong>Dades de la incidència:</strong></p>
		".$dadesBGConsulta;

		$missatgePrisMa = "<p>Hola,</p>
			<p>S'ha efectuat una incidència tècnica.</p>
			<p>A continuació trobaràs les dades de la incidència.</p>
			".$dadesUsuari."
			".$dadesConsulta;

		$missatgeAlumnes = "<p>Hola, ".$nom.",</p>
		<p>Hem enregistrat correctament la teva incidència:</p>

		".$dadesBGConsulta."

		<p>En breu resoldrem a la teva incidència.</p>

		<p>Per a qualsevol altra consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

		<p>Salutacions ben cordials,</p>";

		$dateNow = new DateTime('now');
		$dataAct = $dateNow->format('d/m/Y H:i');

		$subjectPrisMa = "Incidències tècniques ".$nom." (".mb_strtoupper($prioritat, 'UTF-8').") - ".$dataAct;
		$subjectAlumne = "Incidències tècniques (".mb_strtoupper($prioritat, 'UTF-8').") - ".$dataAct;

		/* ########################          SMTP          ######################## */
		$authAtencio = $this->getAuthSMTP_AtencioUsuari();
		$userAtencio = $authAtencio[0];
		$passAtencio = $authAtencio[1];
		$nameUserAtencio = $authAtencio[2];

		$nomFromHead = $nameUserAtencio;
		$correuFromHead = $userAtencio;

		$nomFromHead = $nameUserAtencio;
		$correuFromHead = $userAtencio;

		require_once "MailSMTPComvive.php";
		require_once "MailSMTPComviveBBCC.php";

		$nomTo = "Suport Informàtic PrisMa";
		$correuTo = "suport.informatic@prisma.cat";
		// $correuTo = "meriem.prisma.cat@gmail.com";
		$nomReplyHead = $nom." ".$cognoms;
		$correuReplyHead = $email;

		$mailSuport = new MailSMTPComviveBBCC($userAtencio, $passAtencio, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectPrisMa, $missatgePrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

		$mailSuport->addBCC("suport@prisma.cat", "Suport PrisMa");
		$mailSuport->send();

		$nomTo = $nom." ".$cognoms;
		$correuTo = $email;
		// $correuTo = "merimari051094@gmail.com";
		$nomReplyHead = $nameUserAtencio;
		$correuReplyHead = $userAtencio;

		//es notifica a l'alumne que s'ha enviat la proposta de modificació de dades a Secretaria.
		$mailAlumne = new MailSMTPComvive($userAtencio, $passAtencio, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectAlumne, $missatgeAlumnes, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

		echo "OK";

		if ( $prioritat == 'Crítica' ) {
			$nomTo = $tipusConsulta." PrisMa";
			$correuTo = "pablo.martori@prisma.cat";
			// $correuTo = "meriem.prisma.cat@gmail.com";
			$nomReplyHead = $nom." ".$cognoms;
			$correuReplyHead = $email;

			$mailSuport = new MailSMTPComviveBBCC($userSecre, $passSecre, $nomFromHead, $correuFromHead,
								$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
								$subjectPrisMa, $missatgePrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

			$mailSuport->addBCC("webmaster@prisma.cat", "Webmaster PrisMa");
			$mailSuport->send();
		}

	}


	/**
   * @brief Retorna true si existeix la url $url. Altrament retorna false
   * @return Retorna true si existeix la url $url. Altrament retorna false
   */
	public function url_exists($url = NULL) {
		if( empty( $url ) ){
			return false;
		}

		$ch = curl_init( $url );

		// Set a waite time
		curl_setopt( $ch, CURLOPT_TIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 5 );

		// Set NOBODY true for established a new request type HEAD
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		// Accept redirections
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		// Recieve response with string type, no output type
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

		$data = curl_exec( $ch );

		// Obtains de response code
		$httpcode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		// Close session
		curl_close( $ch );

		// Only accept response codes 200 (OK), 301 or 302 (redirections)
		$accepted_response = array( 200, 301, 302 );
		if( in_array( $httpcode, $accepted_response ) ) {
			return true;
		} else {
			return false;
		}
	}

	public function mostrarFilaTaulaResponsive( $capcalera, $apartat ) {
		$apartatResponsive = "<td>
			<div class='d-flex flex-row justify-content-center w-100'>
				<div class='titol-fila'>
					".$capcalera."
				</div>
				<div>
					".$apartat."
				</div>
			</div>
		</td>";
		return $apartatResponsive;
	}

	/**
   * @brief Mostra un text dels anys disponibles separats amb un |
   * @return Mostra un text dels anys disponibles de la taula cursos on cada any esta separat del següent amb un |
   */
	public function buscarAnysDisponibles() {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		$anysCURS = [];
		$anysJOR = [];
		$anys = "";

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsAnysDispo"] ) ) {
			$stmt->execute();
			$stmt->bind_result($any);
			$i = 0;
			while ($stmt->fetch()) {
				$anysCURS[] = $any;
			}
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7004);
		}
		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsAnysJorDispo"] ) ) {
			$stmt->execute();
			$stmt->bind_result($any);
			$i = 0;
			while ($stmt->fetch()) {
				$anysJOR[] = $any;
			}
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7005);
		}

		$i=0; $j=0; $trobat=false;
		while ( $i<count($anysCURS) || $j<count($anysJOR) ) {
			if ( $i<count($anysCURS) && $j<count($anysJOR) ) {
				if ( $anysCURS[$i] < $anysJOR[$j] ) {
					if ( $anys!='' ) $anys .= "|";
					$anys .= $anysJOR[$j];
					$j++;
				}
				else if ( $anysCURS[$i] == $anysJOR[$j] ) {
					if ( $anys!='' ) $anys .= "|";
					$anys .= $anysCURS[$i];
					$i++;
					$j++;
				}
				else {
					if ( $anys!='' ) $anys .= "|";
					$anys .= $anysCURS[$i];
					$i++;
				}
			}
			else if ( $i<count($anysCURS) ) {
				if ( $anys!='' ) $anys .= "|";
				$anys .= $anysCURS[$i];
				$i++;
			}
			else if ( $j<count($anysJOR) ) {
				if ( $anys!='' ) $anys .= "|";
				$anys .= $anysJOR[$j];
				$j++;
			}
		}

		$conWeb->desconectarBD();

		return $anys;
	}

	/**
   * @brief Mostra un text dels mesos disponibles separats amb un |
   * @return Mostra un text dels mesos disponibles de la taula cursos on cada any esta separat del següent amb un |
   */
	public function buscarMesosDisponibles() {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		$mesosCURS = [];
		$mesosJOR = [];
		$mesos = "";

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsMesosDipo"] ) ) {
			$stmt->execute();
			$stmt->bind_result($mes);
			$i = 0;
			while ($stmt->fetch()) {
				$mesosCURS[] = $mes;
			}
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7006);
		}

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsMesosJorDipo"] ) ) {
			$stmt->execute();
			$stmt->bind_result($mes);
			$i = 0;
			while ($stmt->fetch()) {
				$mesosJOR[] = $mes;
			}
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7007);
		}

		$i=0; $j=0; $trobat=false;
		while ( $i<count($mesosCURS) || $j<count($mesosJOR) ) {
			if ( $i<count($mesosCURS) && $j<count($mesosJOR) ) {
				if ( $mesosCURS[$i] < $mesosJOR[$j] ) {
					if ( $mesos!='' ) $mesos .= "|";
					$mesos .= $mesosCURS[$i];
					$i++;
				}
				else if ( $mesosCURS[$i] == $mesosJOR[$j] ) {
					if ( $mesos!='' ) $mesos .= "|";
					$mesos .= $mesosCURS[$i];
					$i++;
					$j++;
				}
				else {
					if ( $mesos!='' ) $mesos .= "|";
					$mesos .= $mesosJOR[$j];
					$j++;
				}
			}
			else if ( $i<count($mesosCURS) ) {
				if ( $mesos!='' ) $mesos .= "|";
				$mesos .= $mesosCURS[$i];
				$i++;
			}
			else if ( $j<count($mesosJOR) ) {
				if ( $mesos!='' ) $mesos .= "|";
				$mesos .= $mesosJOR[$j];
				$j++;
			}
		}

		$conWeb->desconectarBD();

		return $mesos;
	}

	/**
   * @brief Buscar el id del curs corresponent a la inscripció $idInsc en el campus nou
  */
	public function buscarIdAulaOberta_CampusNou( $idInsc ) {
		$id = 'no_existeix';

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		/* Busca info del curs sobre la inscripció */
		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInFoCursByIdInsc"] ) ) {
			$stmt->bind_param('d', $idInsc);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($any, $mes, $curs, $grup, $nom, $cognoms, $dni, $correu);
				$stmt->fetch();
				$shortname = $curs;
				$id = $this->buscarIdCurs_CampusNou($shortname);
			}
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7008);
		}

		$conWeb->desconectarBD();

		return $id;
	}
	/**
   * @brief Buscar el id del curs $shortname en el campus nou
  */
	public function buscarIdCurs_CampusNou( $shortname ) {
		$id = 'no_existeix';

		require_once 'ConnexioMoodle.php';
		$conMoodle = new ConnexioMoodle();
		$conMoodle->connectarBD();

		if ( $stmt=$conMoodle->prepare( $this->cnsBD_Moodle["cnsIdCurs"] ) ) {
			$stmt->bind_param('s', $shortname);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($id);
				$stmt->fetch();
			}
			$conMoodle->closeStmt();
		}
		else {
			throw new Exception('',6002);
		}

		$conMoodle->desconectarBD();

		return $id;
	}

	/**
   * @brief Buscar el id del curs corresponent a la inscripció $idInsc en el campus antic
  */
	public function buscarIdAulaOberta_CampusAntic( $idInsc ) {
		$id = 'no_existeix';

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		/* Busca info del curs sobre la inscripció */
		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInFoCursByIdInsc"] ) ) {
			$stmt->bind_param('d', $idInsc);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($any, $mes, $curs, $grup, $nom, $cognoms, $dni, $correu);
				$stmt->fetch();
				$shortname = $curs;
				$id = $this->buscarIdCurs_CampusAntic($shortname);
			}
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7009);
		}

		$conWeb->desconectarBD();

		return $id;
	}

	/**
   * @brief Buscar el id del curs $shortname en el campus antic
  */
	public function buscarIdCurs_CampusAntic( $shortname ) {
		$id = 'no_existeix';

		require_once 'ConnexioMoodleAntic.php';
		$conMoodle = new ConnexioMoodleAntic();
		$conMoodle->connectarBD();

		if ( $stmt=$conMoodle->prepare( $this->cnsBD_MoodleAntic["cnsIdCurs"] ) ) {
			$stmt->bind_param('s', $shortname);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($id);
				$stmt->fetch();
			}
			$conMoodle->closeStmt();
		}
		else {
			throw new Exception('',8001);
		}

		$conMoodle->desconectarBD();

		return $id;
	}

	/* #######################      FUNCIONS VISTA      ####################### */
	/**
	* @brief Es mostra la informació del tutor corresponent al $dniTutor segons la vista $vista
	* @return Es mostra la informació del tutor corresponent al $dniTutor segons la vista $vista
	*         Si la $vista == 1, es mostra el nom, el cognom, la imatge i la titulació
	*/
	public function mostrarVistaTutor( $dniTutor, $vista ) {
		 $conWeb = new ConnexioWeb();
		 $conWeb->connectarBD();

		 if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInfoTutorByDni"] ) ) {
			  $stmt->bind_param('s', $dniTutor);
			  $stmt->execute();
			  $stmt->bind_result($nom, $cognoms, $mailPrisma, $idImgLarge, $idImgSmall, $titulacio);
			  $stmt->fetch();
			  $conWeb->closeStmt();
		 }
		 else {
			  throw new Exception('',7010);
		 }
		 // $linkImgTutor = $this->buscarLinkImatge( $idImgLarge );
		 $linkImgTutor = $this->buscarLinkImatge( $idImgSmall );

		 $conWeb->desconectarBD();

		 $nomComplet = $nom." ".$cognoms;
		 $urlImatge = 'https://www.prisma.cat'.$linkImgTutor;
		 // $urlImatge = 'https://www.prisma.cat/img/docents/ivon-oviedo-oller-small.jpg?ver=1.0';

		 if ( $vista == 0 ) {
			  //només es mostra el nom i el cognom
		 }
		 if ( $vista >= 1 ) {
			  $view .= "<div class='d-flex flex-column justify-content-center align-items-center p-2'>";
			  //es mostra la imatge
			  $view .= "<div class='tutor-overlay mt-0'>
			  <img role='img' class='w-100' src='".$urlImatge."' alt=\"".$nomComplet."\">
			  </div>";
			  //es mostra el nom, el cognom
			  $view .= "<div class='text-tutor my-1'>";
			  $view .= "<div class='nomComplet mt-0 mb-2 font-weight-bold'>".$nomComplet."</div>";
			  if ( $vista == 1 ) {
					//es mostra la titulació
					$view .= "<div class='titulacio mt-0'>".$titulacio."</div>";
			  }
			  $view .= "</div>";
			  $view .= "</div>";
		 }

		 return $view;
	}

	/**
	* @brief Retorna el contenidor d'un button amb la classe $classIcon, amb el nom de la icona
	* 	$nomIcons, el titol $titleIcon i el id $id
	* @return Retorna el contenidor d'un button amb la classe $classIcon, amb el nom de la icona
	* 	$nomIcons, el titol $titleIcon i el id $id
	*/
	public function obtenirIconaMaterials($id, $classIcon, $nomIcona, $titleIcon) {
	  $nomId = '';
	  if ( $id != '' ) {
		  $nomId = "id = '".$id."'";
	  }
	  $icona = "<i ".$nomId." class='material-icons mx-1 ".$classIcon."' title=\"".$titleIcon."\">
			  ".$nomIcona."
	  </i>";

	  return $icona;
	}

	/**
	* @brief Retorna el contenidor d'un button amb la classe $classButton i amb el text $nomButton
	* @return Retorna el contenidor d'un button amb la classe $classButton i amb el text $nomButton
	*/
	public function obtenirBoto($id, $classButton, $nomButton) {
	  $nomId = '';
	  if ( $id != '' ) {
		  $nomId = "id = '".$id."'";
	  }
	  $button = "<button ".$nomId." role='button' class='boto-blau px-3 d-flex
		  justify-content-center align-items-center ".$classButton."'>
			  ".$nomButton."
	  </button>";

	  return $button;
	}

	/**
	* @brief Retorna el contenidor d'un label amb la classe $classLabel i amb el text $nomLabel
	* @return Retorna el contenidor d'un label amb la classe $classLabel i amb el text $nomLabel
	*/
	public function obtenirLabel($id, $classLabel, $nomLabel) {
	  $nomId = '';
	  if ( $id != '' ) {
		  $nomId = "id = '".$id."'";
	  }
	  $label = "<label ".$nomId." class='label tipus text-uppercase d-flex
		  justify-content-center align-items-center position-relative
		  font-weight-bold px-2 py-2 mr-0 mr-sm-2 mb-2 mb-sm-0 ".$classLabel."'>
			  ".$nomLabel."
	  </label>";

	  return $label;
	}

	/* ############################## FUNCIONS ############################## */

	/**
	* @brief Retorna la URL de pagament de l'idpag $idpag.
	* @return Retorna la URL de pagament de l'idpag $idpag.La url serà /pagaments si
	* $tipusInsc == 'G', altrament serà /pagament
	*/
	public function obtenirUrlPagament( $tipusInsc, $idPag ) {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["buscarParam"] ) ) {
			$stmt->bind_param("s", $tipusParam);

			$tipusParam = 'keyEncriptar';
			$stmt->execute();
			$stmt->bind_result($keyEncr);
			$stmt->fetch();

			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7011);
		}

		$conWeb->desconectarBD();

		$cipher = "AES-128-CBC";

		$ivlen = openssl_cipher_iv_length($cipher);
		$iv = openssl_random_pseudo_bytes($ivlen);
		$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
		$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
		$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

		if ( $keyEncr != '' && $idPag > 0 ) {
			$urlPagament = "/pagament";
			if ( $tipusInsc == 'G' )
				$urlPagament .= 's';
			$urlPagament .= "/".$hashIdPag;
		}
		return $urlPagament;
	}

	 /**
    * @brief Obtenir la url assignada a la imatge
    * @return Es mostra la informació del tutor corresponent al $dniTutor segons la vista $vista
    *         Si la $vista == 1, es mostra el nom, el cognom, la imatge i la titulació
    */
	 public function buscarLinkImatge( $id ) {
      //
      $conWeb = new ConnexioWeb();
      $conWeb->connectarBD();

      if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsImatgeWebById"] ) ) {
          $stmt->bind_param("d", $id);
          $stmt->execute();
          $stmt->bind_result($alt, $link);
          $stmt->fetch();
      }
      else {
          throw new Exception('',7012);
      }

      return $link;
    }
	/**
    * @brief Obtenir la url assignada a la imatge
    * @return Es mostra la informació del tutor corresponent al $dniTutor segons la vista $vista
    *         Si la $vista == 1, es mostra el nom, el cognom, la imatge i la titulació
    */
	 public function buscarLinkUrl( $id ) {
      $conWeb = new ConnexioWeb();
      $conWeb->connectarBD();

      if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsUrlWebById"] ) ) {
          $stmt->bind_param("d", $id);
          $stmt->execute();
          $stmt->bind_result($name, $title, $link);
          $stmt->fetch();
      }
      else {
          throw new Exception('',7013);
      }

      return $link;
  }

	/**
   * @brief Obtens l'any i el mes de la proxima edició que es realitza algun curs
   * @return Obtens un string que es tracta de la composicio de l'any i el mes
	*			de la pròxima edició que es realitza d'algun curs separat per |.
   */
	public function __buscarAnyMesProximaEdicio() {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["buscar1ProxEd"] ) ) {
			$stmt->execute();
			$stmt->bind_result($any, $mes);
			$stmt->fetch();

			$conWeb->closeStmt();
		}
		else {
			throw new Exception('', 7014);
		}

		$conWeb->desconectarBD();

		return $any."|".$mes;
	}

	/**
   * @brief Obtens la data d'obertura d'aula a partir de la data $datai en format YYYY-MM-DD
   * @return Obtens la data d'obertura en format YYYY-MM-DD d'aula a partir de la
	*			 data $datai en format YYYY-MM-DD
   */
	public function getDataObertura( $datai ) {
		$objDateI = new DateTime($datai);
		$nameStartDate = $objDateI->format('l');
		if ( $nameStartDate == "Monday" || $nameStartDate == "Tuesday" )
			$objDateI->modify('-'.$this->dades['dies']['oberturaAules1'].' day');
		else
			$objDateI->modify('-'.$this->dades['dies']['oberturaAules2'].' day');

		return $objDateI->format('Y-m-d');
	}

	/**
   * @brief Busca la clau privada per encriptar dades
   * @return Busca la clau privada per encriptar dades
   */
	public function getAuthKey() {
		$tipus = 'keyEncriptar';
		$autentificacio = $this->__getParamBD( $tipus );
		return $autentificacio;
	}

	/**
   * @brief Busca un parametre a la base de dades
   * @return Busca un parametre a la base de dades
   */
	private function __getParamBD( $param ) {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

 	  if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["buscarParam"] ) ) {
 		  $stmt->bind_param("s", $param);
 		  $stmt->execute();
 		  $stmt->bind_result($valor);
 		  $stmt->fetch();
 		  $conWeb->closeStmt();
 	  }
 	  else
 		  throw new Exception('',7015);

	  $conWeb->desconectarBD();
	  return $valor;
	}

	/* ############################## AUTENTIFICACIÓ ############################## */

	/**
   * @brief Busca les dades d'autentificació del correu SMTP de secretaria
   * @return Busca les dades d'autentificació del correu SMTP de secretaria
   */
	public function getAuthSMTP_Secretaria() {
		$tipus = 'autentificacioSecretaria';
		$autentificacio = $this->__getAuthSMTP( $tipus );
		return $autentificacio;
	}

	/**
   * @brief Busca les dades d'autentificació del correu SMTP de secretaria
   * @return Busca les dades d'autentificació del correu SMTP de secretaria
   */
	public function getAuthSMTP_Gestio() {
		$tipus = 'autentificacioGestio';
		$autentificacio = $this->__getAuthSMTP( $tipus );
		return $autentificacio;
	}

	/**
   * @brief Busca les dades d'autentificació del correu SMTP de secretaria
   * @return Busca les dades d'autentificació del correu SMTP de secretaria
   */
	public function getAuthSMTP_Coordinacio() {
		$tipus = 'autentificacioCoordinacio';
		$autentificacio = $this->__getAuthSMTP( $tipus );
		return $autentificacio;
	}

	/**
   * @brief Busca les dades d'autentificació del correu SMTP de secretaria
   * @return Busca les dades d'autentificació del correu SMTP de secretaria
   */
	public function getAuthSMTP_Tutoria() {
		$tipus = 'autentificacioTutoria';
		$autentificacio = $this->__getAuthSMTP( $tipus );
		return $autentificacio;
	}

	/**
   * @brief Busca les dades d'autentificació del correu SMTP de atencio.usuari
   * @return Busca les dades d'autentificació del correu SMTP de  atencio.usuari
   */
	public function getAuthSMTP_AtencioUsuari() {
		$tipus = 'autentificacioAtencioUsuari';
		$autentificacio = $this->__getAuthSMTP( $tipus );
		return $autentificacio;
	}

	/**
   * @brief Busca les dades d'autentificació del correu SMTP de secretaria
   * @return Busca les dades d'autentificació del correu SMTP de secretaria
   */
	private function __getAuthSMTP( $tipus ) {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		// echo $this->cnsBD_Web["buscarParam"] ."<br>".$tipus."<br>";
 	  if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["buscarParam"] ) ) {
 		  $stmt->bind_param("s", $tipus);
 		  $stmt->execute();
 		  $stmt->bind_result($valor);
 		  $stmt->fetch();

 		  $autentificacio = explode('|',$valor);
 		  $conWeb->closeStmt();
 	  }
 	  else
 		  throw new Exception('', 7016);

	  $conWeb->desconectarBD();
	  return $autentificacio;
	}

	/* ############################## COMPONENTS ############################## */

	/**
   * @brief Mostra l'HTML d'un contenidor que té la classe $classForm
	*			amb el text $nameLabel que té la classe $labelClass
	*			amb un input que té la id $idInput, la classe $classInput
	*			i el valor $valorInput. Si tipus és 1, es mostrarà amb un div amb l'estil d'un input,
	*			altrament es mostrarà un input
   * @return Mostra l'HTML d'un input en un contenidor amb el seu label on
	*			$nameLabel és el nom del label, el $labelClass és la classe del
	*			label, el $idInput és l'id del input, el $classInput és una classe
	*			afegida a l'input. El contenidor del input té la classe $classForm
	*			Si tipus és 1, es mostrarà amb un div amb l'estil d'un input,
	*			altrament es mostrarà un input
   */
	private function mostrarInput($tipus, $classForm, $nameLabel, $labelClass,
	$idInput, $classInput, $valorInput) {
		if ( $idInput == "" ) $textId = "";
		else $textId = "id='".$idInput."' name='".$idInput."' ";
		$mostrar = "<div class='form-group field-wrap position-relative mb-0 py-1 px-md-1 w-100 ".$classForm."'>
			<label class='".$labelClass."'>".$nameLabel."</label>";
		if ( $tipus == 1)
			$mostrar .= "<div class='form-control ".$classInput."' id='".$idInput."'>".$valorInput."</div>";
		else if ( $tipus == 3 ) //Tipus date
			$mostrar .= "<input type='date' class='form-control ".$classInput."'
			".$textId." value=\"".$valorInput."\"/>";
		else if ( $tipus == 4 ) //Tipus fitxer
			$mostrar .= "<input type='file' accept='.csv' class='form-control ".$classInput."'
			".$textId." />";
		else if ( $tipus == 5 ) //Tipus password
			$mostrar .= "<input type='password' class='form-control ".$classInput."'
			".$textId." value=\"".$valorInput."\"/>";
		else
			$mostrar .= "<input type='text' class='form-control ".$classInput."'
			".$textId." value=\"".$valorInput."\"/>";
		$mostrar .= "</div>";
		return $mostrar;
	}

	/**
   * @brief Mostra l'HTML d'un contenidor que té la classe $classForm
	*			amb el text $nameLabel que té la classe $labelClass
	*			amb un input que té la id $idInput, la classe $classInput
	*			i el valor $valorInput. Si tipus és 1, es mostrarà amb un div amb l'estil d'un input,
	*			altrament es mostrarà un input
   * @return Mostra l'HTML d'un input en un contenidor amb el seu label on
	*			$nameLabel és el nom del label, el $labelClass és la classe del
	*			label, el $idInput és l'id del input, el $classInput és una classe
	*			afegida a l'input. El contenidor del input té la classe $classForm
	*			Si tipus és 1, es mostrarà amb un div amb l'estil d'un input,
	*			altrament es mostrarà un input
   */
	private function mostrarInputAdded($tipus, $classForm, $nameLabel, $labelClass,
	$idInput, $classInput, $valorInput, $add) {
		if ( $idInput == "" ) $textId = "";
		else $textId = "id='".$idInput."' name='".$idInput."' ";
		$mostrar = "<div class='form-group field-wrap position-relative mb-0 p-1 w-100 ".$classForm."'>
			<label class='".$labelClass."'>".$nameLabel."</label>";
		if ( $tipus == 1)
			$mostrar .= "<div class='form-control ".$classInput."' id='".$idInput."'>".$valorInput."</div>";
		else if ( $tipus == 3 ) //Tipus date
			$mostrar .= "<input type='date' class='form-control ".$classInput."'
			".$textId." value=\"".$valorInput."\"/>";
		else if ( $tipus == 4 ) //Tipus fitxer
			$mostrar .= "<input type='file' accept='.csv' class='form-control ".$classInput."'
			".$textId." />";
		else
			$mostrar .= "<input type='text' class='form-control ".$classInput."'
			".$textId." value=\"".$valorInput."\"/>";
		$mostrar .= $add."</div>";
		return $mostrar;
	}

	/**
   * @brief Mostra l'HTML d'un contenidor que té la classe $classForm
	*			amb el text $nameLabel que té la classe $labelClass
	*			amb un textarea que té la id $idInput, la classe $classInput,
	*			és de tipus $tipusInput i el valor és  $valorInput.
   * @return Mostra l'HTML d'un input en un contenidor amb el seu label on
	*			$nameLabel és el nom del label, el $labelClass és la classe del
	*			label, el $idInput és l'id del input, el $classInput és una classe
	*			afegida a l'input, el $valorInput és el valor de l'input i
	*			$tipusInput és el tipus de l'input. El contenidor del input té la classe $classForm
   */
	private function mostrarTextarea($classForm, $nameLabel, $labelClass,
	$idInput, $classInput, $valorInput, $tipusInput) {
		$mostrar = "<div class='form-group field-wrap position-relative mb-0 p-1 w-100 ".$classForm."'>
			<label class='".$labelClass."'>".$nameLabel."</label>
			<textarea  type='".$tipusInput."' class='form-control ".$classInput."' id='".$idInput."'>".$valorInput."</textarea>
		</div>";
		return $mostrar;
	}

	/**
   * @brief Mostra l'HTML d'un contenidor que té la classe $classForm amb el text
	* $nameLabel que té la classe $labelClass amb un contenidor per el select
	* que té la id $idSelect, la classe $classSelect, amb l'opció per defecte
	* $valorSelect i amb un vector d'elements per crear el select $vectCrearLlistat.
   * @return Mostra l'HTML d'un select en un contenidor amb el seu label on
	* $nameLabel és el nom del label, el $labelClass és la classe del label, el $idSelect
	* és l'id del select, el $classSelect és una classe afegida a l'input, el $valorSelect
	* és el valor per defecte del select i $llistatSelect és el valor del llistat del Select
	* $vectCrearLlistat és un vector d'elements per crear la continuació del select.
	* El contenidor del select té la classe $classForm
   */
	public function mostrarSelect($classForm, $nameLabel, $labelClass,
	$idSelect, $classSelect, $valorSelect, $llistatSelect, $vectCrearLlistat) {
		$llistatSelect = $this->construirLlistatSelect($idSelect, $llistatSelect, $vectCrearLlistat);

		$mostrar = "<div class='form-group field-wrap position-relative mb-0 py-1 px-md-1 w-100 ".$classForm."'>
			<label class='".$labelClass."'>".$nameLabel."</label>
			<div id='".$idSelect."' class='select d-flex flex-column justify-content-center position-relative m-0 ".$classSelect."'>
				<span class='element-selected font-weight-normal w-100'>".$valorSelect."</span>
				<ul class='select-list position-absolute ps' style='display: none;'>
					".$llistatSelect."
				</ul>
				<i class='fa triangle-inferior fa-angle-down position-absolute'></i>
			</div>
		</div>";
		return $mostrar;
	}

	public function mostrarSelect2($classForm, $nameLabel, $labelClass,
	$idSelect, $classSelect, $valorSelect, $llistatSelect) {
		$mostrar = "<div class='form-group field-wrap position-relative mb-0 py-1 px-md-1 w-100 ".$classForm."'>
			<label class='".$labelClass."'>".$nameLabel."</label>
			<div id='".$idSelect."' class='select d-flex flex-column justify-content-center position-relative m-0 ".$classSelect."'>
				<span class='element-selected font-weight-normal w-100'>".$valorSelect."</span>
				<ul class='select-list position-absolute ps' style='display: none;'>
					".$llistatSelect."
				</ul>
				<i class='fa triangle-inferior fa-angle-down position-absolute'></i>
			</div>
		</div>";
		return $mostrar;
	}

	/**
	* @brief Construeixo el llistat per el select on $llistatSelect és l'inici del
	* llistat del select, $vectCrearLlistat és el vector dels elements on cada element
	* del vector és un element del llistat i aquest element del llistat té com a
	* identificador el $idSelect + un guió + el nom curt de l'element
  * @return Construeixo el llistat per el select on $llistatSelect és l'inici del
  * llistat del select, $vectCrearLlistat és el vector dels elements on cada element
  * del vector és un element del llistat i aquest element del llistat té com a
  * identificador el $idSelect + un guió + el nom curt de l'element
	*/
	public function construirLlistatSelect($idSelect, $llistatSelect, $vectCrearLlistat) {
		$textID = "";
		for ($i=0; $i<count($vectCrearLlistat); $i++) {
			if ( $vectCrearLlistat[$i] != '' ) {
				$objText = new Text($vectCrearLlistat[$i]);
				$objText->setNomCurt();
				$finalIdLlistat = $objText->get();
				$idLlistat = $idSelect."-".$finalIdLlistat;
				$textID = " id='".$idLlistat."'";
			}

			$llistatSelect .= "<li class='border-bottom'".$textID.">";
			$llistatSelect .= "".$vectCrearLlistat[$i]."";
			$llistatSelect .= "</li>";
		}

		return $llistatSelect;
	}

	/**
   * @brief Mostra les dates d'inici i fi en el format de dates.
	* @param $datai Es tracta de la data d'inici i es proporciona amb el format 2022-02-02
	* @param $dataf Es tracta de la data de fi i es proporciona amb el format 2022-02-02
   * @return Mostra les dates d'inici i fi en el format de dates següent:
	* Si la data d'inici i de fi són d'anys diferents: De l'1 de desembre de 2021 al 15 de febrer de 2022
	* Si la data d'inici i de fi són del mateix any i de diferent mes: Del 4 d'abril a l'11 de maig de 2022
	* Si la data d'inici i de fi són del mateix any i mes: Del 4 al 31 de juliol de 2022
   */
	private function __mostrarDatesIniciFi( $datai, $dataf ) {
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
		return $textDates;
	}

	/* ############################## MODALS ############################## */

	private function __mostrarModalConsulta($idModal, $vullHead, $vullFoot, $headerPers, $footPers) {
		$header = "";
		if ( $vullHead == 1 ) {

			if ( $headerPers != '' ) $header = $headerPers;
		}

		$footer = "";
		if ( $vullFoot == 1 ) {
			$footer = "<div class='modal-footer border-0 d-flex align-items-center justify-content-center p-0'>
				<a role='button' class='btn btn-info' aria-label='Close' data-dismiss='modal'>Tanca</a>
			</div>";
			if ( $footPers != '' ) $footer = $footPers;

		}

		$mostrar = "
		<div class='modal fade in' id='".$idModal."' tabindex='-1' role='dialog'
		aria-labelledby='".$idModal."' style='display: none;' aria-hidden='true'>
			<div class='modal-dialog modal-dialog-centered modal-notify modal-scroll modal-info' role='document'>
				<div class='modal-content w-100 p-4 mh-100 ps2'>
					".$header."
					<div class='modal-body p-0 text-center ps2'></div>
					".$footer."
				</div>
			</div>
		</div>";
		return $mostrar;
	}

	/**
   * @brief Mostra modal loading.
   * @return Mostra modal loading.
   */
	private function __mostrarModalLoading() {
		$mostrar .= "<div class='modal modalLoading' id='modalLoading' tabindex='-1' role='dialog'
		aria-labelledby='modalLoading' style='display: none;' aria-hidden='true'>
			<div class='modal-dialog modal-dialog-centered' role='document'>
				<div class='modal-content w-100 border-0'>
					<div class='modal-body'>
						<div class='loading-wrapper d-flex align-items-center justify-content-center'>
							<div class='loading-text d-flex align-items-center justify-content-center w-100 position-absolute text-white'>Buscant...</div>
							<div class='loading-content'></div>
						</div>
					</div>
				</div>
			</div>
		</div>";
		return $mostrar;
	}

	/**
   * @brief Mostra modal error.
   * @return Mostra modal error.
   */
	private function __mostrarModalError() {
		$mostrar .= "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog'
			aria-labelledby='modalErrorsTitle' style='display: none;' aria-hidden='true'>
			<div class='modal-dialog modal-dialog-centered modal-notify modal-scroll modal-danger' role='document'>
				<div class='modal-content w-100 p-4 mh-100 ps2'>
					<div class='modal-header border-0 d-flex flex-column
						justify-content-center align-items-center position-relative p-0'>
						<img src='https://campus.prisma.cat/intranet/img/wrong.png' class='w-25 mb-4' />
						<p class='modal-title font-weight-bold text-center text-danger'>Ooops..</p>
						<button type='button' class='close' data-dismiss='modal'>×</button>
					</div>
					<div class='modal-body pt-2 text-center'></div>
					<div class='modal-footer border-0 d-flex align-items-center justify-content-center p-0'>
						<a role='button' class='btn btn-danger' aria-label='Close' data-dismiss='modal'>Tanca</a>
					</div>
				</div>
			</div>
		</div>";
		return $mostrar;
	}

	/**
   * @brief Mostra modal Success.
   * @return Mostra modal Successs.
   */
	private function __mostrarModalmodalSuccess() {
		$mostrar .= "<div class='modal fade in' id='modalSuccess' tabindex='-1' role='dialog'
			aria-labelledby='modalSuccessTitle' style='display: none;' aria-hidden='true'>
			<div class='modal-dialog modal-dialog-centered modal-notify modal-scroll modal-success' role='document'>
				<div class='modal-content w-100 p-4 mh-100 ps2'>
					<div class='modal-header border-0 d-flex flex-column
						 justify-content-center align-items-center position-relative p-0'>
						<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4' />
						<p class='modal-title font-weight-bold text-center'></p>
						<button type='button' class='close' data-dismiss='modal'>×</button>
					</div>
					<div class='modal-body p-0 text-center'></div>
					<div class='modal-footer border-0 d-flex align-items-center justify-content-center p-0'>
						<a role='button' class='btn btn-success' aria-label='Close' data-dismiss='modal'>Tanca</a>
					</div>
				</div>
			</div>
		</div>";
		return $mostrar;
	}

	/*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

	/**
   * @brief Afegeix la Url $urlAct
   * @return Afegeix la Url $urlAct
   */
	public function setUrl( $urlAct ) {
		if ($urlAct != null && $urlAct != '')
			$this->url = new Text($urlAct);
	}

	/**
   * @brief Afegeix el qui $qui
   * @return Afegeix el qui $qui
   */
	public function setQui( $qui ) {
		if ($qui != null && $qui != '')
			$this->qui = new Text($qui);
	}

	/**
   * @brief Afegeix el departament $dept
   * @return Afegeix el departament $dept
   */
	public function setDepartament( $dept ) {
		if ($dept != null && $dept != '')
			$this->dept = new Text($dept);
	}
}

?>
