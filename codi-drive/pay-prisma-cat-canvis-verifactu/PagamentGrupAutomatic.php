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
      $titol = $this->obtenirTitol()->obtenirTextHTML();
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
          $cnsNumCurs2 = "SELECT i.any, i.mes, i.curs, a_pagar, c.datai, c.dataf, c.hores, c.id_preu FROM inscripcions as i INNER JOIN curs as c
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

              $mostrar .= "<h3>Persona ".$numPersona."</h3>
              <div class='dades-amic px-4 pb-2 pt-3' style='background: #DDEDFF;'>
               <p class='dades'>Curs: <span class='dada titol'>".$nameCourse."</span></p>
               <p class='dades'>Dates: <span class='dada'>".$datesCourse."</span></p>
               <p class='dades'>Durada: <span class='dada'>".$hores." hores</span></p>
               <p class='dades'>Preu: <span class='dada' style='text-decoration: line-through; color: var(--text-light-gray);'>".$priceCourseOrig." euros</span>
               <span class='font-weight-bold'>".$textPriceCourseOffer." euros</span></p>
              </div>";
            }
            $preumarcat = 1;
            $textPrice = new Text($priceTotalAct);
            $textPriceCourseOffer = $textPrice->replace(".", ",");
            $mostrar .= "<h3 class='mb-4'>Preu total: <span class='tatxat'  style='text-decoration: line-through;color: #b2b2b2;;'>".$priceTotalOrig." euros</span> <span class='dada'>".$textPriceCourseOffer." euros</span></h3>";
          }
        }
        else {
         $durada = $this->obtenirHores()->obtenirNumero();
         $dates = $this->__obtenirEdicio(0)->mostrarEdicio();
         $mostrar .= "<p class='dades pt-3'>Curs: <span class='dada titol'>".$titol."</span></p>";
         $mostrar .= "<p class='dades'>Dates: <span class='dada'>".$dates."</span></p>";
         $mostrar .= "<p class='dades'>Durada: <span class='dada'>".$durada." hores</span></p>";
        }
      }
      else if ( $this->tipusInsc == 'P' ) {
         $textInfoPack = "";
         for ($i=0; $i<count($this->edicions); $i++) {
            $textInfoPack .= "<li>".$this->edicions[$i]->mostrarEdicioPagamentPack()."</li>";
         }
         $objText = new Text("Pack");
         $txt = $objText->obtenirTextHTML();

         $mostrar .= "<p class='dades pt-3'>".$txt.": <span class='dada titol'>".$titol."</span></>
         <ul>".$textInfoPack."</ul>";
      }
      if ( !$preumarcat ) {
        if (!$this->obtenirFraccionat())
           $mostrar .= "<p class='dades'>Preu: <span class='dada'>".$preuAPagar." euros</span></p>";
        else {
           $mostrar .= "<p>Has triat l'opció de pagament fraccionat (sense recàrrec).</p>";
           $mostrar .= "<p>Pots triar les quantitats i el termini del pagament sempre que facis un primer pagament abans de  l’inici del curs i que hagis abonat l’import complet com a màxim una setmana després de la finalització del curs.</p>";
           $mostrar .= "<p class='dades'>Preu: <span class='dada'>".$preuAPagar." euros</span></p></div>";
           $mostrar .= "<ul>
              <li class='dades'>Pagat: <span class='dada'>".$preuPagat." euros</span></li>
              <li class='dades'>Pendent: <span class='dada'>".$faltaPagar." euros</span></li>
           </ul>";
           $mostrar .= "</div>";
        }
        if ($preuPagat>0) {
           $mostrar .= "<p class='dades'>S'ha pagat: <span class='dada'>".$preuPagat." euros</span></p>";
           $mostrar .= "<p class='dades'>Falta pagar: <span class='dada'>".$faltaPagar." euros</span></p>";
        }
      }

      if ($faltaPagar>0) {
         $mostrar .= $this->__mostrarPagamentTargeta(1);
         $mostrar .= $this->__mostrarPagamentTransferencia(1);
         $mostrar .= $this->__modalError();
         $mostrar .= $this->__modalSuccess();
      }
      else {
         $mostrar .= "<p>L'import del curs està pagat en la seva totalitat</p>";
      }
      $mostrar .= $this->__modalLoading();
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
      $preuAPagar = floatval($this->obtenirPreuAPagar()->obtenirNumero());
      $preuPagat = floatval($this->obtenirPreuPagat()->obtenirNumero());
      $faltaPagar = $preuAPagar - $preuPagat;

      $mostrar="<div class='d-flex flex-column'>";
      $mostrar .= "<div class='container'><div class='row'>";
      $mostrar .= "<h1 class='mb-4'>Confirmació de la inscripció</h1>";
      $mostrar .= "<p>La teva sol·licitud ha estat enviada. Consulta la safata
                  d'entrada o el correu brossa (<em>spam</em>) de l'adreça
                  <span class='font-weight-bold email'>".$this->obtenirCorreu()->obtenirText()."</span>
                  per comprovar que has rebut el missatge de confirmació de la inscripció.</p>";
      $mostrar .= "<p>L'import a pagar és de <span class='font-weight-bold'>".$faltaPagar."</span> euros.</p>";
      $mostrar .= "</div></div>";
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
      $preuPagat = $this->obtenirPreuPagat()->obtenirNumero();

      $faltaPagar = $aPagar - $preuPagat;

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
      $mostrar .= "<form id='frm' name='frm' action='https://www.prisma.cat/efectPagGrupsAuto/' method='post'>";

      $mostrar .= "<input type='hidden' id='idPag' name='idPag' value='".$this->obtenirIdPag()."'>";
      $mostrar .= "<input type='hidden' id='dniInscrit' name='dniInscrit' value=\"".$this->obtenirDni()->obtenirText()."\">";
      $mostrar .= "<input type='hidden' id='email' name='email' value='".$this->obtenirCorreu()->obtenirText()."'>";

      if ($this->obtenirFraccionat())
         $mostrar .= "<input type='hidden' id='frac' name='frac' value='1'>";
      else
         $mostrar .= "<input type='hidden' id='frac' name='frac' value='0'>";

      $mostrar .= "<div class='row d-flex flex-column'>";
      $mostrar .= "<div class='d-flex flex-column algin-items-center justify-content-center'>";
      $mostrar .= "<div class='col-12'>";
      $mostrar .= "<div class='form-group field-wrap position-relative p-1 w-100'>";
      $mostrar .= "<label><span class='fons'></span><span class='camp'>Nom i cognoms del titular de la targeta</span>";
      $mostrar .= "<span class='req ml-1'>*</span></label>";
      $mostrar .= "<input type='text' class='form-control' id='nom-titular' name='nom-titular'>";
      $mostrar .= "<span id='nom_cognom_titular_erroni'></span></div></div>";
      $mostrar .= "</div>";

      $mostrar .= "<div class='d-flex flex-column flex-md-row algin-items-center justify-content-center'>";
      $mostrar .= "<div class='col-12 col-md-6'>";
      $mostrar .= "<div class='form-group field-wrap position-relative p-1 w-100'>";
      $mostrar .= "<div id='doc' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>";
      $mostrar .= "<span class='element-selected font-weight-normal w-100'>NIF/NIE de l'alumne/a</span>";
      $mostrar .= "<ul class='select-list position-absolute ' style='display: none;'>";
      $mostrar .= "<li class='border-bottom m-0' id='doc-dni'><a href='#'>NIF/NIE de l'alumne/a</a></li>";
      $mostrar .= "<li class='border-bottom m-0' id='doc-passaport'><a href='#'>Una altre documentació de l'alumne/a</a></li>";
      $mostrar .= "</ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i></div>";
      $mostrar .= "</div></div>";
      $mostrar .= "<div class='col-12 col-md-6' id='input_doc'>";
      $mostrar .= "<div class='form-group field-wrap position-relative p-1 w-100'>";
      $mostrar .= "<label><span class='fons'></span><span class='camp'>DNI amb lletra</span>";
      $mostrar .= "<span class='req ml-1'>*</span></label>
      <input type='text' class='form-control' id='nif' name='nif'>";
      $mostrar .= "<span id='dni_erroni'></span></div></div>";

      $mostrar .= "</div>";
      $mostrar .= "</div>";

      if ($this->obtenirFraccionat()) {
         $mostrar .= "<div class='row d-flex flex-row algin-items-center justify-content-center'>";
         $mostrar .= "<div class='col-12'>";
         $mostrar .= "<div class='form-group field-wrap position-relative p-1 w-100'>";
         $mostrar .= "<label><span class='fons'></span><span class='camp'>Quantitat que vull pagar ara</span>";
         $mostrar .= "<span class='req ml-1'>*</span></label>";
         $mostrar .= "<input type='text' class='form-control' id='importPagare' name='importPagare' ";
         $mostrar .= "maxlength='6'>";
         $mostrar .= "<span id='import_erroni'></span></div></div>";
         $mostrar .= "<input type='hidden' id='import' name='import' value='".$aPagar."'>";
         $mostrar .= "<input type='hidden' id='importPagat' name='importPagat' value='".$preuPagat."'>";
         $mostrar .= "</div>";
      }
      else {
         $mostrar .= "<p>L'import a pagar és de <span class='font-weight-bold'>";
         $mostrar .= "<span class='preu'>".$faltaPagar."</span> euros</span>.</p>";

         $mostrar .= "<input type='hidden' id='import' name='import' value='".$faltaPagar."'>";
         $mostrar .= "<input type='hidden' id='importPagare' name='importPagare' value='".$faltaPagar."'>";
         $mostrar .= "<input type='hidden' id='importPagat' name='importPagat' value='".$preuPagat."'>";
      }
      $mostrar .= "<div class='d-flex cnt_enviar_dades border-0 justify-content-center'>
                     <a id='form_enviar_dades' role='button' class='boto-blau text-center position-relative text-white border-0 border-radius-2 px-4 py-2 my-2'>Efectua el pagament</a></div>";
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
      if ( $this->tipusInsc == 'G' )
         $codiPagament = "<span class='codiCurs'>".$this->obtenirCodi()->obtenirText()."</span>-".$this->obtenirMesEdicio()->obtenirText();
      if ( $this->tipusInsc == 'P' )
         $codiPagament = "<span class='codiCurs'>".$this->codi->obtenirText()."</span>";
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
      	$mostrar .= "«".$codiPagament." + el número del teu NIF/NIE/Passaport»</span>, ";
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
