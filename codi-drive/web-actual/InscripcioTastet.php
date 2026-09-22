<?php
/**
* @class InscripcioTastet
* @brief Conté tota la informació relacionada amb una InscripcioTastet.
*/
class InscripcioTastet {
   private $codi; /** Text Codi d'una Inscripcio ex: ACRE */
   private $titol; /** Text El titol de la jorna. ex. Alumnat amb Altes Capacitats */
   private $url; /** URL L'enllaç de la Inscripcio. Si no n'hi ha, valdrà null  */
   private $dispositiu; /** string Mobil si el dispositiu és mobil i altrament, ordinador */
   private $estat; /** string Mobil si el dispositiu és mobil i ordindador si el dispositiu és mobil  */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/

   public function __construct($idUrl, $tipus, $dispositiu) {
      $this->tipus = $tipus;
      $this->estat=1;
      if ($tipus==0) {
         $this->tipus = 0;

         $this->dispositiu = $dispositiu;
         $this->url = new Url($idUrl);

         require_once 'ConnexioBBDD_PreparedStatment.php';
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();
         $cns = "SELECT CODI_CURS, TITOL FROM reptes WHERE ID_URL=? AND ESTAT=1";
         $stm = $connexio->prepare($cns);
         $stm->bind_param("d", $idUrl);
         $stm->execute();
         $stm->store_result();
         if ( $stm->num_rows() <= 0 ) {
            $this->estat=0;
         }
         else {
            $stm->bind_result($codi, $titol);
            $stm->fetch();
         }
         $connexio->closeStmt();

         require_once 'Text.php';
         if ( $codi!=null AND $codi!='' )
            $this->codi = new Text($codi);
         else
            $this->codi = null;

         if ( $titol!=null AND $titol!='' )
            $this->titol = new Text($titol);
         else
            $this->titol = null;
      }
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció «1301»
   */
   private function obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',1801);
      return $this->titol;
   }

   /*
   * @brief Obtens el codi del curs
   * @return Si el curs té un codi curs, retorna el codi del curs del curs. Altrament, null.
   * @throws Si el curs no té un codi curs, envia l'excepció «1303»
   */
   private function obtenirCodiCurs() {
      if ($this->codi==null)
         throw new Exception('',1803);
      return $this->codi;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Mostra la pàgina d'inscripció d'un curs
   * @return Retorna el contingut de la pàgina d'inscripció d'un curs
   */
   public function mostrar() {
      $mostrar="<div class='d-flex flex-column'>";
      $mostrar .= "<h1 class='mb-4'>Formulari d'inscripci&oacute</h1>
      <h2 class='pt-3 mt-0'>Tastet: <span class='nom-curs'>".$this->obtenirTitol()->obtenirTextHTML()."</span></h2>";
	  $mostrar .= $this->__mostrarDadesPersonals();
	  $mostrar .= $this->__mostrarFinal();
	  $mostrar .= $this->__modalError();
	  $mostrar .= $this->__modalSuccess();
	  $mostrar .= $this->__modalInscripcioDuplicada();
	  $mostrar .= "</div>";
	  $mostrar .= $this->__modalLoading();
	  $mostrar .= $this->__modalEnviant();

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
   function mostrarInput($idInput, $nomInput, $typeInput, $idError, $required) {
      $textAsterisk = "";
	  $textRequired = "";
      $pattern = "";
      $length = "";
      $llistat = "";

      if ( $required == "1" ) {
	     $textAsterisk = "<span class='req ml-1'>*</span>";
         $textRequired = " aria-required required";
	  }

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
      <label for='".$idInput."'><span class='camp'>".$nomInput."</span>".$textAsterisk."</label>
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
         <p>Aquesta inscripció és totalment <strong>gratuïta</strong>. Un cop s'hagi formalitzat (en 24/48 hores a partir de la sol·licitud d'inscripció), tindràs accés al tastet durant <strong>una setmana</strong>.</p>
		 <h3>Dades personals</h3>";
      $mostrar.="   <div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
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
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("poble", "Població", "text", "poble_erroni", "1")."</div>
         </div>".$this->__modalCorreuValid()."
         <div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("email", "Correu electrònic", "email", "correu_erroni", "1")."</div>
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("email_conf", "Confirmació del correu electrònic", "email", "correu_conf_erroni", "1")."</div>
         </div>
      </div>";
   	return $mostrar;
   }

   /*
   * @brief Mostra l'apartat de dades finals de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades finals de la pàgina d'inscripció d'un curs
   */
   private function __mostrarFinal() {
      $mostrar = "<div class='form-dades'>
      <h3>I per acabar...</h3>

      <div id='txtHint_conegut'>
      <div class='d-flex flex-row algin-items-center justify-content-center'>
      <div class='col-12 pl-0 pr-0 pr-md-2'>
         ".$this->mostrarSelect('comConegut', "Com has conegut aquest curs? Tria una opció", '', 'listComConegut', 'conegut_erroni')."
      </div>
      </div>
      <div class='' id='comHasConegut_altres'></div>
      <div class='' id='txtHint_mailing'><p class='mb-3'>

      En el moment en què es confirmi la inscripció, automàticament et donarem d'alta en el nostre butlletí electrònic. Si no vols seguir rebent els nostres missatges, te'n podràs donar de baixa en qualsevol moment.
      </p>
      </div>

      <p><strong>Tens algun comentari?</strong></p>
      <div class='d-flex flex-column algin-items-center justify-content-center'>

      <div class='form-group field-wrap position-relative'>
         <label for='comentaris'><span class='camp'>Comentaris</span></label>
         <textarea class='form-control' id='comentaris' name='comentaris'></textarea>
      </div>

      <div class='d-flex cnt_enviar_dades border-0 justify-content-center'>
      <button id='form_enviar_dades' class='boto-blau position-relative text-white border-0 border-radius-2 px-4 py-2 my-2'>Enviar dades</button>
      </div>

      </div></div>";
   	return $mostrar;
   }

   /*
   * @brief Mostra un modal d'error
   * @return Retorna un modal d'error
   */
   private function __modalError() {
      $mostrar = "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' aria-hidden='true'>
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

   	return $mostrar;
   }

   /*
   * @brief Mostra un modal que la inscripció ha estat realitzada correctament
   * @return Retorna un modal que la inscripció ha estat realitzada correctament
   */
   private function __modalSuccess() {
      $mostrar = "<div class='modal fade in' id='modalSuccess' tabindex='-1' role='dialog' aria-labelledby='modalSuccessTitle' aria-hidden='true'>
   		<div class='modal-dialog modal-dialog-centered modal-notify modal-success' role='document'>
   			<div class='modal-content'>
   				<div class='modal-header border-0 text-white'>
   					<p class='modal-title modal-title-success text-white float-left' id='modalSuccessTitle'>Inscripció realitzada</p>

   					<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button>
   				</div>
   				<div class='modal-body' id='modalSuccessBody'></div>
   				<div class='modal-footer justify-content-center text-center'>
   					<a role='button' class='btn btn-success waves-effect waves-light' id='close-sucess'>Close</a>
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
   * @brief Mostra un modal de confirmació d'inscripció
   * @return Retorna un modal de confirmació d'inscripció
   */
   private function __modalInscripcioDuplicada() {
      $mostrar = "<div class='modal fade in' id='modalInscripcioDuplicada' tabindex='-1' role='dialog' aria-labelledby='modalInscripcioDuplicadaTitle' aria-hidden='true'>
   		<div class='modal-dialog modal-dialog-centered modal-notify modal-prisma' role='document'>
   			<div class='modal-content'>
   				<div class='modal-header border-0 justify-content-center'>
   					<p class='modal-title m-0 text-center' id='modalInscripcioDuplicadaTitle'>CONFIRMA LA INSCRIPCIÓ</p>
   					<button role='button' class='close position-absolute' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>×</span></button>
   				</div>
   				<div class='modal-body text-center' id='modalInscripcioDuplicadaBody'></div>
   				<div class='modal-footer justify-content-center text-center border-0 pt-0 mb-2'>
   					<button role='button' data-dismiss='modal' class='boto-blau-disable border-0 border-radius-2 text-center negreta500 m-0 mr-3'>Tanca</button>
   				</div>
   			</div>
   		</div>
   	</div>";

   	return $mostrar;
   }

   /*
   * @brief Mostra un modal de càrrega "buscant"
   * @return Retorna un modal de càrrega "buscant"
   */
   private function __modalLoading() {
      $mostrar = "<div class='modal' id='modalLoading' tabindex='-1' role='dialog'
      aria-labelledby='modalLoading' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div id='loading-wrapper'>
                     <div id='loading-text'>Buscant...</div>
                     <div id='loading-content'></div>
                  </div>
               </div>
            </div>
         </div>
    	</div>";

   	return $mostrar;
   }

   /*
   * @brief Mostra un modal de càrrega "enviant"
   * @return Retorna un modal de càrrega "enviant"
   */
   private function __modalEnviant() {
      $mostrar = "<div class='modal' id='modalSending' tabindex='-1' role='dialog'
      aria-labelledby='modalSending' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div id='loading-wrapper'>
                     <div id='loading-text'>Enviant...</div>
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
