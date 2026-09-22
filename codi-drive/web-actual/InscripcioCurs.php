<?php
/**
* @class InscripcioCurs
* @brief Conté tota la informació relacionada amb una InscripcioCurs.
*/
class InscripcioCurs {
   private $tipus; /** Text Codi d'una Inscripcio ex: ACRE */
   private $tipusCurs; /** Text Codi d'una Inscripcio ex: ACRE */
   private $codi; /** Text Codi d'una Inscripcio ex: ACRE */
   private $titol; /** Text El titol de la jorna. ex. Alumnat amb Altes Capacitats */
   private $url; /** URL L'enllaç de la Inscripcio. Si no n'hi ha, valdrà null  */
   private $dispositiu; /** string Mobil si el dispositiu és mobil i altrament, ordinador */
   private $hores; /** Numero Les hores de la Inscripcio ex: 40 */
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
         $cns = "SELECT CODI_CURS, TITOL, TIPUS_CURS FROM informacio WHERE ID_AMIGABLE=? AND ESTAT=1";
         $stm = $connexio->prepare($cns);
         $stm->bind_param("d", $idUrl);
         $stm->execute();
         $stm->store_result();
         if ( $stm->num_rows() <= 0 ) {
            $this->estat=0;
         }
         else {
            $stm->bind_result($codi, $titol, $tipusCurs);
            $stm->fetch();
         }
         $connexio->closeStmt();

         $consultaHoresPreu = "SELECT HORES, CURS_ESCOLAR, GTAF, ID_PREU FROM curs
            as c WHERE (CURS LIKE ? AND GTAF IS NOT NULL AND GTAF!='' AND
            ((DATAI + 7 > CURRENT_DATE AND (c.HORES=30 OR c.HORES = 40 OR c.HORES = 60))
            OR (DATAI + 14 > CURRENT_DATE AND (c.HORES=100))) AND (CURS NOT LIKE '%JOR%')
            AND (CURS NOT LIKE '%0%')) ORDER BY ANY, MES LIMIT 1";
         $stmtHoresPreu = $connexio->prepare($consultaHoresPreu);
         $stmtHoresPreu->bind_param("s", $codi);
         $stmtHoresPreu->execute();
         $stmtHoresPreu->store_result();

         if ( $stmtHoresPreu->num_rows() > 0 ) {
            $stmtHoresPreu->bind_result($hores, $cursEscolar, $gtaf, $idPreu);
            $stmtHoresPreu->fetch();
            $connexio->closeStmt();
         }
         else {
            $cnsHoresLastEd="SELECT HORES
            FROM curs WHERE CURS LIKE ? AND PUBLIC=1
            AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
            ORDER BY ANY DESC, MES DESC LIMIT 1";
            $stmtLastEd = $connexio->prepare($cnsHoresLastEd);
            $stmtLastEd->bind_param("s", $codi);
            $stmtLastEd->execute();
            $stmtLastEd->bind_result($hores);
            $stmtLastEd->fetch();
            $connexio->closeStmt();

            $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
            $stmtParam = $connexio->prepare($cnsParams);
            // echo $cnsParams."<br />";
            $stmtParam->bind_param("ss", $tipus, $orderBy);
            $tipus='dies-inscriu-cursos';
            $orderBy='VALOR';
            $stmtParam->execute();
            $stmtParam->bind_result($valor);
            $diesObets=0;
             while ($stmtParam->fetch()) {
                $valors = explode('|',$valor);
                if (count($valors) != 2)
                   $diesObets=0;
                else if (intval($valors[0])>0 && $valors[0]==$hores) //si el valor és un numero i les hores son iguals al curs
                   $diesObets = $valors[1];
                else if (intval($valors[0])<=0 && $valors[0]==$codi) //si el valor no és un numero i el codi és igual al curs
                   $diesObets = $valors[1];
             }
             $connexio->closeStmt();

             $consultaHoresPreu = "SELECT HORES, CURS_ESCOLAR, GTAF, ID_PREU FROM curs WHERE
                DATAI+?>CURRENT_DATE AND CURS LIKE ? AND PUBLIC=1
                AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
                ORDER BY ANY, MES LIMIT 1";
             $stmtHoresPreu=$connexio->prepare($consultaHoresPreu);
             $stmtHoresPreu->bind_param("ds", $diesObets, $codi);
             $stmtHoresPreu->execute();
             $stmtHoresPreu->store_result();
             if ( $stmtHoresPreu->num_rows() > 0 ) {
               $stmtHoresPreu->bind_result($hores, $cursEscolar, $gtaf, $idPreu);
               $stmtHoresPreu->fetch();
             }
             $connexio->closeStmt();
         }

         require_once 'Text.php';
         if ( $tipusCurs!=null AND $tipusCurs!='' )
            $this->tipusCurs = $tipusCurs;
         else
            $this->tipusCurs = null;

         if ( $codi!=null AND $codi!='' )
            $this->codi = new Text($codi);
         else
            $this->codi = null;

         if ( $titol!=null AND $titol!='' )
            $this->titol = new Text($titol);
         else
            $this->titol = null;

         require_once 'Numero.php';
         if ($hores!=null AND $hores!='')
            $this->hores = new Numero($hores);
         else
            $this->hores = null;
      }
   }


   public function cursEsSubvencionat() {
     return ( $this->tipusCurs == 'S' );
   }
   public function cursEsNormal() {
     return ( $this->tipusCurs == 'N' );
   }
   public function cursEsRepte() {
     return ( $this->tipusCurs == 'R' );
   }

   /**
   * @brief Comprova si el curs té CDD
   * @return Retirna si el curs té CDD
   */
   public function placesExharuidesSubv() {
      $complet = 0;

      $codiCurs = $this->codi->obtenirText();

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consultaCurs = "SELECT ANY, MES FROM curs AS c INNER JOIN informacio AS i ON c.CURS = i.CODI_CURS
      WHERE i.TIPUS_CURS = 'S' AND c.ESTAT = '1' AND c.CURS = ? AND PUBLIC = 1";
      $stmtInfo=$connexio->prepare($consultaCurs);
      $stmtInfo->bind_param("s", $codiCurs);
      $stmtInfo->execute();
      $stmtInfo->bind_result($any, $mes);
      $stmtInfo->store_result();

      if ( $stmtInfo->num_rows() <= 0 )
         $complet = 1;
      $connexio->closeStmt();
      $connexio->desconectarBD();
      return $complet;
   }

   public function esSubvencionat() {
      $subv = 0;

      $codiCurs = $this->codi->obtenirText();

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consultaCurs = "SELECT ANY, MES FROM curs AS c INNER JOIN informacio AS i
      WHERE i.TIPUS_CURS = 'S' AND c.ESTAT = '1' AND c.CURS = ? AND PUBLIC = 1";
      $stmtInfo=$connexio->prepare($consultaCurs);
      $stmtInfo->bind_param("s", $codiCurs);
      $stmtInfo->execute();
      $stmtInfo->bind_result($any, $mes);
      $stmtInfo->store_result();

      if ( $stmtInfo->num_rows() <= 0 )
         $complet = 1;
      $connexio->closeStmt();
      $connexio->desconectarBD();
      return $complet;
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció «1301»
   */
   private function obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',1301);
      return $this->titol;
   }

   /*
   * @brief Obtens el codi del curs
   * @return Si el curs té un codi curs, retorna el codi del curs del curs. Altrament, null.
   * @throws Si el curs no té un codi curs, envia l'excepció «1303»
   */
   private function obtenirCodiCurs() {
      if ($this->codi==null)
         throw new Exception('',1303);
      return $this->codi;
   }

   /*
   * @brief Obtens les hores del curs
   * @return Si el curs té unes hores, retorna les hores del curs. Altrament, null.
   * @throws Si el curs no té unes ures, envia l'excepció «1304»
   */
   private function obtenirHores() {
      if ($this->hores==null)
         throw new Exception('',1304);
      return $this->hores;
   }

   /*
   * @brief Mostra el llistat d'edicions disponibles del curs en el que es vol inscriure
   * @return Retorna el llistat d'edicions disponibles del curs en el que es vol inscriure
   */
   private function __buscarEdicions() {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $mostrar='';

      $codi = $this->obtenirCodiCurs()->obtenirText();
      $hores = $this->obtenirHores()->obtenirNumero();

      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);

      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='limit-editions';
      if ( $this->cursEsSubvencionat() ) $tipus = 'limit-editions-subv';
      $orderBy='DATAI';
      $stmtParam->execute();
      $stmtParam->bind_result($limitEd);
      $stmtParam->fetch();

      $tipus='dies-inscriu-cursos';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $diesObets=0;
      while ($stmtParam->fetch()) {
         $valors = explode('|',$valor);
         if (count($valors) != 2)
            throw new Exception('',1304);
         else if (intval($valors[0])>0 && $valors[0]== $hores) //si el valor és un numero i les hores son iguals al curs
            $diesObets = $valors[1];
         else if (intval($valors[0])<=0 && $valors[0]== $codi) //si el valor no és un numero i el codi és igual al curs
            $diesObets = $valors[1];
      }
      $connexio->closeStmt();

      $diesOberts = $diesObets;
      // if ( $codi == 'GED' || $codi == 'SDA' ) $diesOberts = 15;

      $cnsEd = "SELECT DATAI, DATAF, ANY, MES, c.ESTAT FROM curs AS c INNER JOIN aula AS a ON
         c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
         INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE c.CURS=? AND PUBLIC=1
         AND c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1 AND
         (DATEDIFF(DATE_ADD(DATAI, INTERVAL ? DAY),CURRENT_DATE)>0) AND c.CURS NOT LIKE '%JOR%' AND
         (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
         OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
         AND ORDRE_TUTOR is not NULL)) GROUP BY ANY, MES ORDER BY ANY, MES LIMIT ?";
      $stmtEd = $connexio->prepare($cnsEd);
      // echo $cnsEd." ".$codi." ".$diesOberts;
      $stmtEd->bind_param("sds", $codi, $diesOberts, $limitEd);
      $stmtEd->execute();
      // $stmtEd->bind_result($datai, $dataf, $cursEscolar, $fiss, $gtaf, $dataRes, $any, $mesDesc, $curs, $dni, $hores, $estatEd);
      $stmtEd->bind_result($datai, $dataf, $any, $mesDesc, $estatEd);
      require_once 'Edicio.php';
      while ( $stmtEd->fetch() ){
         if ($estatEd!='T') {
            $textDataI = new Text($datai);
            $textDataF = new Text($dataf);

            $textDataF2=$textDataF->obtenirText();
            $dataFL=$textDataF->convertirDataLlarga();

            $dateDataF = new DateTime($textDataF2);
            $diaDataF=$dateDataF->format('j');
            $dataFPref = $dataFL;
            if ( $diaDataF == "1" or $diaDataF == "11" ) $dataFPref = " a l'".$dataFL;
            else $dataFPref = " al ".$dataFL;

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
            $mostrar .= "<li class='border-bottom m-0' id='dates-".$any."-".$mesDesc."'>";
            $mostrar .= "<a href='#'>Del dia ".$dataIL.$dataFPref."</a></li>";
         }
      }

      $connexio->closeStmt();
      $connexio->desconectarBD();

      return $mostrar;
   }

   /*
   * @brief Retorna una alerta ssi existeix a la BD que ha d'apareixer aquesta alerta
   * @return Reviso si el curs té una alerta per posar a la inscripció.
   Si el curs disposa d'una alerta, es torna una alerta en un contenidor amb una
   estetica amb una exclamació i el missatge que existeix al base de dades
   */
   private function alertaInscripcio() {
      $codiCurs = $this->obtenirCodiCurs()->obtenirText();

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      // Cercar paramatre X per veure si el curs actual té una alerta d'inscripció
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("sss", $tipus, $valor, $orderBy);
      $tipus = 'alerta-inscripcio';
      $valor = $codiCurs.'%';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->store_result();
      // Si té una alerta d'inscripció
      if ( $stmtParam->num_rows() > 0 ) {
         $stmtParam->bind_result($valorRes);
         $stmtParam->fetch();
         $arrValors = explode('|',$valorRes);
         $mostrar = $this->__mostrarAlertaInscripcio( $arrValors[1] );
      }
      else {
         $mostrar = '';
      }
      $connexio->closeStmt();

      return $mostrar;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Mostra la pàgina d'inscripció d'un curs
   * @return Retorna el contingut de la pàgina d'inscripció d'un curs
   */
   public function mostrar() {
      $mostrar="<div class='d-flex flex-column'>";
      $mostrar .= "<h1 class='mb-4'>Formulari d'inscripci&oacute</h1>
      <h2 class='pt-3 mt-0'>Curs: <span class='nom-curs'>".$this->obtenirTitol()->obtenirTextHTML()."</span></h2>";
	  if (($this->cursEsSubvencionat() && !$this->placesExharuidesSubv()) || ($this->cursEsNormal())) {
			  $mostrar .= $this->__mostrarDadesPersonals();
			  $mostrar .= $this->__mostrarDadesCurriculars();
			  $mostrar .= $this->__mostrarDadesCurs();
			  $mostrar .= $this->__mostrarFinal();
			  $mostrar .= $this->__modalError();
			  $mostrar .= $this->__modalSuccess();
			  $mostrar .= $this->__modalInscripcioDuplicada();
			  $mostrar .= "</div>";
			  $mostrar .= $this->__modalLoading();
			  $mostrar .= $this->__modalEnviant();
			  $mostrar .= $this->__modalInfoPreu();
	   }
	   else {
		  $mostrar .= "<p style='font-size: 1.1rem'>
			 Actualment les places d'aquest curs estan exhaurides.
			 Tot i això, si hi estàs interessat,
			 pots posar-te en contacte amb nosaltres a través del 
			 <a class='font-weight-bold' target='_self'
			 href='https://www.prisma.cat/contacte'>formulari de contacte</a>
			 i, en cas que s'alliberi una plaça del curs, 
			 <strong>Secretaria</strong> es posarà en contacte amb tu.
		  </p>
		  ";
	   }
      return $mostrar;
   }

   /*
   * @brief Mostra l'apartat de dades personals de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades personals de la pàgina d'inscripció d'un curs
   */
   private function __mostrarDadesPersonals() {
      $mostrar = "<div class='form-dades'>
         <h3>Dades personals</h3>";
      if ( !$this->cursEsSubvencionat() )
        $mostrar.= "<p class='text-justify mb-3'>Si ja has realitzat altres cursos amb nosaltres, se t'aplicarà el descompte d'«Alumnes PrisMa» automàticament en <strong>introduir les teves dades personals en el formulari d'inscripció</strong>.</p>";

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
            <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("telf", "Telèfon de contacte", "tel", "telf_erroni", "1")."</div>
         </div>".$this->__modalCorreuValid()."
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
      <li class='border-bottom m-0' id='titulacio-estudiant' class='estudiant'><a href='#'>Encara no tinc cap titulaci&oacute, Sóc estudiant de</a></li>";

      $mostrar = "<div class='form-dades'>
      <h3>Dades curriculars</h3>
      <div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>
         <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
            ".$this->mostrarSelect('perfil', 'Estic treballant a', $llistatPerfils, '', 'perfil_erroni')."
         </div>
         <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
            ".$this->mostrarSelect('titulacio', 'Tinc la titulaci&oacute de', $llistatTitulacio, '', 'titulacio_erroni')."
         </div>
      </div>
      <div class='' id='perfils-altres'></div>";

      if ( $this->cursEsSubvencionat() )
         $mostrar .= "<div class='' id='perfils-subv'></div>";

      $mostrar .= "<div class='' id='titulacions-altres'></div>
      <div class='' id='titulacions-secundaria'></div>
      <div class='' id='titulacions-estudiant'></div>
      <div class='d-flex flex-row algin-items-center justify-content-center'>
         <div class='col-12 pl-0 pr-0 pr-md-2'>".$this->mostrarInput("titol_de", "També tinc la titulaci&oacute de", "text", "", "0")."</div>
      </div>
      </div>";
      return $mostrar;
   }

   /*
   * @brief Mostra l'apartat de dades del curs de la pàgina d'inscripció d'un curs
   * @return Retorna el contingut del apartat de dades del curs de la pàgina d'inscripció d'un curs
   */
   private function __mostrarDadesCurs() {
      $checkCarnetJove = "<div class='form-group field-wrap position-relative'>
         <input class='checksNoAccum mr-2' type='checkbox' name='carnetjove' id='carnetjove'
         value='Carnet Jove'><font id='text_carnet_jove'>Sóc titular d’un Carnet Jove vigent</font>
      </div>";
      $checkCarnetUSOC = "<div class='form-group field-wrap position-relative'>
         <input class='checksNoAccum mr-2' type='checkbox' name='usoc' id='usoc'
         value='USOC'><font id='text_usoc'>Sóc titular d’un carnet d'afiliació a la USOC vigent</font>
      </div>";
      $checkCarnetDiscapacitat = "<div class='form-group field-wrap position-relative'>
         <input class='checksNoAccum mr-2' type='checkbox' name='discapacitat' id='discapacitat'
         value='Discapacitat'><font id='text_discapacitat'>Sóc titular d’un carnet de discapacitat > 33% vigent</font>
      </div>";
      $checkCarnetFamiliaNumerosa = "<div class='form-group field-wrap position-relative'>
         <input class='checksNoAccum mr-2' type='checkbox' name='familia_numerosa' id='familia_numerosa'
         value='Familia numerosa'><font id='text_familia_numerosa'>Sóc titular d’un carnet de família nombrosa vigent</font>
      </div>";
      $checkCarnetFamiliaMono = "<div class='form-group field-wrap position-relative'>
         <input class='checksNoAccum mr-2' type='checkbox' name='familia_mono' id='familia_mono'
         value='Familia monoparental'><font id='text_familia_mono'>Sóc titular d’un carnet de família monoparental vigent</font>
      </div>";
      $checkVictimaViolencia = "<div class='form-group field-wrap position-relative'>
         <input class='checksNoAccum mr-2' type='checkbox' name='victima_violencia' id='victima_violencia'
         value='Víctima de violència de gènere'><font id='victima_violencia'>Sóc víctima de violència de gènere</font>
      </div>";
      $checkCarnetFile = "<div id='cnt_input_carnet' class='form-group field-wrap position-relative d-none'>
         <font id='text_carnet'>Adjunta la foto del carnet:</font> <input class='mr-2' type='file' name='carnet' id='carnet'>
         <span id='carnet_erroni'></span>
         <p class='mt-2'>
         Les dades de les acreditacions es mantindran exclusivament per verificar el descompte corresponent i seran eliminades un cop finalitzat aquest procés, garantint la seva confidencialitat.
         </p>
      </div>";
      $checkFraccio = "<div class='form-group field-wrap position-relative'>
         <input type='checkbox' name='fraccionat' id='pagament_fraccionat' class='mr-1'
         value='Pagament fraccionat'><font id='text_fraccionat'>
         Vull fraccionar el pagament (sense recàrrec)</font>
      </div>";
      $checkNovell = "<div id='cnt_input_novell' class='form-group field-wrap position-relative'>
         <input type='checkbox' name='recent_titulat' id='recent_titulat' class='mr-1'
         value='Fa menys d’un any que m’he titulat com a docent'><font id='text_soc_recent_titulat'>
         Fa menys d’un any que m’he titulat com a docent</font>
      </div>";
      $checkFileResguard = "<div id='cnt_input_resguard' class='form-group field-wrap position-relative d-none'>
         <font id='text_carnet'>Adjunta la foto del resguard o el títol:</font> <input class='mr-2' type='file' name='resguard' id='resguard'>
         <span id='resguard_erroni'></span>
         <p>Un cop comprovada la validesa del títol i s’hagi formalitzat la inscripció, rebràs un codi promocional per descomptar aquest import al pròxim curs de PrisMa que realitzis.</p>
      </div>";

      //Select dates
      $cntEdicions = "<div id='dates_curs' class='d-flex flex-row algin-items-center justify-content-center'>
      <div class='col-12 pl-0 pr-0 pr-md-2'>
         ".$this->mostrarSelect('dates', "Durant quines dates vols realitzar el curs? Tria l'edició", $this->__buscarEdicions(), '', 'dates_erroni')."
      </div>
      </div>";

      //Input codi promocional
      $cntCodiProm = "<div id='cnt-codis-promocionals' class='d-flex flex-column algin-items-center justify-content-center'>
         <div class='col-12 pl-0 pr-0 pr-md-2'>
            ".$this->mostrarInput("promo", "Codi promocional", "text", "promo_erroni", "0")."
         </div>
         <div id='text-promo-info' class='col-12 pl-0 pr-0 pr-md-2'></div>
      </div>";

      $mostrar = "<div class='form-dades'>";
      $mostrar .= "<h3>Dades del curs</h3>";
      $mostrar .= $cntEdicions;
      $mostrar .= "<div id='preu'><p>Preu</p></div>";
      if ( !$this->cursEsSubvencionat() ) {
        $mostrar .= $checkFraccio;
        if ( $this->obtenirCodiCurs()->obtenirText() == 'JASOM' ) {
          $mostrar .= $checkNovell;
          $mostrar .= $checkFileResguard;
        }
        $mostrar .= "<p><strong>Descomptes a aplicar</strong></p>";
        $mostrar .= $checkCarnetJove;
        $mostrar .= $checkCarnetDiscapacitat.$checkCarnetFamiliaNumerosa.$checkCarnetFamiliaMono.$checkVictimaViolencia;
        $mostrar .= $checkCarnetUSOC;
        $mostrar .= $checkCarnetFile;
        $mostrar .= $cntCodiProm;
      }
      if ( $this->cursEsSubvencionat() ) {
        $mostrar .= "<div>
          <img class='w-100 my-2'
            src='https://www.prisma.cat/img/generalitat-catalunya.png'
            style='max-width: 250px'
          >
          <img
            class='w-100 my-2 px-2 ml-2'
            src='https://www.prisma.cat/img/next-generation.png'
            style='max-width: 250px'
          >
        </div>";
      }
      $mostrar .= "<div id='missInformatiuEdicioRec'></div>
      <div id='missInformatiuEdicioPerf'></div>
      <div id='cnt-codis-promocionals'></div>";

      $mostrar.= $this->alertaInscripcio();
      $mostrar .= "";

      $mostrar .= "</div>";
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
      <div class='' id='txtHint_mailing'></div>

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
   * @brief Retorna una alerta amb el missatge $missatgeAlerta
   * @return Retorna una alerta en un contenidor amb una estetica amb una exclamació i el missatge $missatgeAlerta
   */
   private function __mostrarAlertaInscripcio( $missatgeAlerta ) {
      $mostrar = "<div style='background: #e8ecf5 !important; border: none'
      class='prisma-contact border-radius-2 px-3 pb-1 pt-3 my-3'>";
      $mostrar .= $missatgeAlerta;
      $mostrar .= "</div>";

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
   					<button role='button' data-dismiss='modal' class='boto-blau-disable border-0 border-radius-2 text-center negreta500 m-0 mr-3'>Cancel·la</button>
                  <button role='button' id='confirmInsc' class='boto-blau border-0 border-radius-2 text-center negreta500 text-white m-0'>Vull inscriure'm</button>
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

   /*
   * @brief Mostra un modal de càlcul del preu
   * @return Retorna un modal càlcul del preu
   */
   private function __modalInfoPreu() {
      $mostrar .= "
      <div class='modal fade in' id='modalInfoPreu' tabindex='-1' role='dialog' aria-labelledby='modalInfoPreu' aria-hidden='true'>
         <div class='modal-dialog modal-dialog-centered modal-notify modal-prisma justify-content-center text-center' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-header border-0 justify-content-center'>
                  <p class='modal-title m-0 text-center' id='modalTitleInfoPreu'>CÀLCUL DEL PREU</p>
                  <button role='button' class='close position-absolute' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>×</span></button>
               </div>
               <div class='modal-body' id='modalBodyInfoPreu'></div>
               <div class='modal-footer justify-content-center text-center border-0 pt-0 mb-2'>
                  <button role='button' data-dismiss='modal' class='boto-blau
                     border-0 border-radius-2 text-center negreta500 text-white m-0'>
                     Tanca</button>
               </div>
            </div>
         </div>
      </div>";
   	return $mostrar;
   }

}
?>
