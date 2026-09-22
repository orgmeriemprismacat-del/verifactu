<?php
/**
   * @class Pack
   * @brief Conté tota la informació relacionada amb un Pack.
*/
class Pack {
   protected $idPack; /**< Text Codi d'un curs ex: ACRE */
   protected $titol; /**< Text El titol d'un curs. ex. Alumnat amb Altes Capacitats */
   protected $shortDesc; /**< Text La descripció curta d'un curs */
   protected $idPreu; /**< Numero El ID preu que correspon al curs. Per poder obtenir el preu d'un curs cal anar a buscar a la taula preus, tots els registres amb ID = ID_PREU  */
   protected $hores; /**< Array[int] El ID preu que correspon al curs. Per poder obtenir el preu d'un curs cal anar a buscar a la taula preus, tots els registres amb ID = ID_PREU  */
   protected $nivells; /**< Array[Nivell] Els nivells del curs */
   protected $tematiques; /**< Array[Temes] Les tematiques del curs */
   protected $video; /**< Video El video del curs. Si no n'hi ha, valdrà null  */
   protected $idNivells; /**< Array[int] Els nivells del curs */
   protected $idTemes; /**< Array[int] Les tematiques del curs */
   protected $imgLarge; /**< Imatge La imatge allargada del curs. Si no n'hi ha, valdrà null  */
   protected $imgSmall; /**< Imatge La imatge rectangular del curs. Si no n'hi ha, valdrà null */
   protected $url; /**< URL L'enllaç del curs. Si no n'hi ha, valdrà null  */
   protected $estat; /**< string Si el curs (info) està actiu o no */
   protected $dispositiu; /**< string Mobil si el dispositiu és mobil, altrament ordindador */
   protected $edicions; /**< Array Les edicions del curs */
   protected $etiquetaPack; /**< string Etiqueta del pack */
   protected $perfils; /**< string perfils */
   protected $cdd; /**< int cdd */
   protected $data_creacio; /**< int cdd */

   protected $imgPerfil; /**< Imatge La imatge rectangular del curs. Si no n'hi ha, valdrà null */
   protected $perfil; /**< Perfil El perfil del curs de les pròximes edicions */
   protected $perfil_pendent; /**< Perfil El perfil del curs de les pròximes edicions */
   protected $cursos; /**< Perfil El perfil del curs de les pròximes edicions */
   protected $edicionsReconegudes; /**< Numero Les edicions del curs disponibles amb reconeixement */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @param $url URL que correspon a la informació del pack
   * @return Crees un Pack
   */
   public function __construct($idPack, $dispositiu, $modeLlistat = false) {

      $this->idPack = new Text($idPack);
      $this->dispositiu = $dispositiu;
      $this->estat=1;

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Es busca la presentacio, autoria, destinataris, objectius, programa_min,
      valoracions, el ids_article, el ids_video del registre que la ID_URL de la taula
      info_packs correspon a la ID url obtinguda anteriorment */
      $cnsInfoPack = "SELECT ID, ID_URL, TITOL, SHORT_DESC, PRESENTACIO, AUTORIA,
      DESTINATARIS, PROGRAMA_MIN, VALORACIONS, ID_PREU, ETIQ_DESC, IDS_VIDEO,
      IDS_ARTICLE, ID_IMG_LARGE, ID_IMG_SMALL, ETIQ_PERFIL, ETIQ_EDICIO, ETIQ_CDD, DATA_CREATE
      FROM info_pack WHERE ID_PACK=? AND ESTAT=1";
      if ( $stmt = $connexio->prepare($cnsInfoPack) ) {
         $stmt->bind_param("d", $idPack);
         $stmt->execute();
         $stmt->bind_result($id, $idUrl, $titol, $desc, $pres, $autoria, $dest,
            $prog_min, $val, $idPreu, $etiqDesc, $idsVideo, $idsArticle,
            $idImgLarge, $idImgSmall, $etiqPerfil, $etiqEdicio, $etiqCDD, $data_creacio);
         $stmt->store_result();
         if ( $stmt->num_rows() > 0 ) {
            $stmt->fetch();
            $connexio->closeStmt();

            require_once 'Text.php';

            $this->url = !empty($idUrl)
                ? new URL($idUrl)
                : null;

             $this->titol = !empty($titol)
                ? new Text($titol)
                : null;

             $this->shortDesc = !empty($desc)
                ? new Text($desc)
                : null;

            $this->idPreu = new Numero($idPreu);
            $this->dataCreate = $data_creacio;

            $this->etiquetaPack = !empty($etiqDesc)
                ? $etiqDesc
                : null;

            $this->perfils = !empty($etiqPerfil)
                ? $etiqPerfil
                : null;

            $this->cdd = !empty($etiqCDD)
                ? $etiqCDD
                : null;

            require_once 'Imatge.php';

            $this->imgLarge = !empty($idImgLarge)
                ? new Imatge($idImgLarge)
                : null;
            $this->imgSmall = !empty($idImgSmall)
                ? new Imatge($idImgSmall)
                : null;

            $this->estat=1;

            if ($modeLlistat) {
                $connexio->desconectarBD();
                return;
            }

            if ($pres!=null AND $pres!='')
               $this->presentacio = new Text($pres);
            else
               $this->presentacio = null;
            if ($autoria!=null AND $autoria!='')
               $this->autoria = new Text($autoria);
            else
               $this->autoria = null;
            if ($dest!=null AND $dest!='')
               $this->destinataris = new Text($dest);
            else
               $this->destinataris = null;
            if ($prog_min!=null AND $prog_min!='')
               $this->programes_min = new Text($prog_min);
            else
               $this->programes_min = null;
            if ($val!=null AND $val!='')
               $this->valoracions = new Text($val);
            else
               $this->valoracions = null;

            require_once 'Video.php';
            if ($idsVideo!=null AND $idsVideo!='') {
              $vect_videos = explode('|',$idsVideo);
              foreach($vect_videos as $clau => $valor){
                 $this->video[$i] = new Video($valor);
                 $i++;
              }
            }
            else
               $this->video = null;


            $cnsidTema = "SELECT ID_TEMA FROM filtres WHERE ID_INFO LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
            $idTemes = "";
            $textConsId = "";

            $connexio3 = new ConnexioBBDDSTMT();
            $connexio3->connectarBD();

            /* Es busca els objectius i els programes del registre dels cursos que trobarem
            a la taula packs que corresponen al codi de pack que té el pack que la ID_URL de la taula
            info_packs correspon a la ID urk obtinguda anteriorment */
            $cnsInfoOrig = "SELECT DATAI, DATAF, CURS_ESCOLAR, FISS, GTAF, DATA_RESOL,
               ANY, MES, c.CURS, p.ID_CURS, HORES, i.ID, i.ID_AMIGABLE, c.ESTAT
               FROM packs AS p INNER JOIN info_pack AS ip ON p.ID_PACK = ip.ID_PACK INNER
               JOIN curs AS c ON p.ID_CURS = c.ID_CURS INNER JOIN informacio AS i ON
               c.CURS = i.CODI_CURS INNER JOIN aula AS a ON c.ID_AULA=a.ID_AULA INNER JOIN
               rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO INNER JOIN honoraris AS h ON r.ID_HONO=h.ID
               WHERE ip.ID_PACK = ? AND p.PUBLIC=1 AND c.PUBLIC=1 AND c.CURS!='PROVA' AND
               c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1 AND i.ESTAT = 1
               AND c.CURS NOT LIKE '%JOR%' AND (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC') OR
               (a.ID_CUHO!=17 AND h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor' AND
               ORDRE_TUTOR is not NULL)) ORDER BY c.DATAI";
            if ( $stmt = $connexio->prepare($cnsInfoOrig) ) {
               if ( $stmt3 = $connexio3->prepare($cnsidTema) ) {
                   //echo $idPack;
                  $stmt->bind_param("d", $idPack);
                  $stmt->execute();
                  $stmt->store_result();
                  if ( $stmt->num_rows() > 0 ) {
                     require_once 'EdicioPack.php';
                     require_once 'Curs.php';
                     $stmt->bind_result($dataiEd, $datafEd, $cursEscEd, $fissEd, $gtafEd,
                        $dataResEd, $anyEd, $mesEd, $cursEd, $idCursEd, $horesEd, $idInfoEd, $idUrl, $estatEd);
                     $i = 0; $this->cursos = [];
                     while ( $stmt->fetch() ) {
                        $this->edicions[$i] = new EdicioPack($cursEd, $idUrl, $this->dispositiu, $anyEd, $mesEd, $horesEd, $dataiEd, $datafEd, $cursEscEd, $gtafEd, $dataResEd, $fissEd, $estatEd);

                        /* Es busca les tematiques de les infos corresponents del pack */
                        $stmt3->bind_param("d", $idInfoEd);
                        $stmt3->execute();
                        $stmt3->bind_result($idTema);
                        $stmt3->fetch();
                        if ( $idTemes != '' ) $idTemes .= "|";
                        $idTemes .= $idTema;

                        $this->cursos[$i][0] = $this->edicions[$i]->obtenirCurs()->obtenirPerfils();
                        $this->cursos[$i][1] = $this->edicions[$i]->obtenirCurs()->obtenirPerfilsPendents();

                        /* Recullo les ids de ls infos per consultar els docents */
                        if ( $textConsId != "" ) $textConsId .= " OR ";
                        $textConsId .= "i.ID = ".$idInfoEd;

                        $i++;
                     }
                  }
                  else {
                     $connexio->closeStmt();
                     $this->estat=0;
                  }
                  $connexio->closeStmt();
               }
            }
            else {
               throw new Exception('',2702);
            }

            $connexio3->desconectarBD();

            if ( $this->estat == 1 ) {

               if ( $idTemes == '' ) throw new Exception('',2708);

               // si existeixen edicions. busco la primera edicio i comprovo si l'edicio està oberta. si no, envio msg de pack no disponible

               $horesPrimeraEdicio = $this->edicions[0]->obtenirHores()->obtenirNumero();

               $cnsParam="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
                     DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
               if ( $stmt = $connexio->prepare($cnsParam) ) {
                  $stmt->bind_param("ss", $tipus, $orderBy);

                  $tipus='limit-filtre-info-packs';
                  $orderBy='DATAI';
                  $stmt->execute();
                  $stmt->bind_result($limitFiltrePacks);
                  $stmt->fetch();

                  $tipus='dies-inscriu-cursos';
                  $orderBy='VALOR';
                  $stmt->execute();
                  $stmt->bind_result($valor);
                  $diesOberts=0;
                  while ($stmt->fetch()) {
                     $valors = explode('|',$valor);
                     if (count($valors) != 2)
                        throw new Exception('',2615);
                     else if (intval($valors[0])>=0 && $valors[0]== $horesPrimeraEdicio) //si el valor és un numero i les hores son iguals al curs
                        $diesOberts = $valors[1];
                  }

                  $connexio->closeStmt();
               }
               else {
                  throw new Exception('',2611);
               }

               if ( $diesOberts == 0 )
                  throw new Exception('',2616);

               if ( $this->edicions[0]->inscripcioOberta($diesOberts) < 0 ) {
                  throw new Exception('',2624);
               }

               /* Es busca els nivells i els cursos relacionats de la info del pack */
               $cnsNivellRel = "SELECT ID_NIVELL, ID_REL FROM filtres WHERE ID_INFO LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";

               if ( $stmt = $connexio->prepare($cnsNivellRel) ) {
                  $stmt->bind_param("d", $idPack);
                  $stmt->execute();
                  $stmt->bind_result($idsNivell, $idRel);
                  $stmt->fetch();
                  $connexio->closeStmt();

                  if ($idsNivell=='' or $idsNivell==null)
                     throw new Exception('',2617);
                  else if ($idRel=='' or $idRel==null)
                     throw new Exception('',2618);
               }
               else {
                  throw new Exception('',2607);
               }

               $ids_nivell = explode('|',$idsNivell);
               $this->idNivells = $ids_nivell;

               /*Es busca els nivells de la info */
               $cntNiv = 0;
               $cnsNivell = "SELECT NOM_INFO, ORDRE FROM nivells WHERE ID=?";
               if ( $stmtNiv = $connexio->prepare($cnsNivell) ) {
                  $stmtNiv->bind_param("d", $idNiv);
                  while ($cntNiv < count($ids_nivell)) {
                     $idNiv = $ids_nivell[$cntNiv];
                     $stmtNiv->execute();
                     $stmtNiv->bind_result($nomNiv, $ordreNiv);
                     $stmtNiv->fetch();
                     // Assignem a l'array $ordre_nivells com a clau l'ordre del nivell i com a valor el nom del nivell
                     $ordre_nivells[$ordreNiv] = $nomNiv;
                     $cntNiv++;
                  }
                  $connexio->closeStmt();
               }
               else {
                  throw new Exception('',2609);
               }

               // Ordenem l'array per la clau, de manera que, obtenim els nivells ordenats per ORDRE
               ksort($ordre_nivells);
               // Construïm els nivells amb els valors del nom dels nivells
               $i = 0;
               foreach($ordre_nivells as $clau => $valor){
                  $this->nivells[$i] = new Text($valor);
                  $i++;
               }

               $idsRel = explode('|',$idRel);

               /* Es busca les tematiques de les infos corresponents del pack */

               $ids_temes = explode('|',$idTemes);
               $this->idTemes = $ids_temes;

               $cntTema = 0;
               $cnsTema = "SELECT NOM, ORDRE FROM temes WHERE ID=?";
               if ( $stmtTema = $connexio->prepare($cnsTema) ) {
                  $stmtTema->bind_param("d", $idTema);
                  while ($cntTema < count($ids_temes)) {
                     $idTema = $ids_temes[$cntTema];
                     $stmtTema->execute();
                     $stmtTema->bind_result($nomTema, $ordreTema);
                     $stmtTema->fetch();
                     // Assignem a l'array $ordre_temes com a clau l'ordre del tema i com a valor el nom del tema
                     $ordre_temes[$ordreTema] = $nomTema;
                     $cntTema++;
                  }
                  $connexio->closeStmt();
               }
               else {
                  throw new Exception('',2612);
               }

               // Ordenem l'array per la clau, de manera que, obtenim els temes ordenats per ORDRE
               ksort($ordre_temes);
               // Construïm els temes amb els valors del nom dels temes
               $i = 0;
               foreach($ordre_temes as $clau => $valor){
                  $this->tematiques[$i] = new Text($valor);
                  $i++;
               }

               /* Consulta els docents que tutoritzen el curs */
               $cnsDoc = "SELECT DNI_TUTOR FROM honoraris AS h INNER JOIN informacio AS i
                  ON i.CODI_CURS = h.CURS WHERE (".$textConsId.") AND
                  h.ESTAT=1 AND PERFIL='tutor' AND DNI_TUTOR REGEXP '^[0123456789XYZ]'
                  GROUP BY DNI_TUTOR ORDER BY ORDRE_TUTOR ASC";
               if ( $stmt = $connexio->prepare($cnsDoc) ) {
                  $stmt->execute();
                  $stmt->bind_result($dniTutor);
                  $cntDoc = 0;
                  while ( $stmt->fetch() ) {
                     $ordreDoc[$cntDoc] = $dniTutor;
                     $cntDoc++;
                  }
                  $connexio->closeStmt();
               }
               else {
                  throw new Exception('',2613);
               }

               $cnsUrlDoc = "SELECT ID_URL FROM personal WHERE DNI=?";
               if ( $stmt = $connexio->prepare($cnsUrlDoc)) {
                  $stmt->bind_param("s", $dniDoc);
                  $cntDoc = 0;
                  while ($cntDoc < count($ordreDoc)) {
                     $dniDoc = $ordreDoc[$cntDoc];
                     $stmt->execute();
                     $stmt->bind_result($idUrlDoc);
                     $stmt->fetch();
                     $urlDoc = new Url($idUrlDoc);
                     require_once 'Tutor.php';
                     $this->docents[$cntDoc] = new Tutor($urlDoc, $this->dispositiu);
                     $cntDoc++;
                  }
                  $connexio->closeStmt();
               }
               else {
                  throw new Exception('',2614);
               }
            }
         }
      }
      else {
         $this->estat=0;
         $connexio->closeStmt();
      }

      $connexio->desconectarBD();

   }

   /*********************** FUNCIONS CONSULTAR ATRIBUTS ***********************/

   public function cursEsMixt() {
      $cntEd = 0; $algunCursEsMixt = 0;
      while ($cntEd < count($this->obtenirEdicions()) && !$algunCursEsMixt ) {
         $edicio = $this->obtenirEdicions()[$cntEd];
         if ( $edicio->obtenirCurs()->cursEsMixt() ) $algunCursEsMixt = 1;
         $cntEd++;
      }
      return $algunCursEsMixt;
   }

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció «No existeix el nom del curs»
   */
   public function obtenirIdPack() {
      if ($this->idPack==null)
         throw new Exception('',2704);
      return $this->idPack;
   }

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció «No existeix el nom del curs»
   */
   public function obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',2701);
      return $this->titol;
   }

   /*
   * @brief Obtens la descripció curta
   * @return La descripció curta
   * @throws Si el curs no té una descripció curta, envia l'excepció «No existeix la descripcio curta del curs»
   */
   public function obtenirShortDesc() {
      if ($this->shortDesc==null)
         throw new Exception('',2722);
      return $this->shortDesc;
   }

   /*
   * @brief Obtens les hores del curs
   * @return Les hores del curs
   * @throws Si el curs té unes hores i la posicio existeix a la llista de hores, retorna les hores dels cursos del pack
   */
   public function obtenirHores() {
      return $this->hores;
   }
   public function obtenirDataCreacio() {
      return $this->data_creacio;
   }

   /*
   * @brief Obtens la hora del curs
   * @param $posicio La posicio de la llista de hores corresponent al curs.
   * @return Si el curs té un hora i la posicio existeix a la llista de hores, retorna l'hora del curs. Altrament, null.
   */
   protected function obtenirHora($posicio) {
      if ($this->hores == null and count($this->hores)==0)
         return null;
      else if ($posicio>=0 && $posicio < count($this->hores))
         return $this->hores[$posicio];
      else
         return null;
   }

   /*
   * @brief Obtens el nivell del curs
   * @param $posicio La posicio de la llista de nivells corresponent al curs.
   * @return Si el curs té un nivell i la posicio existeix a la llista de nivells, retorna el perfil del curs. Altrament, null.
   */
   public function obtenirNivells() {
      return $this->nivells;
   }

   /*
   * @brief Obtens el nivell del curs
   * @param $posicio La posicio de la llista de nivells corresponent al curs.
   * @return Si el curs té un nivell i la posicio existeix a la llista de nivells, retorna el perfil del curs. Altrament, null.
   */
   public function obtenirIdNivells() {
      return $this->idNivells;
   }

   /*
   * @brief Obtens el nivell del curs
   * @param $posicio La posicio de la llista de nivells corresponent al curs.
   * @return Si el curs té un nivell i la posicio existeix a la llista de nivells, retorna el perfil del curs. Altrament, null.
   */
   public function obtenirIdTemes() {
      return $this->idTemes;
   }

   /*
   * @brief Obtens els perfils dels dos cursos
   * @return Si algun curs té un perfil existeix, retorna el perfil del curs. Altrament, null.
   */
   public function obtenirIdPerfils() {
      // echo count($this->cursos)."<br />";
      $perfilsActuals = [];
      // $cnt = 0;

      // cerquem els cursos que hi ha
      for ( $i = 0; $i < count($this->cursos); $i++ ) {
         // perfils del curs actual;
         $perfilsCurs = $this->cursos[$i][0];

         // cerquem els perfils que té cada curs
         for ( $j = 0; $j < count($perfilsCurs); $j++ ) {
            // si en la taula de perfils ja existeix
            $perfilActual = $perfilsCurs[$j];
            $cnt = 0; $trobat = 0;
            while ( $cnt < count($perfilsActuals) && !$trobat ) {
               $trobat = $perfilActual->obtenirIdPerfil() == $perfilsActuals[$cnt];
               $cnt++;
            }
            if ( !$trobat )
               $perfilsActuals[] = $perfilActual->obtenirIdPerfil();
         }

         // perfils pendents del curs actual;
         $perfilsPendentsCurs = $this->cursos[$i][1];

         for ( $j = 0; $j < count($perfilsPendentsCurs); $j++ ) {
            // si en la taula de perfils ja existeix
            $perfilActual = $perfilsPendentsCurs[$j];
            $cnt = 0; $trobat = 0;
            while ( $cnt < count($perfilsActuals) && !$trobat ) {
               $trobat = $perfilActual->obtenirIdPerfil() == $perfilsActuals[$cnt];
               $cnt++;
            }
            if ( !$trobat )
               $perfilsActuals[] = $perfilActual->obtenirIdPerfil();
         }
      }
      return $perfilsActuals;
   }

   /*
   * @brief Obtens la imatge rectangular
   * @return Si el curs té un video, retorna el video del curs. Altrament, null.
   * @throws Si el curs no té un video, envia l'excepció «No existeix el video del curs»
   */
   protected function obtenirVideos() {
      return $this->video;
   }

   /*
   * @brief Obtens la imatge allargada
   * @return Si el curs té una imatge gran, retorna la imatge gran del curs, és a dir la caratula del
      curs. Altrament, null.
   * @throws Si el curs no té una imatge gran, envia l'excepció «No existeix la imatge gran del curs»
   */
   public function obtenirImgLarge() {
      if ($this->imgLarge==null)
         throw new Exception('',2702);
      return $this->imgLarge;
   }

   /*
   * @brief Obtens la imatge rectangular
   * @return Si el curs té una imatge petita, retorna la imatge petita del curs, és a dir la caratula del
      curs. Altrament, null.
   * @throws Si el curs no té una imatge petita, envia l'excepció «No existeix la imatge petita del curs»
   */
   public function obtenirImgSmall() {
      if ($this->imgSmall==null) {
         echo $this->idPack;
         throw new Exception('',2720);
      }
      return $this->imgSmall;
   }

   /*
   * @brief Obtens l'estat del curs
   * @return L'estat del curs
   */
   public function obtenirEstat() {
      return $this->estat;
   }

   /*
   * @brief Obtens el id preu del pack
   * @return El id preu del pack
   * @throws Si el pack no té un idPreu, envia l'excepció «No existeix el id preu del curs»
   */
   public function obtenirPreu() {
      if ($this->idPreu==null)
         throw new Exception('',2703);
      return $this->idPreu;
   }

   /*
   * @brief Obtens la Url del curs
   * @return Si el curs té una url, retorna l'enllaç amigable al curs. Altrament, null.
   * @throws Si el curs no té un enllaç, envia l'excepció «No existeix l'enllaç del curs»
   */
   public function obtenirUrl() {
      if ($this->url==null)
         throw new Exception('',2705);
      return $this->url;
   }

   /*
   * @brief Obtens les edicions
   * @return Obtens les edicions del curs
   * @throws Si el curs no té una llista d'edicions, envia l'excepció «No existeixen edicions del curs»
   */
   public function obtenirEdicions() {
      if ($this->edicions==null)
         throw new Exception('', 2721);
      return $this->edicions;
   }

   /*
   * @brief Obtens true si les inscripcions de l'any $any i del mes $edicio estan
   obertes. Altrament, retorna false
   * @return Obtens true si les inscripcions de l'any $any i del mes $edicio estan
   obertes. Altrament, retorna false
   */
   public function inscripcionsObertes($any, $mes) {
      $edicions = $this->obtenirEdicions(); $oberta = 1;

      if ( count($edicions) > 0 ) {
         $firstEd = $edicions[0];

         $cursEd = $firstEd->obtenirCurs();

         $codiCurs = $cursEd->obtenirCodi()->obtenirText();
         $hores = $firstEd->obtenirHores()->obtenirNumero();

         $connexio = new ConnexioBBDDSTMT();
      	$connexio->connectarBD();

         /* Busco els dies que poden estar obert els cursos després de la data d'inscripció */
         $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
               DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
         $stmtParam = $connexio->prepare($cnsParams);
         $stmtParam->bind_param("sss", $tipus, $valor, $orderBy);
         $tipus='dies-inscriu-cursos';
         $orderBy='VALOR';
         $stmtParam->execute();
         $stmtParam->bind_result($valor);
         $diesOberts=[];
         $pos=0;
         while ($stmtParam->fetch()) {
            $valors = explode('|',$valor);
            if (count($valors) !== 2 || !is_numeric($valors[1])) {
               throw new Exception('', 714);
            }
            $diesOberts[] = (int)$valors[1];
         }
         $connexio->closeStmt();

         $diesObert = !empty($diesOberts) ? max($diesOberts) : 0;

         // /* Busco si l'edicio està disponible */
         $cnsEd = "SELECT DISTINCT c.MES, c.ANY FROM curs AS c
            INNER JOIN aula AS a ON c.ID_AULA=a.ID_AULA
            INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
            INNER JOIN honoraris AS h ON r.ID_HONO=h.ID
            WHERE c.PUBLIC=1 AND c.CURS!='PROVA'
            AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1
            AND DATE_ADD(c.DATAI, INTERVAL ? DAY) > CURRENT_DATE
            AND c.CURS NOT LIKE '%JOR%' AND c.PUBLIC = 1
            AND (a.ID_CUHO IN (17, 13) OR ( a.ID_CUHO <> 17 AND a.AULA = 'A'
            OR ( a.ID_CUHO <> 17 AND h.DNI_TUTOR = 'GENERIC' )
            AND h.DNI_TUTOR <> 'GENERIC' AND h.perfil = 'tutor' AND h.ORDRE_TUTOR = 1))
            AND c.ANY=? AND c.MES=? AND c.CURS=?";
         $stmtEd = $connexio->prepare($cnsEd);
         $stmtEd->bind_param("ddss", $diesObert, $any, $mes, $codiCurs);
         $stmtEd->execute();
         $stmtEd->store_result();
         if ($stmtEd->num_rows() > 0)
            $oberta = 1;
         else
            $oberta = 0;

         $connexio->closeStmt();

         $connexio->desconectarBD();
      }
      else {
         return 0;
      }
      return $oberta;
   }

   /***********************       FUNCIONS MOSTRAR       ***********************/

   /**
   * @brief Mostra la informació d'un curs (cas 3 en una fila)
   * @return Mostra la informació d'un curs (cas 3 en una fila)
   */
   public function mostrarCurs() {
      $mostrar = "<div class='col-12 col-sm-6 col-lg-4'>".$this->__mostrarCurs("h2")."</div>";
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (cas 4 en una fila)
   * @return Mostra la informació d'un curs (cas 4 en una fila)
   */
   public function mostrarCursRelacionat() {
      $mostrar = "<div class='col-12 col-sm-6 col-lg-3'>".$this->__mostrarCurs("div class='h3'")."</div>";
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (cas 3 en una fila)
   * @return Mostra la informació d'un curs (cas 3 en una fila)
   */
   public function mostrarCursosFiltres($head) {
      // echo "4".$this->obtenirTitol()->obtenirTextHTML();
      $mostrar = "<div class='col-12 col-sm-6 col-lg-4'>".$this->__mostrarCurs($head)."</div>";
      return $mostrar;
   }


   /**
   * @brief Mostra el modal de veure un tastet
   * @return  Mostra la informació breu d'un curs
   */
   public function crearTastet(){
      $mostrar='';
      if ($this->dispositiu=='ordinador') {
         $codiCursMin=$this->obtenirIdPack()->obtenirTextHTML();
         // $hores=$this->obtenirHores()->obtenirNumero();
         $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
         $titol=$this->obtenirTitol()->obtenirText();
         $shortDesc=$this->obtenirShortDesc()->obtenirText();

         $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
         $elemLink=explode('/',$linkCurs);
         $extUrl=$elemLink[count($elemLink)-1];

         $urlInsc="https://www.prisma.cat/inscripcions/packs/".$extUrl;
         // $urlPerfil="https://www.prisma.cat/perfils-professionals/".$extUrl;

         //$etiquetaNou=$this->__etiquetaCursNou();
         $etiquetaPack=$this->__etiquetaPacks();

         $botoClose="<button role='button'type='button' data-dismiss='modal' aria-label='Close' ";
         $botoClose.="class='modal-close border-0 position-absolute'>×</button>";

         $linkImg = "https://www.prisma.cat".$this->obtenirImgLarge()->obtenirLink();
         $versioImg = $this->obtenirImgLarge()->obtenirVersio();
         $linkImg .= "?ver=".$versioImg;

         $videoOimg=$etiquetaPack."<div class='img-modal'>".$botoClose."<img role='img' class='w-100' ";
         $videoOimg.="src='".$linkImg."' ";
         $videoOimg.="alt=\"".$this->obtenirImgLarge()->obtenirAlt()."\"></div>";

         $mostrar = "<div class='veure-tastet modal fade' id='veure_tastet_".$codiCursMin."' ";
         $mostrar .= "role='dialog'><div class='modal-dialog modal-dialog-centered'>";
         $mostrar .= "<div class='modal-content border-0'><div class='modal-body'>".$videoOimg;
         $mostrar .= "<div class='d-flex flex-row'><div class='col-md-8'><div class='modal-titol-curs ";
         $mostrar .= "border-0 font-weight-bold'>".$etiquetaNou.$titolHTML."</div>";
         $mostrar .= "<p class='modal-descripcio-curta'>".$shortDesc."</p></div>";
         $mostrar .= "<div class='col-md-4 d-flex flex-column border-left my-2'>";

         $mostrar .= "<button role='button' class='inscripcio border-0
         border-radius-2 text-white position-relative text-center negreta500 position-relative text-center border-0 text-white'
         title=\"Inscriu-te al curs ".$titol."\" onclick=\"location.href='".$urlInsc."'\">
         <i class='fas fa-edit'></i> Inscripció</button>
         <ul>";

         $textHores = "<li class='d-flex flex-row align-items-center'>
         <i class='far fa-clock'></i><span class='text'>Durada</span>
         <div class='d-flex flex-column'>";
         $textNivells = "<li class='d-flex flex-row align-items-center'>
         <i class='fas fa-signal'></i><span class='text'>Nivell</span>
         <div class='d-flex flex-column'>";
         $cntEd = 0;
         while ($cntEd < count($this->obtenirEdicions())) {
            $edicio = $this->obtenirEdicions()[$cntEd];

            /* Text hores */
            if ($cntEd == 0) $niv1 = "niv1";
            else $niv1 = "";

            $textHores .= "<span class='niv-apartat ".$niv1." negreta500 text-right'>".$edicio->obtenirHores()->obtenirNumero()." hores</span>";

            $cntEd++;
         }
         /* Text nivells */
         $cntNiv = 0;
         while ($cntNiv < count($this->obtenirNivells())) {
            if ($cntEd == 0) $niv1 = "niv1";
            else $niv1 = "";

            $textNivells .= "<span class='niv-apartat niv1 negreta500 text-right'>".$this->obtenirNivells()[$cntNiv]->obtenirText()."</span>";

            $cntNiv++;
         }

         $textHores .= "</div></li>";
         $textNivells .= "</div></li>";

         $mostrar .= $textHores.$textNivells;

         require_once 'ConnexioBBDD_PreparedStatment.php';
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();

         $cnsPreus = "SELECT IMPORT FROM preu WHERE ID=? AND DATAI <=CURRENT_DATE
                       AND (DATAF IS NULL OR CURRENT_DATE<=DATAF)";
         if ( $stmt = $connexio->prepare($cnsPreus) ) {
            $stmt->bind_param("d", $idPreu);

            $preuOrig = 0;

            for ($i=0; $i < count($this->obtenirEdicions()); $i++) {
               $edicio = $this->obtenirEdicions()[$i];
               $edicio->setInfo();
               $idPreu = $edicio->obtenirIdPreu()->obtenirNumero();

               $stmt->execute();
               $stmt->bind_result($import);
               $stmt->fetch();

               $preuOrig = floatval($import) + $preuOrig;
            }

            $objImp = new Numero($preuOrig);
            $importOrigFormCorrecteOK = $objImp->mostrarNumeroDecimalsSense0();

            $idPreu=$this->obtenirPreu()->obtenirNumero();

            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows() > 0) {
               $stmt->bind_result($import);
               $stmt->fetch();
               $objImp = new Numero($import);
               $importFormatCorrecte = $objImp->mostrarNumeroDecimalsSense0();
               $textPreu.="
               <li class='d-flex flex-row align-items-center'><i class='far fa-credit-card'></i><span class='text'>Preu</span>
                  <span class='negreta500 px-2 py-1 text-danger tatxat pr-2'>".$importOrigFormCorrecteOK." €</span>
                  <span class='negreta500'>".$importFormatCorrecte." €</span></li>";
            }
            $connexio->closeStmt();

         }
         else {
            throw new Exception('',2723);
         }

         $mostrar .= $textPreu;

         // $mostrar .= $this->filaTaulaCursAmbPerfil();

         $mostrar.="</ul></div><div class='clear-both'></div></div><div class='modal-footer border-0'>";
         $mostrar.="<button class='mesinformacio position-relative border-radius-2 ";
         $mostrar.="text-center w-100 border-0' target='".$this->obtenirUrl()->obtenirTarget()."' ";
         $mostrar.="onclick =\"location.href='".$linkCurs."'\">";
         $mostrar.="Més informació <i class='fas fa-long-arrow-alt-right'></i></button>";
         $mostrar.="</div></div></div></div></div>";
      }
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs
   * @return Mostra la informació d'un curs
   */
   private function __mostrarCurs($tagTitol) {
      $linkImg="https://www.prisma.cat".$this->obtenirImgSmall()->obtenirLink();
      $versioImg = $this->obtenirImgSmall()->obtenirVersio();
      $dscImg=$this->obtenirImgSmall()->obtenirAlt();

      $codiMin=$this->obtenirIdPack()->convertirMin();
      $idtastet="veure_tastet_".$codiMin;
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();
      $linkUrl=$this->obtenirUrl()->obtenirLink();

      $codi=$this->obtenirIdPack()->obtenirText();

      $mostrar="<div class='prisma-related-course border-radius-2'>
      <div class='prisma-related-course-overlay'>";

      $mostrar.=$this->__etiquetaPacks();
      $etiquetaDesc=$this->etiquetaPromocio();
      /* Si fa quatre mesos de la data d'inici de la primera edició, curs nou = true*/
      //$etiquetaNou=$this->__etiquetaCursNou();

      $linkImgOrig = substr($linkImg, 0, -4);
      $linkImgWeb = $linkImgOrig.".webp";
      $linkImg .= "?ver=".$versioImg;

      $linkImg540JPG=$linkImgOrig."-540.jpg?ver=".$versioImg;
      $linkImg345JPG=$linkImgOrig."-345.jpg?ver=".$versioImg;
      $linkImg210JPG=$linkImgOrig."-210.jpg?ver=".$versioImg;
      $linkImg260JPG=$linkImgOrig."-260.jpg?ver=".$versioImg;
      $linkImg540Webp=$linkImgOrig."-540.webp?ver=".$versioImg;
      $linkImg345Webp=$linkImgOrig."-345.webp?ver=".$versioImg;
      $linkImg210Webp=$linkImgOrig."-210.webp?ver=".$versioImg;
      $linkImg260Webp=$linkImgOrig."-260.webp?ver=".$versioImg;

      if ($this->dispositiu=='ordinador')
         $onclick = " onclick=\"mostrarTaset('".$codiMin."',1)\"";
      else
         $onclick = "onclick=\"location.href='https://www.prisma.cat".$linkUrl."'\"";

      $mostrar .= "<picture>
      <source media='(max-width: 576px)' type='image/webp' data-srcset='".$linkImg540Webp."' srcset='".$linkImg540Webp."'>
      <source media='(max-width: 576px)' type='image/jpeg' data-srcset='".$linkImg540JPG."' srcset='".$linkImg540JPG."'>
      <source media='(max-width: 990px)' type='image/webp' data-srcset='".$linkImg345Webp."' srcset='".$linkImg345Webp."'>
      <source media='(max-width: 990px)' type='image/jpeg' data-srcset='".$linkImg345JPG."' srcset='".$linkImg345JPG."'>
      <source media='(max-width: 1199px)' type='image/webp' data-srcset='".$linkImg210Webp."' srcset='".$linkImg210Webp."'>
      <source media='(max-width: 1199px)' type='image/jpeg' data-srcset='".$linkImg210JPG."' srcset='".$linkImg210JPG."'>
      <source media='(min-width: 1200px)' type='image/webp' data-srcset='".$linkImg260Webp."' srcset='".$linkImg260Webp."'>
      <source media='(min-width: 1200px)' type='image/jpeg' data-srcset='".$linkImg260JPG."' srcset='".$linkImg260JPG."'>

      <source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
      <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
      <img data-src='".$linkImg."' alt=\"".$dscImg."\"
         class='prisma-related-course-image w-100 lazyload' ".$onclick." />
      </picture>";

      if ($this->dispositiu=='ordinador') {
         $mostrar .= "<div class='prisma-course-middle pack-button-tastet position-absolute'>
         <button role='button' id='boto_veure_tastet_".$codiMin."'
         class='prisma-course-text border-radius-2' data-placement='bottom'
         type='button' data-html='true' title=\"Mostra el tastet del curs ".$titol."\"
         onclick=\"mostrarTaset('".$codiMin."',1)\" aria-label=\"Mostra tastet ".$titol."\"
         data-keyboard='true'>Mostra'n un resum</button></div>";
      }

      $etiquetaCDD=$this->__etiquetaCDD2();
      $etiquetes = $etiquetaNou.$etiquetaDesc.$etiquetaCDD;

      $mostrar .= "</div><div class='related-course-content px-3 border-radius-2'>";
      $mostrar .= "<div class='espai-titol-etiqueta d-flex flex-column'><div class='titol flex-1-0-auto'>".$etiquetes;
      $mostrar .= "<a role='link' class='color-text' href='https://www.prisma.cat".$linkUrl."' ";
      $mostrar .= "title=\"Mostra la informació del curs ".$titol."\">";
      $mostrar .= "<".$tagTitol.">".$titolHTML."</".$tagTitol."></a></div>";
      $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-row flex wrap'>";
      $mostrar .= $this->etiquetaCursAmbPerfil();
      $mostrar .= "</div></div><div class='related-course-footer clear-both d-flex flex-row align-items-center justify-content-between'>";

      $cntEd = 0; $textHores = "";

      $mostrar .= "<span class='cnt-hores'>".$textHores."</span>";
      $mostrar .= "<span class='mes-informacio'>";
      $mostrar .= "<a role='link' class='mes-informacio' href='".$linkUrl."' ";
      $mostrar .= "target='".$this->obtenirUrl()->obtenirTarget()."' ";
      $mostrar .= "title=\"Mostra més informació del curs ".$titol."\">Més informació ";
      $mostrar .= "<i class='fas fa-long-arrow-alt-right'></i></a></span>";
      $mostrar .= "</div></div></div>";
      return $mostrar;
   }

   /**
   * @brief Comprova si el curs és un curs nou (<4 mesos)
   * @return Si la data de la primera edició és menor a 4 mesos de diferencia amb el mes actual, retorna cert.
   */
   public function __esCursNou() {
      $cursNou = true;
      return $cursNou;
   }

   /**
   * @brief Comprova si el pack és un pack nou (<4 mesos)
   * @return Si la data de la primera edició del pack és menor a 4 mesos de diferencia amb el mes actual, retorna cert.
   */
   public function __esPackNou() {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();
      /* busco la primer data d'inici i el mes.
         Si la (data d'inici - dues setmanes) és posterior al current_date, $curs_a_partir = true;
         Si la (data d'inici + quatre mesos) és posterior al current date, $curs_nou = true; */
      $cnsDatai="SELECT DATA_CREATE FROM info_pack WHERE ID_PACK=?";
      $stmt=$connexio->prepare($cnsDatai);
      $stmt->bind_param("s", $idPack);
      $idPack=$this->obtenirIdPack()->obtenirTextHTML();
      $stmt->execute();
      $stmt->bind_result($dataiPrimeraEdicio);
      $stmt->fetch();
      $connexio->closeStmt();

      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='etiqueta-curs-nou';
      $orderBy='DATAI';
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $stmtParam->fetch();
      $connexio->closeStmt();

      $dataDeixaDeSerNou = strtotime($valor, strtotime($dataiPrimeraEdicio));

      $packNou = 0;
      if ($dataDeixaDeSerNou > strtotime(date("Y-m-d")) ) {
         $packNou = 1;
      }
      return $packNou;
   }

   /**
   * @brief Comprova si el curs té CDD
   * @return Retirna si el curs té CDD
   */
   public function esCDD() {
      return $this->cdd != 0;
   }

   /**
   * @brief Mostra la etiqueta del curs nou
   * @return Si el curs és nou, mostra la etiqueta del curs nou
   */
   protected function __etiquetaCursNou() {
      $etiquetaNou='';
      if ($this->__esCursNou())
         $etiquetaNou = "<div class='nou float-left'>NOVETAT</div>";
      return $etiquetaNou;
   }

   /**
   * @brief Mostra la etiqueta novetat
   * @return Mostra la etiqueta novetat
   */
   protected function etiquetaNovetat() {
       $etiquetaNou = "<div class='nou float-left'>NOVETAT</div>";
      return $etiquetaNou;
   }
   /**
   * @brief Mostra la etiqueta 25% dte.
   * @return Mostra la etiqueta 25% dte.
   */
   protected function etiquetaPromocio() {
       $etiquetaNou = "<div class='dte bg_vermell float-left'>25% DTE</div>";
      return $etiquetaNou;
   }

   /**
   * @brief Mostra la etiqueta del «A partir de»
   * @return Si el curs encara no ha començat, mostra la etiqueta de «A partir de»
   */
   private function __etiquetaPacks() {
      $objTxt = new Text("Pack");
      $txt = $objTxt->obtenirTextHTML();
      $etiqueta="<div class='etiquetaPack position-absolute'>
      <div class='nom'>".$txt."</div></div>";

      return $etiqueta;
   }

   /**
   * @brief Mostra la etiqueta del curs amb els perfils del curs
   * @return Si el curs té algun perfil actiu, mostra la etiqueta del perfil
   */
   protected function etiquetaCursAmbPerfil() {
     //Separem els diferents perfils que hi pot haver
     require_once 'Perfil.php';
     if ( $this->perfils != null ) {
       $perfils = explode('|', $this->perfils);
       for ( $cntPerf = 0; $cntPerf < count($perfils); $cntPerf++) {
         $vectPerfil = explode('_', $perfils[$cntPerf]);
         $numHoresRecPerfil = $vectPerfil[0];
         $idPerfil = $vectPerfil[1];

         $perfilEd = new Perfil($idPerfil);

         $shortnamePerfil = $perfilEd->obtenirNomCurt()->obtenirText();

         require_once 'Text.php';
         $textClassPerfil = new Text($shortnamePerfil);
         $textClassPerfil->obtenirNomCurt();
         $classPerfil = $textClassPerfil->obtenirText();

         $p = "";
         if ( count($perfils) > 1 ) {
           $p = "font-size: 0.85rem";
           if ( $cntPerf < count($perfils) - 1 ) $classPerfil .= " mr-1";
         }

         $mostrar .= "<div role='button' title=\"Mostra totes les edicions dels
         cursos que acrediten el perfil «".$perfilEd->obtenirNomLlarg()->obtenirText()."»\"  style='".$p."'
         class='perfil-curs text-center ".$classPerfil." border-radius-2 d-flex align-items-center justify-content-between p-0 ml-0 mb-2'
         onclick=\"location.href='https://www.prisma.cat/perfils-professionals'\">";
         $mostrar .= "<span class='px-2'>".$numHoresRecPerfil." h ".$perfilEd->obtenirNomCurt()->obtenirText()."</span></div>";
       }
     }
     return $mostrar;
   }

   /**
   * @brief Mostra la etiqueta del curs amb els perfils del curs
   * @return Si el curs té algun perfil actiu, mostra la etiqueta del perfil
   */
   protected function etiquetaCursAmbPerfil2() {
     //Separem els diferents perfils que hi pot haver
     if ( $this->perfils != null ) {
       $perfils = explode('|', $this->perfils);
       for ( $cntPerf = 0; $cntPerf < count($perfils); $cntPerf++) {
         $vectPerfil = explode('_', $perfils[$cntPerf]);
         $numHoresRecPerfil = $vectPerfil[0];
         $idPerfil = $vectPerfil[1];

         $perfilEd = new Perfil($idPerfil);

         $shortnamePerfil = $perfilEd->obtenirNomCurt()->obtenirText();

         require_once 'Text.php';
         $textClassPerfil = new Text($shortnamePerfil);
         $textClassPerfil->obtenirNomCurt();
         $classPerfil = $textClassPerfil->obtenirText();

         $mostrar .= "<div role='button' title=\"Mostra totes les edicions dels
         cursos que acrediten el perfil «".$perfilEd->obtenirNomLlarg()->obtenirText()."»\"
         class='perfil-curs text-center ".$classPerfil." border-radius-2 d-flex align-items-center justify-content-between p-0 ml-0 mt-0'
         onclick=\"location.href='https://www.prisma.cat/perfils-professionals'\">";
         $mostrar .= "<span class='px-2'>".$numHoresRecPerfil." h ".$perfilEd->obtenirNomCurt()->obtenirText()."</span></div>";
       }
     }
     return $mostrar;
   }

   /**
   * @brief Mostra la etiqueta en format etiqueta NOU
   * @return Si el curs te CDD, mostra la etiqueta de «CDD» en format  etiqueta NOU
   */
   protected function __etiquetaCDD2() {
      $etiqueta = '';
      if ( $this->cdd != null || $this->cdd != '' ) {
         $objTxt = new Text("CDD");
         $txt = $objTxt->obtenirTextHTML();
         $etiqueta="<div class='nou cdd text-white float-left'>".$txt."</div>";
      }

      return $etiqueta;
   }

   /**
   * @brief Mostra la etiqueta en format etiqueta perfil
   * @return Si el curs te CDD, mostra la etiqueta de «CDD» en format etiqueta perfil
   */
   protected function __etiquetaCDD3() {
      $etiqueta = '';
      if ( $this->cdd != null || $this->cdd != '' ) {
         $objTxt = new Text("Acreditació CDD");
         $txt = $objTxt->obtenirTextHTML();
         $etiqueta="<button class='nou cdd text-white float-left'>".$txt."</button>";

         $nivellCDD = $this->getNivellCDD();

        if ( $nivellCDD != '' ) {
          $objTxt = new Text($nivellCDD);
          $txt = $objTxt->obtenirTextHTML();
          $etiqueta.="<button class='nou cdd text-white float-left'>".$txt."</button>";

        }
      }

      return $etiqueta;
   }

   /**
   * @brief el curs té CDD
   * @return Retirna el CDD
   */
   public function getNivellCDD() {
     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();

     $cns="SELECT NOM_CURT FROM acredit_cdd WHERE ID_CDD = ?";
     $stmt=$connexio->prepare($cns);
     $stmt->bind_param("d", $cdd);
     $cdd = $this->cdd;
     $stmt->execute();
     $stmt->bind_result($nom_curt);
     $stmt->fetch();
     $connexio->closeStmt();
     $connexio->desconectarBD();

      return $nom_curt;
   }
}
?>
