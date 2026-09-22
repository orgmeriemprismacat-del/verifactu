<?php
/**
   * @class Edicio
   * @brief Conté la informació de l'edició d'un curs
*/
class Edicio {
   private $codiCurs; /**< string El codi del curs de l'edició */
   private $tipusCurs; /**< string El codi del curs de l'edició */
   private $any; /**< Numero L'any del curs de l'edició */
   private $mes; /**< Text El mes del curs de l'edició */
   private $hores; /**< Numero Les hores del curs de l'edició */
   private $datai; /**< string La data d'inici del curs de l'edició */
   private $dataf; /**< string La data de fi del curs de l'edició */
   private $curs_escolar; /**< string El curs escolar de l'edició */
   private $codi_gtaf; /**< string El codi GTAF de l'edició */
   private $data_res; /**< string La data de resolució de l'edició */
   private $codi_fiss; /**< string El codi FISS de l'edició */
   private $cdd; /**< Indica si és CDD */
   private $perfils; /**< Array Els perfils que estan associats a l'edició */
   private $estat; /**< Array L'estat de l'edició del curs */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
      * @brief Constructor de la classe.
      * @param $codiCurs El codi del curs de l'edició
      * @param $dispositiu El dispositu en que s'està reproduint la classe
      * @param $any L'any del curs de l'edició
      * @param $mes El mes del curs de l'edició
      * @param $hores Les hores del curs de l'edició
      * @param $datai La data d'inici del curs de l'edició
      * @param $dataf La data de fi del curs de l'edició
      * @param $curs_escolar El curs escolar de l'edició
      * @param $codi_gtaf El codi GTAF de l'edició
      * @param $data_res Data de resolució de l'edició
      * @param $codi_fiss El codi FISS de l'edició
      * @param $estat L'estat de l'edició del cur
      * @return Crea l'edició corresponent
   */
   public function __construct($codiCurs,$tipusCurs,$dispositiu,$any,$mes,$hores,$datai,$dataf,$curs_escolar,$codi_gtaf,$data_res,$codi_fiss,$estat, $cdd) {
      require_once 'Curs.php';
      require_once 'Text.php';
      require_once 'Numero.php';

      if ($codiCurs==null OR $codiCurs=='')
         throw new Exception('',801);
      $this->curs = new Curs($codiCurs, $dispositiu);

      if ($tipusCurs==null OR $tipusCurs=='')
         throw new Exception('',801);
      $this->tipusCurs = new Text($tipusCurs);

      if ($any==null OR $any=='')
         throw new Exception('',802);
      $this->any = new Numero($any);

      if ($mes==null OR $mes=='')
         throw new Exception('',803);
      $this->mes = new Text($mes);

      if ($hores==null OR $hores=='')
         throw new Exception('',804);
      $this->hores = new Numero($hores);

      if ($datai==null OR $datai=='')
         throw new Exception('',805);
      $this->datai = new Text($datai);

      if ($dataf==null OR $dataf=='')
         throw new Exception('',806);
      $this->dataf = new Text($dataf);

      if ($curs_escolar==null OR $curs_escolar=='')
         throw new Exception('',807);
      $this->curs_escolar = new Text($curs_escolar);

      if ($codi_gtaf==null OR $codi_gtaf=='')
         throw new Exception('',808);
      $this->codi_gtaf = new Text($codi_gtaf);

      if ($data_res==null OR $data_res=='')
         $this->data_res = null;
      else
         $this->data_res = new Text($data_res);

      if ($codi_fiss==null OR $codi_fiss=='')
         $this->codi_fiss = null;
      else
         $this->codi_fiss = new Text($codi_fiss);

      if ($estat==null OR $estat=='')
         $this->estat = null;
      else
         $this->estat = new Text($estat);

        if ($cdd!=null and $cdd!='')
           $this->cdd = 1;
        else
           $this->cdd = 0;

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Buscar el perfil de l'edicio corresponent */
      $consultaEdicio = "SELECT c.CURS, c.HORES, p.CODI_GTAF, c.CURS_ESCOLAR, p.ID_PERFIL FROM curs AS c
      INNER JOIN perfils as p ON c.CURS=p.CODI AND c.HORES=p.HORES AND c.CURS_ESCOLAR=p.CURS_ESCOLAR AND
      c.GTAF=p.CODI_GTAF WHERE c.ANY=? AND c.MES=? AND c.CURS=? ORDER BY ID_PERFIL";
      $sentencia = $connexio->prepare($consultaEdicio);
      $sentencia->bind_param("dss", $any, $mes, $codiCurs);
      $sentencia->execute();
      $sentencia->bind_result($curs, $hores, $codi_gtaf, $curs_escolar, $id_perfil);

      $cnt_perfils = 0;
      require_once 'Perfil.php';
      while ($sentencia->fetch()) {
         $this->perfils[$cnt_perfils] = new Perfil($id_perfil);
         $cnt_perfils++;
      }

      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /**
      * @brief Obtens l'any de l'edicio del curs
      * @return L'any de l'edicio del curs.
      * @throws Si el curs no té un any, envia l'excepció «No existeix l'any de l'edicio curs»
   */
   public function obtenirAny() {
      if ($this->any==null)
         throw new Exception('',811);
      return $this->any;
   }

   /**
      * @brief Obtens el mes de l'edicio del curs
      * @return El mes de l'edicio del curs.
      * @throws Si el curs no té un mes, envia l'excepció «No existeix el mes de l'edicio curs»
   */
   public function obtenirMes() {
      if ($this->mes==null)
         throw new Exception('',812);
      return $this->mes;
   }

   /**
      * @brief Obtens les hores de l'edicio del curs
      * @return Les hores de l'edicio del curs.
      * @throws Si l'edició no té hores, envia l'excepció «No existeix les hores de l'edicio curs»
   */
   public function obtenirHores() {
      if ($this->hores==null)
         throw new Exception('',813);
      return $this->hores;
   }

   /**
      * @brief Obtens el curs escolar de l'edicio del curs
      * @return El curs escolar de l'edicio del curs.
      * @throws Si el curs no té un curs escolar, envia l'excepció «No existeix el curs escolar de l'edicio curs»
   */
   public function obtenirCursEscolar() {
      if ($this->curs_escolar==null)
         throw new Exception('',814);
      return $this->curs_escolar;
   }

   /**
      * @brief Obtens el codi fiss de l'edicio del curs
      * @return El codi fiss de l'edicio del curs.
   */
   public function obtenirCodiFiss() {
      return $this->codi_fiss;
   }


   /**
      * @brief Obtens la data d'inici de l'edicio del curs
      * @return La data d'inici de l'edicio del curs.
      * @throws Si el curs no té una data d'inici, envia l'excepció «No existeix la data d'inici de l'edicio curs»
   */
   public function obtenirDataInici() {
      if ($this->datai==null)
         throw new Exception('',816);
      return $this->datai;
   }

   /**
      * @brief Obtens la data de fi de l'edicio del curs
      * @return La data de fi de l'edicio del curs.
      * @throws Si el curs no té una data de fi, envia l'excepció «No existeix la data de fi de l'edicio curs»
   */
   public function obtenirDataFi() {
      if ($this->dataf==null)
         throw new Exception('',817);
      return $this->dataf;
   }

   /*
      * @brief Obtens els perfils del curs
      * @return Si el curs té un llista de perfils, retorna la llista de perfil de la edicio. Altrament, null.
   */
   public function obtenirPerfils() {
      if ($this->perfils==null and count($this->perfils)==0)
         return null;
      else
         return $this->perfils;
   }

   /*
      * @brief Obtens el perfil del curs
      * @param $posicio La posicio de la llista de perfils corresponent al curs.
      * @return Si el curs té un perfil i la posicio existeix a la llista de perfils, retorna el perfil del curs. Altrament, null.
   */
   public function obtenirPerfil($posicio) {
      if ($this->obtenirPerfils() == null and count($this->obtenirPerfils())==0)
         return null;
      else if ($posicio>=0 && $posicio < count($this->obtenirPerfils()))
         return $this->perfils[$posicio];
      else
         return null;
   }

   /**
      * @brief Obtens l'estat de l'edicio del curs
      * @return L'estat de l'edicio del curs.
      * @throws Si l'edició del curs no té un estat, envia l'excepció «No existeix l'estat de l'edicio curs»
   */
   public function obtenirEstat() {
      if ($this->estat==null)
         throw new Exception('',818);
      return $this->estat;
   }

   public function esCDD() {
      return $this->cdd;
   }

   /**
   * @brief Obtens el curs
   * @return El el curs.
   * @throws Si la edició no té un curs, envia l'excepció «No existeix el curs»
   */
   private function obtenirCurs() {
      if ($this->curs==null)
         throw new Exception('',810);
      return $this->curs;
   }
   /**
   * @brief Obtens el curs
   * @return El el curs.
   * @throws Si la edició no té un curs, envia l'excepció «No existeix el curs»
   */
   private function obtenirTipusCurs() {
      if ($this->tipusCurs==null)
         throw new Exception('',811);
      return $this->tipusCurs;
   }

   /**
      * @brief Obtens el codi gtaf de l'edicio del curs
      * @return El codi gtaf de l'edicio del curs.
      * @throws Si el curs no té un codi gtaf, envia l'excepció «No existeix el codi gtaf de l'edicio curs»
   */
   public function obtenirCodiGtaf() {
      return $this->codi_gtaf;
   }

   /**
      * @brief Obtens la data de resolució de l'edicio del curs
      * @return la data de resolució  de l'edicio del curs.
      * @throws Si el curs no té la data de resolució, envia l'excepció «No existeix la data de resolució  de l'edicio curs»
   */
   public function obtenirDataRes() {
      return $this->data_res;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
   /*
      * @brief Mostra l'edició amb el format a la pestanya Info
      * @return Mostra l'edició amb data d'inici i data de fi amb un enllaç a la inscripció, el codi
      gtaf i la data de resolució. Si l'edició està tancada, mostra Edició Completa i no tens l'enllaç
      per accedir a la inscripció. Si l'edició ha començat, mostre «Últims dies!». Si existeix alguna
      descompte vigent i el curs del descompte correspont al curs de l'edició, o és TOTS o el curs
      correspon al nombre d'hores correspon al nombre d'hores de l'edició del curs, i el mes del
      descompte correspon al mes de l'edició o és TOTS, apareixerà «Descompte del X%!» on X és el
      percentatge de descompte.
   */
   public function mostrarEdicioInfo($nomCurs) {
      $dataI=$this->obtenirDataInici();
      $dataIL=$dataI->convertirDataLlarga();
      $dataIT=$dataI->obtenirText();

      $objDataF=$this->obtenirDataFi();
      $textDataF=$objDataF->obtenirText();
      $dataFL=$objDataF->convertirDataLlarga();
      $dateDataF = new DateTime($textDataF);
      $diaDataF=$dateDataF->format('j');
      // echo $diaDataF." ";
      $dataFPref = $dataFL;
      if ( $diaDataF == "1" or $diaDataF == "11" ) $dataFPref = " a l'".$dataFL;
      else $dataFPref = " al ".$dataFL;

      $cursEsc=$this->obtenirCursEscolar()->obtenirText();
      if ($dataRes=$this->obtenirDataRes()!=null)
         $dataRes=$this->obtenirDataRes()->obtenirText();
      else
         $dataRes=null;
      if ($codiGtaf=$this->obtenirCodiGtaf()!=null)
         $codiGtaf=$this->obtenirCodiGtaf()->obtenirText();
      else
         $codiGtaf=null;
      $any=$this->obtenirAny()->obtenirNumero();
      $mes=$this->obtenirMes()->obtenirText();
      $urlInsc="https://www.prisma.cat/inscripcions/".$nomCurs."/".$mes."/".$any;

      require_once 'Date.php';

      $objDateI = new Date($dataI->obtenirText());
   	$objDateF = new Date($objDataF->obtenirText());

      $pronomDel = $objDateI->getPronomDel();
      $objText = new Text($pronomDel);
      $pronomDel = $objText->convertirMajPrimLletra();

   	if ( $objDateI->getAny() != $objDateF->getAny() ) {
   		//De l'1 de desembre de 2021 al 15 de febrer de 2022
   		//De l'1 de desembre de 2021 a l'11 de febrer de 2022
   		//Del 2 de desembre de 2021 al 15 de febrer de 2022
   		//Del 2 de desembre de 2021 a l'11 de febrer de 2022
   		$textDates = $pronomDel."".$objDateI->getDataLlarga()."
   		".$objDateF->getPronomAl()."".$objDateF->getDataLlarga()."";
   	}
   	else {
   		if ( $objDateI->getMes() != $objDateF->getMes() ) {
   			//Del 4 d'abril a l'11 de maig de 2022
   			$textDates = $pronomDel."".intval($objDateI->getDia())."
   			".$objDateI->getNomMesArticle()."
   			".$objDateF->getPronomAl()."".$objDateF->getDataLlarga()."";
   		}
   		else {
   			//Del 4 al 31 de juliol de 2022
   			$textDates = $pronomDel."".intval($objDateI->getDia())."
   			".$objDateF->getPronomAl()."".$objDateF->getDataLlarga()."";
   		}
   	}
   	$datesRealitzacio = $textDates;

      if ($this->obtenirEstat()->obtenirText()=='T'){ //Edició completa
         // $mostrar = "Del dia ".$dataIL.$dataFPref." ";
         $mostrar = $datesRealitzacio." ";
         $mostrar.="<span class='codigtaf'>(".$cursEsc;
         if ( $this->obtenirTipusCurs()->obtenirText() != 'S' ) {
         if ($dataRes != null)
            $mostrar .= " / ".$codiGtaf;
         else
            $mostrar .= "*";
          }
         $mostrar .= ")</span> ";
         $mostrar.="<span class='ultimsdies'>Edició completa!</span>";

         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();

         //si existeix el camp de la llista espera a params i està actiu, el curs tancat mostrarà llista d'espera
         $cns = "SELECT ID FROM params WHERE TIPUS=? AND VALOR=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)";
         $stmt = $connexio->prepare($cns);
         $stmt->bind_param("ss", $tipus, $codiCurs);
         $tipus='llistaespera';
         $codiCurs=$this->obtenirCurs()->obtenirCodi()->obtenirText();
         $stmt->execute();
         $stmt->bind_result($idParam);
         $stmt->store_result();
         if ($stmt->num_rows() > 0) {
            $mostrar .= " <span role='button' class='llista-espera font-weight-bold'
            data-toggle='modal' data-target='#modalLlista'>Vols que t'avisem si s'allibera una plaça?</span>";

            $mostrar .= "<div class='modal fade in' id='modalLlista' tabindex='-1' role='dialog' aria-labelledby='modalLlistaTitle' aria-hidden='true'>";
            $mostrar .= "<div class='modal-dialog modal-dialog-centered modal-notify modal-success justify-content-center text-center' role='document'>";
            $mostrar .= "<div class='modal-content w-100 border-0'><div class='modal-header border-0 text-white background-prisma'>";
            $mostrar .= "<p class='modal-title modal-title-success text-white m-0' id='modalLlistaTitle'>Vols que t'avisem si s'allibera una plaça?</p>";
            $mostrar .= "<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button></div>";
            $mostrar .= "<div class='modal-body' id='modalLlistaBody'>";
            $mostrar .= "<div class='formulari-llista-espera flex-column'>";
            $mostrar .= "<p>Completa les dades següents i
            en cas que hi hagi una plaça disponible ens
            posarem en contacte amb tu (seguint l'ordre d'arribada de les
            sol·licituds de la llista d'espera).</p>";
            $mostrar .= "<div class='d-flex flex-row w-100'>";
            $mostrar .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $mostrar .= "<label for='nom'><span class='camp'>Nom</span><span class='req'>*</span></label>";
            $mostrar .= "<input type='text' class='form-control' id='nom' aria-required='' required=''>";
            $mostrar .= "<span id='nom_erroni'></span></div></div>";
            $mostrar .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $mostrar .= "<label for='cog'><span class='camp'>Cognoms</span><span class='req'>*</span></label>";
            $mostrar .= "<input type='text' class='form-control' id='cog' aria-required='' required=''>";
            $mostrar .= "<span id='cog_erroni'></span></div></div>";
            $mostrar .= "</div>";
            $mostrar .= "<div class='d-flex flex-row w-100'>";
            $mostrar .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $mostrar .= "<label for='email'><span class='camp'>Adreça electrònica</span><span class='req'>*</span></label>";
            $mostrar .= "<input type='email' class='form-control' id='email' aria-required='' required=''>";
            $mostrar .= "<span id='correu_erroni'></span></div></div>";
            $mostrar .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $mostrar .= "<label for='telf'><span class='camp'>Telèfon</span><span class='req'>*</span></label>";
            $mostrar .= "<input type='tel' class='form-control' id='telf' aria-required='' required='' maxlength='9'>";
            $mostrar .= "<span id='telf_erroni'></span></div></div>";
            $mostrar .= "</div>";
            $mostrar .= "<label for='comprovaSpam' class='comprovaSpam d-none'>Si veus això, no omplis el camp!</label>";
            $mostrar .= "<input id='comprovaSpam' name='comprovaSpam' class='comprovaSpam d-none' value=''>";
            $mostrar .= "<input id='edicio' name='edicio' class='comprovaSpam d-none' value='".$mes."'>";
            $mostrar .= "<input id='curs' name='curs' class='comprovaSpam d-none' value='".$codiCurs."'>";
            $mostrar .= "</div>
               <div id='loading-wrapper' style='display: none'>
                  <div id='loading-text'>Enviant...</div>
                  <div id='loading-content'></div>
               </div>
               <div id='modalSuccessLlistaBody' style='display: none'>
            </div>

            </div>";
            $mostrar .= "<div class='modal-footer justify-content-center text-center border-0 pt-0'>";
            $mostrar .= "<button role='button' id='form_enviar_dades' class='boto-blau ";
            $mostrar .= "border-0 border-radius-2 text-center negreta500 text-white m-0'>";
            $mostrar .= "Envia</button>";
            $mostrar .= "</div></div></div></div>";
         }
         $connexio->closeStmt();
         $connexio->desconectarBD();
      }
      else {
         $mostrar="<a role='link' class='font-weight-bold' href='".$urlInsc."'>";
         // $mostrar.= "Del dia ".$dataIL.$dataFPref."</a> ";
         $mostrar.= "".$datesRealitzacio."</a> ";
         // $mostrar.= "Del dia ".$dataIL." al ".$dataFL;
         if ( $this->obtenirTipusCurs()->obtenirText() != 'S' ) {
         $mostrar.=" <span class='codigtaf'>(".$cursEsc;
         if ($dataRes != null)
            $mostrar .= " / ".$codiGtaf;
         else
            $mostrar .= "*";
          }
         $mostrar .= ")</span>. ";

         require_once 'ConnexioBBDD_PreparedStatment.php';
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();

         $consDesc = "SELECT PERCENTATGE, FINS_AL FROM descomptes WHERE TIPUS > 10 AND
                     DATAI<=CURRENT_TIMESTAMP AND (MES='TOTS' OR MES=?) AND
                     (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL) AND ID_PREU=? AND
                     (CURS=? OR CURS=? OR CURS='TOTS') ORDER BY ORDRE";
         $stmtDesc = $connexio->prepare($consDesc);
         $stmtDesc->bind_param("sdsd", $mes, $idPreu, $cursDesc, $horesDesc);
         $idPreu=$this->obtenirCurs()->obtenirPreu()->obtenirNumero();
         $cursDesc=$this->obtenirCurs()->obtenirCodi()->obtenirText();
         $horesDesc=$this->obtenirHores()->obtenirNumero();
         $stmtDesc->execute();
         $stmtDesc->store_result();
         $stmtDesc->bind_result($percentatge, $finsal);
         $descomptes=[]; $i=0;
         while ($stmtDesc->fetch()) {
            //Comprovar que el descompte es pot aplicar en les edicions obertes
            $edicioOberta=true;
            //S'aplica el descompte a les edicions obertes
            if ($mes == 'TOTS' or $edicioOberta) {
               //Empleno el vector dels descomptes.
               $cntVectDesc=0;
               $exDecVect=false;
               //Miro si existeix el descompte a la taula descomptes
               while ($cntVectDesc<=count($descomptes) && !$exDecVect) {
                  if ($descomptes[$cntVectDesc][1]==$percentatge)
                     $exDecVect=true;
                  else
                     $cntVectDesc++;
               }

               if ($exDecVect) $descomptes[$cntVectDesc][0][]=$mes;
               else $descomptes[$i]=[[$mes], $percentatge, $finsal];

               $i++;
            }
         }

         for ($i=0; $i<count($descomptes); $i++) {
            $percentatge=$descomptes[$i][1];
            $mostrar.="<span class='ultimsdies'>Descompte del ".$percentatge."% ".$finsal."!</span> ";
         }

         if (date($dataIT) <= date("Y-m-d") && date("Y-m-d") >= date($dataIT)) {
            $mostrar.="<span class='ultimsdies'>Últims dies!</span>";
         }
      }

      return $mostrar;
   }

   /*
      * @brief Mostra l'edició amb el format a la pestanya Info
      * @return Mostra l'edició amb data d'inici i data de fi amb un enllaç a la inscripció, el codi
      gtaf i la data de resolució. Si l'edició està tancada, mostra Edició Completa i no tens l'enllaç
      per accedir a la inscripció. Si l'edició ha començat, mostre «Últims dies!». Si existeix alguna
      descompte vigent i el curs del descompte correspont al curs de l'edició, o és TOTS o el curs
      correspon al nombre d'hores correspon al nombre d'hores de l'edició del curs, i el mes del
      descompte correspon al mes de l'edició o és TOTS, apareixerà «Descompte del X%!» on X és el
      percentatge de descompte.
   */
   public function mostrarEdicioInfoMixt($nomCurs) {
      $dataI=$this->obtenirDataInici();
      $dataIL=$dataI->convertirDataLlarga();
      $dataIT=$dataI->obtenirText();

      $objDataF=$this->obtenirDataFi();
      $textDataF=$objDataF->obtenirText();
      $dataFL=$objDataF->convertirDataLlarga();
      $dateDataF = new DateTime($textDataF);
      $diaDataF=$dateDataF->format('j');
      // echo $diaDataF." ";
      $dataFPref = $dataFL;
      if ( $diaDataF == "1" or $diaDataF == "11" ) $dataFPref = " a l'".$dataFL;
      else $dataFPref = " al ".$dataFL;

      $cursEsc=$this->obtenirCursEscolar()->obtenirText();
      if ($dataRes=$this->obtenirDataRes()!=null)
         $dataRes=$this->obtenirDataRes()->obtenirText();
      else
         $dataRes=null;
      if ($codiGtaf=$this->obtenirCodiGtaf()!=null)
         $codiGtaf=$this->obtenirCodiGtaf()->obtenirText();
      else
         $codiGtaf=null;
      $any=$this->obtenirAny()->obtenirNumero();
      $mes=$this->obtenirMes()->obtenirText();
      $urlInsc="https://www.prisma.cat/inscripcions/".$nomCurs."/".$mes."/".$any;

      require_once 'Date.php';

      $objDateI = new Date($dataI->obtenirText());
   	$objDateF = new Date($objDataF->obtenirText());

      $pronomDel = $objDateI->getPronomDel();
      $objText = new Text($pronomDel);
      $pronomDel = $objText->convertirMajPrimLletra();

   	if ( $objDateI->getAny() != $objDateF->getAny() ) {
   		//De l'1 de desembre de 2021 al 15 de febrer de 2022
   		//De l'1 de desembre de 2021 a l'11 de febrer de 2022
   		//Del 2 de desembre de 2021 al 15 de febrer de 2022
   		//Del 2 de desembre de 2021 a l'11 de febrer de 2022
   		$textDates = $pronomDel."".$objDateI->getDataLlarga()."
   		".$objDateF->getPronomAl()."".$objDateF->getDataLlarga()."";
   	}
   	else {
   		if ( $objDateI->getMes() != $objDateF->getMes() ) {
   			//Del 4 d'abril a l'11 de maig de 2022
   			$textDates = $pronomDel."".intval($objDateI->getDia())."
   			".$objDateI->getNomMesArticle()."
   			".$objDateF->getPronomAl()."".$objDateF->getDataLlarga()."";
   		}
   		else {
   			//Del 4 al 31 de juliol de 2022
   			$textDates = $pronomDel."".intval($objDateI->getDia())."
   			".$objDateF->getPronomAl()."".$objDateF->getDataLlarga()."";
   		}
   	}
   	$datesRealitzacio = $textDates;

      if ($this->obtenirEstat()->obtenirText()=='T'){ //Edició completa
         // $mostrar = "Del dia ".$dataIL.$dataFPref." ";
         $mostrar = $datesRealitzacio." ";
         $mostrar.="<span class='codigtaf'>(".$cursEsc;
         if ( $this->obtenirTipusCurs()->obtenirText() != 'S' ) {
         if ($dataRes != null)
            $mostrar .= " / ".$codiGtaf;
         else
            $mostrar .= "*";
          }
         $mostrar .= ")</span> ";
         $mostrar.="<span class='ultimsdies'>Inscripcions tancades!</span>";

         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();

         //si existeix el camp de la llista espera a params i està actiu, el curs tancat mostrarà llista d'espera
         $cns = "SELECT ID FROM params WHERE TIPUS=? AND VALOR=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)";
         $stmt = $connexio->prepare($cns);
         $stmt->bind_param("ss", $tipus, $codiCurs);
         $tipus='llistaespera';
         $codiCurs=$this->obtenirCurs()->obtenirCodi()->obtenirText();
         $stmt->execute();
         $stmt->bind_result($idParam);
         $stmt->store_result();
         if ($stmt->num_rows() > 0) {
            $mostrar .= " <span role='button' class='llista-espera font-weight-bold'
            data-toggle='modal' data-target='#modalLlista'>Vols que t'avisem si s'allibera una plaça?</span>";

            $mostrar .= "<div class='modal fade in' id='modalLlista' tabindex='-1' role='dialog' aria-labelledby='modalLlistaTitle' aria-hidden='true'>";
            $mostrar .= "<div class='modal-dialog modal-dialog-centered modal-notify modal-success justify-content-center text-center' role='document'>";
            $mostrar .= "<div class='modal-content w-100 border-0'><div class='modal-header border-0 text-white background-prisma'>";
            $mostrar .= "<p class='modal-title modal-title-success text-white m-0' id='modalLlistaTitle'>Vols que t'avisem si s'allibera una plaça?</p>";
            $mostrar .= "<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button></div>";
            $mostrar .= "<div class='modal-body' id='modalLlistaBody'>";
            $mostrar .= "<div class='formulari-llista-espera flex-column'>";
            $mostrar .= "<p>Completa les dades següents i
            en cas que hi hagi una plaça disponible ens
            posarem en contacte amb tu (seguint l'ordre d'arribada de les
            sol·licituds de la llista d'espera).</p>";
            $mostrar .= "<div class='d-flex flex-row w-100'>";
            $mostrar .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $mostrar .= "<label for='nom'><span class='camp'>Nom</span><span class='req'>*</span></label>";
            $mostrar .= "<input type='text' class='form-control' id='nom' aria-required='' required=''>";
            $mostrar .= "<span id='nom_erroni'></span></div></div>";
            $mostrar .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $mostrar .= "<label for='cog'><span class='camp'>Cognoms</span><span class='req'>*</span></label>";
            $mostrar .= "<input type='text' class='form-control' id='cog' aria-required='' required=''>";
            $mostrar .= "<span id='cog_erroni'></span></div></div>";
            $mostrar .= "</div>";
            $mostrar .= "<div class='d-flex flex-row w-100'>";
            $mostrar .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $mostrar .= "<label for='email'><span class='camp'>Adreça electrònica</span><span class='req'>*</span></label>";
            $mostrar .= "<input type='email' class='form-control' id='email' aria-required='' required=''>";
            $mostrar .= "<span id='correu_erroni'></span></div></div>";
            $mostrar .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $mostrar .= "<label for='telf'><span class='camp'>Telèfon</span><span class='req'>*</span></label>";
            $mostrar .= "<input type='tel' class='form-control' id='telf' aria-required='' required='' maxlength='9'>";
            $mostrar .= "<span id='telf_erroni'></span></div></div>";
            $mostrar .= "</div>";
            $mostrar .= "<label for='comprovaSpam' class='comprovaSpam d-none'>Si veus això, no omplis el camp!</label>";
            $mostrar .= "<input id='comprovaSpam' name='comprovaSpam' class='comprovaSpam d-none' value=''>";
            $mostrar .= "<input id='edicio' name='edicio' class='comprovaSpam d-none' value='".$mes."'>";
            $mostrar .= "<input id='curs' name='curs' class='comprovaSpam d-none' value='".$codiCurs."'>";
            $mostrar .= "</div>
               <div id='loading-wrapper' style='display: none'>
                  <div id='loading-text'>Enviant...</div>
                  <div id='loading-content'></div>
               </div>
               <div id='modalSuccessLlistaBody' style='display: none'>
            </div>

            </div>";
            $mostrar .= "<div class='modal-footer justify-content-center text-center border-0 pt-0'>";
            $mostrar .= "<button role='button' id='form_enviar_dades' class='boto-blau ";
            $mostrar .= "border-0 border-radius-2 text-center negreta500 text-white m-0'>";
            $mostrar .= "Envia</button>";
            $mostrar .= "</div></div></div></div>";
         }
         $connexio->closeStmt();
         $connexio->desconectarBD();
      }
	    else {
         // $mostrar="<a role='link' class='font-weight-bold' href='".$urlInsc."'>";
         // $mostrar.= "Del dia ".$dataIL.$dataFPref."</a> ";
         $mostrar.= "".$datesRealitzacio;
         // $mostrar.= "Del dia ".$dataIL." al ".$dataFL;
         if ( $this->obtenirTipusCurs()->obtenirText() != 'S' ) {
         $mostrar.=" <span class='codigtaf'>(".$cursEsc;
         if ($dataRes != null)
            $mostrar .= " / ".$codiGtaf;
         else
            $mostrar .= "*";
          }
         $mostrar .= ")</span>";

         if (
           $this->obtenirCurs()->cursEsMixt() &&
           (
             $this->obtenirMes()->obtenirText() == '07' ||
             $this->obtenirMes()->obtenirText() == '08'
           )
         ) {
           $mostrar.=" <span class='ultimsdies mr-1'>Intensiu!</span>";
         }
         else {
           $mostrar.=".";
         }

         require_once 'ConnexioBBDD_PreparedStatment.php';
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();

         $consDesc = "SELECT PERCENTATGE, FINS_AL FROM descomptes WHERE TIPUS > 10 AND
                     DATAI<=CURRENT_TIMESTAMP AND (MES='TOTS' OR MES=?) AND
                     (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL) AND ID_PREU=? AND
                     (CURS=? OR CURS=? OR CURS='TOTS') ORDER BY ORDRE";
         $stmtDesc = $connexio->prepare($consDesc);
         $stmtDesc->bind_param("sdsd", $mes, $idPreu, $cursDesc, $horesDesc);
         $idPreu=$this->obtenirCurs()->obtenirPreu()->obtenirNumero();
         $cursDesc=$this->obtenirCurs()->obtenirCodi()->obtenirText();
         $horesDesc=$this->obtenirHores()->obtenirNumero();
         $stmtDesc->execute();
         $stmtDesc->store_result();
         $stmtDesc->bind_result($percentatge, $finsal);
         $descomptes=[]; $i=0;
         while ($stmtDesc->fetch()) {
            //Comprovar que el descompte es pot aplicar en les edicions obertes
            $edicioOberta=true;
            //S'aplica el descompte a les edicions obertes
            if ($mes == 'TOTS' or $edicioOberta) {
               //Empleno el vector dels descomptes.
               $cntVectDesc=0;
               $exDecVect=false;
               //Miro si existeix el descompte a la taula descomptes
               while ($cntVectDesc<=count($descomptes) && !$exDecVect) {
                  if ($descomptes[$cntVectDesc][1]==$percentatge)
                     $exDecVect=true;
                  else
                     $cntVectDesc++;
               }

               if ($exDecVect) $descomptes[$cntVectDesc][0][]=$mes;
               else $descomptes[$i]=[[$mes], $percentatge, $finsal];

               $i++;
            }
         }

         for ($i=0; $i<count($descomptes); $i++) {
            $percentatge=$descomptes[$i][1];
            $mostrar.="<span class='ultimsdies'>Descompte del ".$percentatge."% ".$finsal."!</span> ";
         }

         if (date($dataIT) <= date("Y-m-d") && date("Y-m-d") >= date($dataIT)) {
            $mostrar.="<span class='ultimsdies'>Últims dies!</span>";
         }
      }

      return $mostrar;
   }

   /*
      * @brief Mostra el text de l'edició amb la data d'inici i de fi.
      * @return  Mostra el text de l'edició amb el format "Del dia " + data d'inici
      * amb el format DD del MM del YYYY + " al " + data de fi  amb el format DD del
      * MM del YYYY on DD és el dia corresponent a la data sense zeros, MM és el
      * mes escrit i YYYY és l'any. Per exemple: 22 de maig del 2021
   */
   public function mostrarEdicio() {
      $dataI=$this->obtenirDataInici();

      $dataIL=$dataI->convertirDataLlarga();
      $dataIT=$dataI->obtenirText();

      $objDataF=$this->obtenirDataFi();
      $textDataF=$objDataF->obtenirText();
      $dataFL=$objDataF->convertirDataLlarga();
      $dateDataF = new DateTime($textDataF);
      $diaDataF=$dateDataF->format('j');

      $dataFPref = $dataFL;
      if ( $diaDataF == "1" or $diaDataF == "11" ) $dataFPref = " a l'".$dataFL;
      else $dataFPref = " al ".$dataFL;

      $mostrar.= "del dia ".$dataIL.$dataFPref."</a> ";
      return $mostrar;
   }

   /*
   */
   public function mostrarSessionsSincronesEdicioInfo() {
      $any = $this->any->obtenirNumero();
      $mes = $this->mes->obtenirText();
      $codiCurs=$this->obtenirCurs()->obtenirCodi()->obtenirText();

      $mostrar = "";
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consultaEdicio = "SELECT NUM_SESSIO, DATA_SESSIO FROM sessions_sincrones WHERE
      ANY=? AND MES=? AND CURS=? AND ESTAT=1 ORDER BY NUM_SESSIO";
      $sentencia = $connexio->prepare($consultaEdicio);
      $sentencia->bind_param("dss", $any, $mes, $codiCurs);
      $sentencia->execute();
      $sentencia->bind_result($num_sessio, $data_sessio);
      $mostrar .= "<ul class='llistes mb-0'>";
      while ($sentencia->fetch()) {
         $objDate = new Date( $data_sessio );
         $artucleEl = $objDate->getPronomEl();
         $objText = new Text($artucleEl);
         $artucleEl = $objText->convertirMajPrimLletra();

         $textDates = $artucleEl."".$objDate->getDataLlarga();

         $mostrar .= "<li>
               <span class='font-weight-bold'>Sessió ".$num_sessio."</span>.
               ".$textDates.".
         </li>";
      }

	  $consultaHoraris = "SELECT VALOR FROM params WHERE
      TIPUS=? AND VALOR LIKE ? AND DATAF IS NULL";
      $sentencia = $connexio->prepare($consultaHoraris);
	  $tipus="horari-sessions-sincrones";
	  $valor=$any."|".$mes."|".$codiCurs."|"."%";
      $sentencia->bind_param("ss", $tipus, $valor);
      $sentencia->execute();
      $sentencia->bind_result($text_horari);
      $sentencia->store_result();
      if ( $sentencia->num_rows() == 0 )
	  	$text_horari="de <span class='font-weight-bold'>17:30 a 19:30 h</span>";
	  else {
	  	$sentencia->fetch();
	  	$text_horari=str_replace($any."|".$mes."|".$codiCurs."|",' ',$text_horari);
	  }

      $mostrar .= "<p class='pb-3'>L’horari de les videotrucades per Zoom serà ".$text_horari.". L'assistència a totes les sessions síncrones forma part dels requisits per superar el curs.</p>";
      $mostrar .= '</ul>';

      $connexio->closeStmt();
      $connexio->desconectarBD();

      return $mostrar;
   }

}
?>
