<?php

/**
	* @class Intranet
	* @brief Conté totes les funcionalitats de l'intranet
*/
class IntranetAlumne
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
						CURS_ESCOLAR, HORES, ID_CUHO, CONFIRMAT, i.TIPUS_CURS
						FROM curs AS c INNER JOIN aula AS a ON c.ID_AULA = a.ID_AULA
						INNER JOIN informacio AS i ON i.CODI_CURS = c.CURS
						WHERE ANY = ? AND MES = ? AND c.CURS = ? AND a.AULA = ? AND PUBLIC = 1
						GROUP BY c.ANY, c.MES, c.CURS, a.AULA",
			"cnsTutorByIdCuho"      => "SELECT DNI_TUTOR FROM rel_cuho AS r
						INNER JOIN honoraris AS h ON r.id_hono=h.ID
						WHERE r.ID_CUHO = ?",
			"cnsTutorByCursos"   => "SELECT DNI_TUTOR FROM cursos
						WHERE ANY = ? AND MES = ? AND CURS = ? AND AULA = ?",
			"cnsPerfil"             => "SELECT ID_PERFIL, NOM, NCURT FROM perfils INNER JOIN
						perfils_list ON perfils.ID_PERFIL = perfils_list.ID WHERE CODI LIKE ?
						AND HORES=? AND CURS_ESCOLAR=? AND CODI_GTAF=? GROUP BY ID_PERFIL",
			"cnsInfoSubv"             => "SELECT GTAF FROM gtafs_subv
						WHERE ANY = ? AND MES = ? AND CURS = ? AND AULA = ?",
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
						WHERE USUARI = ? AND USUARI != 0 AND UPPER(`INSC CURS`) != 'D' ORDER BY DATA_INSC DESC LIMIT 1",
			"cnsInscCursosNoAcabats" => "SELECT c.NOM_CURS, c.HORES, p.IMPORT, i.ID,
						i.A_PAGAR, i.PAGAMENT, i.`INSC CURS`, i.IDPAG, i.TIPUS_INSC, info.TIPUS_CURS, i.ENTITAT
						FROM curs AS c INNER JOIN inscripcions AS i ON c.ANY = i.ANY AND
						c.MES = i.MES AND c.CURS = i.CURS INNER JOIN preu AS p ON p.ID = c.ID_PREU
						INNER JOIN informacio AS info ON info.CODI_CURS = c.CURS
						WHERE i.USUARI = ? AND i.USUARI != 0 AND ( `INSC CURS`='0' OR `INSC CURS`='1' )
						AND c.DATAF > CURRENT_DATE GROUP BY i.ANY, i.MES, i.CURS, i.Grup ORDER BY i.DATA_INSC DESC",
			"cnsInscCursosAcabats"  => "SELECT c.NOM_CURS, c.HORES, p.IMPORT, i.ID, i.ANY, i.A_PAGAR,
						i.PAGAMENT, i.`INSC CURS`, i.IDPAG, i.CERTIFICAT, i.PERENNE, i.TIPUS_INSC, info.TIPUS_CURS, i.ENTITAT
						FROM curs AS c INNER JOIN inscripcions AS i ON c.ANY = i.ANY AND
						c.MES = i.MES AND c.CURS = i.CURS INNER JOIN preu AS p ON p.ID = c.ID_PREU
						INNER JOIN informacio AS info ON info.CODI_CURS = c.CURS
						WHERE i.USUARI = ? AND i.USUARI != 0 AND ( `INSC CURS`='0' OR `INSC CURS`='1' )
						AND c.DATAF <= CURRENT_DATE GROUP BY i.ANY, i.MES, i.CURS, i.Grup  ORDER BY i.DATA_INSC DESC",
			"cnsAllCourses"					=> "SELECT CODI_CURS, TITOL FROM informacio WHERE ESTAT = 1 ORDER BY TITOL",
			"cnsInfoContacte"					=> "SELECT ADRECA, CP, POBLE, FIX, MBL, EMAIL, HORARI, HESTIU, MAPA
         			FROM contacte WHERE ESTAT=1",
			"cnsInfoFestiusDates"			=> "SELECT ATENCIO FROM festius_text AS ft INNER JOIN
			         festius_dates AS fd ON ft.ID=fd.ID_FESTIU
						WHERE DATAI-3<=CURRENT_TIMESTAMP AND (CURRENT_TIMESTAMP<=DATAF OR DATAF
						LIKE '%0000-00-00%' OR DATAF IS NULL)
						ORDER BY DATAI DESC LIMIT 1",
			"xx"									=> "",
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
	public function mostrarPage() {
		if ( $this->url->get() == '/alumnes/' ) {
			$mostrar = $this->__mostrarPage_Inici( $this->url->get() );
		}
		else if ( $this->url->get() == '/alumnes/dades/' ) {
			$mostrar = $this->__mostrarPage_Alumnes_Dades_Personals( $this->url->get() );
		}
		else if ( $this->url->get() == '/alumnes/meus-cursos/' ) {
			$mostrar = $this->__mostrarPage_Alumnes_Meus_Cursos( $this->url->get() );
		}
		else if ( $this->url->get() == '/alumnes/cursos-no-he-realitzat/' ) {
			$mostrar = $this->__mostrarPage_Alumnes_Cursos_No_He_Realitzat( $this->url->get() );
		}
		else if ( $this->url->get() == '/alumnes/contacte/' ) {
			$mostrar = $this->__mostrarPage_Alumnes_Contacte( $this->url->get() );
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
	public function __mostrarPage_Inici() {
		$nom = $this->usuari->getNom()->get();
		$idPicture = $this->usuari->getIdPicture();
		$idUser = $this->usuari->getIdUser();

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
			throw new Exception('', 7014);
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
		    <p class='text-center'>Aquí trobaràs les teves dades que ens consten a Secretaria que ens vas proporcionar a la teva darrera inscripció i els cursos que has realitzat i/o estàs cursant.</p>
		    <p class='text-center'>Per a qualsevol consulta o incidència, no dubtis a posar-te en contacte amb nosaltres des de l'apartat de <a href='https://campus.prisma.cat/alumnes/contacte/'><strong>Contacte</strong></a>.</p>
		  </div>
	  </div>";
		return $mostrar;
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

	/* ######################  INTRANET - DADES PERSONALS  ####################### */
	public function __mostrarPage_Alumnes_Dades_Personals() {
		//Buscar el paràmetre text-dades-personals que estigui actiu
		$conIntra = new ConnexioIntranet();
		$conIntra->connectarBD();

		if ( $stmt = $conIntra->prepare( $this->cnsBD_Intra['buscarParam'] ) ) {
			$stmt->bind_param('s', $parametre);
			$parametre = 'text-dades-personals';
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor );
				$stmt->fetch();
			}
		}
		else {
			throw new Exception('', 6001);
		}

		$conIntra->desconectarBD();

		$cntDades = $this->mostrarDadesPersonalsCurriculars();

		$mostrar = $valor."<div id='dades' class='card card-blau mt-5 d-flex flex-column justify-content-center align-items-center'>
			<div class='d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header'>
				<p class='title font-weight-bold text-center py-3 mb-0 align-items-center
				justify-content-center d-flex'>Dades personals i curriculars</p>
			</div>
			<div class='card-body px-0 w-100 ps2'>
				<div class='d-flex flex-column justify-content-center align-items-center my-2'>
						".$cntDades."
				</div>
			</div>
		</div>";
		return $mostrar;
	}

	/*
	* @brief Mostra dos apartats amb les dades personals i curriculars de l'usuari
	*/
	public function mostrarDadesPersonalsCurriculars() {
		$user = $this->usuari->getUsuari()->get();

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsInfoInscByUser'] ) ) {
			$stmt->bind_param('d', $user);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($idInsc, $nom, $cog, $email, $dni, $adreca, $cp, $poble, $perfil, $titulacio, $tel, $any, $mes, $curs, $inscrit, $apagar, $idPag );
				$stmt->fetch();
			}
		}
		else {
			throw new Exception('', 7001);
		}

		$conWeb->desconectarBD();

		$cntDadesPers = "<div class='cnt-dades-pers w-100 mb-4'>
			<p class='titol-apartat'>Dades personals</p>
			<div class='apartat d-flex flex-column flex-md-row justify-content-center align-items-center'>
				<div class='d-flex flex-column align-items-center justify-content-center w-100'>
					".$this->mostrarInput(1, '', 'NOM', 'active', 'nom', 'no-edit', $nom)."
					".$this->mostrarInput(1, '', 'COGNOMS', 'active', 'cog', 'no-edit', $cog)."
					".$this->mostrarInput(1, '', 'DNI', 'active', 'dni', 'no-edit', $dni)."
					".$this->mostrarInput(1, '', 'E-MAIL', 'active', 'email', 'no-edit', $email)."
				</div>
				<div class='d-flex flex-column align-items-center justify-content-center w-100'>
					".$this->mostrarInput(1, '', 'TELÈFON', 'active', 'tel', 'no-edit', $tel)."
					".$this->mostrarInput(1, '', 'ADREÇA', 'active', 'adreca', 'no-edit', $adreca)."
					".$this->mostrarInput(1, '', 'CODI POSTAL', 'active', 'cp', 'no-edit', $cp)."
					".$this->mostrarInput(1, '', 'POBLACIÓ', 'active', 'poble', 'no-edit', $poble)."
				</div>
			</div>
		</div>";
		$cntDadesCurr = "<div class='cnt-dades-curr w-100 mb-4'>
			<p class='titol-apartat'>Dades curriculars</p>
			<div class='apartat d-flex flex-column flex-md-row justify-content-center align-items-center'>
				<div class='d-flex flex-column align-items-center justify-content-center w-100'>
					".$this->mostrarInput(1, '', 'TREBALLA A', 'active', 'treballa-a', 'no-edit', $perfil)."
					".$this->mostrarInput(1, '', 'TITULACIÓ', 'active', 'titulacio', 'no-edit', $titulacio)."
				</div>
			</div>
		</div>";

		$cntDades = $cntDadesPers.$cntDadesCurr;

		$cntBoto = "<div class='apartat d-flex flex-column flex-md-row justify-content-center align-items-center'>
			<button id='solicita-modificacio-".$idInsc."' role='button' class='edita-info boto-blau px-4 d-flex'>
				Modifica les dades<i class='material-icons ml-2'>edit</i>
			</button>
		</div>";

		$cnt = $cntDades.$cntBoto;

		return $cnt;
	}
	/*
	*@brief Mostra dos apartats amb les dades personals i curriculars de l'usuari
	que siguin editables i un apartat per comentar alguna cosa
	*/
	public function mostrarDadesPersonalsCurricularsEditables() {
		$user = $this->usuari->getUsuari()->get();

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsInfoInscByUser'] ) ) {
			$stmt->bind_param('d', $user);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($idInsc, $nom, $cog, $email, $dni, $adreca, $cp, $poble, $perfil, $titulacio, $tel, $any, $mes, $curs, $inscrit, $apagar, $idPag );
				$stmt->fetch();
				$buit = 0;
			}
			else {
				$buit = 1;
			}
		}
		else {
			throw new Exception('', 7002);
		}

		$conWeb->desconectarBD();

		$titulacions = [];
		$perfils = [];

		$conIntra = new ConnexioIntranet();
		$conIntra->connectarBD();

		if ( $stmt = $conIntra->prepare( $this->cnsBD_Intra['buscarParamOrderBy'] ) ) {
			$stmt->bind_param('s', $parametre);
			$parametre = 'dades-apartat-web-titulacions';
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor, $tipus );
				$cntParam = 0;
				while( $stmt->fetch() ) {
					$titulacions[$cntParam] = $valor;
					$cntParam++;
				}
			}

			$parametre = 'dades-apartat-web-treballa-a';
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor );
				$cntParam = 0;
				while( $stmt->fetch() ) {
					$perfils[$cntParam] = $valor;
					$cntParam++;
				}
			}
		}
		else {
			throw new Exception('', 6002);
		}

		$conIntra->desconectarBD();

		$perfilAltres = ''; $classPerfilAltres = 'hide';
		if ( $perfil != '' ) {
			$arrExplodePerfil = explode(': ', $perfil);
			$textArr = new Text($arrExplodePerfil[0]);
			$textArr->setNomCurt();
			if ( $textArr->get() == 'altres' ) {
				$perfilAltres = $arrExplodePerfil[1];
				$perfil = $arrExplodePerfil[0];
				$classPerfilAltres = '';
			}
		}
		$perfilCmpl = $this->mostrarInput(0, 'perfil-altres '.$classPerfilAltres, 'ESTIC TREBALLANT A...', 'active', 'perfil-altres', 'edit', $perfilAltres);

		$titulacioAltres = '';
		$classTitulacioAltres = 'hide';
		$titulacioSecundaria = '';
		$classTitulacioSecundaria = 'hide';
		$titulacioEstudiant = '';
		$classTitulacioEstudiant = 'hide';
		$variesTitulacions = '';
		if ( $titulacio != '' ) {
			$arrExplodeTitulacio = explode(', ', $titulacio);
			$i = 0;
			while ( $i < count( $arrExplodeTitulacio ) ) {
				$textArr = new Text($arrExplodeTitulacio[$i]);
				$textArr->setNomCurt();
				if ( $textArr->get() == 'altres' ) {
						$titulacio = $arrExplodeTitulacio[$i];
						$titulacioAltres = $arrExplodeTitulacio[$i+1];
						$classTitulacioAltres = '';
						$i = $i+2;
				}
				else if ( $textArr->get() == 'ed-secundaria' ) {
						$titulacio = $arrExplodeTitulacio[$i];
						$titulacioSecundaria = $arrExplodeTitulacio[$i+1];
						$classTitulacioSecundaria = '';
						$i = $i+2;
				}
				else if ( $textArr->get() == 'estudiant' ) {
						$titulacio = $arrExplodeTitulacio[$i];
						$titulacioEstudiant = $arrExplodeTitulacio[$i+1];
						$classTitulacioEstudiant = '';
						$i = $i+2;
				}
				else if ( strpos( $textArr->get(), 'tinc') > 0  && strpos( $textArr->get(), 'titulacio') > 0 ) {
						$variesTitulacions = explode( ':', $arrExplodeTitulacio[$i] )[1];
						$i = $i+1;
				}
				else {
					$i++;
				}
			}
		}
		$titulacionsCmpl = $this->mostrarInput(0, 'titulacio-altres '.$classTitulacioAltres, 'TINC LA TITULACIÓ DE...', 'active', 'titulacio-altres', 'edit', $titulacioAltres);
		$titulacionsCmpl .= $this->mostrarInput(0, 'titulacio-ed-secundaria '.$classTitulacioSecundaria, 'LA MEVA ESPECIALITAT ÉS', 'active', 'titulacio-secundaria', 'edit', $titulacioSecundaria);
		$titulacionsCmpl .= $this->mostrarInput(0, 'titulacio-estudiant '.$classTitulacioEstudiant, 'SÓC ESTUDIANT DE...', 'active', 'titulacio-estudiant', 'edit', $titulacioEstudiant);
		$cntVariesTitulacions = $this->mostrarInput(0,'titulacio-tinc-varies', 'TAMBÉ TINC LA TITULACIÓ DE...', 'active', 'varies-titulacio', 'edit', $variesTitulacions);

		$cntDadesPers = "<div class='cnt-dades-pers w-100 mb-4'>
			<p class='titol-apartat'>Dades personals</p>
			<div class='apartat d-flex flex-column flex-md-row justify-content-center align-items-center'>
				<div class='d-flex flex-column align-items-center justify-content-center w-100'>
					".$this->mostrarInput(0, '', 'NOM', 'active', 'nom', 'edit', $nom)."
					".$this->mostrarInput(0, '', 'COGNOMS', 'active', 'cog', 'edit', $cog)."
					".$this->mostrarInput(0, '', 'DNI', 'active', 'dni', 'edit', $dni)."
					".$this->mostrarInput(0, '', 'E-MAIL', 'active', 'email', 'edit', $email)."
				</div>
				<div class='d-flex flex-column align-items-center justify-content-center w-100'>
					".$this->mostrarInput(0, '', 'TELÈFON', 'active', 'tel', 'edit', $tel)."
					".$this->mostrarInput(0, '', 'ADREÇA', 'active', 'adreca', 'edit', $adreca)."
					".$this->mostrarInput(0, '', 'CODI POSTAL', 'active', 'cp', 'edit', $cp)."
					".$this->mostrarInput(0, '', 'POBLACIÓ', 'active', 'poble', 'edit', $poble)."
				</div>
			</div>
		</div>";

		$cntDadesCurr = "<div class='cnt-dades-curr w-100 mb-4'>
			<p class='titol-apartat'>Dades curriculars</p>
			<div class='apartat d-flex flex-column justify-content-center align-items-center'>
				<div class='d-flex flex-column align-items-center justify-content-center w-100'>
					".$this->mostrarSelect('', 'TREBALLA A', 'active', 'perfil-'.$idInsc, 'perfil', $perfil, '', $perfils)."
				</div>
				<div id='perfil-cmpl' class='d-flex flex-column justify-content-center align-items-center w-100'>".$perfilCmpl."</div>
				<div class='d-flex flex-column align-items-center justify-content-center w-100'>
					".$this->mostrarSelect('', 'TITULACIÓ', 'active', 'titulacio-'.$idInsc, 'titulacio', $titulacio, '', $titulacions)."
				</div>
				<div id='titulacions-cmpl' class='d-flex flex-column justify-content-center align-items-center w-100'>".$titulacionsCmpl."</div>
				<div id='varies-titulacions' class='d-flex flex-column justify-content-center align-items-center w-100'>".$cntVariesTitulacions."</div>
			</div>
		</div>";

		$cntComentari = "<div class='cnt-coment w-100 mb-4'>
			<p class='titol-apartat'>Vols afegir algun comentari?</p>
			<div class='apartat d-flex flex-column flex-md-row justify-content-center align-items-center'>
				<div class='d-flex flex-column align-items-center justify-content-center w-100'>
					".$this->mostrarTextarea('', 'COMENTARI', 'active', 'comentari', 'edit', '', 'text')."
				</div>
			</div>
		</div>";

		$cntDades = $cntDadesPers.$cntDadesCurr.$cntComentari;

		$cntBoto = "<div class='cnt-send-info apartat d-flex flex-column flex-md-row justify-content-center align-items-center'>
			<button id='solicita-modificacio-".$idInsc."' role='button' class='send-info boto-blau px-4 d-flex'>
			Envia la sol·licitud de modificació de dades<i class='material-icons ml-2'>send</i>
			</button>
		</div>";

		$cnt = $cntDades.$cntBoto;

		return $cnt;
	}

	/*
	* @brief S'envia un missatge a secretaria amb les noves dades
	*/
	public function enviarMsgSolicitantModificacioDades( $idInsc, $nomNou,
	$cogNou, $dniNou, $emailNou, $telNou, $adrecaNou, $cpNou, $pobleNou, $perfilNou, $titulacioNou, $comentari	) {
		//PEr cada dada que es pugui canviar, es comprova si es diferent de la guardada. Si és així, s'afegeix en el missatge a enviar
		$user = $this->usuari->getUsuari()->get();

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsInfoInscByUser'] ) ) {
			$stmt->bind_param('d', $user);
			$limit = 1;
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($idInsc, $nom, $cog, $email, $dni, $adreca, $cp, $poble,
				$perfil, $titulacio, $tel, $any, $mes, $curs, $inscrit, $apagar, $idPag );
				$stmt->fetch();
			}
		}
		else {
			throw new Exception('', 7003);
		}

		$conWeb->desconectarBD();

		$dadesActuals = $dadesNoves = $dadesComent = '';

		if ( $nomNou != $nom ) {
			$dadesActuals .= '<p><strong>Nom</strong>: '.$nom.'</p>';
			$dadesNoves .= '<p><strong>Nom</strong>: '.$nomNou.'</p>';
		}
		if ( $cogNou != $cog ) {
			$dadesActuals .= '<p><strong>Cognoms</strong>: '.$cog.'</p>';
			$dadesNoves .= '<p><strong>Cognoms</strong>: '.$cogNou.'</p>';
		}
		if ( $emailNou != $email ) {
			$dadesActuals .= '<p><strong><em>E-mail</em></strong>: '.$email.'</p>';
			$dadesNoves .= '<p><strong><em>E-mail</em></strong>: '.$emailNou.'</p>';
		}
		if ( $dniNou != $dni ) {
			$dadesActuals .= '<p><strong>Dni</strong>: '.$dni.'</p>';
			$dadesNoves .= '<p><strong>Dni</strong>: '.$dniNou.'</p>';
		}
		if ( $telNou != $tel ) {
			$dadesActuals .= '<p><strong>Telèfon</strong>: '.$tel.'</p>';
			$dadesNoves .= '<p><strong>Telèfon</strong>: '.$telNou.'</p>';
		}
		if ( $adrecaNou != $adreca ) {
			$dadesActuals .= '<p><strong>Adreça</strong>: '.$adreca.'</p>';
			$dadesNoves .= '<p><strong>Adreça</strong>: '.$adrecaNou.'</p>';
		}
		if ( $cpNou != $cp ) {
			$dadesActuals .= '<p><strong>Codi postal</strong>: '.$cp.'</p>';
			$dadesNoves .= '<p><strong>Codi postal</strong>: '.$cpNou.'</p>';
		}
		if ( $pobleNou != $poble ) {
			$dadesActuals .= '<p><strong>Població</strong>: '.$poble.'</p>';
			$dadesNoves .= '<p><strong>Població</strong>: '.$pobleNou.'</p>';
		}
		if ( $perfilNou != $perfil ) {
			$dadesActuals .= '<p><strong>Treballa a</strong>: '.$perfil.'</p>';
			$dadesNoves .= '<p><strong>Treballa a</strong>: '.$perfilNou.'</p>';
		}
		if ( $titulacioNou != $titulacio ) {
			$dadesActuals .= '<p><strong>Titulació</strong>: '.$titulacio.'</p>';
			$dadesNoves .= '<p><strong>Titulació</strong>: '.$titulacioNou.'</p>';
		}

		//s'envia missatge a secretaria
		if ( $dadesActuals != '' && $dadesNoves != '' ) {

			$dadesActuals = "<p><strong>Dades actuals</strong></p>
			<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
				".$dadesActuals."
			</div>";
			$dadesNoves = "<p><strong>Dades a modificar</strong></p>
			<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
				".$dadesNoves."
			</div>";

			if ( $comentari != '' ) {

				$order   = array("\r\n", "\n", "\r");
				$replace = '</p>
				<p>';

				$comentariNew = str_replace( $order, $replace, $comentari);

				$dadesComent = "<p><strong>Comentari</strong></p>
				<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
					<p>".$comentariNew."</p>
				</div>";
			}

			$missatgePrisMa = "<p>L'alumne/a <strong>".$nomNou." ".$cogNou."</strong> amb el correu electrònic
			<strong style='color: ".$this->dades['colors']['blauPrisma']."'>".$email."</strong>
			 ha sol·licitat la modificació de les dades.</p>
			<p>A continuació trobaràs les dades que ha solicitat modificar:</p>

			".$dadesActuals."
			".$dadesNoves."
			".$dadesComent;

			$missatgeAlumnes = "<p>Hem enregistrat correctament la teva sol·licud de modificació de les dades.</p>

			<p>En 24/48h laborables realitzarem el canvi i podràs veure les modificacions a la teva
			<a href='https://campus.prisma.cat/alumnes/dades' target='_blank'
			style='color: ".$this->dades['colors']['blauPrisma']."'>
			<strong>intranet</strong></a>.</p>

			<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

			<p>Salutacions ben cordials,</p>";

			$dateNow = new DateTime('now');
			$dataAct = $dateNow->format('d/m/Y');

			$subjectPrisMa = "Sol·licitud de modificació de les dades personals i curriculars ".$dni." | ".$dataAct;
			$subjectAlumne = "Sol·licitud de modificació de les dades personals i curriculars ";

			/* ########################          SMTP          ######################## */
			$authSecre = $this->getAuthSMTP_Secretaria();
			$userSecre = $authSecre[0];
			$passSecre = $authSecre[1];
			$nameUserSecre = $authSecre[2];

			$nomFromHead = $nameUserSecre;
			$correuFromHead = $userSecre;

			$nomFromHead = $nameUserSecre;
			$correuFromHead = $userSecre;

			require_once "MailSMTPComvive.php";

			$nomTo = $nameUserSecre;
			$correuTo = "secretaria@prisma.cat";
			// $correuTo = "meriem.prisma.cat@gmail.com";
			$nomReplyHead = $nomNou." ".$cogNou;
			$correuReplyHead = $emailNou;

			$mailSecre = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
								$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
								$subjectPrisMa, $missatgePrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

			$nomTo = $nomNou." ".$cogNou;
			$correuTo = $emailNou;
			$nomReplyHead = $nameUserSecre;
			$correuReplyHead = $userSecre;

			//es notifica a l'alumne que s'ha enviat la proposta de modificació de dades a Secretaria.
			$mailAlumne = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
								$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
								$subjectAlumne, $missatgeAlumnes, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);
		}
		else {
			return "No canvi";
		}
	}

	public function enviarSubscripcio( $emailSubsc ) {
		$user = $this->usuari->getUsuari()->get();

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsInfoInscByUser'] ) ) {
			$stmt->bind_param('d', $user);
			$limit = 1;
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($idInsc, $nom, $cog, $email, $dni, $adreca, $cp, $poble,
				$perfil, $titulacio, $tel, $any, $mes, $curs, $inscrit, $apagar, $idPag );
				$stmt->fetch();
			}
		}
		else {
			throw new Exception('', 7003);
		}
		if ( $stmt = $conWeb->prepare( $this->updBD_Web['addEmailSubsc'] ) ) {
			$stmt->bind_param('s', $emailSubsc);
			$stmt->execute();
		}
		else {
			throw new Exception('', 14000);
		}

		$data = new DateTime();
		$timestamp = $data->getTimestamp();

		$subjPrisMa = "[Sol·licitud de subscripció] núm.".$timestamp;
		$subjAlumne = "Confirmació subscripció butlletí PrisMa";

		$msgPrisMa = "<p>".$email."</p>";

		$msgAlumne = "<p>Hola,</p>
		<p>Hem rebut una sol·licitud de subscripció al nostre butlletí electrònic per a l'adreça de correu electrònic <strong>".$emailSubsc."</strong>.</p>
		<p>Pots confirmar-la <a href='https://www.prisma.cat/mailing/subscripcio.php?mail=".$emailSubsc."' target='_blank'>fent clic aquí</a> o bé visitant l'enllaç https://www.prisma.cat/mailing/subscripcio.php?mail=".$emailSubsc.".</p>
		<p>Si la subscripció no va adreçada a tu, simplement ignora aquest missatge.</p>";

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

		$nomTo = $nameUserSecre;
		$correuTo = "atencio.usuari@prisma.cat";
		$nomReplyHead = $nom." ".$cog;
		$correuReplyHead = $email;

		$mailSecre = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjPrisMa, $msgPrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

		$nomTo = $nom." ".$cog;
		$correuTo = $email;
		$nomReplyHead = $nameUserSecre;
		$correuReplyHead = "atencio.usuari@prisma.cat";

		$mailAlumne = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjAlumne, $msgAlumne, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);
	}

	/* #######################   INTRANET - MEUS CURSOS   ####################### */

	/*
	* @brief Mostrar apartat cursos pendents, que estan cursant o que estan acabats
	* en cas que l'alumne hagi fet algun curs. Si no, apareixerà un missatge
	* informant que no té cap curs disponible.
	*/
	public function __mostrarPage_Alumnes_Meus_Cursos() {
		$cntCursosPendentsCursant = $this->mostrarPage_Alumnes_Meus_Cursos_PendentsCursant();
		$cntCursosAcabats = $this->mostrarPage_Alumnes_Meus_Cursos_Acabats();

		if ( $cntCursosPendentsCursant != '' || $cntCursosAcabats != '' ) {
			$mostrar .= $cntCursosPendentsCursant;
			$mostrar .= $cntCursosAcabats;
			$mostrar .= "<div class='avis d-flex flex-row mb-3 py-4 px-2'>
				<i class='material-icons mx-1'>info</i>
				<p class='mb-0'>
					Si detectes algun error en les teves dades, comenta’ns-ho a través del <a href='https://campus.prisma.cat/alumnes/contacte/'><strong>formulari de contacte</strong></a>.
				</p>
			</div>";
		}
		else {
			$mostrar = "<p class='text-center'>No tens cap curs.</p>";
		}

		return $mostrar;
	}

	/*
	* @brief En cas que l'alumne tingui algun curs pendent,
	*				Mostrarà un apartat dels cursos pendents o que estan cursant de l'alumne.
	* @return En cas que l'alumne tingui alguna inscripció que INSC CURS és 0 o 1 i
						// el curs encara no ha acabat, mostrarà un apartat amb aquestes inscripcions
						amb la info següent: Nom del curs, Durada del curs, Preu de la inscripció,
						El que li falta per pagar, l'Estat de la inscripció (INSC_CURS) i
						dos icones (una <<i>> d'info, una icona de compartir) i una llegenda
						explicant els estats d'inscripció
	*/
	public function mostrarPage_Alumnes_Meus_Cursos_PendentsCursant() {
		$mostrar = '';
		$parametre = 'llegenda-icones-estat-inscripcio-cursos-pendents';
		$table = $this->mostrarTableCurosNoAcabats($parametre);
		if ( $table != '' ) {
			//Incloure la llegenda
			// $llegenda = $this->mostrarLlegenda($parametre);
			$explicacio = "<p>
				L'accés al curs estarà disponible <strong>uns dies abans de la data d'inici del curs</strong>.
			</p>
			<p>
				Si us matriculeu al curs un cop aquest ha començat, hi tindreu accés en un màxim de 24 hores laborals.
			</p>";
			$msg = $table.$explicacio;
		}
		else {
			$msg = "<p class='text-center'>No tens cap curs pendent de començar o cursant.</p>";
		}

		$mostrar = "<div id='cursosPendentsActius' class='card card-blau mt-5 d-flex flex-column justify-content-center align-items-center'>
			<div class='d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header'>
				<p class='title font-weight-bold text-center py-3 mb-0 align-items-center
				justify-content-center d-flex'>Cursos pendents de començar o que s'estan cursant</p>
			</div>
			<div class='card-body px-0 w-100 ps2'>
				<div class='d-flex flex-column justify-content-center align-items-center my-2'>
					".$msg."
				</div>
			</div>
			".$this->__mostrarModalConsulta("modalInfoDadesCurs", 1, 0, '', '')."
			".$this->__mostrarModalConsulta("modalInfoCompartirCurs", 1, 0, '', '')."
			".$this->__mostrarModalConsulta("modaConfirmaSolicitudAO", 1, 0, '', '')."
		</div>";

		return $mostrar;
	}
	/*
	* @brief Retorna una taula amb la informacio necesaria de les inscripcions dels
					cursos que encara no han acabat de l'usuari actual
	* @return Retorna una taula amb la informacio NOM DEL CURS, DURADA DEL CURS,
	 				PREU DEL CURS i EL PREU QUE HA PAGAT L'ALUMNE, FALTA PER PAGAR,
					L'ESTAT DEL CURS i dues ICONES de les inscripcions dels
					cursos que encara no han acabat de l'usuari actual
	*/
	private function mostrarTableCurosNoAcabats( $parametre ) {
		/* Busco el dni sense lletra de l'usuari actual */
		$user = $this->usuari->getUsuari()->get();
		/*
			Buscar les inscripcions de l'usuari $user dels cursos que encara no han acabat
			 on a `INS CURS` és 0 o 1  ordenada per data d'inscripció
		*/
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsInscCursosNoAcabats'] ) ) {
			$stmt->bind_param('d', $user);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$existeix = 1;
				$stmt->bind_result($nom, $hores, $import, $id, $aPagar, $pagament, $inscCurs, $idPag, $tipusInsc, $tipusCurs, $entitat);
				$cnt = 0;
				while ( $stmt->fetch() ) {
					$results[$cnt]["curs"]["nom"] = $nom;
					$results[$cnt]["curs"]["durada"] = $hores;
					$results[$cnt]["curs"]["preu"] = $import;
					$results[$cnt]["curs"]["tipusCurs"] = $tipusCurs;
					$results[$cnt]["insc"]["id"] = $id;
					$results[$cnt]["insc"]["aPagar"] = $aPagar;
					$results[$cnt]["insc"]["pagament"] = $pagament;
					$results[$cnt]["insc"]["estat"] = $inscCurs;
					$results[$cnt]["insc"]["idPag"] = $idPag;
					$results[$cnt]["insc"]["tipusInsc"] = $tipusInsc;
					$results[$cnt]["insc"]["entitat"] = $entitat;
					$cnt++;
				}
			}
			else {
				$existeix = 0;
			}
		}
		else {
			throw new Exception('', 7005);
		}

		$conWeb->desconectarBD();

		if ( $existeix ) {
			$titolsTaulaCursos[0] = "Nom del curs";
			$titolsTaulaCursos[1] = "Durada";
			$titolsTaulaCursos[2] = "Preu";
			$titolsTaulaCursos[3] = "Pendent de pagar";
			$titolsTaulaCursos[4] = "Estat";
			$titolsTaulaCursos[5] = "";

			$table = "<table class='table table-striped table-hover table-lg-responsive text-center mt-2 mb-4'>
				<thead><tr>
					<th>".$titolsTaulaCursos[0]."</th>
					<th>".$titolsTaulaCursos[1]."</th>
					<th>".$titolsTaulaCursos[2]."</th>
					<th>".$titolsTaulaCursos[3]."</th>
					<th>".$titolsTaulaCursos[4]."</th>
					<th>".$titolsTaulaCursos[5]."</th>
				</tr></thead>
				<tbody>";

			for ( $cnt = 0; $cnt < count($results); $cnt++ ) {
				$nomCurs = $results[$cnt]["curs"]["nom"];
				$duradaCurs = $results[$cnt]["curs"]["durada"];
				$preuCurs = $results[$cnt]["curs"]["preu"];
				$tipusCurs = $results[$cnt]["curs"]["tipusCurs"];
				$importInsc = $results[$cnt]["insc"]["aPagar"];
				$pagamentInsc = $results[$cnt]["insc"]["pagament"];
				$estatInsc = $results[$cnt]["insc"]["estat"];
				$idInsc = $results[$cnt]["insc"]["id"];
				$idPagInsc = $results[$cnt]["insc"]["idPag"];
				$tipusInsc = $results[$cnt]["insc"]["tipusInsc"];
				$entitat = $results[$cnt]["insc"]["entitat"];

				/* Preparo el cnt del preu de la inscripció */

				$textPreuCurs = new Text($preuCurs);
				$textImportCurs = new Text($importInsc);

				$textPreuCurs->setFormatNumberEuros();
				$textImportCurs->setFormatNumberEuros();

				if ( $textPreuCurs->get() != $textImportCurs->get() ) {
					$textTatxat = "<div class='tatxat mx-sm-1'>".$textPreuCurs->get()."</div>";
				}
				else $textTatxat = '';

				$labelPreuSubv = $this->obtenirLabel("", 'ml-md-2 preuDest subv', "SUBVENCIONAT");
				$labelPreuReg = $this->obtenirLabel("", 'ml-md-2 preuDest regal', "REGAL");
				$labelPreuEntitat = $this->obtenirLabel("", 'ml-md-2 preuDest entitat', "PAGA L'ENTITAT");
				$cntImport = $textTatxat."<div class='mx-sm-1'>".$textImportCurs->get()."</div>";
				if ( $tipusCurs == 'S' )
					$cntImport = $textTatxat.$labelPreuSubv;
				if ( $tipusInsc == 'R' )
					$cntImport = $labelPreuReg;
				if ( $entitat != '' && $entitat != null ) {
					$cntImport = $labelPreuEntitat;
				}

				$cntPreu = "<div class='d-flex flex-column flex-sm-row justify-content-center align-items-center'>
					".$cntImport."
				</div>";

				/* Preparo el falta a pagar de la inscripció */
				$faltaPagar = $importInsc - $pagamentInsc;
				if ( $faltaPagar <= 0 )
					$strFaltaPagar = "0.00";
				else
					$strFaltaPagar = $faltaPagar;

				$textFaltaPagar = new Text($strFaltaPagar);
				$textFaltaPagar->setFormatNumberEuros();

				if ( $faltaPagar <= 0 )
					$cntFaltaPagar = $textFaltaPagar->get();
				else {
					if ( $entitat != '' && $entitat != null ) {
						$cntFaltaPagar = "<div class='d-flex justify-content-center
						'>-</div>";
					}
					else {
						$cntFaltaPagar = "<div class='d-flex justify-content-center
						'>".$this->obtenirBoto("pay-".$idPagInsc."-".$tipusInsc, 'payInsc', $textFaltaPagar->get() )."</div>";
					}
				}


				/* Preparo el estat de la inscripció */
				$cntEstatInsc = "<div class='d-flex justify-content-center align-items-center'>
					".$this->cursos_ObtenirLabelEstatInscr($parametre, $estatInsc)."
				</div>";

				/* Preparo el cnt de les icones de la inscripció */
				$cntIcones = "<div class='d-flex flex-column flex-sm-row justify-content-center align-items-center'>
					".$this->obtenirIconaMaterials('info-'.$idInsc, 'info-curs pointer', 'info', 'Consulta la informació')."
					".$this->obtenirIconaMaterials('compartir-'.$idInsc, 'compartir-curs pointer', 'share', 'Comparteix aquest curs!')."
				</div>";

				$table .= "<tr>
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[0], $nomCurs)."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[1], $duradaCurs." h")."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[2], $cntPreu)."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[3], $cntFaltaPagar)."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[4], $cntEstatInsc)."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[5], $cntIcones)."
				</tr>";

			}

			$table .= "</tbody></table>";
		}

		return $table;
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



	public function mostrarInformacioDadesCurs_Modal_Cursos( $idInsc ) {
		/*
			Mostra la informació del curs
		*/

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		/* Busca info del curs sobre la inscripció */
		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInFoCursByIdInsc"] ) ) {
			$stmt->bind_param('d', $idInsc);
			$stmt->execute();
			$stmt->bind_result($any, $mes, $curs, $grup, $nom, $cognoms, $dni, $correu);
			$stmt->fetch();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7007);
		}
		// echo $any."<br>".$mes."<br>".$curs."<br>".$grup."<br><hr>";

		/* Busco la informació del curs */
		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInFoCursByIdCurs"] ) ) {
			$stmt->bind_param('dsss', $any, $mes, $curs, $grup);
			$stmt->execute();
			$stmt->bind_result($nomCurs, $datai, $dataf, $gtaf, $fiss,
			$cursEsc, $hores, $idCuho, $confirmat, $tipusCurs);
			$stmt->fetch();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7008);
		}

		// echo $datai."<br>".$dataf."<br>".$gtaf."<br>".$fiss."<br>".$cursEsc."<br>".$hores."<br>".$idCuho."<br>".$confirmat."<br><hr>";

		$dadesTutor = '';
		if ( $confirmat ) {
			/* Cns el dni del tutor de la idcuho del curs */
			if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsTutorByIdCuho"] ) ) {
				$stmt->bind_param('d', $idCuho);
				$stmt->execute();
				$stmt->bind_result($dniTutor);
				$stmt->fetch();
				$conWeb->closeStmt();
			}
			else {
				throw new Exception('',7009);
			}
			if ( $dniTutor == 'GENERIC' ) {
				if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsTutorByCursos"] ) ) {
					$stmt->bind_param('dsss', $any, $mes, $curs, $grup);
					$stmt->execute();
					$stmt->bind_result($dniTutor);
					$stmt->fetch();
					$conWeb->closeStmt();
				}
			}

			/* Cns la info del tutor */
			$tutor = $this->mostrarVistaTutor($dniTutor, 1);
		}
		else {
			$tutor = "<p class='mt-3'>El tutor encara no està assignat.</p>";
		}
		$dadesTutor = "<div class='d-flex flex-column align-items-center justify-content-start w-100'>
			".$tutor."
		</div>";

		/* Buscar els perfils del curs */
		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsPerfil"] ) ) {
			$stmt->bind_param('sdss', $curs, $hores, $cursEsc, $gtaf);
			$stmt->execute();
			$stmt->bind_result($idPerfil, $nom, $nCurt);
			$cntPerfils=0;
			while ( $stmt->fetch() ) {
				$perfils[$cntPerfils]["shortName"] = $nCurt;
				$perfils[$cntPerfils]["largeName"] = $nom;
				$cntPerfils++;
			}

			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7011);
		}

		$i=0;

		/* TODO HTML Crear l'apartat dels perfils */
		/* ##################### RECONEIXEMENTS DEL CURS  ##################### */
		while ( $i < $cntPerfils ) {
			$shortName = $perfils[$i]["shortName"];
			$largeName = $perfils[$i]["largeName"];

			$textShortname = new Text($shortName);
			$textShortname->setNomCurt();
			$classPerfil = $textShortname->get();

			if ($i > 0)
				 $textsDades .= " i ";

			$textsDades .= "<strong class='".$classPerfil."'>".$largeName."</strong>";

			$i++;
		}

		if ( $cntPerfils > 0 ) {
			$dateEdicio = new Date('01-'.$mes."-".$any);
			$pronom = $dateEdicio->getPronomD_ByMes();
			$edicio = $dateEdicio->getNomMes();
			$linkPerfil = "https://www.prisma.cat/perfils-professionals/";

			$dadesRecPerfils = "<p>L'edició ".$pronom."<strong>".$edicio." de ".$any."</strong> de l'<strong>any escolar ".$cursEsc."</strong> d'aquest curs està
			acollida ";

			if ( $cntPerfils > 1 )
				$dadesRecPerfils .= "als perfils professonals ";
			else
				$dadesRecPerfils .= "al perfil professonal ";

			$dadesRecPerfils .= $textsDades.".
			Podeu consultar-ne tota la informació a la nostra pàgina d'<strong>
			<a href='".$linkPerfil."' target='_self' title='Acreditació de perfils professionals'>
			acreditació de perfils professionals</a></strong>.</p>";

			$existeixenPerfils = 1;
		}
		else {
			$dadesRecPerfils = "";
		}

		$dadesReconeixement = '';
		if ( $tipusCurs != 'S' ) {
			if ( $gtaf != '' || $fiss != '' ) {
				if ( $gtaf != '' ) {
					$dadesReconeixement .= "<p>
					Aquesta activitat de formació <strong>(codi GTAF ".$gtaf." / ".$cursEsc.")</strong>
					ha estat reconeguda com a <strong>formació permanent del professorat</strong> pel Departament d'Educació de la Generalitat de Catalunya.
					</p>";
				}
				if ( $fiss != '' ) {
					$dadesReconeixement .= "<p>
					Aquesta activitat ha rebut el <strong>reconeixement de formació d'interès en serveis
					socials (codi FISS / ".$fiss.")</strong> que concedeix el Departament de Drets Socials i Inclusió de
					la Generalitat de Catalunya.
					</p>";
				}
			}
			else {
				/* TODO TEXT NO RECONEIXEMENT */
				$dadesReconeixement = "<p>
				Text curs no reconegut.
				</p>";
			}
		}
		else {
			if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInfoSubv"] ) ) {
				$stmt->bind_param('dsss', $any, $mes, $curs, $grup);
				$stmt->execute();
				$stmt->bind_result($gtaf);
				$stmt->fetch();
				if ( $gtaf != '' ) {
					$dadesReconeixement .= "<p>
					Aquesta activitat de formació <strong>(codi GTAF ".$gtaf." / ".$cursEsc.")</strong>
					ha estat reconeguda com a <strong>formació permanent del professorat</strong> pel Departament d'Educació de la Generalitat de Catalunya.
					</p>";
				}
				$conWeb->closeStmt();
			}
			else {
				throw new Exception('',7011);
			}
		}
		$conWeb->desconectarBD();

		/* #####################      DATES DEL CURS      ##################### */
		$dateI = new Date($datai);
		$dateF = new Date($dataf);

		$dataInici = $dateI->getDataLlarga();
		$dataFi = $dateF->getDataLlarga();

		$dadesCurs = "
		<div class='d-flex flex-column align-items-center justify-content-start w-100 my-2 pr-md-2'>
		<div class='d-flex flex-column align-items-center justify-content-center w-100'>
			".$this->mostrarInput(1, '', "NOM DEL CURS", 'active', 'nomCurs', 'no-edit', $nomCurs)."
		</div>
		<div class='d-flex flex-column align-items-center justify-content-center w-100'>
			".$this->mostrarInput(1, '', "HORES", 'active', 'hores', 'no-edit', $hores." h")."
		</div>
		<div class='d-flex flex-column align-items-center justify-content-center w-100'>
				".$this->mostrarInput(1, '', "DATA D'INICI", 'active', 'datai', 'no-edit', $dataInici)."
			</div>
			<div class='d-flex flex-column align-items-center justify-content-center w-100'>
				".$this->mostrarInput(1, '', 'DATA DE FI', 'active', 'dataf', 'no-edit', $dataFi)."
			</div>
		</div>";

		/* #####################           DADES           ##################### */
		$info = "<div id='dades-curs' class='d-flex flex-column justify-content-center align-items-center my-2'>
			<p class='titol-apartat'>DADES DEL CURS</p>
			<div class='apartat d-flex flex-column flex-md-row w-100 my-2'>
				<div class='d-flex flex-column w-100 mr-md-2 mb-2 mb-md-0'>
					<div class='p-2 w-100 titol-dades'>DADES DEL CURS</div>
					".$dadesCurs."
				</div>
				<div class='d-flex flex-column w-100'>
					<div class='p-2 w-100 titol-dades'>TUTOR/A DEL CURS</div>
					".$dadesTutor."
				</div>
			</div>
			<div class='apartat perfils d-flex flex-column align-items-center justify-content-center w-100 my-2'>
				".$dadesReconeixement.$dadesRecPerfils."
			</div>
		</div>";

		return $info;
	}

	/**
	* @brief mostar l'opció de compartir la url del curs assignat a idInsc
	* per facebook, whatsapp i twitter i la url.
	*/
	public function mostrarInformacioCompartirCurs_Modal_Cursos( $idInsc ) {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInFoCursByIdInsc"] ) ) {
			$stmt->bind_param('d', $idInsc);
			$stmt->execute();
			$stmt->bind_result($any, $mes, $curs, $grup, $nom, $cognoms, $dni, $correu);
			$stmt->fetch();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7014);
		}

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInfoCursWeb"] ) ) {
			$stmt->bind_param('s', $curs);
			$stmt->execute();
			$stmt->bind_result($titol, $shortDesc, $idImgLarge, $idImgSmall, $idUrl);
			$stmt->fetch();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7015);
		}

		$conWeb->desconectarBD();

		//buscar idurl de informació

		$linkCurs="https://www.prisma.cat".$this->buscarLinkUrl( $idUrl );

      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

		$urlComp="https%3A%2F%2Fwww.prisma.cat%2Fcursos%2F".$extUrl;

		/* CREAR les urls de FACE, TWITT, WHATS */
		$compartirFacebook = "<a id='fb' rel='noreferrer' class='p-3'
		onclick=\"window.open('http://www.facebook.com/sharer.php?u=".$urlComp."',
		'ventanacompartir', 'toolbar=0, status=0, width=650, height=450');\">
		<img src='https://www.prisma.cat/img/social/share_facebook.png'><p class='mt-2'>Facebook</p></a>";

		$compartirTwitter = "<a id='tw' rel='noreferrer' class='p-3' target='_blank'
		href=\"http://twitter.com/home?status=".$textTw."%20".$urlComp."\">
		<img src='https://www.prisma.cat/img/social/share_twitter.png'><p class='mt-2'>Twitter</p></a>";

		$compartirWhatsapp = "<a id='ws' rel='noreferrer' class='p-3' target='_blank'
		href=\"https://api.whatsapp.com/send?text=".$urlComp."\" data-action='share/whatsapp/share'>
		<img src='https://www.prisma.cat/img/social/share_whatsapp.png'><p class='mt-2'>WhatsAapp</p></a>";

		$compartirUrl = "<div class='form-control edit w-100 h-100 p-2'>
		".$linkCurs."</div>
		<button class='copy boto-blau px-3 d-flex justify-content-center align-items-center '>COPIA
		".$this->obtenirIconaMaterials('', 'ml-2', 'content_copy', "Copia l'enllaç")."
		</button>";

		/* HTML Construir l'apartat */
		$dades = "
		<div class='d-flex flex-column flex-sm-row align-items-center justify-content-around text-center w-100'>
	      ".$compartirFacebook."
	      ".$compartirTwitter."
	      ".$compartirWhatsapp."
	   </div>
	   <div class='cnt-copy d-flex w-100 mt-2'>
	      ".$compartirUrl."
	   </div>";

		$info = "<div id='compartir-curs' class='d-flex flex-column justify-content-center align-items-center my-2'>
			<p class='titol-apartat'>COMPARTEIX L'ENLLAÇ D'AQUEST CURS!</p>
			<div class='apartat d-flex flex-column align-items-center justify-content-center w-100 my-2'>
				".$dades."
			</div>
		</div>";
		return $info;
	}

	/**
	* @brief Mostrar la info per la solicitud de l'accés a l'aula oberta
	*/
	public function mostrarInformacioSolicitudAulaOberta( $idInsc ) {
		$textAO = "<p>Per poder entrar a les aules obertes, cal que hi donis el <strong>teu consentiment</strong> i en
	  <strong>demanis l'accés</strong> a partir de l'<strong>endemà de la data de finalització del curs</strong>.</p>";

		$textConsentiment = "<p class='titol-consentiment'>Acceptes donar-hi el teu consentiment?</p>";

		$dades = $textAO.$textConsentiment."
		<div class='d-flex flex-column flex-sm-row align-items-center justify-content-around text-center'>
	      ".$this->obtenirBoto('', "mx-3 consentiment success", "SÍ" )."
	      ".$this->obtenirBoto('', "mx-3 consentiment wrong ", "NO" )."
	   </div>
		<div class='solicito-access-ok hide d-flex flex-column align-items-center justify-content-around text-center w-100 mt-4'>
		 	<p>
				En acceptar donar el teu consentiment, pots sol·licitar l'accés clicant al botó «Sol·licitar-hi l'accés».
			</p>
			".$this->obtenirBoto('solicitoAccessAo-'.$idInsc, "solicito-access-ao", "Sol·licitar-hi l'accés" )."
	   </div>
		<div class='solicito-access-wrong hide d-flex flex-column align-items-center justify-content-around text-center w-100 mt-4'>
		 	<p>
				En no acceptar donar el teu consentiment, no t'és possible sol·licitar l'accés a l'aula oberta.
			</p>
	   </div>";

		$info = "<div class='d-flex flex-column justify-content-center align-items-center my-2'>
			<p class='titol-apartat'>SOL·LICITUD D'ACCÉS A L'AULA OBERTA!</p>
			<div class='apartat d-flex flex-column align-items-center justify-content-center w-100 my-2'>
				".$dades."
			</div>
		</div>";
		return $info;
	}

	/*
	* @brief Mostrar apartat cursos pendents, que estan cursant o que estan acabats
	* en cas que l'alumne hagi fet algun curs. Si no, apareixerà un missatge
	* informant que no té cap curs disponible.
	*/
	public function mostrarPage_Alumnes_Meus_Cursos_Acabats() {
		$mostrar = '';
		$parametre = 'llegenda-icones-estat-inscripcio-cursos-acabats';
		$table = $this->mostrarTableCurosAcabats($parametre);
		$existeix = 1;
		if ( $table != '' ) {
			//Incloure la llegenda
			$llegenda = $this->mostrarLlegenda($parametre);
			$msg = $table.$llegenda;
		}
		else {
			$msg = "<p class='text-center'>Encara no has finalitzat cap curs.</p>";
		}

		$mostrar = "<div id='cursosAcabats' class='card card-blau mt-5 d-flex flex-column justify-content-center align-items-center'>
			<div class='d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header'>
				<p class='title font-weight-bold text-center py-3 mb-0 align-items-center
				justify-content-center d-flex'>Cursos acabats</p>
			</div>
			<div class='card-body px-0 w-100 ps2'>
				<div class='d-flex flex-column justify-content-center align-items-center my-2'>
					".$msg."
				</div>
			</div>
			".$this->__mostrarModalConsulta("modalInfoDadesCurs", 1, 0, '', '')."
			".$this->__mostrarModalConsulta("modalInfoCompartirCurs", 1, 0, '', '')."
		</div>";

		return $mostrar;
	}

	/*
	* @brief Retorna una taula amb la informacio necesaria de les inscripcions dels
					cursos que encara no han acabat de l'usuari actual
	* @return Retorna una taula amb la informacio NOM DEL CURS, DURADA DEL CURS,
	 				PREU DEL CURS i EL PREU QUE HA PAGAT L'ALUMNE, FALTA PER PAGAR,
					L'ESTAT DEL CURS i dues ICONES de les inscripcions dels
					cursos que encara no han acabat de l'usuari actual
	*/
	private function mostrarTableCurosAcabats( $parametre )  {
		/* Busco el dni sense lletra de l'usuari actual */
		$user = $this->usuari->getUsuari()->get();
		/*
			Buscar les inscripcions de l'usuari $user dels cursos que encara no han acabat
			 on a `INS CURS` és 0 o 1  ordenada per data d'inscripció
		*/
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsInscCursosAcabats'] ) ) {
			$stmt->bind_param('d', $user);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$existeix = 1;
				$stmt->bind_result($nom, $hores, $import, $id, $anyInsc, $aPagar, $pagament,
				$inscCurs, $idPag, $certificat, $perenne, $tipusInsc, $tipusCurs, $entitat);
				$cnt = 0;
				while ( $stmt->fetch() ) {
					$results[$cnt]["curs"]["nom"] = $nom;
					$results[$cnt]["curs"]["durada"] = $hores;
					$results[$cnt]["curs"]["preu"] = $import;
					$results[$cnt]["insc"]["id"] = $id;
					$results[$cnt]["insc"]["any"] = $anyInsc;
					$results[$cnt]["insc"]["aPagar"] = $aPagar;
					$results[$cnt]["insc"]["pagament"] = $pagament;
					$results[$cnt]["insc"]["estat"] = $inscCurs;
					$results[$cnt]["insc"]["idPag"] = $idPag;
					$results[$cnt]["insc"]["certificat"] = $certificat;
					$results[$cnt]["insc"]["perenne"] = $perenne;
					$results[$cnt]["insc"]["tipusInsc"] = $tipusInsc;
					$results[$cnt]["insc"]["tipusCurs"] = $tipusCurs;
					$results[$cnt]["insc"]["entitat"] = $entitat;
					$cnt++;
				}
			}
			else {
				$existeix = 0;
			}
		}
		else {
			throw new Exception('', 7006);
		}

		$conWeb->desconectarBD();

		if ( $existeix ) {
			$titolsTaulaCursos[0] = "Nom del curs";
			$titolsTaulaCursos[1] = "Durada";
			$titolsTaulaCursos[2] = "Preu";
			$titolsTaulaCursos[3] = "Pendent de pagar";
			$titolsTaulaCursos[4] = "Certificat";
			$titolsTaulaCursos[5] = "Aula oberta";
			$titolsTaulaCursos[6] = "";

			$table = "<table class='table table-striped table-hover table-lg-responsive text-center mt-2'>
				<thead><tr>
					<th>".$titolsTaulaCursos[0]."</th>
					<th>".$titolsTaulaCursos[1]."</th>
					<th>".$titolsTaulaCursos[2]."</th>
					<th>".$titolsTaulaCursos[3]."</th>
					<th>".$titolsTaulaCursos[4]."</th>
					<th>".$titolsTaulaCursos[5]."</th>
					<th>".$titolsTaulaCursos[6]."</th>
				</tr></thead>
				<tbody>";

			for ( $cnt = 0; $cnt < count($results); $cnt++ ) {
				$nomCurs = $results[$cnt]["curs"]["nom"];
				$duradaCurs = $results[$cnt]["curs"]["durada"];
				$preuCurs = $results[$cnt]["curs"]["preu"];
				$importInsc = $results[$cnt]["insc"]["aPagar"];
				$pagamentInsc = $results[$cnt]["insc"]["pagament"];
				$anyInsc = $results[$cnt]["insc"]["any"];
				$estatInsc = $results[$cnt]["insc"]["estat"];
				$idInsc = $results[$cnt]["insc"]["id"];
				$idPagInsc = $results[$cnt]["insc"]["idPag"];
				$certificat = $results[$cnt]["insc"]["certificat"];
				$perenne = $results[$cnt]["insc"]["perenne"];
				$tipusInsc = $results[$cnt]["insc"]["tipusInsc"];
				$tipusCurs = $results[$cnt]["insc"]["tipusCurs"];
				$entitat = $results[$cnt]["insc"]["entitat"];

				/* Preparo el cnt del preu de la inscripció */

				$textPreuCurs = new Text($preuCurs);
				$textImportCurs = new Text($importInsc);

				$textPreuCurs->setFormatNumberEuros();
				$textImportCurs->setFormatNumberEuros();

				if ( $textPreuCurs->get() != $textImportCurs->get() ) {
					$textTatxat = "<div class='tatxat mx-sm-1'>".$textPreuCurs->get()."</div>";
				}
				else $textTatxat = '';

				$cntImport = $textTatxat."<div class='mx-sm-1'>".$textImportCurs->get()."</div>";

				if ( $tipusCurs == 'S' )
					$cntImport = $textTatxat."<div class='mx-sm-1 text-danger'>Subvencionat</div>";

				if ( $tipusInsc == 'R' )
					$cntImport = "<div class='mx-sm-1 text-danger'>Regal</div>";

				$labelPreuEntitat = $this->obtenirLabel("", 'ml-md-2 preuDest entitat', "PAGA L'ENTITAT");

				if ( $entitat != '' && $entitat != null ) {
					$cntImport = $labelPreuEntitat;
				}

				$cntPreu = "<div class='d-flex flex-column flex-sm-row justify-content-center align-items-center'>
					".$cntImport."
				</div>";

				/* Preparo el falta a pagar de la inscripció */
				$faltaPagar = $importInsc - $pagamentInsc;
				if ( $faltaPagar <= 0 )
					$strFaltaPagar = "0.00";
				else
					$strFaltaPagar = $faltaPagar;

				$textFaltaPagar = new Text($strFaltaPagar);
				$textFaltaPagar->setFormatNumberEuros();

				if ( $faltaPagar <= 0 )
					$cntFaltaPagar = $textFaltaPagar->get();
				else
					$cntFaltaPagar = "<div class='d-flex justify-content-center
					'>".$this->obtenirBoto("pay-".$idPagInsc."-".$tipusInsc, 'payInsc', $textFaltaPagar->get() )."</div>";


				/* Preparo el estat de la inscripció */
				$cntCertInsc = $certificat;
				if ( $importInsc - $pagamentInsc > 0 ) {
					$idCertificat = 'pendent-pagar';
				}
				else {
					if ( $certificat == '' or $certificat == null ) {
						if ( strpos($textCertLower, "no trobat") !== false )
							$idCertificat = 'tramitant';
						else {
							if ( $anyInsc >= 2023 )
								$idCertificat = 'properament';
							else
								$idCertificat = 'cert-fp-prisma';
						}
					}
					else {
						$textCert = new Text($certificat);
						$textCert->setMin();
						$textCertLower = $textCert->get();

						if ( strpos($textCertLower, "pujat") !== false ||
					 		strpos($textCertLower, "titulacio") !== false ) {
							$idCertificat = 'pendent-validacio';
						}
						else if ( strpos($textCertLower, "estudiant") !== false ||
					 		strpos($textCertLower, "udg") !== false ||
						 	strpos($textCertLower, "no reconegut") !== false ) {
							$idCertificat = 'cert-prisma';
						}
						else if ( strpos($textCertLower, "demanat") !== false ) {
							$idCertificat = 'demanat';
						}
						else if ( strpos($textCertLower, "digital") !== false ||
					 		strpos($textCertLower, "paper") !== false ||
						 		strpos($textCertLower, "fora cat") !== false) {
							$idCertificat = 'cert-fp-prisma';
						}
						else if ( strpos($textCertLower, "alta") !== false ||
					 		strpos($textCertLower, "gtaf") !== false) {
							$idCertificat = 'cert-dept';
						}
						else if ( strpos($textCertLower, "no aprovat") !== false ) {
							$idCertificat = 'no-aprovat';
						}
						else if ( strpos($textCertLower, "bolcat") !== false ) {
							$idCertificat = 'bolcat';
						}
					}
				}
				if ( $idCertificat != '' ) {
					$cntCertInsc = "<div class='d-flex justify-content-center align-items-center'>
						".$this->cursos_ObtenirLabelEstatInscr($parametre, $idCertificat)."
					</div>";
				}

				$cntAO = '';
				if ( $importInsc - $pagamentInsc <= 0 ) {
					if ( $perenne == '1' ) {
						$cntAO = "<div class='d-flex justify-content-center
						'>".$this->obtenirBoto("accesAO-".$idInsc, 'accesAO acces', "ACCEDIR-HI" )."</div>";
					}
					else if ( $perenne == 'X' ) {
						$cntAO = "<div class='d-flex justify-content-center
						'>".$this->obtenirBoto("accesAO-".$idInsc, 'accesAO demana-access', "DEMANAR-HI ACCÉS" )."</div>";
					}
					else if ( $perenne == '0' ) {
						$labelAO = $this->obtenirLabel("accesAO-".$idInsc, 'accesAO tramitant-access', "ACCÉS EN TRÀMIT");

						$cntAO = "<div class='d-flex justify-content-center
						'>".$labelAO."</div>";
					}
				}

				/* Preparo el cnt de les icones de la inscripció */
				$cntIcones = "<div class='d-flex flex-column flex-sm-row justify-content-center align-items-center'>
					".$this->obtenirIconaMaterials('info-'.$idInsc, 'info-curs pointer', 'info', 'Consulta la informació')."
					".$this->obtenirIconaMaterials('compartir-'.$idInsc, 'compartir-curs pointer', 'share', 'Comparteix aquest curs!')."
				</div>";

				$table .= "<tr>
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[0], $nomCurs)."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[1], $duradaCurs." h")."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[2], $cntPreu)."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[3], $cntFaltaPagar)."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[4], $cntCertInsc)."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[5], $cntAO)."
					".$this->mostrarFilaTaulaResponsive($titolsTaulaCursos[6], $cntIcones)."
				</tr>";
			}

			$table .= "</tbody></table>";
		}

		return $table;
	}

	/*
		* @brief Mostrar la llegenda corresponent al parametre $parametre
		* @returns Retorna la taula amb la llegenda corresponent a la BD params que conté el paràmetre $parametre
	*/
	private function mostrarLlegenda($parametre) {
		$llegenda = '';

		$conIntra = new ConnexioIntranet();
		$conIntra->connectarBD();

		$titolsTaulaLlegenda[0] = "Icona";
		$titolsTaulaLlegenda[1] = "Descripció";

		if ( $stmt = $conIntra->prepare( $this->cnsBD_Intra['buscarParamOrderBy'] ) ) {
			$stmt->bind_param('s', $parametre);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor, $tipus );
				// Constuir la taula llegenda
				$llegenda = "<table class='llegenda table table-striped table-hover table-sm-responsive text-center mt-2'>
				<thead><tr>
					<th>".$titolsTaulaLlegenda[0]."</th>
					<th>".$titolsTaulaLlegenda[1]."</th>
				</tr></thead>
				<tbody>";
				while ( $stmt->fetch() ) {
						$arrValor = explode('|', $valor);
						//Constuir taula amb el label d'inscripció i el text de descripció
						$icona = "<div class='d-flex justify-content-center align-items-center'>
						".$this->cursos_ObtenirLabelEstatInscr($parametre, $tipus)."
						</div>";
						$descripcio = "<p class='mb-0'>".$arrValor[2]."</p>";

						$llegenda .= "<tr>
							".$this->mostrarFilaTaulaResponsive( $titolsTaulaLlegenda[0], $icona )."
							".$this->mostrarFilaTaulaResponsive( $titolsTaulaLlegenda[1], $descripcio )."
						</tr>";
					//Acabar de constuir la taula llegenda
				}
				$llegenda .= "</tbody></table>";
			}
		}
		else {
			throw new Exception('', 6004);
		}

		$conIntra->desconectarBD();

		return $llegenda;
	}

	/**
   * @brief Retorna el contenidor d'un label amb l'estat del curs
   * @return Retorna el contenidor d'un label amb l'estat del curs
   */
	private function cursos_ObtenirLabelEstatInscr($parametre, $estat) {
		$conIntra = new ConnexioIntranet();
		$conIntra->connectarBD();

		if ( $stmt = $conIntra->prepare( $this->cnsBD_Intra['buscarParamType'] ) ) {
			$stmt->bind_param('ss', $parametre, $estat);
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor );
				$stmt->fetch();

				$arrValor = explode('|', $valor);

				$nomEstat = $arrValor[0];
				$colorLabel = $arrValor[1];
			}
			else {
				$nomEstat = '';
				$colorLabel = '';
			}
		}
		else {
			throw new Exception('', 6003);
		}

		$conIntra->desconectarBD();

		$label = $this->obtenirLabel('', $colorLabel, $nomEstat);

		return $label;
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
			throw new Exception('',7015);
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
			throw new Exception('',9001);
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
			throw new Exception('',7014);
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

	/**
	* @brief Actualitza la taula perenne i envio missatges
	* @return Actualitza la columna PERENNE a 0 a la taula d'inscripcions.
	* Envia missatge a inscripcions un missatge indicant que l'alumne amb la id $idInsc
	* ha sol·licitud de l'aula oberta del curs
	* Envia missatge a secretaria i a l'alumne un missatge indicant que ha sol·licitud
	* l'accés a l'aula oberta.
	*/
	public function subscripcioAulaOberta( $idInsc ) {

		/* Obtenir info d'inscripció */
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		/* Busca info del curs sobre la inscripció */
		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInFoCursByIdInsc"] ) ) {
			$stmt->bind_param('d', $idInsc);
			$stmt->execute();
			$stmt->bind_result($any, $mes, $curs, $grup, $nom, $cognoms, $dni, $correu);
			$stmt->fetch();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7016);
		}

		/* Busco la informació del curs */
		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInFoCursByIdCurs"] ) ) {
			$stmt->bind_param('dsss', $any, $mes, $curs, $grup);
			$stmt->execute();
			$stmt->bind_result($nomCurs, $datai, $dataf, $gtaf, $fiss, $cursEsc, $hores, $idCuho, $confirmat);
			$stmt->fetch();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7017);
		}

		/* actualitzar perenne */
		// UPDATE PERENNE = '0' WHERE ID = ?
		if ( $stmt=$conWeb->prepare( $this->updBD_Web["updPerenneInsc"] ) ) {
			$stmt->bind_param('sd', $perenne, $idInsc);
			$perenne = '0';
			$stmt->execute();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7018);
		}
		echo $this->updBD_Web["updPerenneInsc"]."<br />";
		echo $perenne."<br />";
		echo $idInsc."<br />";

		$msgAlumne = "<p>Hola ".$nom.",</p>
		<p align=justify>Et confirmem que has sol·licitat l'accés a l'Aula Oberta
		del curs <strong style='color: ".$this->dades['colors']['blauPrisma']."'>
		".$nomCurs."</strong> i que en 24/48 hores laborables
		hi tindràs accés.</p>

		<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

		<p>Salutacions ben cordials,</p>";

		$msgPrisMa = "<p>Hola,</p>
		<p align=justify>L'alumne <strong>".$nom." ".$cognoms."</strong>
		amb el correu electrònic <strong style='color: ".$this->dades['colors']['blauPrisma']."'>".$correu."</strong>
		ha sol·licitat l'accés a l'Aula Oberta del curs <strong
		style='color: ".$this->dades['colors']['blauPrisma']."'>".$nomCurs."</strong>.</p>
		<p>Salutacions ben cordials,</p>";

		$dateNow = new DateTime('now');
		$dataAct = $dateNow->format('d/m/Y');

		$subjAlumne = "Sol·licitud d'accés a l'aula oberta ".$nomCurs." - ".$dataAct;
		$subjPrisMa = "Aula oberta ".$curs." - ".$dni;

		/* ########################          SMTP          ######################## */
		$authSecre = $this->getAuthSMTP_Secretaria();
		$userSecre = $authSecre[0];
		$passSecre = $authSecre[1];
		$nameUserSecre = $authSecre[2];

		$nomFromHead = $nameUserSecre;
		$correuFromHead = $userSecre;

		require_once "MailSMTPComvive.php";

		$nomTo = "Secretaria PrisMa";
		$correuTo = "resguard.secretaria@prisma.cat";
		// $correuTo = "meriem.prisma.cat@gmail.com";
		$nomReplyHead = $nom." ".$cognoms;
		$correuReplyHead = $correu;

		$mailSecre = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjAlumne, $msgAlumne, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

		$nomTo = "Inscripcions PrisMa";
		$correuTo = "inscripcions@prisma.cat";
		// $correuTo = "meriem.prisma.cat@gmail.com";
		$nomReplyHead = $nom." ".$cognoms;
		$correuReplyHead = $correu;

		$mailInsc = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjPrisMa, $msgPrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

		$nomTo = $nom." ".$cognoms;
		$correuTo = $correu;
		$nomReplyHead = $nameUserSecre;
		$correuReplyHead = $userSecre;

		$mailAlumne = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjAlumne, $msgAlumne, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

	}

	/* #######################  INTRANET -CURSOS NO FET   ####################### */
	public function __mostrarPage_Alumnes_Cursos_No_He_Realitzat() {
		$mostrar = "<div id='cursos' class='card card-blau mt-5 d-flex flex-column justify-content-center align-items-center'>
			<div class='d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header'>
				<p class='title font-weight-bold text-center py-3 mb-0 align-items-center
				justify-content-center d-flex'>Inici</p>
			</div>
			<div class='card-body px-0 w-100 ps2'>
				<div class='d-flex flex-column justify-content-center align-items-center my-2'>

				</div>
			</div>
		</div>";
		return $mostrar;
	}

	/* #######################    INTRANET - CONTACTE    ####################### */
	public function __mostrarPage_Alumnes_Contacte() {
		$icones = $this->mostrarIconesContacteDefecte();
		$formulari = $this->mostrarFormulariContacteDefecte();

		$mostrar = $icones."<div id='contacte' class='card card-blau mt-5 d-flex flex-column justify-content-center align-items-center'>
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

	public function mostrarIconesContacteDefecte() {
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsInfoContacte'] ) ) {
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($adreca, $cp, $poblacio, $numFix, $numMbl, $email, $horariTemp, $hestiu, $mapa);
				$stmt->fetch();
			}
		}
		else {
			throw new Exception('', 7022);
		}
		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsInfoFestiusDates'] ) ) {
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result($atencio);
				$stmt->fetch();
			}
		}
		else {
			throw new Exception('', 7023);
		}

		$conWeb->desconectarBD();

		$poble = new Text($poblacio);
		$poble->setMaj();

		for ( $i = 0; $i < count( explode("<br>", $horariTemp) ); $i++ ) {
			$horari .= "<p";
			if ( $i < count( explode("<br>", $horariTemp) )-1 ) $horari .= " class='mb-1'";
			$horari .= ">".explode("<br>", $horariTemp)[$i]."</p>";
		}
      if (date(n)>=6 && date(n)<=9) {
         $horari .= "<p>".$hestiu."</p>";
      }

		$iconaHorari = "<div class='d-flex flex-column align-items-center justify-content-center text-center w-100 apartat-contacte'>
	      <div class='icones d-flex justify-content-center align-items-center mb-3 mt-0'>
	         <i class='fas fa-user-clock' aria-hidden='true'></i>
	      </div>
	      <p class='titol-contacte mb-2'>Horari</p>
	      ".$horari."
	   </div>";
		$textMbl = new Text($numMbl);
		$textMbl->replace(' ', '');

		$iconaTel = "<div class='d-flex flex-column align-items-center justify-content-center text-center w-100 apartat-contacte'>
	      <div class='icones d-flex justify-content-center align-items-center mb-3 mt-0'>
	         <i class='fa fa-phone' style='transform: rotate(90deg);' aria-hidden='true'></i>
	      </div>
	      <p class='titol-contacte mb-2'>Telèfons</p>
	      <p class='mb-1'><a href='tel:+34".$numFix."'>".$numFix."</a></p>
			<div class='d-flex justify-content-center align-items-center'>
				<span></span>
				<a href='tel:+34".$numMbl."' class='ml-1'>".$numMbl."</a>
			</div>
			<div class='d-flex justify-content-center align-items-center'>
				<a href='https://api.whatsapp.com/send?phone=34".$textMbl->get()."' target='_blank'
					class='whatsapp ml-1 ml-sm-2 d-flex justify-content-center align-items-center'>
					WhatsApp!
					<i class='fa-brands fa-whatsapp ml-1'></i>
				</a>
			</div>
	   </div>";

		$iconaLloc = "<div class='d-flex flex-column align-items-center justify-content-center text-center w-100 apartat-contacte'>
	      <div class='icones d-flex justify-content-center align-items-center mb-3 mt-0'>
	         <i class='fa fa-map-marker-alt' aria-hidden='true'></i>
	      </div>
	      <p class='titol-contacte mb-2'>Adreça</p>
	      <p class='mb-1'>".$adreca."</p>
			<p>".$cp." ".$poble->get()."</p>
	   </div>";

		// $cnt = "<div class='d-flex flex-column flex-md-row w-100'>
		// 	".$iconaHorari."
		// 	".$iconaTel."
		// 	".$iconaLloc."
		// </div>";

		$cnt = "<div class='d-flex flex-column flex-md-row w-100 justify-content-md-center align-items-center align-items-md-start'>
			".$iconaHorari."
			".$iconaTel."
		</div>";

		if ( $atencio != null or $atencio != '' )
         $cnt .= "<div class='horari-atencio w-100 text-center'>
			".$atencio."</div>";

      return $cnt;
	}

	public function mostrarFormulariContacteDefecte() {
		$user = $this->usuari->getUsuari()->get();
		$classDadesPersonals = '';

		$llistatCursos = "";

		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsInfoInscByUser'] ) ) {
			$stmt->bind_param('d', $user);
			$limit = 1;
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$classDadesPersonals = 'active';
				$stmt->bind_result($idInsc, $nom, $cog, $email, $dni, $adreca, $cp, $poble, $perfil, $titulacio, $tel, $any, $mes, $curs, $inscrit, $apagar, $idPag );
				$stmt->fetch();
			}
		}
		else {
			throw new Exception('', 7019);
		}
		$conWeb->closeStmt();

		if ( $stmt = $conWeb->prepare( $this->cnsBD_Web['cnsAllCourses'] ) ) {
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $codiCurs, $nomCurs );
				while( $stmt->fetch() ) {
					$llistatCursos .= "<li class='border-bottom' id='curs-consulta-".$codiCurs."'>";
					$llistatCursos .= $nomCurs;
					$llistatCursos .= "</li>";
				}
			}
		}
		else {
			throw new Exception('', 7020);
		}
		$conWeb->closeStmt();

		$conWeb->desconectarBD();

		$conIntra = new ConnexioIntranet();
		$conIntra->connectarBD();

		$cntDadesPersonals = "<div id='cntDadesPersonals' class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
			<div class='d-flex flex-column align-items-center justify-content-center w-100'>
				".$this->mostrarInput(0, '', 'Nom', $classDadesPersonals, 'nom', 'edit', $nom)."
				".$this->mostrarInput(0, '', 'Cognoms', $classDadesPersonals, 'cog', 'edit', $cog)."
			</div>
			<div class='d-flex flex-column align-items-center justify-content-center w-100'>
				".$this->mostrarInput(0, '', '<em>E-mail</em>', $classDadesPersonals, 'email', 'edit', $email)."
				".$this->mostrarInput(0, '', 'Telèfon', $classDadesPersonals, 'tel', 'edit', $tel)."
			</div>
			".$this->__modalCorreuValid()."
		</div>";

		//Buscar els tipus de consultes
		$tipusConsultes = [];
		$motius = [];

		if ( $stmt = $conIntra->prepare( $this->cnsBD_Intra['buscarParamOrderBy'] ) ) {
			$stmt->bind_param('s', $parametre);
			$parametre = 'dades-apartat-contacte-tipus-consulta';
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor, $tipus );
				$cntParam = 0;
				while( $stmt->fetch() ) {
					$tipusConsultes[$cntParam] = $valor;
					$cntParam++;
				}
			}

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

			$parametre = 'dades-apartat-contacte-motius-consulta';
			$stmt->execute();
			$stmt->store_result();
			if ( $stmt->num_rows() > 0 ) {
				$stmt->bind_result( $valor, $tipus );
				$cntParam = 0;
				while( $stmt->fetch() ) {
					$motius[$cntParam] = $valor;
					$cntParam++;
				}
			}
			$conIntra->closeStmt();
		}
		else {
			throw new Exception('', 6005);
		}

		$tipusConsulta = $this->mostrarSelect('', 'Tipus de consulta', '', 'tipus-consulta', 'tipus-consulta', '', '', $tipusConsultes);
		$cntTipusConsulta = "<div id='cntTipusConsulta' class='d-flex flex-column flex-md-row justify-content-center align-items-center w-100'>
			<div class='d-flex flex-column align-items-center justify-content-center w-100'>
				".$tipusConsulta."
			</div>
		</div>";


		$dispositiu = [];
		$sistemes = [];

		$motiuConsulta = $this->mostrarInput(0, 'hide', 'Motiu de consulta', '', 'motiu-consulta-text-altres', 'edit', '');
		$typeReasons = $this->mostrarSelect('', 'Motiu de consulta', '', 'motiu-consulta', 'motiu-consulta', '', '', $motius);
		$courses = $this->mostrarSelect2('', 'Curs relacionat', '', 'curs-consulta', 'curs-consulta', '', $llistatCursos);
		$devices = $this->mostrarSelect('', 'Dispositiu utilitzat', '', 'dispositiu', 'dispositiu', '', '', $dispositiu);
		$systems = $this->mostrarSelect('', 'Sistema operatiu', '', 'sistemes', 'sistemes', '', '', $sistemes);
		$browsers = $this->mostrarSelect('', 'Navegador', '', 'navegador', 'navegador', '', '', $navegadors);
		$cntAdds = "<div id='adds'class='d-flex flex-column align-items-center justify-content-center w-100'>
			<div id='cnt-motiu' class='d-flex flex-column align-items-center justify-content-center w-100 hide'>
				".$typeReasons."
				".$motiuConsulta."
			</div>
			<div id='cnt-courses' class='d-flex flex-column align-items-center justify-content-center w-100 hide'>
				".$courses."
			</div>
			<div id='cnt-devices' class='hide d-flex flex-column align-items-center justify-content-center w-100 px-md-2'>
				<p class='w-100'>El dispositiu que utilitzo és:</p>
				<div class='d-flex flex-row flex-wrap w-100 px-1 mb-3'>
			         ".$textDevices."
				</div>
			</div>
			<div id='cnt-systems' class='hide d-flex flex-column align-items-center justify-content-center w-100 px-md-2'>
				<p class='w-100'>El sistema operatiu del dispositiu és:</p>
				<div class='d-flex flex-row flex-wrap w-100 px-1 mb-3'>
			         ".$textSystems."
				</div>
			</div>
			<div id='cnt-browsers' class='hide d-flex flex-column align-items-center justify-content-center w-100 px-md-2'>
				<p class='w-100'>El navegador que faig servir és:</p>
				<div class='d-flex flex-row flex-wrap w-100 px-1 mb-3'>
			         ".$textBrowsers."
				</div>
			</div>
		</div>";

		$assumpte = $this->mostrarInput(0, '', 'Assumpte', '', 'assumpte', 'edit', '');
		$cntAssumpte = "<div id='cntAssumpte' class='d-flex flex-column align-items-center justify-content-center w-100'>
			".$assumpte."
		</div>";

		$missatge = $this->mostrarTextarea('', 'Missatge', '', 'missatge', 'edit', '', 'text');
		$cntMissatge = "<div id='cntMissatge' class='d-flex flex-column align-items-center justify-content-center w-100'>
			".$missatge."
		</div>";

		$conIntra->desconectarBD();

		$cntBoto = "<div class='cnt-send-info mt-4 d-flex flex-column flex-md-row justify-content-center align-items-center'>
			<button id='envia-consulta' role='button' class='send-info boto-blau px-4 d-flex'>
			Envia consulta <i class='material-icons ml-2'>send</i>
			</button>
		</div>";

		$form = "<div class='d-flex flex-column justify-content-center align-items-center w-100'>
			".$cntDadesPersonals."
			".$cntTipusConsulta."
			".$cntAdds."
			".$cntAssumpte."
			".$cntMissatge."
			".$cntBoto."
		</div>";

		return $form;
	}

	public function enviarMsgConsulta( $nom, $cognoms, $email, $telefon,
	$typeCons, $reasonCons, $reasonConsA, $idCourseCons,
	$deviceCons, $systemCons, $browserCons, $subject, $message ) {


		$order   = array("\r\n", "\n", "\r");
		$replace = '</p>
		<p>';

		$messageNew = str_replace( $order, $replace, $message);

		//buscar el nom de l'apartat del tipus de consulta $typeCons
		$tipusCons = $typeCons;

		if ( $typeCons == 'tipus-consulta-secretaria-consultes-generals' ) {
			$motiuConsCompl = $reasonCons;
			if ( $reasonCons == 'Altres' ) $motiuConsCompl .= ": ".$reasonConsA;

			$courseCons = explode('-', $idCourseCons)[2];

			$this->enviarMsgConsultaSecreConsultaGeneral( $nom, $cognoms, $email, $telefon,
				$reasonCons, $motiuConsCompl, $courseCons, $subject, $messageNew);
		}
		else if ( $typeCons == 'tipus-consulta-secretaria-suggeriments' ) {
			$this->enviarMsgConsultaSecreSuggeriment( $nom, $cognoms, $email, $telefon,
				$subject, $messageNew);
		}
		else if ( $typeCons == 'tipus-consulta-secretaria-atencio-de-queixes') {
			$this->enviarMsgConsultaSecreQueixes( $nom, $cognoms, $email, $telefon,
				$subject, $messageNew);
		}
		else if ( $typeCons == 'tipus-consulta-suport-informatic-incidencies-tecniques') {
			$courseCons = explode('-', $idCourseCons)[2];

			$this->enviarMsgConsultaIncidencia( $nom, $cognoms, $email, $telefon,
				$courseCons, $deviceCons, $systemCons, $browserCons, $subject, $messageNew);
		}
	}

	/**
	* @brief Enviar MSG de consulta a secretaria@prisma.cat
	* @return Enviar msg de consultes generals amb els camps nom, cognoms, email, telefon,
	* motiu de consulta, curs relacionat, assumpte i missatge al
	* correu eletrònic de secretaria@prisma.cat i un msg informant a l'alumne que en
	* 24/48h rebrà una resposta a la consulta.
	*/
	public function enviarMsgConsultaSecreConsultaGeneral( $nom, $cognoms, $email, $telefon,
	$reasonCons, $motiuConsCompl, $courseCons, $subject, $message) {

		//buscar el nom del curs a partir del codi curs $courseCons
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInfoCursWeb"] ) ) {
			$stmt->bind_param('s', $courseCons);
			$stmt->execute();
			$stmt->bind_result($titol, $shortDesc, $idImgLarge, $idImgSmall, $idUrl);
			$stmt->fetch();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7021);
		}
		$conWeb->desconectarBD();

		$dadesUsuari = "<p><strong>Dades de l'alumne</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Nom</strong>: ".$nom."</p>
			<p><strong>Cognoms</strong>: ".$cognoms."</p>
			<p><strong><em>E-mail</em></strong>: ".$email."</p>
			<p><strong>Telèfon</strong>: ".$telefon."</p>
		</div>";

		$dadesConsulta = "<p><strong>Dades de la consulta</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Motiu de la consulta</strong>: ".$motiuConsCompl."</p>
			<p><strong>Curs relacionat</strong>: ".$titol."</p>
			<p><strong>Assumpte</strong>: ".$subject."</p>
			<p><strong>Missatge</strong>: ".$message."</p>
		</div>";

		$missatgePrisMa = "<p>Hola,</p>
			<p>S'ha efectuat una consulta general.</p>
			<p>A continuació trobaràs les dades de la consulta.</p>
			".$dadesUsuari."
			".$dadesConsulta;

		$missatgeAlumnes = "<p>Hola, ".$nom.",</p>
		<p>Hem enregistrat correctament la teva consulta:</p>

		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Motiu de la consulta</strong>: ".$motiuConsCompl."</p>
			<p><strong>Curs relacionat</strong>: ".$titol."</p>
			<p><strong>Assumpte</strong>: ".$subject."</p>
			<p><strong>Missatge</strong>: ".$message."</p>
		</div>

		<p>En 24/48 hores laborables respondrem a la teva consulta.</p>

		<p>Per a qualsevol altra consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

		<p>Salutacions ben cordials,</p>";

		$dateNow = new DateTime('now');
		$dataAct = $dateNow->format('d/m/Y');

		$subjectPrisMa = "Consultes generals ";
		if ( $reasonCons != 'Altres' )
			$subjectPrisMa .= "(".$reasonCons.")";
		$subjectPrisMa .= " de ".$nom." | ".$dataAct;

		$subjectAlumne = "Consulta general | ".$dataAct;

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

		$nomTo = $nameUserSecre;
		$correuTo = "secretaria@prisma.cat";
		// $correuTo = "meriem.prisma.cat@gmail.com";
		$nomReplyHead = $nom." ".$cognoms;
		$correuReplyHead = $email;

		$mailSecre = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectPrisMa, $missatgePrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

		$nomTo = $nom." ".$cognoms;
		$correuTo = $email;
		// $correuTo = "meriem.prisma.cat@gmail.com";
		$nomReplyHead = $nameUserSecre;
		$correuReplyHead = $userSecre;

		//es notifica a l'alumne que s'ha enviat la proposta de modificació de dades a Secretaria.
		$mailAlumne = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectAlumne, $missatgeAlumnes, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);
	}

	/**
	* @brief Enviar MSG de consulta a secretaria@prisma.cat
	* @return Enviar msg de consulta amb els camps nom, cognoms, email, telefon,
	* tipus de consulta, assumpte i missatge al
	* correu eletrònic de secretaria@prisma.cat i un msg informant a l'alumne que en
	* 24/48h rebrà una resposta a la consulta.
	*/
	public function enviarMsgConsultaSecreSuggeriment( $nom, $cognoms, $email, $telefon,
	$subject, $message) {
		$dadesUsuari = "<p><strong>Dades de l'alumne</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Nom</strong>: ".$nom."</p>
			<p><strong>Cognoms</strong>: ".$cognoms."</p>
			<p><strong><em>E-mail</em></strong>: ".$email."</p>
			<p><strong>Telèfon</strong>: ".$telefon."</p>
		</div>";

		$dadesConsulta = "<p><strong>Dades de la consulta</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Assumpte</strong>: ".$subject."</p>
			<p><strong>Missatge</strong>: ".$message."</p>
		</div>";

		$missatgePrisMa = "<p>Hola,</p>
			<p>S'ha efectuat un suggeriment.</p>
			<p>A continuació trobaràs les dades de la consulta.</p>
			".$dadesUsuari."
			".$dadesConsulta;

		$missatgeAlumnes = "<p>Hola, ".$nom.",</p>
		<p>Hem enregistrat correctament el teu suggeriment:</p>

		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Assumpte</strong>: ".$subject."</p>
			<p><strong>Missatge</strong>: ".$message."</p>
		</div>

		<p>Per a qualsevol altra consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

		<p>Salutacions ben cordials,</p>";

		$data = new DateTime();
      $timestamp = $data->getTimestamp();

		$subjectPrisMa .= "Bústia de suggeriments núm. ".$timestamp;

		$subjectAlumne = "Bústia de suggeriments núm. ".$timestamp;

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

		$nomTo = $nameUserSecre;
		$correuTo = "secretaria@prisma.cat";
		// $correuTo = "meriem.prisma.cat@gmail.com";
		$nomReplyHead = $nom." ".$cognoms;
		$correuReplyHead = $email;

		$mailSecre = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectPrisMa, $missatgePrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

		$nomTo = $nom." ".$cognoms;
		$correuTo = $email;
		$nomReplyHead = $nameUserSecre;
		$correuReplyHead = $userSecre;

		//es notifica a l'alumne que s'ha enviat la proposta de modificació de dades a Secretaria.
		$mailAlumne = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectAlumne, $missatgeAlumnes, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);
	}

	/**
	* @brief Enviar MSG de consulta a secretaria@prisma.cat
	* @return Enviar msg de consulta amb els camps nom, cognoms, email, telefon,
	* tipus de consulta, assumpte i missatge al
	* correu eletrònic de secretaria@prisma.cat i un msg informant a l'alumne que en
	* 24/48h rebrà una resposta a la consulta.
	*/
	public function enviarMsgConsultaSecreQueixes( $nom, $cognoms, $email, $telefon,
	$subject, $message) {
		$dadesUsuari = "<p><strong>Dades de l'alumne</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Nom</strong>: ".$nom."</p>
			<p><strong>Cognoms</strong>: ".$cognoms."</p>
			<p><strong><em>E-mail</em></strong>: ".$email."</p>
			<p><strong>Telèfon</strong>: ".$telefon."</p>
		</div>";

		$dadesConsulta = "<p><strong>Dades de la consulta</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Assumpte</strong>: ".$subject."</p>
			<p><strong>Missatge</strong>: ".$message."</p>
		</div>";

		$missatgePrisMa = "<p>Hola,</p>
			<p>S'ha efectuat una queixa.</p>
			<p>A continuació trobaràs les dades de la consulta.</p>
			".$dadesUsuari."
			".$dadesConsulta;

		$missatgeAlumnes = "<p>Hola, ".$nom.",</p>
		<p>Hem enregistrat correctament la teva queixa:</p>

		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Assumpte</strong>: ".$subject."</p>
			<p><strong>Missatge</strong>: ".$message."</p>
		</div>

		<p>Per a qualsevol altra consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

		<p>Salutacions ben cordials,</p>";

		$data = new DateTime();
      $timestamp = $data->getTimestamp();

		$subjectPrisMa .= "Servei atenció queixes núm. ".$timestamp;
		$subjectAlumne = "Servei atenció queixes núm. ".$timestamp;

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

		$nomTo = $nameUserSecre;
		$correuTo = "secretaria@prisma.cat";
		// $correuTo = "meriem.prisma.cat@gmail.com";
		$nomReplyHead = $nom." ".$cognoms;
		$correuReplyHead = $email;

		$mailSecre = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectPrisMa, $missatgePrisMa, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);

		$nomTo = $nom." ".$cognoms;
		$correuTo = $email;
		$nomReplyHead = $nameUserSecre;
		$correuReplyHead = $userSecre;

		//es notifica a l'alumne que s'ha enviat la proposta de modificació de dades a Secretaria.
		$mailAlumne = new MailSMTPComvive($userSecre, $passSecre, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectAlumne, $missatgeAlumnes, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);
	}

	/**
	* @brief Enviar MSG de consulta a suport.informatic@prisma.cat i a suport@risma.cat
	* @return Enviar msg de consulta amb els camps nom, cognoms, email, telefon,
	* tipus de consulta, curs de consulta, els dispositiu de consulta, els sistema de
	* consulta, el navegador de consulta, l'assumpte i el missatge al
	* correu eletrònic de suport.informatic@prisma.cat i un msg informant a l'alumne que en
	* 24/48h rebrà una resposta a la consulta.
	*/
	public function enviarMsgConsultaIncidencia( $nom, $cognoms, $email, $telefon,
	$courseCons, $deviceCons, $systemCons, $browserCons, $subject, $message) {
		//buscar el nom del curs a partir del codi curs $courseCons
		$conWeb = new ConnexioWeb();
		$conWeb->connectarBD();

		if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["cnsInfoCursWeb"] ) ) {
			$stmt->bind_param('s', $courseCons);
			$stmt->execute();
			$stmt->bind_result($titol, $shortDesc, $idImgLarge, $idImgSmall, $idUrl);
			$stmt->fetch();
			$conWeb->closeStmt();
		}
		else {
			throw new Exception('',7021);
		}
		$conWeb->desconectarBD();

		$dispositiu = $deviceCons;
		$so = $systemCons;
		$navegador = $browserCons;

		$conIntra = new ConnexioIntranet();
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

		$dadesUsuari = "<p><strong>Dades de l'alumne:</strong></p>
		<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Nom</strong>: ".$nom."</p>
			<p><strong>Cognoms</strong>: ".$cognoms."</p>
			<p><strong><em>E-mail</em></strong>: ".$email."</p>
			<p><strong>Telèfon</strong>: ".$telefon."</p>
		</div>";

		$dadesBGConsulta = "<div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
			<p><strong>Curs relacionat</strong>: ".$titol."</p>
			<p><strong>Dispositiu</strong>: ".$dispositiu."</p>
			<p><strong>Sistema operatiu</strong>: ".$so."</p>
			<p><strong>Navegador</strong>: ".$navegador."</p>
			<p><strong>Assumpte</strong>: ".$subject."</p>
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

			<p>En 24/48 hores laborables resoldrem a la teva incidència.</p>

			<p>Per a qualsevol altra consulta, no dubtis a posar-te en contacte amb nosaltres.</p>

			<p>Salutacions ben cordials,</p>";

		$dateNow = new DateTime('now');
		$dataAct = $dateNow->format('d/m/Y H:i');

		$subjectPrisMa = "Incidències tècniques ".$nom." ".$cognoms." - ".$dataAct;
		$subjectAlumne = "Incidències tècniques - ".$dataAct;

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
		$nomReplyHead = $nameUserAtencio;
		$correuReplyHead = $userAtencio;

		//es notifica a l'alumne que s'ha enviat la proposta de modificació de dades a Secretaria.
		$mailAlumne = new MailSMTPComvive($userAtencio, $passAtencio, $nomFromHead, $correuFromHead,
							$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
							$subjectAlumne, $missatgeAlumnes, $this->firmes['Equip']['qui'], $this->firmes['Equip']['dept']);
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
			/* TODO EXCEPTION Afegir url*/
			throw new Exception('',xxx);
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
        //
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
			throw new Exception('', xxxx);
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
 		  throw new Exception('',4369);

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

		echo $this->cnsBD_Web["buscarParam"] ."<br>".$tipus."<br>";
 	  if ( $stmt=$conWeb->prepare( $this->cnsBD_Web["buscarParam"] ) ) {
 		  $stmt->bind_param("s", $tipus);
 		  $stmt->execute();
 		  $stmt->bind_result($valor);
 		  $stmt->fetch();

 		  $autentificacio = explode('|',$valor);
 		  $conWeb->closeStmt();
 	  }
 	  else
 		  throw new Exception('', 7004);

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
   				<div class='modal-body text-center pt-3 px-3' id='modalCorreuValidBody'></div>
   				<div class='modal-footer justify-content-center text-center border-0 pt-0 mb-2'>
   					<button role='button' data-dismiss='modal' class='btn btn-warning border-0 border-radius-2 text-center negreta500 m-0 mr-3'>D'acord</button>
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
