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

      require_once 'Template.php';
      $templates = new Template();

      if ( $factura != -1 ) {
         if ($factura==0) {
            $vistaPag = $this->__mostrarPagamentTargeta(1);
            $vistaPag .= $this->__mostrarPagamentTransferencia(1);
            $vistaPag .= $this->__modalError();
            $vistaPag .= $this->__modalSuccess();
            $vistaPag .= "</div>";
            $vistaPag .= $this->__modalLoading();
         }
         else {
            $vistaPag = "<p>L'import del regal està pagat.</p>";
         }

         $titolPage = "Pagament d'un regal";
         $msg = $templates->getTemplate_Pagament_VistaRegal();
      	$names_template = array("[TITOL_PAGE]", "[TITOL]", "[PAY]", "[VISTA_PAY]");
      	$names_function   = array($titolPage, $titol, $preuAPagar, $vistaPag);
      	$mostrar = str_replace($names_template, $names_function, $msg);
      }
      else {
         $mostrar = $templates->getTemplate_Pagament_VistaError();
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
      $correu = $this->obtenirCorreu()->obtenirText();

      require_once 'Template.php';
      $templates = new Template();

      $titolPagina = "Confirmació del regal";
      $rebutConfirmacio = "el missatge de confirmació del regal";

      $msg = $templates->getTemplate_Pagament_VistaPagHeader();
   	$names_template = array("[EMAIL]", "[PAY]", "[TITOL_PAGE]", "[REBUT_CONF]");
   	$names_function   = array($correu, $preuAPagar, $titolPagina, $rebutConfirmacio);
   	$mostrar = str_replace($names_template, $names_function, $msg);

      if ( $factura == 0 ) {
         $mostrar .= $this->__mostrarPagamentTargeta(2);
         $mostrar .= $this->__modalError();
         $mostrar .= $this->__mostrarPagamentTransferencia(2);
         $mostrar .= $templates->getTemplate_Pagament_VistaPagFooter();
      }
      else {
         $mostrar .= "<p>L'import del regal està pagat.</p>";
      }

      $mostrar .= $this->__modalLoading();

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

      require_once 'Template.php';
      $templates = new Template();
      $urlEfectPagament = "https://www.prisma.cat/regal/efectuarPagament/";

      $inputNom = $templates->getTemplate_Web_Formulari_Nom();
      $names_template = array("[NOM_LABEL]", "[ID_INPUT]", "[ID_SPAN_ERRONI]");
      $names_function   = array("Nom i cognoms del titular de la targeta", 'nom-titular');
      $formNom = str_replace($names_template, $names_function, $inputNom);

      $inputDni = $templates->getTemplate_Web_Formulari_Dni();
      $names_template = array("[NOM_LABEL]", "[ID_INPUT]", "[ID_SPAN_ERRONI]");
      $names_function   = array("DNI amb lletra", 'nif', 'dni_erroni');
      $formDni = str_replace($names_template, $names_function, $inputDni);

      $textInputs = "
         <input type='hidden' id='codiCurs' name='codiCurs' value='".$codiCurs."'>
         <input type='hidden' id='codiRegal' name='codiRegal' value='".$codiRegal."'>
         <input type='hidden' id='titol' name='titol' value=\"".$titol."\">
         <input type='hidden' id='email' name='email' value='".$correu."'>";

      $msg = $templates->getTemplate_Web_Pagaments_PagamentAmbTargeta( $vista, "R", 0, 0);
   	$names_template = array("[CODI_CURS]", "[CODI_REGAL]", "[TITOL]", "[CORREU]",
         "[PAY_ORIG]", "[PAY_FALTA]", "[URL_PAY]", "[FORM_NOM]", "[FORM_DNI]", "[INPUTS_HIDDEN]");
   	$names_function   = array($codiCurs, $codiRegal, $titol, $correu,
         $aPagar, $aPagar, $urlEfectPagament, $formNom, $formDni, $textInputs);
   	$mostrar = str_replace($names_template, $names_function, $msg);

   	return $mostrar;
   }

   /*
   * @brief Mostra l'apartat del pagament amb targeta
   * @return Mostra l'apartat del pagament amb targeta.
            Si $vista es 1, llavors mostra
            l'apartat segons la pagina de pagament. Si $vista és 2, llavors mostra
            l'apartat segons la pagina de la confirmació de pagament
   */
   private function __mostrarPagamentTransferencia( $vista ) {
      // require_once 'Template.php';
      // $templates = new Template();
      //
      // $msg = $templates->getTemplate_Web_Pagaments_PagamentAmbTransferencies( $vista, "R");
   	// $names_template = array("[CODI]");
   	// $names_function   = array($this->obtenirCodiRegal()->obtenirText());
   	// $mostrar = str_replace($names_template, $names_function, $msg);

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
