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
			"[PAY_ALUMNE]" 				=> "Pagament de l'alumne del curs",
			"[METHOD_PAY]" 				=> "Mètode del pagament de l'alumne del curs",
			"[NOM_RESPONSABLE]"   		=> "Nom del responable de l'entitat/grup",
			"[URL_PAGAMENT]"      		=> "L'enllaç de pagament",
			"[TEXT_MES_EDICIO]"   		=> "El text que correspondria al mes de l'edició indicada. Ex: maig",
			"[DATA_SESSIO1]"   			=> "La data en format llarg de la sessió 1 ",
			"[HORA_SESSIO1]"   			=> "La hora en format llarg de la sessió 1 ",
			"[DIASETMANA_DATAI]"   		=> "El dia de la setmana de la data d'inici",
			"[EDICIO_MES]"   				=> "El mes amb text llarg. Per ex. maig",
			"[NOM_CURS_AULA_OBERTA]" 	=> "El nom de l'aula oberta",
			"[ID_MDL_AULA_BERTA]" 		=> "El ID del moodle de l'aula oberta",
		];
	}

	/* ########################################################################## */
	/* #######################      RECLAMACIO FINAL      ####################### */
	/* ########################################################################## */

	public function getTemplate_LastClaimPayNoApproveAlumnPayNothing() {
		$missatge = "<p>Benvolgut/da [NOM_ALUMNE],</p>

		<p>Fa uns dies ens vam posar en contacte amb tu perquè no havíem rebut
		el pagament corresponent a la teva inscripció al curs
		<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>
		[TITOL]</strong>, que va acabar <strong>[DATAF]</strong>. En no
		haver rebut el pagament dins el termini establert, i atès que no has
		pogut superar el curs, hem hagut de donar-t’hi de baixa.</p>

		<p>Si vols reprendre’l i completar la formació, estarem encantats de
		comptar amb tu. Pots inscriure’t en una nova edició o bé contactar amb nosaltres a
		<a href='mailto:gestio@prisma.cat' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>gestio@prisma.cat</a>
		des del correu electrònic
		<a href='mailto:[CORREU_ALUMNE]' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[CORREU_ALUMNE]</a>,
		i t’ajudarem en el que necessitis.</p>

		<p>Si és la primera vegada que canvies d’edició, aquest canvi es pot
		fer sense cap recàrrec. Si ja has fet un canvi anteriorment, et recordem
		que aquest nou canvi té un cost afegit de [DESPESES_GESTIO] euros en
		concepte de despeses de gestió.</p>

		<p>Si tens qualsevol pregunta, no dubtis a escriure’ns.</p>

		<p>Moltes gràcies per la teva atenció i esperem veure’t aviat!</p>

		<p>Atentament,</p>";
		return $missatge;
	}

	public function getTemplate_LastClaimPayNoApproveAlumnPay() {
		$missatge = "<p>Benvolgut/da [NOM_ALUMNE],</p>

		<p>Fa uns dies ens vam posar en contacte amb tu perquè no havíem rebut
		el pagament corresponent a la teva inscripció al curs
		<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>
		[TITOL]</strong>, que va acabar <strong>[DATAF]</strong>. En no
		haver rebut el pagament dins el termini establert, i atès que no has
		pogut superar el curs, hem hagut de donar-t’hi de baixa.</p>

		<p>No obstant això, et reservem l’import de <strong>[PAGAMENT] euros</strong>
		per a una futura edició del curs.</p>

		<p>Si vols reprendre’l i completar la formació, estarem encantats de
		comptar amb tu. Pots formalitzar el pagament i recuperar l’accés contactant amb nosaltres a
		<a href='mailto:gestio@prisma.cat' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>gestio@prisma.cat</a>
		des del correu electrònic
		<a href='mailto:[CORREU_ALUMNE]' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[CORREU_ALUMNE]</a>,
		o bé inscriure’t directament en una nova edició.</p>

		<p>Si és la primera vegada que canvies d’edició, aquest canvi es pot
		fer sense cap recàrrec. Si ja has fet un canvi anteriorment, et recordem
		que aquest nou canvi té un cost afegit de [DESPESES_GESTIO] euros en
		concepte de despeses de gestió.</p>

		<p>Si tens qualsevol pregunta, no dubtis a escriure’ns. Estarem encantats d’ajudar-te!</p>

		<p>Moltes gràcies per la teva atenció i esperem veure’t aviat.</p>

		<p>Atentament,</p>";
		return $missatge;
	}

	public function getTemplate_LastClaimPayNoApproveTut() {
		$missatge = "<p>Benvolgut/da [NOM_TUTOR],</p>
		<p>Et comuniquem que l’alumne <strong>[NOM_ALUMNE] [COG_ALUMNE]</strong>
		s'ha donat de baixa del curs
		<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[TITOL] - Aula [AULA]</strong>
		que va acabar [DATAF].</p>
		<p>Si tens qualsevol dubte o necessites més informació, ens pots escriure a
		<a href='mailto:gestio@prisma.cat' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>gestio@prisma.cat</a>.</p>
		<p>Moltes gràcies per la teva comprensió i col·laboració.</p>
		<p>Atentament,</p>";

		return $missatge;

	}

	public function getTemplate_LastClaimPayApproveAlumn() {
		$missatge = "<p>Benvolgut/da [NOM_ALUMNE],</p>

		<p>Fa uns dies ens vam posar en contacte amb tu perquè no havíem rebut
		el pagament corresponent a la teva inscripció al curs
		<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>
		[TITOL]</strong>, que va acabar <strong>[DATAF]</strong>
		i hem hagut de suspendre-te’n l’accés temporalment. Tot i això, hem
		comprovat que has superat el curs amb èxit. </p>

		<p>Per poder tramitar el teu certificat, necessitem que completis el
		pagament al més aviat possible. Un cop l’hàgim rebut, et tornarem
		a donar d’alta en el curs, gestionarem el teu certificat, i a més podràs
		sol·licitar l’accés a l’aula oberta, on tindràs accés permanent als recursos del curs.</p>

		<p>Pots fer el pagament a través del enllaç següent:
		<a href='[URL_PAGAMENT]' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[URL_PAGAMENT]</a></p>

		<p>Si tens qualsevol pregunta o necessites ajuda, no dubtis a contactar amb nosaltres a
		<a href='mailto:gestio@prisma.cat' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>gestio@prisma.cat</a>
		des del correu electrònic
		<a href='mailto:[CORREU_ALUMNE]' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[CORREU_ALUMNE]</a>.</p>

		<p>Moltes gràcies per la teva atenció i enhorabona per haver superat el curs!</p>

		<p>Atentament,</p>";

		return $missatge;
	}

	public function getTemplate_LastClaimPayApproveTut() {
		$missatge = "<p>Benvolgut/da [NOM_TUTOR],</p>
		<p>Et comuniquem que hem hagut de suspendre temporalment a l’alumne <strong>[NOM_ALUMNE] [COG_ALUMNE]</strong>
		l’accés al curs
		<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>
		[TITOL] - Aula [AULA]</strong>, que va acabar [DATAF] per no haver-ne completat el pagament.</p>
		<p>No obstant això, com que ha superat el curs, t’assegurem que la quota
		corresponent a aquest alumne estarà inclosa en la teva gestió de cobraments.</p>
		<p>Si tens qualsevol dubte o necessites més informació, ens pots escriure a
		<a href='mailto:gestio@prisma.cat' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>gestio@prisma.cat</a>.</p>
		<p>Moltes gràcies per la teva comprensió i col·laboració.</p>
		<p>Atentament,</p>";

		return $missatge;
	}

	public function getTemplate_LastClaimPay_Responsable() {
		$missatge = "<p>Benvolgut/da [NOM_RESPONSABLE],</p>
		<p>Fa uns dies ens vam posar en contacte amb tu perquè no havíem rebut el pagament de l’alumne
		<strong>[NOM_ALUMNE] [COG_ALUMNE]</strong>
		corresponent a la inscripció del curs
		<strong style='color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>
		[TITOL]</strong>, que va finalitzar [DATAF].</p>
		<p>T’agrairíem que ens ho poguessis regularitzar com més aviat millor.
		Pots fer el pagament a través de l’enllaç següent:
		<a href='[URL_PAGAMENT]' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>[URL_PAGAMENT]</a></p>
		<p>Un cop fet el pagament, et recomanem que conservis el justificant bancari fins que rebis el correu electrònic de confirmació de recepció.</p>
		<p>Si ja has realitzat el pagament recentment, si us plau, ignora aquest missatge.</p>
		<p>Si tens qualsevol dubte o necessites més informació, ens pots escriure a
		<a href='mailto:gestio@prisma.cat' style='font-weight: bold; text-decoration: none;
		color: ".$this->colors_destacats["msgDestacatCorreu1"]."'>gestio@prisma.cat</a> i estarem encantats d'ajudar-te.</p>
		<p>Moltes gràcies per la teva atenció.</p>
		<p>Atentament,</p>";
		return $missatge;
	}

	/* ########################################################################## */
	/* #######################      CONTROL MOROSOS      ####################### */
	/* ########################################################################## */

	/* Template per l'apartat de CONTROL DMOROSOS per lapartat entitats */
	public function getTemplate_RespEntity_EntityClaimPayDefaulter() {
		include ('templates/facturacio/control-morosos/responsable-grup-entitat.php');
		return $missatge;
	}

	/* Template per l'apartat de CONTROL DMOROSOS per l'apartat Alumnes sense certificat */
	public function getTemplate_AlumnNoCertClaimPayDefaulter() {
		include ('templates/facturacio/control-morosos/alumnes-sense-certificat.php');
		return $missatge;
	}

	/* Template per l'apartat de CONTROL DMOROSOS per l'apartat Deutes antics (alumnes amb certificat) */
	public function getTemplate_AlumnCertClaimPayDefaulter() {
		include ('templates/facturacio/control-morosos/deutes-antics.php');
		return $missatge;
	}

	/* ########################################################################## */
	/* #######################         COMUNICATS         ####################### */
	/* ########################################################################## */

	/**
	* @brief comunicat d'obertura d'aules
	* @return comunciat d'obertura d'aules. Si el cas1 = 1, la intro del comunicat canviarà per adaptar-se
	* al cas dels comunicats de 15 hores que tenen una sessió a la primera setmana
	* Si el cas2 = 1, el llistat de materials només posarà la guia general,
	* els enllaços de la mediateca i el fòrum de presentació i espectatives
	*/
	public function getTemplate_Comunicat_OberturaAules( $cas1, $cas2 ) {
		include ('templates/secretaria/comunicats/obertura-aules.php');
		return $missatge;
	}

	/**
	* @brief comunicat de punt de començar
	* @return string comunicat de punt de començar.
	*/
	public function getTemplate_Comunicat_aPuntComencar( $cas1 ) {
		include ('templates/secretaria/comunicats/a-punt-comencar.php');
		return $missatge;
	}

	/**
	* @brief comunicat de servei d'atenció
	* @return string comunicat de servei d'atenció
	*/
	public function getTemplate_Comunicat_ServeiAtencio() {
		include ('templates/secretaria/comunicats/servei-atencio.php');
		return $missatge;
	}

	/**
	* @brief comunicat de les normes d'ús d'eines d'intel·ligència artificial
	* @return string comunicat de les normes d'ús d'eines d'intel·ligència artificial
	*/
	public function getTemplate_Comunicat_IA() {
		include ('templates/secretaria/comunicats/ia.php');
		return $missatge;
	}

	/**
	* @brief comunciat d'aules obertes
	* @return string comunciat d'aules obertes. Si el cas1 = 1,
	* l'apartat del llistat del comunicat canviarà per adaptar-se
	* al cas dels comunicats de 15 hores que tenen presentacions i no lectures.
	*/
	public function getTemplate_Comunicat_AulesObertes( $cas1 ) {
		// sda, ged, sui getTemplate_Comunicat_AulesObertes(1)
		// altres getTemplate_Comunicat_AulesObertes(0)
		include ('templates/secretaria/comunicats/aules-obertes.php');
		return $missatge;
	}

	/**
	* @brief comunicat de certificat
	* @return string comunicat de certificat
	*/
	public function getTemplate_Comunicat_Certificat() {
		include ('templates/secretaria/comunicats/certificat.php');
		return $missatge;
	}

	/**
	* @brief comunicat del tancament de curs
	* @return string comunicat del tancament de curs. Si el cas1 = 1,
	* l'apartat del llistat del comunicat canviarà per adaptar-se
	* al cas dels comunicats de 15 hores que tenen presentacions i no lectures.
	*/
	public function getTemplate_Comunicat_Tancament( $cas1 ) {
		// sda, ged, sui getTemplate_Comunicat_TancamentCurs(1)
		// altres getTemplate_Comunicat_TancamentCurs(0)
		include ('templates/secretaria/comunicats/tancament-curs.php');
		return $missatge;
	}

	/* ########################################################################## */
	/* #######################            WEB            ####################### */
	/* #######################   TEXT MANERES DE PAGAR   ####################### */
	/* ########################################################################## */

	/**
	* @brief Missatge del text de maneres de pagar de la inscripció
	* @return string del text de maneres de pagar de la inscripció
	*/
	public function getTemplate_Inscripcions_Pagaments_MissatgeTextManeresPagar() {
		include ('templates/inscripcions/pagaments/missatgeTextManeresPagar.php');
		return $missatge;
	}

	/* ########################################################################## */
	/* #######################            WEB            ####################### */
	/* #######################       INSCRIPCIONS       ####################### */
	/* ########################################################################## */

	/**
	* @brief Missatge del text d'enviament d'inscripció
	* @param $pagFrac pagament fraccionat
	* @param $tipusCurs tipus del curs
	* @return string del text d'enviament d'inscripció
	*/
	public function getTemplate_Inscripcions_EnviamentInscripcio( $fraccio,
	$tipusCurs,	$tipusDesc, $dataResol, $datai, $mailing, $titolDocencia,
	$titulacio, $verificatDescompte, $verificatNovell, $apagar, $preuCar, $conegut ) {
		$mostrarPagament = 1;
		include ('templates/inscripcions/dades/dadesNecesariesInscripcio.php');
		include ('templates/inscripcions/dades/dadesNecesariesVerificacioDescompte.php');
		include ('templates/inscripcions/enviaments/inscripcio.php');
		return $missatge;
	}
	
	public function getTemplate_Inscripcions_EnviamentNovellUsoc($verificatNovell ) {
		include ('templates/inscripcions/enviaments/novell_usoc.php');
		return $missatge;
	}

	/* ########################################################################## */
	/* #######################         SECRETARIA         ####################### */
	/* #######################       ANUL·LAR CURS       ####################### */
	/* ########################################################################## */

	/**
	* @brief Missatge que s'envia a l'alumne a l'hora d'anular un curs
	* @param $dates Array amb les dates de les properes edicions
	* @param $pagament Els diners que ha pagat l'alumne
	* @return string Missatge que s'envia a l'alumne a l'hora d'anular un curs
	*/
	public function getTemplate_Secretaria_AnularCurs_Alumne($dates, $pagament) {
		include ('templates/secretaria/previ-inici-cursos/anular-curs.php');
		return $missatge;
	}

	public function getTemplate_Secretaria_EnviarCertificat_Alumne() {
		include ('templates/secretaria/enviament-certificat.php');
		return $missatge;
	}

	/* ########################################################################## */
	/* #######################            MRKT            ####################### */
	/* #######################       PBULCIAR POSTS       ####################### */
	/* ########################################################################## */

	public function viewTemplateFrase() {
		include ('templates/mrkt/xarxes/frase.php');
		return $missatge;
	}
	public function viewTemplateInici() {
		include ('templates/mrkt/xarxes/inici-cursos.php');
		return $missatge;
	}
	public function viewTemplateEdicio() {
		include ('templates/mrkt/xarxes/edicio-cursos.php');
		return $missatge;
	}
	public function viewTemplatePublicacio() {
		include ('templates/mrkt/xarxes/publicacio.php');
		return $missatge;
	}

}

?>
