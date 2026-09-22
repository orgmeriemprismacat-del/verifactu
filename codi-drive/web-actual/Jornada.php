<?php

/**
* @class Jornada
* @brief Conté tota la informació relacionada amb una Jornada
*/

class Jornada {
	private $anyActiu; /* Any actiu de les jornades en línia */
	private $id; /* Int identificador de la jornada en línia */
	private $titol; /** Text Títol de la jornada */
	private $ponents; /** array[Tutor] Diferents Ponents de la jornada */
	private $dataInici; /** Text La data d'inici de la jornada */
	private $codiDesc; /** array[Text] Conté el  odi del descompte de la jornada i la durada d'aquest descompte */
	private $cursDesc; /** array[Text] Codi del curs sobre el qual es fa el descompte, el percentatge del descompte i l'edició on s'aplicarà aquest descompte */
	private $shortDesc; /** array[Text] Diferents descripcions */
	private $intro; /** array[Text] Diferents texts introductoris */
	private $objectius; /** array[Text] Diferents llistats d'objectius  */
	private $cursosRel; /** array[Curs] Llistats de cursos relacionats */
	private $url; /** Url La URL de la jornada */
	private $img; /** Imatge La imatge de la jornada */
	private $videoDirecte; /** Video El video des del qual es farà el directe de la jornada*/
	private $videos; /** array[Video] Els videos que queden com a resultat del directe de la jornada */
	private $documents; /** array[Text] Els documents relacionats amb la jornada */
	private $dispositiu; /** string Dispositiu amb el que s'accedeix a la web */
	private $estat; /** int Indica l'estat de la jornada en línia */

	/* #################################    FUNCIONS CONSTRUCTORS    ################################# */

	public function __construct( $idUrl, $dispositiu ) {

	}

	/* ################################# FUNCIONS CONSULTAR ATRIBUTS ################################# */

	/* #################################  FUNCIONS MOSTRAR MODALS  ################################# */

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

	/*
   * @brief Retorna un modal per poder subscriure'ns al mailing de cursos i avisar-nos 30 minuts abans
   * @return Retorna un modal per poder subscriure'ns al mailing de cursos i avisar-nos 30 minuts abans
   */
   private function __modalSubscription() {
      $modal = "<div class='modal fade in' id='modalSubscription' tabindex='-1' role='dialog' aria-labelledby='modalSubscription' style='padding-right: 17px; display: none;' aria-modal='true'>
			<div class='modal-dialog modal-dialog-centered modal-notify modal-success justify-content-center text-center ' role='document'>
				<div class='modal-content w-100 border-0'>
					<div class='modal-body p-5' id='modalSubscritionBody'>
						<button role='button' class='close' data-dismiss='modal' aria-label='Close' style=''><span aria-hidden='true'>×</span></button>
						<div class='text-left'>
							<div class='d-flex flex-row mb-3'>
								<label><input type='checkbox' class='mailing' id='mailing-all' value='1'>
									<span class='checkmark'></span>
									<span>Avisa'm 30 minuts abans de la jornada <strong>".$this->__obtenirTitol()->obtenirTextHTML()."</strong></span>
								</label>
							</div>
							<div class='d-flex flex-row mb-4'>
								<label><input type='checkbox' class='mailing' id='mailing-course' value='1'>
									<span class='checkmark'></span>
									<span>Vull rebre més informació de cursos i serveis de l'Associació PrisMa</span>
								</label>
							</div>
						</div>
						<div class='d-flex flex-lg-row flex-column algin-items-center justify-content-center cnt-mailing'>
							<div class='col-12 col-lg-9 p-0 h-100'>
								<div class='form-group field-wrap position-relative h-100 mb-0'>
									<label class=''>
										<span class='camp'>Correu electrònic</span>
										<span class='req'>*</span>
									</label>
									<input type='email' class='form-control email-mailing h-100' id='email-mailing' name='email-mailing'>
									<span id='correu_erroni' class='text-center text-white position-absolute'></span></div>
							</div>
							<div class='col-12 col-lg-3 p-0'>
								<a role='button' class='btn boto-blau text-white negreta500 w-100 h-100 m-0' id='send-subscrition'>Enviar</a>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>";

   	return $modal;
   }

	/*
   * @brief Retorna un modal per poder donar una confirmació del mailing
   * @return Retorna un modal per poder donar una confirmació del mailing
   */
   private function __modalSubscriptionOK() {
      $modal = "<div class='modal fade in' id='modalSubscriptionOK' tabindex='-1' role='dialog' aria-labelledby='modalSubscriptionOK' style='padding-right: 17px; display: none;' aria-modal='true'>
			<div class='modal-dialog modal-dialog-centered modal-notify modal-success justify-content-center text-center ' role='document'>
				<div class='modal-content w-100 border-0'>
					<div class='modal-header border-0 text-white background-prisma'><p class='modal-title modal-title-success text-white float-left' id='modalSuccessTitle'>Sol·licitud enviada</p><button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button></div>
					<div class='modal-body pb-0' id='modalSubscriptionOKBody'>
					</div>
					<div class='modal-footer justify-content-center text-center border-0'><a role='button' class='btn boto-blau text-white negreta500' id='close-sucess' aria-label='Close' data-dismiss='modal'>Tanca</a></div>
				</div>
			</div>
		</div>";

   	return $modal;
   }

	/*********************************** FUNCIONS MODIFICAR ATRIBUTS ################################# */
}

?>
