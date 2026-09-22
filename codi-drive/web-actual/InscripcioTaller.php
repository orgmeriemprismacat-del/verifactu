<?php
/**
* @class InscripcioTaller
* @brief Conté tota la informació relacionada amb una InscripcioPack.
*/
class InscripcioTaller{
   private $codiTaller; /** Text IdPack d'una Inscripcio ex: ACRE */
   private $titol; /** Text El titol de la jorna. ex. Alumnat amb Altes Capacitats */
   private $url; /** URL L'enllaç de la Inscripcio. Si no n'hi ha, valdrà null  */
   private $dispositiu; /** string Mobil si el dispositiu és mobil i altrament, ordinador */
   private $estat; /** string Mobil si el dispositiu és mobil i ordindador si el dispositiu és mobil  */
   private $hores; /** Numero Les hores de la Inscripcio ex: 40 */

   private $dataI; /** Text Codi d'una Inscripcio ex: ACRE */


   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/

   public function __construct($idUrl, $dispositiu) {
      $this->dispositiu = $dispositiu;
      $this->url = new Url($idUrl);
      $this->estat=1;

      $this->cons = [
         "cnsInfo"            => "SELECT CODI_CURS, TITOL
                                  FROM informacio WHERE ID_AMIGABLE=? AND ESTAT=1 AND TIPUS_CURS = 'T'",
         "cnsInfoTaller"      => "SELECT HORES, ID_PREU, DATAI
                                  FROM info_taller WHERE CODI_CURS = ? AND ESTAT = 1 AND DATAI >= CURRENT_DATE"
      ];

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();
      if ( $stm = $connexio->prepare( $this->cons["cnsInfo"] ) ) {
         $stm->bind_param("d", $idUrl);
         $stm->execute();
         $stm->store_result();
         if ( $stm->num_rows() <= 0 ) {
            $this->estat=0;
            throw new Exception('',3301);
         }
         else {
            $stm->bind_result($codiTaller, $titol);
            $stm->fetch();
         }
         $connexio->closeStmt();
      }
      else {
         throw new Exception('',3303);
      }

      if ( $stm = $connexio->prepare( $this->cons["cnsInfoTaller"] ) ) {
         $stm->bind_param("s", $codiTaller);
         $stm->execute();
         $stm->store_result();
         if ( $stm->num_rows() <= 0 ) {
            $this->estat=0;
             throw new Exception('',3302);
         }
         else {
            $stm->bind_result($hores, $idPreu, $dataI );
            $stm->fetch();
         }
         $connexio->closeStmt();
      }
      else {
         throw new Exception('',3304);
      }

      require_once 'Text.php';
      require_once 'Date.php';

      if ($codiTaller!=null AND $codiTaller!='')
         $this->codiTaller = new Text($codiTaller);
      else
         $this->codiTaller = null;

      if ($titol!=null AND $titol!='')
         $this->titol = new Text($titol);
      else
         $this->titol = null;

      $this->dataI = null;
      $this->dataI = $dataI;
      $this->hores = $hores;
      $this->idPreu = $idPreu;

      $connexio->desconectarBD();
   }

   /* ########################### FUNCIONS CONSULTAR ########################### */

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció «2902»
   */
   private function obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',2902);
      return $this->titol;
   }

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció «2902»
   */
   private function obtenirData() {
      if ($this->dataI==null)
         throw new Exception('',2902);
      return $this->dataI;
   }

   /* ########################### FUNCIONS VISTES ########################### */

   /*
   * @brief Mostra la pàgina d'inscripció d'un curs
   * @return Retorna el contingut de la pàgina d'inscripció d'un curs
   */
   public function mostrar() {
      $edicions = "";

      // $connexio = new ConnexioBBDDSTMT();
      // $connexio->connectarBD();
      //
      // $cnsInfoOrig = "SELECT DATAI, DATAF, CURS_ESCOLAR, FISS, GTAF, DATA_RESOL,
      //    ANY, MES, c.CURS, p.ID_CURS, HORES, i.ID, i.ID_AMIGABLE, c.ESTAT
      //    FROM packs AS p INNER JOIN info_pack AS ip ON p.ID_PACK = ip.ID_PACK INNER
      //    JOIN curs AS c ON p.ID_CURS = c.ID_CURS INNER JOIN informacio AS i ON
      //    c.CURS = i.CODI_CURS INNER JOIN aula AS a ON c.ID_AULA=a.ID_AULA INNER JOIN
      //    rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO INNER JOIN honoraris AS h ON r.ID_HONO=h.ID
      //    WHERE ip.ID_PACK = ? AND p.PUBLIC=1 AND c.PUBLIC=1 AND c.CURS!='PROVA' AND
      //    c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1 AND i.ESTAT = 1
      //    AND c.CURS NOT LIKE '%JOR%' AND (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC') OR
      //    (a.ID_CUHO!=17 AND h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor' AND
      //    ORDRE_TUTOR is not NULL)) ORDER BY c.DATAI";
      // if ( $stmt = $connexio->prepare($cnsInfoOrig) ) {
      //    $stmt->bind_param("d", $codiTaller);
      //    $codiTaller = $this->codiTaller->obtenirText();
      //    $stmt->execute();
      //    require_once 'EdicioPack.php';
      //    $stmt->bind_result($dataiEd, $datafEd, $cursEscEd, $fissEd, $gtafEd,
      //       $dataResEd, $anyEd, $mesEd, $cursEd, $idCursEd, $horesEd, $idInfoEd, $idUrl, $estatEd);
      //    $i = 0;
      //    while ( $stmt->fetch() ) {
      //       $edicio = new EdicioPack($cursEd, $idUrl, $this->dispositiu, $anyEd, $mesEd, $horesEd, $dataiEd, $datafEd, $cursEscEd, $gtafEd, $dataResEd, $fissEd, $estatEd);
      //       $edicio->setInfo();
      //       $edicions .= "<li>".$edicio->mostrarEdicioInfo()."</li>";
      //    }
      //    $connexio->closeStmt();
      // }
      // else {
      //    throw new Exception('',2909);
      // }
      // $connexio->desconectarBD();
      //
      $objText = new Text("Taller");
      $tipus = $objText->obtenirTextHTML();

      $objText = new Text("Data");
      $textData = $objText->obtenirTextHTML();

      $titol = $this->obtenirTitol()->obtenirTextHTML();
      $data = new Date($this->obtenirData());
      $textdata = $data->getDataLlarga();

      $mostrar="<div class='d-flex flex-column'>
      <h1 class='mb-4'>Formulari d'inscripci&oacute</h1>
      <h2 class='pt-3 mt-0'>".$tipus.": <span class='nom-curs'>".$titol."</span></h2>
      <h2 class='pt-0 mt-0'>".$textData.": <span class='nom-curs'>".$textdata."</span></h2>";
      $mostrar .= $this->__mostrarDadesPersonals();
      $mostrar .= $this->__mostrarDadesCurriculars();
      $mostrar .= $this->__mostrarDadesCurs();
      $mostrar .= $this->__mostrarFinal();
      $mostrar .= $this->modalError();
      $mostrar .= $this->modalSuccess();
      $mostrar .= $this->modalInscripcioDuplicada();
      $mostrar .= "</div>";
      $mostrar .= $this->modalLoading();
      $mostrar .= $this->modalEnviant();
      $mostrar .= $this->modalInfoPreu();
      return $mostrar;
   }

   /**
   * @brief Mostra l'HTML d'un contenidor per el select
	* que té la id $idSelect, la classe $classSelect, amb l'opció per defecte
	* $valorSelect i amb un vector d'elements per crear el select $vectCrearLlistat.
   * @return Mostra l'HTML d'un select en un contenidor amb el $idSelect
	* és l'id del select, el $classSelect és una classe afegida a l'input, el $valorSelect
	* és el valor per defecte del select i $llistatSelect és el valor del llistat del Select
	* $vectCrearLlistat és un vector d'elements per crear la continuació del select.
   */
	public function mostrarSelect($idSelect, $valorSelect, $llistatSelect, $idLlistat, $idError) {
      $textIdLlistat = '';
      if ( $idLlistat != '' )
         $textIdLlistat = " id = ".$idLlistat;

      $mostrar = "<div class='form-group field-wrap position-relative p-1 w-100'>
			<div id='".$idSelect."' class='select d-flex flex-column justify-content-center position-relative m-0'>
				<span class='element-selected font-weight-normal w-100'>".$valorSelect."<span class='req ml-1'>*</span></span>
				<ul".$textIdLlistat." class='select-list position-absolute ps' style='display: none;'>
					".$llistatSelect."
				</ul>
				<i class='fa triangle-inferior fa-angle-down position-absolute'></i>
			</div>
         <span id='".$idError."' class='select_erroni'></span>
		</div>";
		return $mostrar;
	}

   /*
      * @brief Mostra un input del formulari de contacte de PrisMa
      * @return El formulari de contacte de PrisMa llest per omplir
   */
   public function mostrarInput($idInput, $nomInput, $typeInput, $idError, $required) {
      $textRequired = "";
      $pattern = "";
      $length = "";
      $llistat = "";

      if ( $required == "1" )
         $textRequired = " aria-required required";

      if ( $typeInput == "tel" )
         $pattern = " pattern='[6-9]{1}[0-9]{8}'";

      if ( $idInput == "cp" ) {
         $length = " maxlength='50'";
         $idDiv = " id='cp_box'";
      }
      else if ( $idInput == "poble" ) {
         $llistat = " <ul id='llistat_poblacions' style='display: none'
         class='select-list position-absolute' role='listbox'></ul>";
         $idDiv = " id='poble_box'";
      }

      $mostrar .= "<div class='form-group field-wrap position-relative'".$idDiv.">
      <label for='".$idInput."'><span class='camp'>".$nomInput."</span><span class='req ml-1'>*</span></label>
      <input type='".$typeInput."' class='form-control' id='".$idInput."' name='".$idInput."'".$length.$pattern.$textRequired.">
      <span id='".$idError."'></span>".$llistat."</div>";

      return $mostrar;
   }

   /*
   * @brief Mostra l'apartat de dades personals de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades personals de la pàgina d'inscripció d'un curs
   */
   private function __mostrarDadesPersonals() {
      $mostrar = "<div class='form-dades'>
         <h3>Dades personals</h3>
         <div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("nom", "Nom", "text", "nom_cognom_erroni", "1")."</div>
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("cog", "Cognoms", "text", "cognom_erroni", "1")."</div>
         </div>
         <div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
            <div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
               <div class='form-group field-wrap position-relative'>
                  <div id='doc' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
                  <span class='element-selected font-weight-normal w-100'>NIF/NIE</span>
                  <ul class='select-list position-absolute ' style='display: none;'>
                  <li class='border-bottom m-0' id='doc-dni'><a href='#'>NIF/NIE</a></li>
                  <li class='border-bottom m-0' id='doc-passaport'><a href='#'>Altres</a></li>
                  </ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i></div>
               </div>
            </div>
            <div class='col-12 col-md-3 pl-0 pr-0 pr-md-2' id='input_doc'>".$this->mostrarInput("nif", "DNI amb lletra", "text", "dni_erroni", "1")."</div>
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("telf", "Telèfon de contacte", "tel", "telf_erroni", "1")."</div>
         </div>".$this->modalCorreuValid()."
         <div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("email", "Correu electrònic", "email", "correu_erroni", "1")."</div>
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("email_conf", "Confirmació del correu electrònic", "email", "correu_conf_erroni", "1")."</div>
         </div>
         <div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
            <div class='col-12 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("adreca", "Adreça (carrer, número...)", "text", "adreca_erroni", "1")."</div>
        </div>
        <div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
           <div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("cp", "Codi postal", "text", "cp_erroni", "1")."</div>
           <div class='col-12 col-md-9 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("poble", "Població", "text", "poble_erroni", "1")."</div>
        </div>
      </div>";

      return $mostrar;
   }

   /*
   * @brief Mostra l'apartat de dades curriculars de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades curriculars de la pàgina d'inscripció d'un curs
   */
   private function __mostrarDadesCurriculars() {
      $llistatPerfils = "<li class='border-bottom m-0' id='perfil-edInfantil03'><a href='#'>Ed. Infantil (0-3)</a></li>
      <li class='border-bottom m-0' id='perfil-edInfantil35'><a href='#'>Ed. Infantil (3-5)</a></li>
      <li class='border-bottom m-0' id='perfil-edPrimaria'><a href='#'>Ed. Primària</a></li>
      <li class='border-bottom m-0' id='perfil-edEspecial'><a href='#'>Ed. Especial</a></li>
      <li class='border-bottom m-0' id='perfil-edSecundaria'><a href='#'>Ed. Secundària (Cicles Formatius, ESO, Batxillerat)</a></li>
      <li class='border-bottom m-0' id='perfil-consultaPrivada'><a href='#'>Consulta privada</a></li>
      <li class='border-bottom m-0' id='perfil-noEsticTreballant'><a href='#'>No estic treballant</a></li>
      <li class='border-bottom m-0' id='perfil-altres'><a href='#'>Altres</a></li>";

      $llistatTitulacio = "<li class='border-bottom m-0' id='titulacio-TEI'><a href='#'>TEI</a></li>
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
      <li class='border-bottom m-0' id='titulacio-estudiant' class='estudiant'><a href='#'>Encara no tinc cap titulaci&oacute, s&oacutec estudiant de</a></li>";

      $mostrar = "<div class='form-dades'>
         <h3>Dades curriculars</h3>
         <div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
               ".$this->mostrarSelect('perfil', 'Estic treballant a', $llistatPerfils,
               '', 'perfil_erroni')."
            </div>
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
               ".$this->mostrarSelect('titulacio', 'Tinc la titulaci&oacute de',
                $llistatTitulacio, '', 'titulacio_erroni')."
            </div>
         </div>
         <div class='' id='perfils-altres'></div>
         <div class='' id='titulacions-altres'></div>
         <div class='' id='titulacions-secundaria'></div>
         <div class='' id='titulacions-estudiant'></div>
         <div class='d-flex flex-row algin-items-center justify-content-center'>
            <div class='col-12 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("titol_de",
            "També tinc la titulaci&oacute de", "text", "", "0")."</div>
         </div>
      </div>";
      return $mostrar;
   }

   /*
   * @brief Mostra l'apartat de dades del curs de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades del curs de la pàgina d'inscripció d'un curs
   */
   private function __mostrarDadesCurs() {
      $textPreu = "<h3>Dades del curs</h3><div id='preu'><p>Preu</p></div>";
      $fracc = "<div class='form-group field-wrap position-relative'>
         <input type='checkbox' name='fraccionat' id='pagament_fraccionat' class='mr-1'
         value='Pagament fraccionat'><font id='text_carnet_jove'>
         Vull fraccionar el pagament (sense recàrrec)</font>
      </div>";
      $msgInfo = "<div id='missInformatiuEdicioRec'></div>
      <div id='missInformatiuEdicioPerf'></div>
      <div id='cnt-codis-promocionals'></div>
      <div id='txtHint_conegut'>";
      $comConec = "<div class='d-flex flex-row algin-items-center justify-content-center'>
         <div class='col-12 pl-0 pr-0 pr-md-2'>
            ".$this->mostrarSelect('comConegut', "Com has conegut aquest curs? Tria una opció", '', 'listComConegut', 'conegut_erroni')."
         </div>
      </div>
      <div class='' id='comHasConegut_altres'></div>";
      $mailing = "<div class='' id='txtHint_mailing'></div>";

      // $mostrar = "<div class='form-dades'>".$textPreu.$fracc.$msgInfo.$comConec.$mailing."</div>";
      $mostrar = "<div class='form-dades'>".$textPreu.$msgInfo.$comConec.$mailing."</div>";
      return $mostrar;
   }

   /*
   * @brief Mostra l'apartat de dades finals de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades finals de la pàgina d'inscripció d'un curs
   */
   private function __mostrarFinal() {
      $mostrar = "<div class='form-dades'>
      <h3>Tens algun comentari?</h3>
      <div class='d-flex flex-column algin-items-center justify-content-center'>
         <div class='form-group field-wrap position-relative'>
            <label for='comentaris'><span class='camp'>Comentaris</span></label>
            <textarea class='form-control' id='comentaris' name='comentaris'></textarea>
         </div>
         <div class='d-flex cnt_enviar_dades border-0 justify-content-center'>
            <button id='form_enviar_dades' class='boto-blau position-relative text-white border-0 border-radius-2 px-4 py-2 my-2'>Enviar dades</button>
         </div>
      </div>
      </div>";
      return $mostrar;
   }

   /*
   * @brief Mostra un modal d'error
   * @return Retorna un modal d'error
   */
   public function modalError() {
      $textFooter = "<a role='button' class='btn btn-danger waves-effect waves-light' aria-label='Close' data-dismiss='modal'>Tanca</a>";

      $mostrar = $this->__modal('modalErrors', 'modal-danger', 'modalErrorsTitle',
         'Errors', 'modalErrorsBody', '', $textFooter);

   	return $mostrar;
   }

   /*
   * @brief Mostra un modal que la inscripció ha estat realitzada correctament
   * @return Retorna un modal que la inscripció ha estat realitzada correctament
   */
   public function modalSuccess() {
      $textFooter = "<a role='button' class='btn btn-success waves-effect waves-light' id='close-sucess'>Tanca</a>";

      $mostrar = $this->__modal('modalSuccess', 'modal-success', 'modalSuccessTitle',
         'Inscripció realitzada', 'modalSuccessBody', '', $textFooter);

   	return $mostrar;
   }

   /*
   * @brief Mostra un modal d'avis
   * @return Retorna un modal d'avis
   */
   public function modalCorreuValid() {
      $textFooter = "<button role='button' data-dismiss='modal' class='btn btn-warning border-0 border-radius-2 text-center negreta500 m-0 mr-3'>D'acord</button>";

      $mostrar = $this->__modal('modalCorreuValid', 'modal-warning', 'modalCorreuValidTitle',
         'Avís', 'modalCorreuValidBody', '', $textFooter);
   	return $mostrar;
   }

   /*
   * @brief Mostra un modal de confirmació d'inscripció
   * @return Retorna un modal de confirmació d'inscripció
   */
   public function modalInscripcioDuplicada() {
      $textFooter = "
      <button role='button' data-dismiss='modal' class='boto-blau-disable border-0 border-radius-2 text-center negreta500 m-0 mr-3'>Cancel·la</button>
      <button role='button' id='confirmInsc' class='boto-blau border-0 border-radius-2 text-center negreta500 text-white m-0'>Vull inscriure'm</button>";

      $mostrar = $this->__modal('modalInscripcioDuplicada', 'modal-prisma justify-content-center text-center', 'modalInscripcioDuplicadaTitle',
         'CONFIRMA LA INSCRIPCIÓ', 'modalInscripcioDuplicadaBody', '', $textFooter);
   	return $mostrar;
   }

   /*
   * @brief Mostra un modal de càlcul del preu
   * @return Retorna un modal càlcul del preu
   */
   public function modalInfoPreu() {
      $mostrar = $this->__modal('modalInfoPreu', 'modal-prisma justify-content-center text-center', 'modalTitleInfoPreu', 'CÀLCUL DEL PREU', 'modalBodyInfoPreu', '', '');
   	return $mostrar;
   }

   /*
   * @brief Mostra un modal
   * @return Retorna un modal amb l'identificador $id, com a títol del modal $textTitol
   * i el corresponent identificador $idTitol, com a text del modal $textBody
   * i el corresponent identificador $idBody i com a text del footer $textFooter
   */
   private function __modal( $id, $classModal, $idTitol, $textTitol, $idBody, $textBody, $textFooter) {
      if ( $textFooter == '' ) {
         $textFooter = "<button role='button' data-dismiss='modal' class='boto-blau
            border-0 border-radius-2 text-center negreta500 text-white m-0'>
            Tanca</button>";
      }
      $mostrar .= "
      <div class='modal fade in' id='".$id."' tabindex='-1' role='dialog' aria-labelledby='".$id."' aria-hidden='true'>
         <div class='modal-dialog modal-dialog-centered modal-notify ".$classModal."' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-header border-0 text-white'>
                  <p class='modal-title m-0 text-center' id='".$idTitol."'>".$textTitol."</p>
                  <button role='button' class='close text-white' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>×</span></button>
               </div>
               <div class='modal-body' id='".$idBody."'>".$textBody."</div>
               <div class='modal-footer justify-content-center text-center border-0 pt-0 mb-2'>".$textFooter."</div>
            </div>
         </div>
      </div>";
   	return $mostrar;
   }

   /*
   * @brief Mostra un modal de càrrega "buscant"
   * @return Retorna un modal de càrrega "buscant"
   */
   public function modalLoading() {
      $mostrar = $this->__modalCarregant( 'modalLoading', 'Buscant...' );
   	return $mostrar;
   }

   /*
   * @brief Mostra un modal de càrrega "enviant"
   * @return Retorna un modal de càrrega "enviant"
   */
   public function modalEnviant() {
      $mostrar = $this->__modalCarregant( 'modalSending', 'Enviant...' );
   	return $mostrar;
   }

   /*
   * @brief Mostra un modal de càrrega amb l'identificador $id i el text $text
   * @return Mostra un modal de càrrega amb l'identificador $id i el text $text
   */
   private function __modalCarregant( $id, $text ) {
      $mostrar = "<div class='modal' id='".$id."' tabindex='-1' role='dialog'
      aria-labelledby='".$id."' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div id='loading-wrapper'>
                     <div id='loading-text'>".$text."</div>
                     <div id='loading-content'></div>
                  </div>
               </div>
            </div>
         </div>
    	</div>";

   	return $mostrar;
   }

}
?>
