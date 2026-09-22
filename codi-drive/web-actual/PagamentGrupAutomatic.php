<?php
/**
* @class PagamentGrupAutomatic
* @brief Conté tota la informació relacionada amb una pagament.
*/
class PagamentGrupAutomatic {
   private $any; /** Numero: Any de la inscripció */
   private $mes; /** Text Mes en digits de la inscripció. ex. 03 */
   private $curs; /** Text Curs de la inscripcio. ex. ACRE */
   private $aPagar; /** Numero El valor que té pagar. ex. 90  */
   private $pagament; /** Numero El valor que ha pagat. ex. 90 */
   private $dni; /** Text El dni de la Inscripcio ex: 77922662L */
   private $idpag; /** Text El IDPAG de la Inscripcio ex: 10825 */
   private $tipusInsc; /** Text El tipus de la inscripció ex: 'D' or 'G' */
   private $titol; /** Numero El titol del curs de la Inscripcio ex: Coaching per a Docents */
   private $hores; /** Numero Les hores del curs de la Inscripcio ex: 40 */
   private $datai; /** Text La data d'inici de l'edició del curs de la Inscripcio ex: 2020-10-01 */
   private $dataf; /** Text La data fi de l'edició del curs de la Inscripcio ex: 2020-10-01 */
   private $email; /** Text El email de la Inscripcio ex: suport@prisma.cat */
   private $cursos; /** Text El email de la Inscripcio ex: suport@prisma.cat */
   private $edicions; /** Array Llistat amb les Edicions disponibles */
   private $codi; /**  */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/

   public function __construct($idPag) {
      $this->idpag = $idPag;
      $this->edicions = [];
      $this->fraccInsc = false;
      $this->tipusInsc =  '';

      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $connexio2 = new ConnexioBBDDSTMT();
      $connexio2->connectarBD();

      $connexio3 = new ConnexioBBDDSTMT();
      $connexio3->connectarBD();

      $connexio4 = new ConnexioBBDDSTMT();
      $connexio4->connectarBD();

      $cnsGroup = "SELECT i.ANY, i.MES, i.CURS, c.NOM_CURS, c.HORES, c.DATAI, c.DATAF,
      i.A_PAGAR, SUM(i.PAGAMENT), rg.CORREU, i.DNI, SUM(A_PAGAR) AS total, c.CURS_ESCOLAR,
      c.FISS, c.GTAF, c.DATA_RESOL, c.ESTAT
      FROM inscripcions as i INNER JOIN curs as c
      ON i.ANY = c.ANY and i.MES = c.MES and i.CURS = c.CURS
      INNER JOIN respGrups AS rg ON i.IDPAG = rg.IDPAG
      WHERE rg.IDPAG = ? AND TIPUS_INSC = 'G' AND (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS`='M')
      AND GTAF IS NOT NULL AND GTAF!='' GROUP BY rg.IDPAG";

      $cnsInscPack = "SELECT SUM(i.A_PAGAR), SUM(i.PAGAMENT), i.CORREU, i.DNI, i.OBSERVACIONS, FRACCIONAT
         FROM inscripcions AS i WHERE IDPAG = ? AND TIPUS_INSC = 'P' AND
         (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS`='M') GROUP BY IDPAG";

      $cnsInscripcionsPack = "SELECT ANY, MES, CURS
      FROM inscripcions AS i WHERE IDPAG = ? AND TIPUS_INSC = 'P' AND
      (`INSC CURS` = '0' OR `INSC CURS` = '1' OR `INSC CURS`='M')";

      $cnsInfoPack = "SELECT TITOL FROM info_pack WHERE ID_PACK = ?";

      $cnsInfoCurs = "SELECT c.DATAI, c.DATAF, c.CURS_ESCOLAR, c.FISS, c.GTAF,
      c.DATA_RESOL, c.HORES, i.ID, i.ID_AMIGABLE, c.ESTAT
   		FROM curs AS c INNER JOIN informacio AS i ON c.CURS = i.CODI_CURS
   		WHERE ANY = ? AND MES = ? AND CURS = ? AND c.PUBLIC=1 AND c.CURS!='PROVA'
   		AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND i.ESTAT = 1
   		AND c.CURS NOT LIKE '%JOR%' ORDER BY c.DATAI";

      require_once 'Text.php';
      require_once 'Numero.php';

      if ( $stmt=$connexio->prepare($cnsGroup) ) {
         $stmt->bind_param("d", $idPag);
         $stmt->execute();
         $stmt->store_result();
         if ( $stmt->num_rows() > 0 ) {
            $this->tipusInsc = 'G';

   			$stmt->bind_result($any, $mes, $curs, $nomCurs, $hores, $datai, $dataf,
            $apagar, $pagament, $correu, $dni, $totalPag, $cursEscolar, $fiss,
            $gtaf, $dataRes, $estatEd);

            $stmt->fetch();

            if ($any!=null and $any!='')
               $this->any = new Numero($any);
            else
               $this->any = null;
            if ($mes!=null and $mes!='')
               $this->mes = new Text($mes);
            else
               $this->mes = null;
            if ($curs!=null and $curs!='')
               $this->curs = new Text($curs);
            else
               $this->curs = null;
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

            if ($totalPag!=null and $totalPag!='')
               $this->aPagar = new Numero($totalPag);
            else
               $this->aPagar = null;

            if ($pagament!=null and $pagament!='')
               $this->pagament = new Numero($pagament);
            else
               $this->pagament = null;

            if ($dni!=null and $dni!='')
               $this->dni = new Text($dni);
            else
               $this->dni = null;
            if ($correu!=null and $correu!='')
               $this->email = new Text($correu);
            else
               $this->email = null;
            if ($idPag==0)
               throw new Exception('',2401);

            $this->edicions[0] = new Edicio($curs, "N", "ordinador", $any, $mes,
            $hores, $datai, $dataf, $cursEscolar, $gtaf, $dataRes, $dniTutor, $fiss, $estatEd, "0");

         }
         $connexio->closeStmt();
      }
      else {
         throw new Exception('',2402);
      }

      if ( $this->tipusInsc == '' ) {
        $stmtAlumne=$connexio->prepare($cnsInscPack);
        $stmtInscAlumne=$connexio2->prepare($cnsInscripcionsPack);
        $stmtInfoPack=$connexio3->prepare($cnsInfoPack);
        $stmtInfoCurs=$connexio4->prepare($cnsInfoCurs);

        if (!$stmtAlumne) {
          throw new Exception('',24013);
        }
        if (!$stmtInscAlumne) {
          throw new Exception('',24023);
        }
        if (!$stmtInfoPack) {
          throw new Exception('',24033);
        }
        if (!$stmtInfoCurs) {
          throw new Exception('',24043);
        }

        $stmtAlumne->bind_param("d", $idPag);
        $stmtInscAlumne->bind_param("d", $idPag);
        $stmtInfoPack->bind_param("d", $idInfoPack);
        $stmtInfoCurs->bind_param("dds", $anyInsc, $mesInsc, $cursInsc);
        $this->tipusInsc = 'P';

        /* Calculem les dades de la inscripcio */
        $stmtAlumne->execute();
        $stmtAlumne->store_result();
        if ( $stmtAlumne->num_rows() > 0 ) {
          $stmtAlumne->bind_result($totalPag, $pagament, $correu, $dni, $observacions, $fraccInsc);
          $stmtAlumne->fetch();

          // echo $cnsInscPack."<br>";
          // echo $totalPag." ".$pagament." ".$correu." ".$dni." ".$observacions." ".$fraccInsc."<br>";

          if ($totalPag!=null and $totalPag!='')
             $this->aPagar = new Numero($totalPag);
          else
             $this->aPagar = null;

          if ($pagament!=null and $pagament!='')
             $this->pagament = new Numero($pagament);
          else
             $this->pagament = null;

          if ($dni!=null and $dni!='')
             $this->dni = new Text($dni);
          else
             $this->dni = null;

          if ($correu!=null and $correu!='')
             $this->email = new Text($correu);
          else
             $this->email = null;

          if ($fraccInsc == 0) $this->fraccInsc = false;
          else $this->fraccInsc = true;

          //A partir d'observacions, busco el idpag
          $arrObs = explode(' ',$observacions);
          $i = 0; $trobat = 0;
          while ( $i < count($arrObs) && !$trobat ) {
             if ( explode('|', $arrObs[$i])[0] == 'PACK' ) {
                $idPack = explode('|', $arrObs[$i])[1];
                $trobat = 1;
             }
             $i++;
          }

          if ($idPack!=null and $idPack!='')
             $this->codi = new Text($idPack);
          else
             $this->codi = null;

          $idInfoPack = $idPack;
          $stmtInfoPack->execute();
          $stmtInfoPack->bind_result($titolPack);
          $stmtInfoPack->fetch();

          if ($titolPack!=null and $titolPack!='')
            $this->titol = new Text($titolPack);
          else
            $this->titol = null;

          $cnt = 0;

          require_once 'EdicioPack.php';
          $stmtInscAlumne->execute();
          $stmtInscAlumne->bind_result($anyEd, $mesEd, $cursEd);
          while ( $stmtInscAlumne->fetch() ) {
            $anyInsc = $anyEd;
            $mesInsc = $mesEd;
            $cursInsc = $cursEd;

            $stmtInfoCurs->execute();
            $stmtInfoCurs->bind_result($dataiEd, $datafEd, $cursEscEd, $fissEd, $gtafEd,
            $dataResEd, $horesEd, $idInfoEd, $idUrl, $estatEd);
            $stmtInfoCurs->fetch();

            $this->edicions[$cnt] = new EdicioPack($cursEd, $idUrl, $dispositiu,
            $anyEd, $mesEd, $horesEd, $dataiEd, $datafEd, $cursEscEd, $gtafEd,
            $dataResEd, $fissEd, $estatEd);

            $this->edicions[$cnt]->setInfo();
            $cnt++;
          }

        }
        $connexio4->closeStmt();
        $connexio3->closeStmt();
        $connexio2->closeStmt();
        $connexio->closeStmt();
      }

      $connexio4->desconectarBD();
      $connexio3->desconectarBD();
      $connexio2->desconectarBD();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció 2405
   */
   private function obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',2405);
      return $this->titol;
   }

   /*
   * @brief Obtens les hores del curs
   * @return Les hores del curs
   * @throws Si el curs no té unes hores, envia l'excepció 2406
   */
   private function obtenirHores() {
      if ($this->hores==null)
         throw new Exception('',2406);
      return $this->hores;
   }

   /*
   * @brief Obtens el preu del curs que s'ha de pagar
   * @return Obtens el preu del curs que s'ha de pagar
   * @throws Si el curs no té un preu, envia l'excepció 2409
   */
   private function obtenirPreuAPagar() {
      if ($this->aPagar==null)
         throw new Exception('',2409);
      return $this->aPagar;
   }

   /**
   * @brief Obtens el codi del curs
   * @return El codi del curs.
   * @throws Si el curs no té un codi, envia l'excepció 2404
   */
   public function obtenirCodi() {
      if ($this->curs==null)
         throw new Exception('',2404);
      return $this->curs;
   }

   /**
   * @brief Obtens la data d'inici de l'edició del curs que es vol pagar
   * @return Obtens la data d'inici de l'edició del curs que es vol pagar
   * @throws Si el curs no té un dati, envia l'excepció 2407
   */
   public function obtenirDataIniciEdicio() {
      if ($this->datai==null)
         throw new Exception('',2407);
      return $this->datai;
   }

   /**
   * @brief Obtens la data de fi de l'edició del curs que es vol pagar
   * @return Obtens la data de fi de l'edició del curs que es vol pagar
   * @throws Si el curs no té un dataf, envia l'excepció 2408
   */
   public function obtenirDataFiEdicio() {
      if ($this->dataf==null)
         throw new Exception('',2408);
      return $this->dataf;
   }

   /*
   * @brief Obtens les edicions
   * @return Obtens les edicions del curs
   * @throws Si el curs no té una llista d'edicions, envia l'excepció «No existeixen edicions del curs»
   */
   private function __obtenirEdicions() {
      if ($this->edicions==null)
         throw new Exception('', 2411);
      return $this->edicions;
   }

   /*
   * @brief Obtens una edició del curs
   * @return Obtens l'edicio corresponent a la posicio $posicio de la llista d'edicions
   */
   private function __obtenirEdicio($posicio) {
      if ($this->__obtenirEdicions() == null and count($this->__obtenirEdicions())==0)
         throw new Exception('', 2411);
      else if ($posicio>=0 && $posicio < count($this->__obtenirEdicions()))
         return $this->edicions[$posicio];
      else
         throw new Exception('', 2411);
   }

   /**
   * @brief Obtens el mes de l'edició del curs
   * @return Obtens el mes de l'edició del curs
   * @throws Si el curs no té una edició, envia l'excepció 1509
   */
   public function obtenirMesEdicio() {
      if ($this->mes==null)
         throw new Exception('',xx);
      return $this->mes;
   }

   /*
   * @brief Obtens el preu del curs que s'ha pagat
   * @return Obtens el preu del curs que s'ha pagat
   * @throws Si el curs no té un preu, envia l'excepció 2410
   */
   private function obtenirPreuPagat() {
      if ($this->pagament==null)
         throw new Exception('',2410);
      return $this->pagament;
   }

   /*
   * @brief Obtens el correu de la inscripció
   * @return Obtens el correu de la inscripció
   * @throws Si la encriptacio no té un correu, envia l'excepció 1514
   */
   private function obtenirCorreu() {
      if ($this->email==null)
         throw new Exception('',xxx);
      return $this->email;
   }

   /*
   * @brief Obtens el dni de la inscripció
   * @return Obtens el dni de la inscripció
   * @throws Si la encriptacio no té un dni, envia l'excepció 1515
   */
   private function obtenirDni() {
      if ($this->dni==null)
         throw new Exception('',xx);
      return $this->dni;
   }
   /*
   * @brief Obtens el idpag de la inscripció
   * @return Obtens el idpag de la inscripció
   */

   private function obtenirIdPag() {
      return $this->idpag;
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
   * @brief Obtens els texts dels cursos dels packs
   * @return Obtens els texts dels cursos dels packs
   */
   private function textInfoPack() {
      $text = "";
      for ( $i = 0 ; $i < count($this->cursos); $i++ ) {
         //Això hauria de mostrar: Mans creatives ( del 2 de març al 3 de maig );
         $text .= "<li class='dada titol'><span class='font-weight-bold>'".$this->cursos[$i][0]."</span> (".$$this->cursos[$i][1].")</li>";
      }

      return $text;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
   /*
   * @brief Mostra la pàgina de pagament d'un curs
   * @return Mostra la pàgina de pagament d'un curs
   */
   public function mostrar() {
      $preuAPagar = floatval($this->obtenirPreuAPagar()->obtenirNumero());
      $preuPagat = floatval($this->obtenirPreuPagat()->obtenirNumero());
      $faltaPagar = $preuAPagar - $preuPagat;
      $preumarcat = false;

      $mostrar .= "<h1 class='mb-4'>Pagament d'inscripci&oacute</h1>";
      $titolPage = "Pagament d'inscripci&oacute";
      $titol = $this->obtenirTitol()->obtenirTextHTML();

      require_once 'Template.php';
      $templates = new Template();

      if ( $this->tipusInsc == 'G' ) {
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();

         $cnsNumCurs = "SELECT any, mes, curs FROM inscripcions WHERE idpag = ? GROUP BY any, mes, curs";
         if ( $stmt=$connexio->prepare($cnsNumCurs) ) {
            $stmt->bind_param("d", $idPag);
            $idPag = $this->idpag;
            $stmt->execute();
            $stmt->store_result();
            $numeroCursosDiff = $stmt->num_rows();
         }
         if ( $numeroCursosDiff > 1 ) {
            $cnsNumCurs2 = "SELECT i.any, i.mes, i.curs, a_pagar, c.datai, c.dataf, c.hores, c.id_preu
            FROM inscripcions as i INNER JOIN curs as c
            ON i.any = c.any and i.mes = c.mes and i.curs = c.curs WHERE idpag = ? ";
            $numPersona = 0;
            if ( $stmt=$connexio->prepare($cnsNumCurs2) ) {
               $stmt->bind_param("d", $idPag);
               $stmt->execute();
               $stmt->store_result();
               $numeroCursosDiff = $stmt->num_rows();
               $priceTotalOrig = 0;
               $priceTotalAct = 0;
               $stmt->bind_result($anyCurs, $mesCurs, $codiCurs, $aPagar, $datai, $dataf, $hores, $idPreu);
               $textDades = '';
               while ( $stmt->fetch() ) {
                  $numPersona++;
                  $nameCourse = $this->__searchNameCourse( $codiCurs );
                  $datesCourse = $this->__obtenirDatesLlarges($datai, $dataf);
                  $priceCourseOrig = $this->__getPriceOriginal( $anyCurs, $mesCurs, $codiCurs );
                  $priceCourseOffer = $aPagar;

                  $priceTotalOrig += $priceCourseOrig;
                  $priceTotalAct += $priceCourseOffer;

                  $textPrice = new Text($priceCourseOffer);
                  $textPriceCourseOffer = $textPrice->replace(".", ",");

                  $msg = $templates->getTemplate_Dades_RequadreDadesCursGrup();
               	$names_template = array("[NUM]", "[TITOL]", "[DATAI_DATAF]", "[HORES]", "[PAY_ORIG]", "[PAY_DESC]");
               	$names_function   = array($numPersona, $nameCourse, $datesCourse, $hores, $priceCourseOrig, $textPriceCourseOffer);
               	$textDades .= str_replace($names_template, $names_function, $msg);
               }
               $preumarcat = 1;
               $textPrice = new Text($priceTotalAct);
               $textPriceCourseOffer = $textPrice->replace(".", ",");
            }
         }
         else {
            $durada = $this->obtenirHores()->obtenirNumero();
            $dates = $this->__obtenirEdicio(0)->mostrarEdicio();
         }
      }
      else if ( $this->tipusInsc == 'P' ) {
         $textInfoPack = "";
         for ($i=0; $i<count($this->edicions); $i++) {
            $textInfoPack .= "<li>".$this->edicions[$i]->mostrarEdicioPagamentPack()."</li>";
         }
      }
      /* ########      VISTA TARGETA I TRANSFE      ######## */
      if ( $faltaPagar > 0 ) {
         $vistaPag = $this->__mostrarPagamentTargeta(1);
         $vistaPag .= $this->__mostrarPagamentTransferencia(1);
         $vistaPag .= $this->__modalError();
         $vistaPag .= $this->__modalSuccess();
      }
      else {
         $vistaPag .= "<p>L'import del curs està pagat en la seva totalitat</p>";
      }
      $vistaPag .= $this->__modalLoading();

      if ( $this->tipusInsc == 'G'  ) {
         $msg = $templates->getTemplate_Pagament_VistaGrup( $numeroCursosDiff, $this->obtenirFraccionat(), $preuPagat );
         $names_template = array("[TITOL_PAGE]", "[TITOL]", "[DATAI_FATAF]", "[DURADA]",
            "[PAY]", "[PRICE_PAY]", "[FALTA_PAY]", "[PAY_TOTAL_ORIG]", "[PAY_TOTAL_DESC]",
            "[DADES]", "[VISTA_PAY]");
         $names_function   = array($titolPage, $titol, $dates, $durada,
            $preuAPagar, $preuPagat, $faltaPagar, $priceTotalOrig, $textPriceCourseOffer,
            $textDades, $vistaPag);
         $mostrar = str_replace($names_template, $names_function, $msg);
      }
      else if ( $this->tipusInsc == 'P'  ) {
         $msg = $templates->getTemplate_Pagament_VistaPack( $numeroCursosDiff, $fracc );
         $names_template = array("[TITOL_PAGE]", "[TITOL]", "[PAY]", "[VISTA_PAY]",
            "[PREU_PAGAT]", "[FALTA_PAGAR]", "[TEXT_INFO_PACK]");
         $names_function   = array($titolPage, $titol, $preuAPagar, $vistaPag,
            $preuPagat, $faltaPagar, $textInfoPack);
         $mostrar = str_replace($names_template, $names_function, $msg);
      }

      return $mostrar;
   }

   /*
   * @brief Mostra les dates en text
   * @return Retorna les dates en text
   */
   private function __obtenirDatesLlarges($datai, $dataf) {
      require_once 'Date.php';
      $objDataI = new Date($datai);
      $objDataF = new Date($dataf);

      if ( $objDataI->getAny() != $objDataF->getAny() ) {
         //De l'1 de desembre de 2021 al 15 de febrer de 2022
         //De l'1 de desembre de 2021 a l'11 de febrer de 2022
         //Del 2 de desembre de 2021 al 15 de febrer de 2022
         //Del 2 de desembre de 2021 a l'11 de febrer de 2022
         $textDates = $objDataI->getPronomDel()."".$objDataI->getDataLlarga()."
         ".$objDataF->getPronomAl()."".$objDataF->getDataLlarga()."";
      }
      else {
         if ( $objDataI->getMes() != $objDataF->getMes() ) {
            //Del 4 d'abril a l'11 de maig de 2022
            $textDates = $objDataI->getPronomDel()."".intval($objDataI->getDia())."
            ".$objDataI->getNomMesArticle()."
            ".$objDataF->getPronomAl()."".$objDataF->getDataLlarga()."";
         }
         else {
            //Del 4 al 31 de juliol de 2022
            $textDates = $objDataI->getPronomDel()."".intval($objDataI->getDia())."
            ".$objDataF->getPronomAl()."".$objDataF->getDataLlarga()."";
         }
      }

      return $textDates;
   }

   /*
   * @brief Retorna el preu del curs $codiCurs, de l'edicio $mes i de l'any $any
   * @return Retorna el preu del curs $codiCurs, de l'edicio $mes i de l'any $any
   */
   private function __getPriceOriginal($any, $mes, $codiCurs) {
     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();

     $dadesCurs = "SELECT DATAI, DATAF, ID_PREU FROM curs WHERE
                          ANY = ? AND MES = ? AND CURS LIKE ? AND ESTAT = 1";
     $getPreuCar = "SELECT IMPORT FROM preu WHERE ID = ?
                           AND DATAI <= CURRENT_TIMESTAMP AND
                           (DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)";

     if ( $stmt=$connexio->prepare( $dadesCurs ) ) {
        $stmt->bind_param("dss", $any, $mes, $codiCurs);
        $stmt->execute();
        $stmt->bind_result($datai, $dataf, $idPreu);
        $stmt->fetch();

        $connexio->closeStmt();
     }
     else
        throw new Exception('',xxxx);

     if ( $stmt=$connexio->prepare( $getPreuCar ) ) {
        $stmt->bind_param("d", $idPreu);

        $stmt->execute();
        $stmt->bind_result($price);
        $stmt->fetch();

        $connexio->closeStmt();
     }
     else
        throw new Exception('',xxxx);

      $connexio->desconectarBD();

      return $price;

   }

   /*
   * @brief Retorna el nom del curs del curs amb codi $course
   * @return Retorna el nom del curs del curs amb codi $course
   */
   private function __searchNameCourse( $course ) {
     require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $cnsNomCurs = "SELECT TITOL FROM informacio WHERE CODI_CURS LIKE ? AND ESTAT = 1";

      if ( $stmt=$connexio->prepare( $cnsNomCurs ) ) {
         $stmt->bind_param("s", $course);
         $stmt->execute();
         $stmt->bind_result($nomCurs);
         $stmt->fetch();
         $connexio->closeStmt();
      }
      else
         throw new Exception('',xxxx);

      $connexio->desconectarBD();
      return $nomCurs;
   }


   /*
   * @brief Mostra la pàgina de confirmació d'una inscripció
   * @return Mostra la pàgina de confirmació d'una inscripció
   */
   public function mostrarPaginaConfirmacio() {
      $correu = $this->obtenirCorreu()->obtenirText();
      $preuAPagar = floatval($this->obtenirPreuAPagar()->obtenirNumero());
      $preuPagat = floatval($this->obtenirPreuPagat()->obtenirNumero());
      $faltaPagar = $preuAPagar - $preuPagat;

      $titolPagina = "Confirmació de la inscripció";
      $rebutConfirmacio = "el missatge de confirmació de la inscripció";

      require_once 'Template.php';
      $templates = new Template();

      $msg = $templates->getTemplate_Pagament_VistaPagHeader();
   	$names_template = array("[EMAIL]", "[PAY]", "[TITOL_PAGE]", "[REBUT_CONF]");
   	$names_function   = array($correu, $faltaPagar, $titolPagina, $rebutConfirmacio);
   	$mostrar = str_replace($names_template, $names_function, $msg);

      $mostrar .= $this->__mostrarPagamentTargeta(2);
      $mostrar .= $this->__modalError();
      $mostrar .= $this->__mostrarPagamentTransferencia(2);

      $mostrar .= $templates->getTemplate_Pagament_VistaPagFooter();
      $mostrar .= $this->__modalLoading();
      return $mostrar;
   }

   /*
   * @brief Mostra l'apartat del pagament amb targeta
   * @return Mostra l'apartat del pagament amb targeta. Si $tipus es 1, llavors mostra
            l'apartat segons la pagina de pagament. Si $tipus és 2, llavors mostra
            l'apartat segons la pagina de la confirmació de pagament
   */
   private function __mostrarPagamentTargeta( $vista ) {
      $aPagar = $this->obtenirPreuAPagar()->obtenirNumero();
      $preuPagat = $this->obtenirPreuPagat()->obtenirNumero();

      $faltaPagar = $aPagar - $preuPagat;

      require_once 'Template.php';
      $templates = new Template();
      $urlEfectPagament = "https://www.prisma.cat/efectPagGrupsAuto/";
      $urlEfectPagament = "https://www.prisma.cat/efectPagGrupsAutoProva/";

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

      if ( $this->obtenirFraccionat() ) {
         $valueInputPayOrig = $aPagar;
         $valueInputPayPayed = $preuPagat;
      }
      else {
         $valueInputPayOrig = $faltaPagar;
         $valueInputPayFalta = $faltaPagar;
         $valueInputPayPayed = $preuPagat;
      }

      $textInputs = "<input type='hidden' id='idPag' name='idPag' value='".$this->obtenirIdPag()."'>";
      $textInputs .= "<input type='hidden' id='dniInscrit' name='dniInscrit' value=\"".$this->obtenirDni()->obtenirText()."\">";
      $textInputs .= "<input type='hidden' id='email' name='email' value='".$this->obtenirCorreu()->obtenirText()."'>";

      $msg = $templates->getTemplate_Web_Pagaments_PagamentAmbTargeta( $vista, "R", 1, $this->obtenirFraccionat() );
   	$names_template = array("[PAY_ORIG]", "[PAY_PAGAT]", "[PAY_FALTA]",
         "[URL_PAY]", "[FORM_NOM]", "[FORM_DNI]", "[INPUT_FRACC]", "[INPUTS_HIDDEN]");
   	$names_function   = array($valueInputPayOrig, $valueInputPayPayed, $valueInputPayFalta,
         $urlEfectPagament, $formNom, $formDni, $formFracc, $textInputs);
   	$mostrar = str_replace($names_template, $names_function, $msg);

   	return $mostrar;
   }

   /*
   * @brief Mostra l'apartat del pagament amb targeta
   * @return Mostra l'apartat del pagament amb targeta. Si $tipus es 1, llavors mostra
            l'apartat segons la pagina de pagament. Si $tipus és 2, llavors mostra
            l'apartat segons la pagina de la confirmació de pagament
   */
   private function __mostrarPagamentTransferencia($vista) {
      require_once 'Template.php';
      $templates = new Template();

      $msg = $templates->getTemplate_Web_Pagaments_PagamentAmbTransferencies( $vista, $this->tipusInsc);
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
