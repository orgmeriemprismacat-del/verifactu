<?php
/**
* @class InscripcioCurs
* @brief Conté tota la informació relacionada amb una InscripcioCurs.
*/
class PagamentCursAutomatic {
   private $idInsc; /** ID de la inscripció */
   private $anyInsc; /** Numero: Any de la inscripció */
   private $mesInsc; /** Text Mes en digits de la inscripció. ex. 03 */
   private $cursInsc; /** Text Curs de la inscripcio. ex. ACRE */
   private $aPagarInsc; /** Numero El valor que té pagar. ex. 90  */
   private $pagInsc; /** Numero El valor que ha pagat. ex. 90 */
   private $fraccInsc; /** Boolean Si la persona vol pagar fraccionat. ex. 0 o 1 */
   private $hores; /** Numero Les hores del curs de la Inscripcio ex: 40 */
   private $titol; /** Numero El titol del curs de la Inscripcio ex: Coaching per a Docents */
   private $datai; /** Text La data d'inici de l'edició del curs de la Inscripcio ex: 2020-10-01 */
   private $dataf; /** Text La data fi de l'edició del curs de la Inscripcio ex: 2020-10-01 */
   private $email; /** Text El email de la Inscripcio ex: suport@prisma.cat */
   private $dni; /** Text El dni de la Inscripcio ex: 77922662L */
   private $tipusDesc; /** Text El dni de la Inscripcio ex: 77922662L */
   private $validDesc; /** Text El dni de la Inscripcio ex: 77922662L */
   private $idpag; /** Text El dni de la Inscripcio ex: 77922662L */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/

   public function __construct($idPag) {
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

      $cnsInsc = "SELECT ID, ANY, MES, CURS, A_PAGAR, PAGAMENT, FRACCIONAT, CORREU, DNI, IDPAG, TIPUS_DESC, VALID_DESC FROM inscripcions WHERE IDPAG=? AND (`INSC CURS`='0' OR `INSC CURS`='1' OR `INSC CURS`='M')";
		$stmt=$connexio->prepare($cnsInsc);
		$stmt->bind_param("d", $idPag);
		$stmt->execute();
		$stmt->store_result();
		if ( $stmt->num_rows() == 1 ) {
			$stmt->bind_result($idInsc, $anyInsc, $mesInsc, $cursInsc, $aPagarInsc, $pagInsc, $fraccInsc, $correu, $dni, $idpag, $tipusDesc, $validDesc);
			$stmt->fetch();
         require_once 'Text.php';
         require_once 'Numero.php';
         if ($idInsc!=null and $idInsc!='')
            $this->idInsc = $idInsc;
         if ($anyInsc!=null and $anyInsc!='')
            $this->anyInsc = new Numero($anyInsc);
         else
            $this->anyInsc = null;
         if ($mesInsc!=null and $mesInsc!='')
            $this->mesInsc = new Text($mesInsc);
         else
            $this->mesInsc = null;
         if ($cursInsc!=null and $cursInsc!='')
            $this->cursInsc = new Text($cursInsc);
         else
            $this->cursInsc = null;
         if ($aPagarInsc!=null and $aPagarInsc!='')
            $this->aPagarInsc = new Numero($aPagarInsc);
         else
            $this->aPagarInsc = null;
         if ($pagInsc!=null and $pagInsc!='')
            $this->pagInsc = new Numero($pagInsc);
         else
            $this->pagInsc = null;
         if ($fraccInsc == 0) $this->fraccInsc = false;
         else $this->fraccInsc = true;
         if ($correu!=null and $correu!='')
            $this->email = new Text($correu);
         else
            $this->email = null;
         if ($dni!=null and $dni!='')
            $this->dni = new Text($dni);
         else
            $this->dni = null;
         $this->idpag = $idpag;
         $this->tipusDesc = $tipusDesc;
         $this->validDesc = $validDesc;
         if ($idpag==0)
            throw new Exception('',1516);

         $connexio->closeStmt();
         if ($this->anyInsc!=null and $this->mesInsc!=null and $this->cursInsc!=null) {
            $cnsEd = "SELECT NOM_CURS, HORES, DATAI, DATAF FROM curs WHERE ANY=? AND MES=? AND CURS=?";
      		$stmt=$connexio->prepare($cnsEd);
      		$stmt->bind_param("dss", $anyInsc, $mesInsc, $cursInsc);
      		$stmt->execute();
            $stmt->bind_result($nomCurs, $hores, $datai, $dataf);
   			$stmt->fetch();
            if ($nomCurs!=null and $nomCurs!='')
               $this->titol = new Text($nomCurs);
            else
               $this->titol = null;
            if ($hores!=null and $hores!='')
               $this->hores = new Numero($hores);
            else
               $this->hores = null;
            if ($datai!=null and $datai!='')
               $this->datai = new Text($datai);
            else
               $this->datai = null;
            if ($dataf!=null and $dataf!='')
               $this->dataf = new Text($dataf);
            else
               $this->dataf = null;
         }
         else {
            $this->titol = null;
            $this->hores = null;
            $this->datai = null;
            $this->dataf = null;
         }
		}
      else if ( $stmt->num_rows() > 1 ) {
        echo $idPag;
         throw new Exception('',1512);
		}
      else {
        // echo $idPag;
         throw new Exception('',1502);
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
         throw new Exception('',1503);
      return $this->titol;
   }

   /*
   * @brief Obtens les hores del curs
   * @return Les hores del curs
   * @throws Si el curs no té unes hores, envia l'excepció 1504
   */
   private function obtenirHores() {
      if ($this->hores==null)
         throw new Exception('',1504);
      return $this->hores;
   }

   /*
   * @brief Obtens el preu del curs que s'ha de pagar
   * @return Obtens el preu del curs que s'ha de pagar
   * @throws Si el curs no té un preu, envia l'excepció 1505
   */
   private function obtenirPreuAPagar() {
      if ($this->aPagarInsc==null)
         throw new Exception('',1505);
      return $this->aPagarInsc;
   }

   /**
   * @brief Obtens el codi del curs
   * @return El codi del curs.
   * @throws Si el curs no té un codi, envia l'excepció 1506
   */
   public function obtenirCodi() {
      if ($this->cursInsc==null)
         throw new Exception('',1506);
      return $this->cursInsc;
   }

   /**
   * @brief Obtens la data d'inici de l'edició del curs que es vol pagar
   * @return Obtens la data d'inici de l'edició del curs que es vol pagar
   * @throws Si el curs no té un dati, envia l'excepció 1507
   */
   public function obtenirDataIniciEdicio() {
      if ($this->datai==null)
         throw new Exception('',1507);
      return $this->datai;
   }

   /**
   * @brief Obtens la data de fi de l'edició del curs que es vol pagar
   * @return Obtens la data de fi de l'edició del curs que es vol pagar
   * @throws Si el curs no té un dataf, envia l'excepció 1508
   */
   public function obtenirDataFiEdicio() {
      if ($this->dataf==null)
         throw new Exception('',1508);
      return $this->dataf;
   }

   /**
   * @brief Obtens el mes de l'edició del curs
   * @return Obtens el mes de l'edició del curs
   * @throws Si el curs no té una edició, envia l'excepció 1509
   */
   public function obtenirMesEdicio() {
      if ($this->mesInsc==null)
         throw new Exception('',1509);
      return $this->mesInsc;
   }

   /**
   * @brief Obtens el valor de fraccionat de la inscripció
   * @return Obtens el valor de fraccionat de la inscripció. 0 si no vol fraccionar i 1 si vol fraccionar
   * @throws Si el curs no té una edició, envia l'excepció 1510
   */
   public function obtenirFraccionat() {
      return $this->fraccInsc;
   }

   /*
   * @brief Obtens el preu del curs que s'ha pagat
   * @return Obtens el preu del curs que s'ha pagat
   * @throws Si el curs no té un preu, envia l'excepció 1511
   */
   private function obtenirPreuPagat() {
      if ($this->pagInsc==null)
         throw new Exception('',1511);
      return $this->pagInsc;
   }

   /*
   * @brief Obtens el correu de la inscripció
   * @return Obtens el correu de la inscripció
   * @throws Si la encriptacio no té un correu, envia l'excepció 1514
   */
   private function obtenirCorreu() {
      if ($this->email==null)
         throw new Exception('',1514);
      return $this->email;
   }

   /*
   * @brief Obtens el dni de la inscripció
   * @return Obtens el dni de la inscripció
   * @throws Si la encriptacio no té un dni, envia l'excepció 1515
   */
   private function obtenirDni() {
      if ($this->dni==null)
         throw new Exception('',1515);
      return $this->dni;
   }

   /*
   * @brief Obtens el idpag de la inscripció
   * @return Obtens el idpag de la inscripció
   */
   private function obtenirIdPag() {
      return $this->idpag;
   }

   /*
   * @brief Obtens el idpag de la inscripció
   * @return Obtens el idpag de la inscripció
   */
   private function obtenirIdInsc() {
      return $this->idInsc;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Mostra la pàgina de pagament d'un curs
   * @return Mostra la pàgina de pagament d'un curs
   */
   public function mostrar() {
     $idInsc = $this->obtenirIdInsc();
      $titol = $this->obtenirTitol()->obtenirText();
      $dates = $this->__mostrarDatesEdicio();
      $durada = $this->obtenirHores()->obtenirNumero();
      $preuAPagar = floatval($this->obtenirPreuAPagar()->obtenirNumero());
      $preuPagat = floatval($this->obtenirPreuPagat()->obtenirNumero());
      $faltaPagar = $preuAPagar - $preuPagat;

      $recentTitulat == 0;
      if  ( $this->obtenirCodi()->obtenirText() == 'JASOM' ) {
        //validar si es un professor novell
        $connexio = new ConnexioBBDDSTMT();
        $connexio->connectarBD();
        $cnsInsc = "SELECT ID FROM recent_titulat WHERE ID_INSC = ?";
        $stmt=$connexio->prepare($cnsInsc);
        $stmt->bind_param("d", $idInsc);
        $stmt->execute();
        $stmt->store_result();
        if ( $stmt->num_rows() == 1 ) {
          $recentTitulat = 1;
        }
        $connexio->closeStmt();
        $connexio->desconectarBD();
      }

      $titolPage = "Pagament d'inscripci&oacute";

      if ($faltaPagar>0) {
         if ( $this->obtenirCodi()->obtenirText() == 'JASOM' ) {
            if ( $recentTitulat == 1 )
               $vistaPag .= $this->__mostrarPagamentTargeta(1);
            else
               $vistaPag .= "<p>Tan bon punt hàgim pogut validar el títol, acabarem el procés de reserva de la plaça i t’enviarem les dades per fer el pagament.</p>";
         }
         else {
            $vistaPag .= $this->__mostrarPagamentTargeta(1);
            $vistaPag .= $this->__mostrarPagamentTransferencia(1);
         }
         $vistaPag .= $this->__modalError();
         $vistaPag .= $this->__modalSuccess();
      }
      else {
         $vistaPag .= "<p>L'import del curs està pagat en la seva totalitat</p>";
      }
      $vistaPag .= $this->__modalLoading();

      require_once 'Template.php';
      $templates = new Template();
      $msg = $templates->getTemplate_Pagament_VistaCurs( $this->obtenirFraccionat() );
      $names_template = array("[TITOL_PAGE]", "[TITOL]", "[DATAI_FATAF]", "[DURADA]",
         "[PAY]", "[PRICE_PAY]", "[FALTA_PAY]", "[VISTA_PAY]");
      $names_function   = array($titolPage, $titol, $dates, $durada,
         $preuAPagar, $preuPagat, $faltaPagar, $vistaPag);
      $mostrar = str_replace($names_template, $names_function, $msg);

      return $mostrar;
   }

   /*
   * @brief Mostra la pàgina de confirmació d'una inscripció
   * @return Mostra la pàgina de confirmació d'una inscripció
   */
   public function mostrarPaginaConfirmacio() {
      $idInsc = $this->obtenirIdInsc();
      $titol = $this->obtenirTitol()->obtenirText();
      $dates = $this->__mostrarDatesEdicio();
      $durada = $this->obtenirHores()->obtenirNumero();
      $preuAPagar = floatval($this->obtenirPreuAPagar()->obtenirNumero());
      $preuPagat = floatval($this->obtenirPreuPagat()->obtenirNumero());
      $faltaPagar = $preuAPagar - $preuPagat;

      $titolPagina = "Confirmació de la inscripció";
      $rebutConfirmacio = "el missatge de confirmació de la inscripció";
      $recentTitulat == 0;
      if  ( $this->obtenirCodi()->obtenirText() == 'JASOM' ) {
         //validar si es un professor novell
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();
         $cnsInsc = "SELECT ID FROM recent_titulat WHERE ID_INSC = ?";
         $stmt=$connexio->prepare($cnsInsc);
         $stmt->bind_param("d", $idInsc);
         $stmt->execute();
         $stmt->store_result();
         if ( $stmt->num_rows() == 1 ) $recentTitulat = 1;
         $connexio->closeStmt();
         $connexio->desconectarBD();
      }

      $mostrarImport = ( $this->validDesc == 1 && $faltaPagar > 0 ) || ( $this->obtenirCodi()->obtenirText() == 'JASOM' && $recentTitulat == 0 );

      require_once 'Template.php';
      $templates = new Template();
      $msg = $templates->getTemplate_Pagament_VistaPagHeader($mostrarImport);
   	$names_template = array("[EMAIL]", "[PAY]", "[TITOL_PAGE]", "[REBUT_CONF]");
   	$names_function   = array($this->obtenirCorreu()->obtenirText(), $preuAPagar, $titolPagina, $rebutConfirmacio);
   	$mostrar = str_replace($names_template, $names_function, $msg);

      if ( $faltaPagar > 0 &&
         ( ( $this->obtenirCodi()->obtenirText() == 'JASOM' && $recentTitulat == 0 ) ||
            ( $this->obtenirCodi()->obtenirText() != 'JASOM') )
      )
        $mostrar .= $this->__mostrarPagamentTargeta(2);
      else if ($this->obtenirCodi()->obtenirText() == 'JASOM' && $recentTitulat == 1 )
        $mostrar .= "<p>Tan bon punt hàgim pogut validar el títol, acabarem el
        procés de reserva de la plaça i t’enviarem les dades per fer el pagament.</p>";

      $mostrar .= $this->__modalError();

      if ( $this->obtenirCodi()->obtenirText() == 'JASOM' ) {
        if ( $recentTitulat == 0 ) {
          $mostrar .= $this->__mostrarPagamentTransferencia(2);
        }
      }
      else if ( $this->validDesc == 1 && $faltaPagar > 0 ) {
        $mostrar .= $this->__mostrarPagamentTransferencia(2);
      }

      $mostrar .= "<div class='d-flex flex-column'>";
      $mostrar .= "<div class='container'><div class='row'>";
      if ( $this->validDesc == 1  ) $mostrar .= $templates->getTemplate_Pagament_VistaPagFooter();
      else $mostrar .= "<p>Si la inscripció no s'ha realitzat correctament, contacta amb
                    nosaltres al telèfon 972 21 75 65 o a través del correu electrònic
                    <span class='font-weight-bold email'>secretaria@prisma.cat</span>.</p>
                    <p style='margin-bottom: 100px'>Gràcies per confiar en PrisMa.</p>";
      $mostrar .= "</div></div></div>";

      $mostrar .= $templates->getTemplate_Pagament_VistaPagFooter();
      $mostrar .= $this->__modalLoading();
      return $mostrar;
   }

   /*
   * @brief Mostra la data d'inici i la data de fi de l'edicio sobre la inscripcio a pagar
   * @return Mostra la data d'inici i la data de fi de l'edicio sobre la inscripcio a pagar
   */
   private function __mostrarDatesEdicio() {
      $datai = $this->obtenirDataIniciEdicio()->obtenirText();
      $dataf = $this->obtenirDataFiEdicio()->obtenirText();

      $textDataI = new Text($datai);
      $textDataF = new Text($dataf);
      $dataFL=$textDataF->convertirDataLlarga();

      $partsDataI = explode('-',$datai);
      $partsDataF = explode('-',$dataf);
      if ( $partsDataI[0] == $partsDataF[0]) {
         if ( $partsDataI[1] == $partsDataF[1])
            $dataIL = intval($partsDataI[2]);
         else {
            $textMesLlarg = new Text($partsDataI[1]);
            $dataIL = intval($partsDataI[2])." ".$textMesLlarg->obtenirDeMesLlarg();
         }
      }
      else
         $dataIL=$textDataI->convertirDataLlarga();
      $mostrar .= "del dia ".$dataIL." al ".$dataFL."</a>";
      return $mostrar;
   }

   /*
   * @brief Mostra l'apartat del pagament amb targeta
   * @return Mostra l'apartat del pagament amb targeta. Si $vista es 1, llavors mostra
            l'apartat segons la pagina de pagament. Si $vista és 2, llavors mostra
            l'apartat segons la pagina de la confirmació de pagament
   */
   private function __mostrarPagamentTargeta( $vista ) {
      $aPagar = $this->obtenirPreuAPagar()->obtenirNumero();
      $preuPagat = $this->obtenirPreuPagat()->obtenirNumero();

      require_once 'Template.php';
      $templates = new Template();
      $urlEfectPagament = "https://www.prisma.cat/efectPagAuto/";

      if ( $this->validDesc == 1 ) {
         $inputNom = $templates->getTemplate_Web_Formulari_Nom();
         $names_template = array("[NOM_LABEL]", "[ID_INPUT]", "[ID_SPAN_ERRONI]");
         $names_function   = array("Nom i cognoms del titular de la targeta", 'nom-titular');
         $formNom .= str_replace($names_template, $names_function, $inputNom);

         $inputDni = $templates->getTemplate_Web_Formulari_Dni();
         $names_template = array("[NOM_LABEL]", "[ID_INPUT]", "[ID_SPAN_ERRONI]");
         $names_function   = array("DNI amb lletra", 'nif', 'dni_erroni');
         $formDni .= str_replace($names_template, $names_function, $inputDni);


         $input = $templates->getTemplate_Web_Formulari_Fraccionat();
         $names_template = array("[NOM_LABEL]", "[ID_INPUT]", "[ID_SPAN_ERRONI]");
         $names_function   = array("Quantitat que vull pagar ara",
         'importPagare', 'import_erroni', 'import', 'importPagat');
         $formFracc = str_replace($names_template, $names_function, $input);

         if ($this->obtenirFraccionat()) {
            $valueInputPayOrig = $aPagar;
            $valueInputPayPayed = $preuPagat;
         }
         else {
            $valueInputPayOrig = $aPagar;
            $valueInputPayFalta = $aPagar;
            $valueInputPayPayed = $preuPagat;
         }

         $textInputs = "<input type='hidden' id='idPag' name='idPag' value='".$this->obtenirIdPag()."'>";
         $textInputs .= "<input type='hidden' id='codiCurs' name='codiCurs' value='".$this->obtenirCodi()->obtenirText()."'>";
         $textInputs .= "<input type='hidden' id='titol' name='titol' value=\"".$this->obtenirTitol()->obtenirText()."\">";
         $textInputs .= "<input type='hidden' id='dniInscrit' name='dniInscrit' value=\"".$this->obtenirDni()->obtenirText()."\">";
         $textInputs .= "<input type='hidden' id='email' name='email' value='".$this->obtenirCorreu()->obtenirText()."'>";

         /* #################################################################### */

         $templates = new Template();
         $urlEfectPagament = "https://www.prisma.cat/efectPagAuto/";
         // $urlEfectPagament = "https://www.prisma.cat/efectPagAutoProva/";

         $inputNom = $templates->getTemplate_Web_Formulari_Nom();
         $names_template = array("[NOM_LABEL]", "[ID_INPUT]", "[ID_SPAN_ERRONI]");
         $names_function   = array("Nom i cognoms del titular de la targeta", 'nom-titular');
         $formNom = str_replace($names_template, $names_function, $inputNom);

         $inputDni = $templates->getTemplate_Web_Formulari_Dni();
         $names_template = array("[NOM_LABEL]", "[ID_INPUT]", "[ID_SPAN_ERRONI]");
         $names_function   = array("DNI amb lletra", 'nif', 'dni_erroni');
         $formDni = str_replace($names_template, $names_function, $inputDni);

         $msg = $templates->getTemplate_Web_Pagaments_PagamentAmbTargeta( $vista, "N", 1, $this->obtenirFraccionat() );
      	$names_template = array("[PAY_ORIG]", "[PAY_PAGAT]", "[PAY_FALTA]",
            "[URL_PAY]", "[FORM_NOM]", "[FORM_DNI]", "[INPUT_FRACC]", "[INPUTS_HIDDEN]");
      	$names_function   = array($valueInputPayOrig, $valueInputPayPayed, $valueInputPayFalta,
            $urlEfectPagament, $formNom, $formDni, $formFracc, $textInputs);
      	$mostrar = str_replace($names_template, $names_function, $msg);
      }

   	return $mostrar;
   }

   /*
   * @brief Mostra l'apartat del pagament amb targeta
   * @return Mostra l'apartat del pagament amb targeta. Si $vista es 1, llavors mostra
            l'apartat segons la pagina de pagament. Si $vista és 2, llavors mostra
            l'apartat segons la pagina de la confirmació de pagament
   */
   private function __mostrarPagamentTransferencia( $vista ) {
      require_once 'Template.php';
      $templates = new Template();

      $msg = $templates->getTemplate_Web_Pagaments_PagamentAmbTransferencies( $vista, 'N');
   	$names_template = array("[CODI]", "[MES]");
   	$names_function   = array($this->obtenirCodi()->obtenirText(), $this->obtenirMesEdicio()->obtenirText());
   	$mostrar = str_replace($names_template, $names_function, $msg);

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
