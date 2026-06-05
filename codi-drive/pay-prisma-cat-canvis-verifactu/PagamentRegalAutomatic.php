<?php
/**
* @class InscripcioCurs
* @brief Conté tota la informació relacionada amb una InscripcioCurs.
*/
class PagamentRegal {
   private $codiCurs; /** Text Curs del regal. ex. ACRE */
   private $codiRegal; /** Text Codi regal. ex. */
   private $import; /** Numero El valor que té pagar. ex. 90  */
   private $factrel; /** Numero El valor de la factura relacionada. ex. 90  */
   private $titol; /** Numero El titol del curs de la Inscripcio ex: Coaching per a Docents */
   private $email; /** Text El email de la Inscripcio ex: suport@prisma.cat */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/

   public function __construct($idRegal) {
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

      $cnsInsc = "SELECT IMPORT, NOM_CURS, MAILC, CODI, CCURS, FACT_REL FROM regal WHERE ID=?";
		$stmt=$connexio->prepare($cnsInsc);
		$stmt->bind_param("d", $idRegal);
		$stmt->execute();
		$stmt->store_result();
		if ( $stmt->num_rows() == 1 ) {
         $stmt->bind_result($import, $nomCurs, $correuCurs, $codiRegal, $codiCurs, $factrel);
			$stmt->fetch();
         require_once 'Text.php';
         require_once 'Numero.php';
         if ($import!=null and $import!='')
            $this->import = new Numero($import);
         else
            $this->import;
         $this->factrel = $factrel;
         if ($codiCurs!=null and $codiCurs!='')
            $this->codiCurs = new Text($codiCurs);
         else
            $this->codiCurs = null;
         if ($correuCurs!=null and $correuCurs!='')
            $this->email = new Text($correuCurs);
         else
            $this->email = null;
         if ($nomCurs!=null and $nomCurs!='')
            $this->titol = new Text($nomCurs);
         else
            $this->$titol = null;
         if ($codiRegal!=null and $codiRegal!='')
            $this->codiRegal = new Text($codiRegal);
         else
            $this->codiRegal = null;
		}
      else if ( $stmt->num_rows() > 1 ) {
         throw new Exception('',1712);
		}
      else {
         throw new Exception('',1702);
		}
      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció 1503
   */
   private function obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',1703);
      return $this->titol;
   }

   /*
   * @brief Obtens el preu del curs que s'ha de pagar
   * @return Obtens el preu del curs que s'ha de pagar
   * @throws Si el curs no té un preu, envia l'excepció 1505
   */
   private function obtenirPreuAPagar() {
      if ($this->import==null)
         throw new Exception('',1705);
      return $this->import;
   }

   /**
   * @brief Obtens el codi del curs
   * @return El codi del curs.
   * @throws Si el curs no té un codi, envia l'excepció 1506
   */
   public function obtenirCodiCurs() {
      if ($this->codiCurs==null)
         throw new Exception('',1706);
      return $this->codiCurs;
   }

   /**
   * @brief Obtens el codi regal
   * @return El codi del regal.
   * @throws Si el curs no té un regal, envia l'excepció 1506
   */
   public function obtenirCodiRegal() {
      if ($this->codiRegal==null)
         throw new Exception('',1707);
      return $this->codiRegal;
   }

   /*
   * @brief Obtens el correu del regal
   * @return Obtens el correu del regal
   * @throws Si la encriptacio no té un correu, envia l'excepció 1514
   */
   private function obtenirCorreu() {
      if ($this->email==null)
         throw new Exception('',1514);
      return $this->email;
   }

   /*
   * @brief Obtens la factura del regal
   * @return Obtens la factura del regal
   */
   private function obtenirFactura() {
      return $this->factrel;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Mostra la pàgina de pagament d'un curs
   * @return Mostra la pàgina de pagament d'un curs
   */
   public function mostrar() {
      $titol = $this->obtenirTitol()->obtenirText();
      $preuAPagar = floatval($this->obtenirPreuAPagar()->obtenirNumero());
      $factura = $this->obtenirFactura();

      if ($factura!=-1) {
         $mostrar="<div class='d-flex flex-column'>";
         $mostrar .= "<div class=''>";
         $mostrar .= "<h1 class='mb-4'>Pagament d'un regal</h1>";

         $mostrar .= "<p class='dades pt-3'>Curs regal: <span class='dada titol'>".$titol."</span></p>";
         $mostrar .= "<p class='dades'>Preu: <span class='dada'>".$preuAPagar." euros</span></p></div>";
         if ($factura==0) {
            $mostrar .= $this->__mostrarPagamentTargeta(1);
            $mostrar .= $this->__mostrarPagamentTransferencia(1);
            $mostrar .= $this->__modalError();
            $mostrar .= $this->__modalSuccess();
            $mostrar .= "</div>";
            $mostrar .= $this->__modalLoading();
         }
         else {
            $mostrar .= "<p>L'import del regal està pagat.</p>";
         }
      }
      else {
         $mostrar = "<div class='container' id='notfound'>
            <div class='col-md-12'>
               <div class='page-error-content text-center mt-5'>
                  <img src='https://www.prisma.cat/img/error-pagament.png' alt='Error en el pagament amb targeta' class='mt-3'/>
                  <h2>Pagament no disponible</h1>
                  <p class='mb-4'>Hi ha hagut un error. Contacta amb nosaltres al telèfon 972 21 75 65
                  o a <span class='font-weight-bold email'>secretaria@prisma.cat</span>. </p>
               </div>
            </div>
         </div>";
      }
      return $mostrar;
   }

   /*
   * @brief Mostra la pàgina de confirmació d'una inscripció
   * @return Mostra la pàgina de confirmació d'una inscripció
   */
   public function mostrarPaginaConfirmacio() {
      $titol = $this->obtenirTitol()->obtenirText();
      $preuAPagar = floatval($this->obtenirPreuAPagar()->obtenirNumero());
      $factura = $this->obtenirFactura();

      $mostrar="<div class='d-flex flex-column'>";
      $mostrar .= "<div class='container'><div class='row'>";
      $mostrar .= "<h1 class='mb-4'>Confirmació del regal</h1>";
      $mostrar .= "<p>La teva sol·licitud ha estat enviada. Consulta la safata
                  d'entrada o el correu brossa (<em>spam</em>) de l'adreça
                  <span class='font-weight-bold email'>".$this->obtenirCorreu()->obtenirText()."</span>
                  per comprovar que has rebut el missatge de confirmació del regal.</p>";
      $mostrar .= "<p>L'import a pagar és de <span class='font-weight-bold'>".$preuAPagar."</span> euros.</p>";
      $mostrar .= "</div></div>";

      if ($factura==0) {
         $mostrar .= $this->__mostrarPagamentTargeta(2);
         $mostrar .= $this->__modalError();
         $mostrar .= $this->__mostrarPagamentTransferencia(2);
         $mostrar .= "<div class='d-flex flex-column'>";
         $mostrar .= "<div class='container'><div class='row'>";
         $mostrar .= "<p>Si la inscripció no s'ha realitzat correctament, contacta amb
                     nosaltres al telèfon 972 21 75 65 o a través del correu electrònic
                     <span class='font-weight-bold email'>secretaria@prisma.cat</span>.</p>
                     <p>Gràcies per confiar en PrisMa.</p>";
         $mostrar .= "</div></div></div>";
         $mostrar .= $this->__modalLoading();
      }
      else {
         $mostrar .= "<p>L'import del regal està pagat.</p>";
      }
      return $mostrar;
   }

   /*
   * @brief Mostra l'apartat del pagament amb targeta
   * @return Mostra l'apartat del pagament amb targeta. Si $tipus es 1, llavors mostra
            l'apartat segons la pagina de pagament. Si $tipus és 2, llavors mostra
            l'apartat segons la pagina de la confirmació de pagament
   */
   private function __mostrarPagamentTargeta($tipus) {
      $aPagar = $this->obtenirPreuAPagar()->obtenirNumero();
      $codiCurs = $this->obtenirCodiCurs()->obtenirText();
      $codiRegal = $this->obtenirCodiRegal()->obtenirText();
      $titol = $this->obtenirTitol()->obtenirText();
      $correu = $this->obtenirCorreu()->obtenirText();

      $mostrar = "<div class='form-dades'>";
      if ($tipus==2) {
         $mostrar .= "<p>Per tal de poder realitzar el <span class='font-weight-bold'>";
         $mostrar .= "pagament amb targeta</span>, cal que tinguis activat el codi ";
         $mostrar .= "de compra segura facilitat per la teva entitat bancària.</p>";
      }
      else {
         $mostrar .= "<h3>Pagament amb targeta</h3>";
         $mostrar .= "<p>Per tal de poder realitzar el pagament amb targeta, cal que ";
         $mostrar .= "tinguis activat el <span class='font-weight-bold'>codi de compra segura</span> ";
         $mostrar .= "facilitat per la teva entitat bancària.</p>";
      }
      $mostrar .= "<div class='d-flex flex-column algin-items-center justify-content-center'>";
      $mostrar .= "<form id='frm' name='frm' action='https://www.prisma.cat/regal/efectuarPagament/' method='post'>";

      $mostrar .= "<input type='hidden' id='codiCurs' name='codiCurs' value='".$codiCurs."'>";
      $mostrar .= "<input type='hidden' id='codiRegal' name='codiRegal' value='".$codiRegal."'>";
      $mostrar .= "<input type='hidden' id='titol' name='titol' value=\"".$titol."\">";
      $mostrar .= "<input type='hidden' id='email' name='email' value='".$correu."'>";

      $mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>";
      $mostrar .= "<div class='col-12 pl-0 pr-0 pr-md-2'>";
      $mostrar .= "<div class='form-group field-wrap position-relative'>
                     <label class='position-absolute mb-0'>
                        <span class='camp'>Nom i cognoms del titular de la targeta</span>
                        <span class='req'>*</span>
                     </label>
                     <input type='text' class='form-control' id='nom-titular' name='nom-titular'>
                     <span id='nom_cognom_titular_erroni'
                        class='d-flex justify-content-center align-items-center px-2
                        position-absolute text-center text-white'></span>
                  </div>";
      $mostrar .= "</div>";
      $mostrar .= "</div>";

      $mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
         <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
            <div class='form-group field-wrap position-relative'>
               <div id='doc' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
                  <span class='element-selected font-weight-normal w-100'>NIF/NIE</span>
                  <ul class='select-list position-absolute ' style='display: none;'>
                     <li class='border-bottom m-0' id='doc-dni'><a href='#'>NIF/NIE</a></li>
                     <li class='border-bottom m-0' id='doc-passaport'><a href='#'>Altres</a></li>
                  </ul>
                  <i class='fa triangle-inferior fa-angle-down position-absolute'></i>
               </div>
            </div>
         </div>
         <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2' id='input_doc'>
            <div class='form-group field-wrap position-relative'>
               <label class='position-absolute mb-0'>
                  <span class='camp'>DNI amb lletra</span>
                  <span class='req'>*</span>
               </label>
               <input type='text' class='form-control' id='nif' name='nif'>
               <span id='dni_erroni' class='d-flex justify-content-center
                      align-items-center px-2 position-absolute text-center
                      text-white'></span>
             </div>
          </div>
       </div>";

      if ($tipus!=2) {
         $mostrar .= "<p>L'import a pagar és de <span class='font-weight-bold'>";
         $mostrar .= "<span class='preu'>".$aPagar."</span> euros</span>.</p>";
      }
      $mostrar .= "<input type='hidden' id='import' name='import' value='".$aPagar."'>";

      $mostrar .= "<div class='d-flex cnt_enviar_dades border-0 justify-content-center'>
                     <a id='form_enviar_dades' role='button' class='boto-blau
                     position-relative text-white text-center border-0
                     border-radius-2 w-px-4 py-2 my-2'>";
         if ($tipus==2)
            $mostrar .= "Vull pagar ara";
         else
            $mostrar .= "Efectua el pagament";
         $mostrar .= "</a>";
      $mostrar .= "</div>";

      $mostrar .= "</form>";
      $mostrar .= "</div></div>";
   	return $mostrar;
   }

   /*
   * @brief Mostra l'apartat del pagament amb targeta
   * @return Mostra l'apartat del pagament amb targeta. Si $tipus es 1, llavors mostra
            l'apartat segons la pagina de pagament. Si $tipus és 2, llavors mostra
            l'apartat segons la pagina de la confirmació de pagament
   */
   private function __mostrarPagamentTransferencia($tipus) {
      if ($tipus==2) {
         $mostrar = "<p>Si vols pagar més endavant o mitjançant <span class='font-weight-bold'>
                     transferència</span> o <span class='font-weight-bold'>ingrés
                     bancari</span>, podràs fer-ho visitant l’enllaç que has rebut
                     en el correu de confirmació.</p>";
         $mostrar .= "<p>Un cop hagis efectuat el pagament, et demanem que conservis
                     el justificant bancari fins que t'arribi un correu electrònic
                     que et confirmi que l'hem rebut correctament.</p>";
      }
      else {
         $mostrar = "<div class='form-dades'><h3>Pagament per transferència o ingrés bancari</h3>";
      	$mostrar .= "<p>Si ho prefereixes, pots fer una TRANSFERÈNCIA o INGRÉS BANCARI, ";
      	$mostrar .= "indicant clarament el concepte <span class='font-weight-bold'>";
      	$mostrar .= "«<span class='codiRegal'>".$this->obtenirCodiRegal()->obtenirText();
      	$mostrar .= "</span>»</span> ";
      	$mostrar .= "en qualsevol dels comptes següents:</p>";
      	$mostrar .= "<ul>";
      	$mostrar .= "<li>La Caixa: ES30 2100 4279 21 2200080678</li>";
      	$mostrar .= "<li>BBVA: ES04 0182 5117 00 0201534816</li>";
      	$mostrar .= "</ul>";
      	$mostrar .= "<p>Un cop hagis realitzat el pagament, et demanem que conservis ";
      	$mostrar .= "el justificant bancari fins que t'arribi un correu electrònic ";
      	$mostrar .= "que et confirmi que l'hem rebut correctament.</p>";
      	$mostrar .= "</div>";
      }
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
   * @brief Retorna un modal de success
   * @return Retorna un modal de success
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
   * @brief Retorna un modal de Loading
   * @return Retorna un modal de Loading
   */
   private function __modalLoading() {
      $mostrar = "<div class='modal carrega' id='modalLoading' tabindex='-1' role='dialog'
      aria-labelledby='modalLoading' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div class='loading-wrapper'>
                     <div class='loading-text'>Enviant...</div>
                     <div class='loading-content'></div>
                  </div>
               </div>
            </div>
         </div>
    	</div>";

   	return $mostrar;
   }

}
?>
