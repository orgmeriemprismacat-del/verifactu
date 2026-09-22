<?php
/**
* @class InscripcioCurs
* @brief Conté tota la informació relacionada amb una InscripcioCurs.
*/
class PaginaConfirmacioTastet {
   private $id; /** id de la insripció */
   private $cursInsc; /** Text Curs del tastet. ex. ACRE */
   private $titol; /** Text El titol del curs de la Inscripcio ex: Coaching per a Docents */
   private $imgAmple; /** Img la imatge llarge del tastet*/
   private $cursOrig; /** Curs Curs original del repte */
   private $email; /** Text El email de la Inscripcio ex: suport@prisma.cat */
   private $dni; /** Text El dni de la Inscripcio ex: 77922662L */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/

   public function __construct($id) {
      $this->id = $id;
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

      $cnsInsc = "SELECT CURS, CORREU, DNI FROM inscripcions_reptes WHERE ID=? AND (INSC_CURS='0' OR INSC_CURS='1')";
		$stmt=$connexio->prepare($cnsInsc);
		$stmt->bind_param("d", $id);
		$stmt->execute();
		$stmt->store_result();
		if ( $stmt->num_rows() == 1 ) {
			$stmt->bind_result($cursInsc, $correu, $dni);
			$stmt->fetch();
         require_once 'Text.php';
         require_once 'Imatge.php';
         require_once 'Curs.php';
         if ($cursInsc!=null and $cursInsc!='')
            $this->cursInsc = new Text($cursInsc);
         else
            $this->cursInsc = null;
         if ($correu!=null and $correu!='')
            $this->email = new Text($correu);
         else
            $this->email = null;
         if ($dni!=null and $dni!='')
            $this->dni = new Text($dni);
         else
            $this->dni = null;
         $connexio->closeStmt();

         if ($this->cursInsc!=null) {
            $cnsEd = "SELECT TITOL, ID_IMG_LARGE, CURS_ORIG FROM reptes WHERE CODI_CURS=? AND ESTAT = 1";
      		$stmt=$connexio->prepare($cnsEd);
      		$stmt->bind_param("s", $cursInsc);
      		$stmt->execute();
            $stmt->bind_result($nomCurs, $imgAmple, $cursOrig);
   			$stmt->fetch();
            if ($nomCurs!=null and $nomCurs!='')
               $this->titol = new Text($nomCurs);
            else
               $this->titol = null;
            if ($imgAmple!=null and $imgAmple!='')
               $this->imgAmple = new Imatge($imgAmple);
            else
               $this->imgAmple = null;
            if ($cursOrig!=null and $cursOrig!='')
               $this->cursOrig = new Curs($cursOrig, 'ordinador');
            else
               $this->cursOrig = null;
         }
         else {
            $this->titol = null;
            $this->imgAmple = null;
            $this->cursOrig = null;
         }
		}
      else if ( $stmt->num_rows() > 1 ) {
         throw new Exception('',2512);
		}
      else {
         throw new Exception('',2502);
		}
      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció 2503
   */
   private function obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',2503);
      return $this->titol;
   }

   /*
   * @brief Obtens la imatge3 del repte
   * @return la imatge3 del repte
   * @throws Si el repte no té unna imatge, envia l'excepció 2504
   */
   private function obtenirImatge() {
      if ($this->imgAmple==null)
         throw new Exception('',2504);
      return $this->imgAmple;
   }

   /*
   * @brief Obtens el curs original
   * @return Obtens el curs original
   * @throws Si el repte no té un curs original, envia l'excepció 2505
   */
   private function obtenirCursOrig() {
      if ($this->cursOrig==null)
         throw new Exception('',2505);
      return $this->cursOrig;
   }

   /**
   * @brief Obtens el codi del curs
   * @return El codi del curs.
   * @throws Si el curs no té un codi, envia l'excepció 2506
   */
   public function obtenirCodi() {
      if ($this->cursInsc==null)
         throw new Exception('',2506);
      return $this->cursInsc;
   }
   /*
   * @brief Obtens el correu de la inscripció
   * @return Obtens el correu de la inscripció
   * @throws Si la encriptacio no té un correu, envia l'excepció 2514
   */
   private function obtenirCorreu() {
      if ($this->email==null)
         throw new Exception('',2514);
      return $this->email;
   }

   /*
   * @brief Obtens el dni de la inscripció
   * @return Obtens el dni de la inscripció
   * @throws Si la encriptacio no té un dni, envia l'excepció 2515
   */
   private function obtenirDni() {
      if ($this->dni==null)
         throw new Exception('',2515);
      return $this->dni;
   }
   /*
   * @brief Obtens el id de la inscripció
   * @return Obtens el id de la inscripció
   */
   private function obtenirId() {
      return $this->id;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Mostra la pàgina de pagament d'un curs
   * @return Mostra la pàgina de pagament d'un curs
   */
   // public function mostrar() {
   //    $titol = $this->obtenirTitol()->obtenirText();
   //    $dates = $this->__mostrarDatesEdicio();
   //    $durada = $this->obtenirHores()->obtenirNumero();
   //    $preuAPagar = floatval($this->obtenirPreuAPagar()->obtenirNumero());
   //    $preuPagat = floatval($this->obtenirPreuPagat()->obtenirNumero());
   //    $faltaPagar = $preuAPagar - $preuPagat;
   //
   //    $mostrar="<div class='d-flex flex-column'>";
   //    $mostrar .= "<div class=''>";
   //    $mostrar .= "<h1 class='mb-4'>Pagament d'inscripci&oacute</h1>";
   //    $mostrar .= "<p class='dades pt-3'>Curs: <span class='dada titol'>".$titol."</span></p>";
   //    $mostrar .= "<p class='dades'>Dates: <span class='dada'>".$dates."</span></p>";
   //    $mostrar .= "<p class='dades'>Durada: <span class='dada'>".$durada." hores</span></p>";
   //    if (!$this->obtenirFraccionat())
   //       $mostrar .= "<p class='dades'>Preu: <span class='dada'>".$preuAPagar." euros</span></p></div>";
   //    else {
   //       $mostrar .= "<p>Has triat l'opció de pagament fraccionat (sense recàrrec).</p>";
   //       $mostrar .= "<p>Pots triar les quantitats i el termini del pagament sempre que facis un primer pagament abans de  l’inici del curs i que hagis abonat l’import complet com a màxim una setmana després de la finalització del curs.</p>";
   //       $mostrar .= "<p class='dades'>Preu: <span class='dada'>".$preuAPagar." euros</span></p></div>";
   //       $mostrar .= "<ul>
   //          <li class='dades'>Pagat: <span class='dada'>".$preuPagat." euros</span></li>
   //          <li class='dades'>Pendent: <span class='dada'>".$faltaPagar." euros</span></li>
   //       </ul>";
   //       $mostrar .= "</div>";
   //    }
   //    if ($faltaPagar>0) {
   //       $mostrar .= $this->__mostrarPagamentTargeta(1);
   //       $mostrar .= $this->__mostrarPagamentTransferencia(1);
   //       $mostrar .= $this->__modalError();
   //       $mostrar .= $this->__modalSuccess();
   //    }
   //    else {
   //       $mostrar .= "<p>L'import del curs està pagat en la seva totalitat</p>";
   //    }
   //    $mostrar .= "</div>";
   //    $mostrar .= $this->__modalLoading();
   //    return $mostrar;
   // }

   /*
   * @brief Mostra la pàgina de confirmació d'una inscripció
   * @return Mostra la pàgina de confirmació d'una inscripció
   */
   public function mostrarPaginaConfirmacio() {
      $titol = $this->obtenirTitol()->obtenirText();

      $mostrar="<div class='d-flex flex-column'>";
      $mostrar .= "<div class='container'><div class='row'>";
      $mostrar .= "<h1 class='mb-4 w-100'>Confirmació de la inscripció</h1>";
      $mostrar .= "<p>La teva sol·licitud ha estat enviada.</p>
      <p>Consulta la safata d'entrada o el correu brossa (<em>spam</em>) de l'adreça
      <span class='font-weight-bold email'>".$this->obtenirCorreu()->obtenirText()."</span>
      per comprovar que has rebut el missatge de confirmació de la inscripció.</p>
      <p>La inscripció del tastet és totalment gratuïta.</p>
      <div class='p-3 mt-2' style='background: #e8ecf5 !important;'>
      <p><i class='fas fa-exclamation-circle ml-0'></i>En un període de 24/48 hores laborals podràs accedir al tastet <span class='font-weight-bold'>amb les teves claus</span> del campus virtual de PrisMa. En cas que encara no hagis fet cap curs amb nosaltres i no disposis de claus, rebràs un correu electrònic amb les dades d'accés.</p>
      <p class='mb-0'>Normalment completar un tastet no et portarà més de dues o tres hores; tot i així, <span class='font-weight-bold'>un cop t’hi hàgim donat d’alta hi tindràs accés durant una setmana</span>.</p>";
      $mostrar .= "</div></div></div>";
      $mostrar .= "<div class='d-flex flex-column'>";
      $mostrar .= "<div class='container'><div class='row'>";

      $mostrar .= "<p class='mt-3'>Si la inscripció no s'ha realitzat correctament, contacta amb
                 nosaltres al telèfon 972 21 75 65 o a través del correu electrònic
                 <span class='font-weight-bold email'>secretaria@prisma.cat</span>.</p>
                 <p style='margin-bottom: 100px'>Gràcies per confiar en PrisMa!</p>";

      $mostrar .= "</div></div></div>";
      $mostrar .= $this->__modalLoading();
      return $mostrar;
   }

   /*
   * @brief Retorna un modal d'error
   * @return Retorna un modal d'error
   */
   private function __modalError() {
      $mostrar = "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' aria-hidden='true'>
   		<div class='modal-dialog modal-dialog-centered modal-notify modal-danger' role='document'>
   			<div class='modal-content w-100'>
   				<div class='modal-header border-0 bg-danger color-white'>
   					<p class='modal-title modal-title-danger color-white float-left' id='modalErrorsTitle'>Errors</p>

   					<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='color-white'>×</span></button>
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
   * @brief Retorna un modal de success
   * @return Retorna un modal de success
   */
   private function __modalSuccess() {
      $mostrar = "<div class='modal fade in' id='modalSuccess' tabindex='-1' role='dialog' aria-labelledby='modalSuccessTitle' aria-hidden='true'>
   		<div class='modal-dialog modal-dialog-centered modal-notify modal-success' role='document'>
   			<div class='modal-content'>
   				<div class='modal-header border-0 color-white'>
   					<p class='modal-title modal-title-success color-white float-left' id='modalSuccessTitle'>Inscripció realitzada</p>

   					<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='color-white'>×</span></button>
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
   * @brief Retorna un modal de Loading
   * @return Retorna un modal de Loading
   */
   private function __modalLoading() {
      $mostrar = "<div class='modal' id='modalLoading' tabindex='-1' role='dialog'
      aria-labelledby='modalLoading' style='display: none' aria-modal='true'>
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
