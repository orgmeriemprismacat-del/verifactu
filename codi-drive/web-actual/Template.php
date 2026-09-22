<?php
/**
	* @class Template
	* @brief Plantilles
*/
class Template
{
	private $esquema;
	private $colors_destacats;

	public function __construct()	{
		$this->colors_destacats = [
			"msgDestacatCorreu1" => "#496BAA",
		];
		$this->esquema = [
			"[CODI_CURS]"             	=> "Shortname del curs",
			"[TITOL]"             		=> "Titol del curs",
			"[AULA]"              		=> "Aula relacionada amb el curs",
			"[ANY]"              		=> "Any relacionat amb el curs",
			"[MES]"              		=> "Mes relacionat amb el curs. Ex '06'",
			"[DATAI]"             		=> "Data d'inici del curs",
			"[DATAF]"             		=> "Data de fi del curs",
			"[DATAI_DATAF]"            => "Data realitzada del curs (realitzada del XX al YY)",
			"[DATAF_INCR_3MONTHS]"  	=> "Data de fi + 3 MESOS del curs",
			"[NOM_TUTOR]"         		=> "Nom del tutor que porta el curs",
			"[NOM_ALUMNE]"        		=> "Nom de l'alumne",
			"[COG_ALUMNE]"        		=> "Cognom de l'alumne",
			"[CORREU_ALUMNE]" 			=> "Correu de l'alumne",
			"[DNI_ALUMNE]" 				=> "DNI de l'alumne",
			"[TEL_ALUMNE]" 				=> "Telefon de l'alumne",
			"[EMAIL_ALUMNE]" 				=> "E-mail de l'alumne",
			"[ADRECA_ALUMNE]" 			=> "Adreça de l'alumne",
			"[CP_ALUMNE]" 					=> "Codi postal de l'alumne",
			"[POBLACIO_ALUMNE]" 			=> "Poblacio de l'alumne",
			"[PERFIL_ALUMNE]" 			=> "XXXX de l'alumne",
			"[TITULACIO_ALUMNE]" 		=> "XXXX de l'alumne",
			"[CONEGUT_ALUMNE]" 			=> "XXXX de l'alumne",
			"[COMENTARIS_ALUMNE]" 		=> "XXXX de l'alumne",
			"[PAY_ORIG_ALUMNE]" 			=> "Pagament original de l'alumne del curs",
			"[PAY_DESC_ALUMNE]" 			=> "Pagament amb descompte de l'alumne del curs",
			"[MOTIU_DESCOMPTE_ALUMNE]" => "Motiu del descompte de l'alumne del curs",
			"[IDPAG_PAY]" 				=> "ID PAG de l'alumne del curs",
			"[METHOD_PAY]" 				=> "Mètode del pagament de l'alumne del curs",
			"[URL_PAGAMENT]"      		=> "L'enllaç de pagament",
			"[NOM_RESPONSABLE]"   		=> "Nom del responable de l'entitat/grup",
			"[DATA_SESSIO1]"   			=> "La data en format llarg de la sessió 1 ",
			"[HORA_SESSIO1]"   			=> "La hora en format llarg de la sessió 1 ",
			"[DIASETMANA_DATAI]"   		=> "El dia de la setmana de la data d'inici",
			"[EDICIO_MES]"   				=> "El mes amb text llarg. Per ex. maig",
			"[NOM_CURS_AULA_OBERTA]" 	=> "El nom de l'aula oberta",
			"[ID_MDL_AULA_BERTA]" 		=> "El ID del moodle de l'aula oberta",
		];
	}

	/* ########################################################################## */
	/* #######################            WEB            ####################### */
	/* #######################           DADES          ####################### */
	/* ########################################################################## */

	/**
	* @brief Missatge del text requadre amb dades personals
	* @param $tipus Serveix per indicar si ha d'apareixer o no l'adreça
	* @return string Missatge del text requadre amb dades personals: nom, nif, correu, telefon, adreca
	*/
	public function getTemplate_Dades_RequadreDadesPersonals($tipus) {
		include ('templates/dades/requadreDadesPersonals.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text requadre amb dades personals
	* @param $tipus Serveix per indicar com s'ha de mostrar el preu
	* @return string Missatge del text requadre amb dades personals: nom, nif, correu, telefon.
	* 			 i dades del curs: curs, dates i preu.
	*/
	public function getTemplate_Dades_RequadreDadesPersonalsCurs($tipus) {
		include ('templates/dades/requadreDadesPersonalsCurs.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text requadre amb dades personals i professionals
	* @return string Missatge del text requadre amb dades personals: nom, nif, correu, telefon.
	* 			 i dades del curs: curs, dates i preu.
	*/
	public function getTemplate_Dades_RequadreDadesPersonalsProfessionals() {
		include ('templates/dades/requadreDadesPersonalsProfessionals.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text requadre amb dades curs per persona per el pagament de grup
	* @param $numPersona Numero de persona
	* @param $nameCourse Nom del curs
	* @param $datesCourse Dates del curs
	* @param $hores Hores del curs
	* @param $priceCourseOrig Preu original del curs
	* @param $textPriceCourseOffer Preu amb descompte del curs
	* @return string Missatge del text requadre amb dades curs per persona per el pagament de grup
	*/
	public function getTemplate_Dades_RequadreDadesCursGrup() {
		include ('templates/dades/requadreDadesCurs_DescompteGrup.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text requadre amb dades de pagament de l'alumne i curs
	* @return string Missatge del text requadre amb dades de pagament de la comanda realitzada:
	* 		curs, mes, import, data i hora, numero de comanda i el codi de resposta
	*/
	public function getTemplate_Dades_RequadreDadesComandaNoPagat() {
		include ('templates/dades/pagaments/dadesComandaNoPagament.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text requadre amb dades de pagament de l'alumne i curs
	* @return string Missatge del text requadre amb dades de pagament de la comanda realitzada:
	* 		curs, mes, import, data i hora, numero de comanda i el codi de resposta
	*/
	public function getTemplate_Dades_RequadreDadesComanda($frac, $pendentPagar) {
		include ('templates/dades/pagaments/dadesComanda.php');
		return $missatge;
	}

	/* ########################################################################## */
	/* #######################            WEB            ####################### */
	/* #######################        INSCRIPCIONS       ####################### */
	/* ########################################################################## */

	/* #######################    INSCRIPCIÓ LLARGA    ######################### */

	/**
	* @brief Missatge del text d'enviament d'inscripció
	* @param $pagFrac pagament fraccionat
	* @param $tipusCurs tipus del curs
	* @return string del text d'enviament d'inscripció
	*/
	public function getTemplate_Inscripcions_EnviamentInscripcio( $pagFrac, $tipusCurs,
	$tipusDescompte, $dataResol, $datai, $mailing, $titolDocencia, $textTitulacioEstudiant ) {
		$mostrarPagament = 1;
		include ('templates/dades/dadesNecesariesInscripcio.php');
		include ('templates/inscripcions/enviamentInscripcio.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text d'enviament d'inscripció
	* @param $tipusCurs tipus del curs
	* @param $codiCurs codi curs del curs
	* @return string del text d'enviament d'inscripció
	*/
	public function getTemplate_Inscripcions_EnviamentBescanvia( $tipusDescompte,
	$dataResol, $datai, $mailing, $titolDocencia, $textTitulacioEstudiant ) {
		$mostrarPagament = 0;
		include ('templates/dades/dadesNecesariesInscripcio.php');
		include ('templates/inscripcions/enviamentBescanvia.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text d'enviament d'inscripció de pack
	* @param $pagFrac pagament fraccionat
	* @param $tipusCurs tipus del curs
	* @param $codiCurs codi curs del curs
	* @return string del text d'enviament d'inscripcióó de pack
	*/
	public function getTemplate_Inscripcions_EnviamentPack( $pagFrac,
	$titolDocencia, $esAlumne, $datai, $mailing, $textTitulacioEstudiant ) {
		$mostrarPagament = 1;
		include ('templates/dades/dadesNecesariesPack.php');
		include ('templates/inscripcions/enviamentPack.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text d'enviament d'inscripció USOC
	* @return string del text d'enviament d'inscripció USOC
	*/
	public function getTemplate_Inscripcions_EnviamentUSOC() {
		$mostrarPagament = 1;
		include ('templates/inscripcions/enviamentUSOC.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text d'enviament d'inscripció REGAL
	* @return string del text d'enviament d'inscripció REGAL
	*/
	public function getTemplate_Inscripcions_EnviamentRegal($codiCurs, $percentatgeBD) {
		$mostrarPagament = 1;
		include ('templates/dades/dadesNecesariesRegal.php');
		include ('templates/inscripcions/enviamentRegal.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text d'enviament d'inscripció DESCOMPTE GRUP
	* @return string del text d'enviament d'inscripció DESCOMPTE GRUP
	*/
	public function getTemplate_Inscripcions_EnviamentContacteDescompteGrup(
		$dataResol, $titolDocencia, $mailing) {
		$mostrarPagament = 1;
		include ('templates/dades/dadesNecesariesContacteDescompteGrup.php');
		include ('templates/inscripcions/enviamentContacteDescompteGrup.php');
		return $missatge;
	}
	/**
	* @brief Missatge del text d'enviament d'inscripció DESCOMPTE GRUP
	* @return string del text d'enviament d'inscripció DESCOMPTE GRUP
	*/
	public function getTemplate_Inscripcions_EnviamentAlumnesDescompteGrup(
		$alumnePrisma, $dataResol, $titolDocencia, $titulacioAlumne) {
		$mostrarPagament = 1;
		include ('templates/dades/dadesNecesariesAlumnesDescompteGrup.php');
		include ('templates/inscripcions/enviamentAlumnesDescompteGrup.php');
		return $missatge;
	}

	/* #######################    INSCRIPCIÓ CURT    ######################### */

	/**
	* @brief Missatge curt del text d'enviament d'inscripció
	* @param $tipusCurs tipus del curs
	* @param $codiCurs codi curs del curs
	* @param $promocioATrobadaplicada tipus del curs
	* @param $promocioAplicada tipus del curs
	* @param $mailing tipus de registre de mailing de l'alumne
	* @param $tipusDescompte tipus de descompte de l'alumne
	* @param $pagFrac pagament fraccionat
	* @return string del text curt d'enviament d'inscripció
	*/
	public function getTemplate_Inscripcions_EnviamentInscripcioShort($tipusCurs,
	$codiCurs, $promocioATrobadaplicada, $promocioAplicada, $mailing, $tipusDescompte, $pagFrac) {
		include ('templates/inscripcions/enviamentInscripcioCurt.php');
		return $missatge;
	}

	public function getTemplate_Inscripcions_EnviamentPackShort($mailing, $pagFrac) {
		include ('templates/inscripcions/enviamentPackCurt.php');
		return $missatge;
	}

	/**
	* @brief Missatge curt del text d'enviament d'inscripció
	* @param $mailing tipus de registre de mailing de l'alumne
	* @return string del text curt d'enviament d'inscripció
	*/
	public function getTemplate_Inscripcions_EnviamentBescanviaShort($mailing) {
		include ('templates/inscripcions/enviamentInscripcioCurt.php');
		return $missatge;
	}

	/**
	* @brief Missatge curt del text d'enviament d'inscripció
	* @param $mailing tipus de registre de mailing de l'alumne
	* @return string del text curt d'enviament d'inscripció
	*/
	public function getTemplate_Inscripcions_EnviamenUSOCShort() {
		include ('templates/inscripcions/enviamentUSOCCurt.php');
		return $missatge;
	}

	public function getTemplate_Inscripcions_EnviamenRegalShort($codiCurs, $dedicatoria, $comentaris) {
		include ('templates/inscripcions/enviamentRegalCurt.php');
		return $missatge;
	}

	/* #######################   INSCRIPCIÓ - PARTS   ######################### */

	/**
	* @brief Missatge del text d'enviament d'inscripció amb descompte
	* @param $novell Indica si es novell
	* @return string de la inscripció amb descompte.
	* 			Si és novell s'afegirà el text de pendent de validar el titol
	*/
	public function getTemplate_Inscripcions_EnviamentInscripcioAmbDescompte($novell) {
		include ('templates/inscripcions/enviamentInscripcioAmbDescompte.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text d'enviament de l'usoc
	* @return string de la inscripció per l'usoc
	*/
	public function getTemplate_Inscripcions_USOC_EnviamentInscripcioAmbDescompte() {
		include ('templates/inscripcions/usoc/enviamentInscripcioAmbDescompte.php');
		return $missatge;
	}


	/**
	* @brief Missatge de la part del missatge de dades de alumnes del grup
	* @return string del text part del missatge de dades de alumnes del grup
	*/
	public function getTemplate_Inscripcions_Centres_MissatgeDadesUnAlumne() {
		include ('templates/dades/dadesAlumneCentre.php');
		return $missatge;
	}

	/**
	* @brief Missatge de la part del missatge de dades de alumnes del grup
	* @return string del text part del missatge de dades de alumnes del grup
	*/
	public function getTemplate_Inscripcions_Centres_MissatgeDadesAlumnes() {
		include ('templates/dades/dadesAlumnesCentre.php');
		return $missatge;
	}

	/**
	* @brief Missatge de la part del missatge de dades de curs del grup
	* @return string del text part del missatge de dades de curs del grup
	*/
	public function getTemplate_Inscripcions_Centres_MissatgeDadesCurs() {
		include ('templates/dades/dadesCursCentre.php');
		return $missatge;
	}

	/**
	* @brief Missatge de la part del missatge de dades de contacte del grup
	* @return string del text part del missatge de dades de contacte del grup
	*/
	public function getTemplate_Inscripcions_Centres_MissatgeDadesContacte( $tipusInsc ) {
		include ('templates/dades/dadesContacteCentre.php');
		return $missatge;
	}

	/* ########################################################################## */
	/* #######################            WEB             ####################### */
	/* #######################   Missatges de PAGAMENTS   ####################### */
	/* ########################################################################## */

	/**
	* @brief Missatge del text de maneres de pagar de la inscripció
	* @return string del text de maneres de pagar de la inscripció. Part de les maneres
	*/
	public function getTemplate_Inscripcions_Pagaments_MissatgeTextManeresPagar() {
		include ('templates/dades/pagaments/missatgeTextManeresPagar.php');
		return $missatge;
	}
	public function getTemplate_Inscripcions_Pagaments_MissatgeTextManeresPagar2() {
		include ('templates/dades/pagaments/missatgeTextManeresPagar2.php');
		return $missatge;
	}

	/* ########################################################################## */
	/* #######################            WEB            ####################### */
	/* #######################          VISTES          ####################### */
	/* ########################################################################## */

	/**
	* @brief Missatge del text del pagament amb targeta per la web
	* @param $vista Vista a veure
	* @param $type Tipus de curs que tenim
	* @param $potsFracc Pots fraccionar
	* @param $potsFracc La decisió de fraccionar
	* @return string del text del pagament amb transferències per la web.
	* $tipus Si $vista es 1, llavors mostra l'apartat segons la pagina de pagament
	* Si $vista és 2, llavors mostra l'apartat segons la pagina de la confirmació de pagament
	* Si $type == 'R', llavors mostra el mostra el concepte "<span class='codiRegal'>[CODI]</span>"
	* @return string del text del pagament amb targeta per la web
	*/
	public function getTemplate_Web_Pagaments_PagamentAmbTargeta( $vista, $type, $potsFracc, $fracc ) {
		include ('templates/dades/pagaments/textManeresPagarVersioWebTargeta.php');
		return $missatge;
	}

	/**
	* @brief Missatge del text del pagament amb transferències per la web
	* @param $vista Vista a veure
	* @param $type Tipus de curs que tenim
	* @return string del text del pagament amb transferències per la web.
	* $tipus Si $vista es 1, llavors mostra l'apartat segons la pagina de pagament
	* Si $vista és 2, llavors mostra l'apartat segons la pagina de la confirmació de pagament
	* Si $type == 'R', llavors mostra el mostra el concepte "<span class='codiRegal'>[CODI]</span>"
	*/
	public function getTemplate_Web_Pagaments_PagamentAmbTransferencies( $vista, $type ) {
		include ('templates/dades/pagaments/textManeresPagarVersioWebTransferencia.php');
		return $missatge;
	}

	/**
	* @brief Missatge del vista pàgina not found del pagament
	* @return string del vista pàgina not found del pagament:
	*	<img src='https://www.prisma.cat/img/error-pagament.png' alt='Error en el pagament amb targeta' class='mt-3'/>
	*	<h2>Pagament no disponible</h1>
	*	<p class='mb-4'>Hi ha hagut un error. Contacta amb nosaltres al telèfon 972 21 75 65
	*	o a <span class='font-weight-bold email'>secretaria@prisma.cat</span>. </p>
	*/
	public function getTemplate_Pagament_VistaError() {
		include ('templates/vistes/paginaPagamentError.php');
		return $missatge;
	}

	/**
	* @brief Missatge del vista pàgina capcalera del pagament
	* @return string del vista pàgina capcalera del pagament:
	*	<h1 class='mb-4'>[TITOL_PAGE]</h1>
	*	<p>La teva sol·licitud ha estat enviada. Consulta la safata d'entrada o el correu
	*		brossa (<em>spam</em>) de l'adreça <span class='font-weight-bold email'>[EMAIL]</span>
	*		per comprovar que has rebut [REBUT_CONF].</p>
	*	<p>L'import a pagar és de <span class='font-weight-bold'>[PAY]</span> euros.</p>
	*/
	public function getTemplate_Pagament_VistaPagHeader( $mostrarImport = 1 ) {
		include ('templates/vistes/paginaPagamentVistaHeader.php');
		return $missatge;
	}

	/**
	* @brief Missatge del vista pàgina footer del pagament
	* @return string del vista pàgina footer del pagament: "
	*	Si la inscripció no s'ha realitzat correctament, contacta amb
	*  nosaltres al telèfon 972 21 75 65 o a través del correu electrònic
	*  <span class='font-weight-bold email'>secretaria@prisma.cat</span>.</p>
	*  <p>Gràcies per confiar en PrisMa."
	*/
	public function getTemplate_Pagament_VistaPagFooter() {
		include ('templates/vistes/paginaPagamentVistaFooter.php');
		return $missatge;
	}

	/**
	* @brief Missatge del vista pàgina REGAL del pagament
	* @return string Missatge del vista pàgina REGAL del pagament
	*/
	public function getTemplate_Pagament_VistaRegal() {
		$mostrarPagament = 1;
		include ('templates/vistes/paginaPagaments/regal.php');
		return $missatge;
	}

	/**
	* @brief Missatge del vista pàgina GRUP del pagament
	* @param $numeroCursosDiff Numero de cursos diferents
	* @param $potsFracc La decisió de fraccionar
	* @return string Missatge del vista pàgina GRUP del pagament
	*/
	public function getTemplate_Pagament_VistaGrup($numeroCursosDiff, $fracc, $preuPagat) {
		$mostrarPagament = 1; $potsFracc = 1;
		include ('templates/vistes/paginaPagaments/grup.php');
		return $missatge;
	}

	/**
	* @brief Missatge del vista pàgina PACK del pagament
	* @param $numeroCursosDiff  Numero de cursos diferents
	* @param $potsFracc La decisió de fraccionar
	* @return string Missatge del vista pàgina PACK del pagament
	*/
	public function getTemplate_Pagament_VistaPack($numeroCursosDiff, $fracc) {
		$mostrarPagament = 1; $potsFracc = 1;
		include ('templates/vistes/paginaPagaments/pack.php');
		return $missatge;
	}

	/**
	* @brief Missatge del vista pàgina NORMAL del pagament
	* @param $potsFracc La decisió de fraccionar
	* @return string Missatge del vista pàgina NORMAL del pagament
	*/
	public function getTemplate_Pagament_VistaCurs($fracc) {
		$mostrarPagament = 1; $potsFracc = 1;
		include ('templates/vistes/paginaPagaments/curs.php');
		return $missatge;
	}

	/* #######################          HTMLS          ####################### */

	/**
	* @brief vista formulari del fraccionat
	* @return string vista formulari del fraccionat
	*/
	public function getTemplate_Web_Formulari_Fraccionat() {
		$id = 'fraccionat';
		include ('templates/vistes/inputText.php'); // $input
		include ('templates/vistes/paginaPagaments/html/inputFraccionat.php');
		return $missatge;
	}

	/**
	* @brief vista formulari del dni
	* @return string vista formulari del dni
	*/
	public function getTemplate_Web_Formulari_Dni() {
		include ('templates/vistes/inputText.php'); // $input
		include ('templates/vistes/paginaPagaments/html/inputDNI.php');
		return $missatge;
	}

	/**
	* @brief vista formulari del nom
	* @return string vista formulari del nom
	*/
	public function getTemplate_Web_Formulari_Nom() {
		include ('templates/vistes/inputText.php'); // $input
		include ('templates/vistes/paginaPagaments/html/inputNom.php');
		return $missatge;
	}

}

?>
