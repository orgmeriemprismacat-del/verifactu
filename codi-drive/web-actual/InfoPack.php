<?php

require_once 'Pack.php';

/**
   * @class InfoPack
   * @brief Conté tota la informació extesa sobre un Curs
*/
class InfoPack extends Pack {
   private $presentacio; /**< string Presentació del curs */
   private $autoria; /**< string Autoria del curs */
   private $destinataris; /**< string Destinataris del curs */
   private $programes_min; /**< Array[string] Programa del curs */
   private $valoracions; /**< string Valoracions del curs */
   private $articles; /**< Array[Article] Article relacionat amb el curs */
   private $docents; /**< Array[Docent] Els docents que porten el curs */
   private $cursos_relacionats; /**< LlistatCursos Els cursos relacionats amb el curs */
   private $packs_relacionats; /**< LlistatCursos Els packs relacionats amb el curs */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/

   /*
   * @brief Constructor de la classe.
   * @param $url URL que correspon a la informació del pack
   * @return Crees una Info d'un Pack
   */
   public function __construct($url, $dispositiu) {
      $this->url = $url;
      $this->dispositiu = $dispositiu;
      $this->estat=1;

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Es busca la presentacio, autoria, destinataris, objectius, programa_min,
      valoracions, el ids_article, el ids_video del registre que la ID_URL de la taula
      info_packs correspon a la ID url obtinguda anteriorment */
      $cnsInfoPack = "SELECT ID, ID_PACK, TITOL, SHORT_DESC, PRESENTACIO, AUTORIA,
      DESTINATARIS, PROGRAMA_MIN, VALORACIONS, ID_PREU, ETIQ_DESC, IDS_VIDEO,
      IDS_ARTICLE, ID_IMG_LARGE, ID_IMG_SMALL, ETIQ_PERFIL, ETIQ_EDICIO, ETIQ_CDD
      FROM info_pack WHERE ID_URL=? AND ESTAT=1";
      if ( $stmt = $connexio->prepare($cnsInfoPack) ) {
         $stmt->bind_param("d", $idAmigable);
         $idAmigable = $url->obtenirID();
         $stmt->execute();
         $stmt->store_result();
         if ( $stmt->num_rows() <= 0 ) {
            $connexio->closeStmt();
            $this->estat=0;

            $connexio2 = new ConnexioBBDDSTMT();
            $connexio2->connectarBD();

            $cnsExistInfoPack = "SELECT ID FROM info_pack WHERE ID_URL=? AND ESTAT=0";
            $stmt2 = $connexio2->prepare($cnsExistInfoPack);
            $stmt2->bind_param("d", $idAmigable);
            $stmt2->execute();
            $stmt2->store_result();
            if ( $stmt2->num_rows() > 0 )
               throw new Exception('',2603);
            else
               throw new Exception('',2604);
            $connexio2->closeStmt();

            $connexio2->desconectarBD();
         }
         $stmt->bind_result($id, $idPack, $titol, $desc, $pres, $autoria, $dest,
            $prog_min, $val, $idPreu, $etiqDesc, $idsVideo, $idsArticle,
            $idImgLarge, $idImgSmall, $etiqPerfil, $etiqEdicio, $etiqCDD);
         $stmt->fetch();
         $connexio->closeStmt();
      }
      else {
         throw new Exception('',2601);
      }
      require_once 'Text.php';

      if ($idPack!=null AND $idPack!='')
         $this->idPack = new Text($idPack);
      else
         $this->idPack = null;
      if ($titol!=null AND $titol!='')
         $this->titol = new Text($titol);
      else
         $this->titol = null;
      if ($desc!=null AND $desc!='')
         $this->shortDesc = new Text($desc);
      else
         $this->shortDesc = null;
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

      if ($idPreu!=null and $idPreu!='')
         $this->idPreu = new Numero($idPreu);
      else
         $this->idPreu = null;

      if ($etiqDesc!=null and $etiqDesc!='')
         $this->etiquetaPack = new Text($etiqDesc);
      else
         $this->etiquetaPack = null;

      if ($etiqPerfil!=null and $etiqPerfil!='') {
        $this->perfils = $etiqPerfil;
      }
      else
         $this->perfils = null;

      if ($etiqCDD!=null and $etiqCDD!='') {
        $this->cdd = $etiqCDD;
      }
      else
         $this->cdd = null;

      require_once 'Imatge.php';
      if ($idImgLarge!=null AND $idImgLarge!='')
         $this->imgLarge = new Imatge($idImgLarge);
      else
         $this->imgLarge = null;

      if ($idImgSmall!=null AND $idImgSmall!='')
         $this->imgSmall = new Imatge($idImgSmall);
      else
         $this->imgSmall = null;

      require_once 'Video.php'; $i = 0;
      if ($idsVideo!=null AND $idsVideo!='') {
        $vect_videos = explode('|',$idsVideo);
        foreach($vect_videos as $clau => $valor){
           $this->video[$i] = new Video($valor);
           $i++;
        }
      }
      else
         $this->video = null;

      $cnsidTema = "SELECT ID_TEMA FROM filtres WHERE ID_INFO LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ";
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
         c.CURS = i.CODI_CURS WHERE ip.ID_URL = ? AND p.PUBLIC=1 AND c.PUBLIC=1
         AND c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0'
         AND i.ESTAT = 1 AND c.CURS NOT LIKE '%JOR%' ORDER BY c.DATAI";

      if ( $stmt = $connexio->prepare($cnsInfoOrig) ) {
         if ( $stmt3 = $connexio3->prepare($cnsidTema) ) {
            $stmt->bind_param("d", $idAmigable);
            $idAmigable = $url->obtenirID();
            $stmt->execute();
            $stmt->store_result();
            if ( $stmt->num_rows() > 0 ) {
               require 'EdicioPack.php';
               $stmt->bind_result($dataiEd, $datafEd, $cursEscEd, $fissEd, $gtafEd,
                  $dataResEd, $anyEd, $mesEd, $cursEd, $idCursEd, $horesEd, $idInfoEd, $idUrl, $estatEd);
               $i = 0; $this->estat=1;
               while ( $stmt->fetch() ) {
                  $this->edicions[$i] = new EdicioPack($cursEd, $idUrl, $this->dispositiu, $anyEd, $mesEd, $horesEd, $dataiEd, $datafEd, $cursEscEd, $gtafEd, $dataResEd, $fissEd, $estatEd);

                  /* Es busca les tematiques de les infos corresponents del pack */
                  $stmt3->bind_param("d", $idInfoEd);
                  $stmt3->execute();
                  $stmt3->bind_result($idTema);
                  $stmt3->fetch();
                  if ( $idTemes != '' ) $idTemes .= "|";
                  $idTemes .= $idTema;

                  /* Recullo les ids de ls infos per consultar els docents */
                  if ( $textConsId != "" ) $textConsId .= " OR ";
                  $textConsId .= "i.ID = ".$idInfoEd;

                  $i++;
               }
               $connexio3->closeStmt();
            }
            else {
               $connexio->closeStmt();
               $this->estat=0;
            }
         }
         $connexio->closeStmt();
      }
      else {
         throw new Exception('',2602);
      }

      $connexio3->desconectarBD();

      if ( $this->estat == 1 ) {
         if ( $idTemes == '' ) throw new Exception('',2608);

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
               else if (intval($valors[0])>0 && $valors[0]== $horesPrimeraEdicio) //si el valor és un numero i les hores son iguals al curs
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
         $cnsNivellRel = "SELECT ID_NIVELL, ID_REL, ID_PACK_REL FROM filtres WHERE ID_INFO LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";

         if ( $stmt = $connexio->prepare($cnsNivellRel) ) {
            $stmt->bind_param("d", $idPack);
            $stmt->execute();
            $stmt->bind_result($idsNivell, $idRel, $idPackRel);
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

         require_once 'Curs.php';
         /*Es busca els cursos relacionats de la info */
         $cntRel = 0;
         $cntRelReals = 0;
         while ( $cntRel < count($idsRel) ) {
            $idInfo = $idsRel[$cntRel];
            if ( intval($idInfo) < intval($limitFiltrePacks) ) {
               // Es tracta d'una info
               $cnsCodi = "SELECT CODI_CURS FROM informacio WHERE ID=?";
               if ( $stmt = $connexio->prepare($cnsCodi) ) {
                  $stmt->bind_param("d", $idInfo);
                  $stmt->execute();
                  $stmt->bind_result($codiCurs);
                  $stmt->fetch();
                  $curs = new Curs($codiCurs, $this->dispositiu);
                  if ($curs->obtenirEstat()==1) {
                     $cursosRel[$cntRelReals] = new Curs($codiCurs, $this->dispositiu);
                     $cntRelReals++;
                  }
                  $connexio->closeStmt();
               }
               else {
                  throw new Exception('',2610);
               }
            }
            else {
               // Es tracta d'un pack
               $pack = new Pack($idInfo, $this->dispositiu);
               if ($pack->obtenirEstat()==1) {
                  $cursosRel[$cntRelReals] = $pack;
                  $cntRelReals++;
               }
            }
            $cntRel++;
         }
         require_once 'LlistatCursosProva.php';
         $this->cursos_relacionats = new LlistatCursosProva($cursosRel);

         $this->packs_relacionats = null;

         /*Es busca els packs relacionats de la info */
         if ( $idPackRel != null ) {
            $idsPacksRel = explode('|',$idPackRel);
            $cntPackRel = 0;
            $cntPackRelReals = 0;
            while ( $cntPackRel < count($idsPacksRel) ) {
               $idInfo = $idsPacksRel[$cntPackRel];
               // Es tracta d'un pack
               $pack = new Pack($idInfo, $this->dispositiu);
               if ($pack->obtenirEstat()==1) {
                  $packsRel[$cntPackRelReals] = $pack;
                  $cntPackRelReals++;
               }
               $cntPackRel++;
            }
            $this->packs_relacionats = new LlistatCursosProva($packsRel);
         }

         // /* Es busca les tematiques de les infos corresponents del pack */

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
      $connexio->desconectarBD();

   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens els cursos relacionats
   * @return Obtens els cursos relacionats
   * @throws Si el curs no té una llista de cursos relacionats, envia l'excepció «No existeixen cursos relacionats del curs»
   */
   public function obtenirCursosRelacionats() {
      if ($this->cursos_relacionats==null)
         throw new Exception('', 2620);
      return $this->cursos_relacionats;
   }

   /*
   * @brief Obtens un el curs relacionats
   * @return Obtens el curs relacionat corresponent a la posicio $posicio de la llista de cursos relacionats
   */
   public function obtenirCursRelacionat($posicio) {
      if ($this->obtenirCursosRelacionats() == null)
         throw new Exception('', 2621);
      else if ($posicio>=0 && $posicio < $this->obtenirCursosRelacionats()->obtenirNumeroElements())
         return $this->obtenirCursosRelacionats()->obtenirCurs($posicio);
      else
         throw new Exception('', 2621);
   }

   /*
   * @brief Obtens els packs relacionats
   * @return Obtens els packs relacionats
   * @throws Si el curs no té una llista de packs relacionats, envia l'excepció «No existeixen packs relacionats del curs»
   */
   public function obtenirPacksRelacionats() {
      return $this->packs_relacionats;
   }

   /*
   * @brief Obtens un el pack relacionats
   * @return Obtens el pack relacionat corresponent a la posicio $posicio de la llista de packs relacionats
   */
   public function obtenirPackRelacionat($posicio) {
      if ($this->obtenirPacksRelacionats() != null && $posicio>=0 && $posicio < $this->obtenirPacksRelacionats()->obtenirNumeroElements())
         return $this->obtenirPacksRelacionats()->obtenirCurs($posicio);
      else
         return null;
   }

   /*
   * @brief Obtens la presentacio
   * @return Obtens la presentacio
   * @throws Si el curs no té una presentacio, envia l'excepció «No existeix la presentacio del curs»
   */
   private function __obtenirPresentacio() {
      if ($this->presentacio==null)
         throw new Exception('', 2622);
      return $this->presentacio;
   }

   /*
   * @brief Obtens l'autoria
   * @return Obtens l'autori
   */
   private function __obtenirAutoria() {
      return $this->autoria;
   }

   /*
   * @brief Obtens els destinataris
   * @return Obtens els destinataris
   * @throws Si el curs no té un destinatari, envia l'excepció «No existeix xxxx del curs»
   */
   private function __obtenirDestinataris() {
      if ($this->destinataris==null)
         throw new Exception('', 2623);
      return $this->destinataris;
   }

   /*
   * @brief Obtens el programa
   * @return Obtens el programa
   * @throws Si el curs no té un programa, envia l'excepció «No existeix el programa del curs»
   */
   private function __obtenirProgramaMin() {
      return $this->programes_min;
   }

   /*
   * @brief Obtens les valoracions
   * @return Obtens les valoracions
   * @throws Si el curs no té una valoracio, envia l'excepció «No existeix les valoracions del curs»
   */
   private function __obtenirValoracions() {
      if ($this->valoracions==null)
         throw new Exception('', 2626);
      return $this->valoracions;
   }

   /*
   * @brief Obtens els docents
   * @return Obtens els docents
   * @throws Si el curs no té una llista de docents, envia l'excepció «No existeixen docents del curs»
   */
   private function __obtenirDocents() {
      if ($this->docents==null)
         throw new Exception('', 2627);
      return $this->docents;
   }

   /*
   * @brief Obtens el docent
   * @return Obtens el docent corresponent a la posicio $posicio de la llista de docents
   */
   private function __obtenirDocent($posicio) {
      if ($this->__obtenirDocents() == null and count($this->__obtenirDocents())==0)
         throw new Exception('', 2628);
      else if ($posicio>=0 && $posicio < count($this->__obtenirDocents()))
         return $this->docents[$posicio];
      else
         throw new Exception('', 2629);
   }

   /*
   * @brief Obtens les edicions
   * @return Obtens les edicions del curs
   * @throws Si el curs no té una llista d'edicions, envia l'excepció «No existeixen edicions del curs»
   */
   private function __obtenirEdicions() {
      if ($this->edicions==null)
         throw new Exception('', 2630);
      return $this->edicions;
   }

   /*
   * @brief Obtens una edició del curs
   * @return Obtens l'edicio corresponent a la posicio $posicio de la llista d'edicions
   */
   private function __obtenirEdicio($posicio) {
      if ($this->__obtenirEdicions() == null and count($this->__obtenirEdicions())==0)
         throw new Exception('', 2631);
      else if ($posicio>=0 && $posicio < count($this->__obtenirEdicions()))
         return $this->edicions[$posicio];
      else
         throw new Exception('', 2632);
   }

   /*
   * @brief Consulta si es visualitza des d'un mòbil
   * @return Obtens TRUE si es visualitza des d'un mòbil, altramanet retorna FALSE
   */
   private function __isMobile() {
      if ($this->dispositiu == "mobil") return true;
      else return false;
   }

   /*********************************** FUNCIONS MOSTRAR ***********************************/

   /**
   * @brief Mostra la informació d'un curs
   * @return Mostra la informació d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   public function mostrarInfo() {
      for ($i = 0; $i<count($this->edicions); $i++) {
         $this->edicions[$i]->setInfo();
      }
      if (!$this->__isMobile()) {
         $mostrar = "<div class='container' id='prisma-curs'><div class='row'>";
         $mostrar .= "<div class='col-12 col-lg-9 sticky-body'>";
         $mostrar .= $this->mostrarTitolInfo();
         $mostrar .= $this->mostrarImgLargeInfo();
         $mostrar .= $this->mostrarInformacioInfo();
         $mostrar .= "</div>";
         $mostrar .= "<div class='col-12 col-lg-3 sticky-sidebar position-relative'>";
         $mostrar .= $this->mostrarMesInformacio();
         $mostrar .= "</div></div></div>";
      }
      else {
         $mostrar = $this->mostrarImgLargeInfo(); //Mostrar imatge
         $mostrar .= $this->mostrarPeuFix();
         $mostrar .= "<div class='container' id='prisma-curs'>";
         $mostrar .= $this->mostrarTitolInfo(); //Mostrar TITOL
         $mostrar .= $this->mostrarInformacioInfo(); //Mostrar el bloc de la informació del curs
         $mostrar .= "<div class='col-12 col-lg-3 sticky-sidebar position-relative'>";
         $mostrar .= $this->mostrarMesInformacio();
         $mostrar .= "</div></div>";
      }
      return $mostrar;
   }

   /**
   * @brief Mostra l'apartat els quatre cursos relacionats d'un pack
   * @return Mostra l'apartat els quatre cursos relacionats tal i com es mostra a la pestanya d'Informació d'un curs
   */
   public function mostrarCursosRelacionats() {
      $mostrar="<div class='d-flex flex-wrap'>";

      $numero_cursos = $this->obtenirCursosRelacionats()->obtenirNumeroElements();
      $cnt=0;
      while ($cnt < $numero_cursos) {
         $curs=$this->obtenirCursRelacionat($cnt);
         $mostrar.=$curs->mostrarCursRelacionat();
         $cnt++;
      }
      $mostrar.="</div>";

      return $mostrar;
   }

    /**
    * @brief Mostra l'apartat els quatre packs relacionats d'un curs tal «INFORMACIO»
    * @return Mostra l'apartat els quatre packs relacionats tal i com es mostra a la pestanya d'Informació d'un curs
    */
    public function mostrarPacksRelacionats() {
       $mostrar="<div class='d-flex flex-wrap w-100'>";

       $numero_cursos = $this->obtenirPacksRelacionats()->obtenirNumeroElements();
       $cnt=0;
       while ($cnt < $numero_cursos) {
          $curs=$this->obtenirPackRelacionat($cnt);
          $mostrar.=$curs->mostrarCursRelacionat();
          $cnt++;
       }
       $mostrar.="</div>";

       return $mostrar;
   }

   /**
   * @brief Mostra la informació del titol d'un pack
   * @return Mostra la informació del titol d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   public function mostrarTitolInfo() {
      $mostrar = "<div class='info-titol' id='titol'>";
      $mostrar .= "<h1>".$this->obtenirTitol()->obtenirTextHTML()."</h1>";

      if ($this->__isMobile() || $vista == "2") {
         $mostrar .= "<div class='d-flex flex-row flex-wrap align-items-center'>
         <div class='curs-linia'>Cursos en línia</div>";
         //$mostrar .= $this->etiquetaNovetat();
         $mostrar .= $this->etiquetaPromocio();
         $mostrar .= $this->etiquetaCursAmbPerfil2();
         $mostrar .= $this->__etiquetaCDD3();

         $textHores = "";
         $textNivells = "";
         $cntEd = 0;
         while ($cntEd < count($this->__obtenirEdicions())) {
            $edicio = $this->__obtenirEdicions()[$cntEd];

            /* Text hores */
            if ($cntEd == 0)
               $textHores .= $edicio->obtenirHores()->obtenirNumero()." h";
            else
               $textHores .= "<span class='mx-1'>|</span><span class='hores'>".$edicio->obtenirHores()->obtenirNumero()." h</span>";
            $cntEd++;
         }
         /* Text nivells */
         $cntNiv = 0;
         while ($cntNiv < count($this->obtenirNivells())) {
            if ($cntNiv == 0)
               $textNivells .= $this->obtenirNivells()[$cntNiv]->obtenirText();
            else
               $textNivells .= "<span class='nivells'>|</span><span class='nivells'>".$this->obtenirNivells()[$cntNiv]->obtenirText()."</span>";
            $cntNiv++;
         }
         $mostrar .= "<div class='cnt-hores'><i class='far fa-clock mr-1'></i>";
         $mostrar .= $textHores."</div>";

         $mostrar .= "<div class='cnt-niv'><i class='fas fa-signal mr-1'></i>";
         $mostrar .= $textNivells."</div>";
      }
      else {
         $mostrar .= "<div class='d-flex flex-row flex-wrap align-items-start align-items-sm-center'>
         <div class='curs-linia mb-3 mb-sm-0'>Cursos en línia</div>";

         //$mostrar .= $this->etiquetaNovetat();
         $mostrar .= $this->etiquetaPromocio();
         $mostrar .= $this->etiquetaCursAmbPerfil2();
         $mostrar .= $this->__etiquetaCDD3();

         /*
         $cntPerf=0;
         while ($this->obtenirPerfil($cntPerf)!=null) {
            $perfilEd = $this->obtenirPerfil($cntPerf);
            $shortnamePerfil = $perfilEd->obtenirNomCurt()->obtenirText();

            require_once 'Text.php';
            $textClassPerfil = new Text($shortnamePerfil);
            $textClassPerfil->obtenirNomCurt();
            $classPerfil = $textClassPerfil->obtenirText();

            $mostrar .= "<div role='button' title=\"Mostra totes les edicions dels
            cursos que acrediten el perfil «".$perfilEd->obtenirNomLlarg()->obtenirText()."»\"
            class='perfil-curs text-center ".$classPerfil." border-radius-2 d-flex
            align-items-center justify-content-between p-0 ml-0 mb-0 mt-0'
            onclick=\"location.href='https://www.prisma.cat/perfils-professionals'\">";
            $mostrar .= "<span class='px-2'>".$perfilEd->obtenirNomCurt()->obtenirText()."</span></div>";
            $cntPerf++;
         }
         $cntPerf=0;
         while ($this->obtenirPerfilPendent($cntPerf)!=null) {
            $perfilEd = $this->obtenirPerfilPendent($cntPerf);
            $shortnamePerfil = $perfilEd->obtenirNomCurt()->obtenirText();

            require_once 'Text.php';
            $textClassPerfil = new Text($shortnamePerfil);
            $textClassPerfil->obtenirNomCurt();
            $classPerfil = $textClassPerfil->obtenirText();

            $mostrar .= "<div role='button' title=\"Mostra totes les edicions dels
            cursos que acrediten el perfil «".$perfilEd->obtenirNomLlarg()->obtenirText()."»\"
            class='perfil-curs text-center ".$classPerfil." border-radius-2 d-flex
            align-items-center justify-content-between p-0 ml-0 mb-0 mt-0'
            onclick=\"location.href='https://www.prisma.cat/perfils-professionals'\">";
            $mostrar .= "<span class='px-2'>".$perfilEd->obtenirNomCurt()->obtenirText()."</span>
            <div class='perfilPendent'><div class='border m-0'></div><div class='nom position-absolute'>Pendent</div></div>
            </div>";
            $cntPerf++;
         }*/
      }
      $mostrar .= "</div></div>";
      return $mostrar;
   }

   /**
   * @brief Mostra la imatge allargada d'un pack
   * @return Mostra la imatge allargada d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   public function mostrarImgLargeInfo() {
      $altImg=$this->obtenirImgLarge()->obtenirAlt();
      $versioImg = $this->obtenirImgLarge()->obtenirVersio();
      $linkImg="https://www.prisma.cat".$this->obtenirImgLarge()->obtenirLink();
      $linkImgWeb=substr($linkImg, 0, -4).".webp";

      $linkImgOrig = substr($linkImg, 0, -4);
      $linkImg .= "?ver=".$versioImg;
      $linkImgWeb = $linkImgOrig.".webp?ver=".$versioImg;

      $linkImg400JPG=$linkImgOrig."-400.jpg?ver=".$versioImg;
      $linkImg700JPG=$linkImgOrig."-700.jpg?ver=".$versioImg;
      $linkImg750JPG=$linkImgOrig."-750.jpg?ver=".$versioImg;
      $linkImg400Webp=$linkImgOrig."-400.webp?ver=".$versioImg;
      $linkImg700Webp=$linkImgOrig."-700.webp?ver=".$versioImg;
      $linkImg750Webp=$linkImgOrig."-750.webp?ver=".$versioImg;

      $cont = "<div class='info-banner position-relative mb-4'>
        <div class='etiquetaPack position-absolute'>
          <div class='nom'><em>Pack</em></div>
        </div>
        <div class='noComencat promocio'>
          <div class='border'></div>
          <div class='nom font-weight-bold'>PROMOCIÓ</div>
        </div>
      <picture>
   		<source media='(max-width: 576px)' type='image/webp' data-srcset='".$linkImg400Webp."' srcset='".$linkImg400Webp."'>
   		<source media='(max-width: 576px)' type='image/jpeg' data-srcset='".$linkImg400JPG."' srcset='".$linkImg400JPG."'>
   		<source media='(max-width: 768px)' type='image/webp' data-srcset='".$linkImg700Webp."' srcset='".$linkImg700Webp."'>
   		<source media='(max-width: 768px)' type='image/jpeg' data-srcset='".$linkImg700JPG."' srcset='".$linkImg700JPG."'>
   		<source media='(max-width: 1200px)' type='image/webp' data-srcset='".$linkImg750Webp."' srcset='".$linkImg750Webp."'>
   		<source media='(max-width: 1200px)' type='image/jpeg' data-srcset='".$linkImg750JPG."' srcset='".$linkImg750JPG."'>
   		<source media='(min-width: 1201px)' type='image/webp' data-srcset='".$linkImgWeb."' srcset='".$linkImgWeb."'>
   		<source media='(min-width: 1201px)' type='image/jpeg' data-srcset='".$linkImg."' srcset='".$linkImg."'>
   		<source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
   		<source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
   		<img data-src='".$linkImg."' alt=\"".$dscImg."\" src='".$linkImg."'
   			class='w-100 border-radius-2 lazyload'/>
   	</picture></div>";

      $mostrar = $cont;

      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un pack
   * @return Mostra la informació d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   public function mostrarInformacioInfo() {
      $mostrar = "<div class='v_ord'>
      <ul class='nav nav-tabs nav-justified info-descripcio-nav' role='tablist' aria-label='Contingut del curs'>
      <li role='tab'><a class='text-center negreta500 active' href='#descripcio' data-toggle='tab' aria-controls='descripcio' id='tab-descripcio'><i class='fa fa-bookmark'></i>Descripció</a></li>
      <li role='tab'><a class='text-center negreta500' href='#programa' data-toggle='tab' aria-controls='programa' id='tab-programa'><i class='fa fa-cube'></i>Programa</a></li>
      <li role='tab'><a class='text-center negreta500' href='#docents' data-toggle='tab' aria-controls='docents' id='tab-docents'><i class='fa fa-user'></i>Docents</a></li>
      <li role='tab'><a class='text-center negreta500' href='#dates' data-toggle='tab' aria-controls='dates' id='tab-dates'><i class='fat fa-calendar'></i>Dates</a></li>
      <li role='tab'><a class='text-center negreta500' href='#preu' data-toggle='tab' aria-controls='preu' id='tab-preu'><i class='fa fa-credit-card'></i>Preu</a></li>
      </ul><div class='tab-content info-descripcio-tab'>";
      // $mostrar = "<div class='v_ord'>
      // <ul class='nav nav-tabs nav-justified info-descripcio-nav' role='tablist' aria-label='Contingut del curs'>
      // <li role='tab'><a class='text-center negreta500 active' href='#descripcio' data-toggle='tab' aria-controls='descripcio' id='tab-descripcio'><i class='fa fa-bookmark'></i>Descripció</a></li>
      // <li role='tab'><a class='text-center negreta500' href='#programa' data-toggle='tab' aria-controls='programa' id='tab-programa'><i class='fa fa-cube'></i>Programa</a></li>
      // <li role='tab'><a class='text-center negreta500' href='#docents' data-toggle='tab' aria-controls='docents' id='tab-docents'><i class='fa fa-user'></i>Docents</a></li>
      // <li role='tab'><a class='text-center negreta500' href='#dates' data-toggle='tab' aria-controls='dates' id='tab-dates'><i class='fat fa-calendar'></i>Data</a></li>
      // <li role='tab'><a class='text-center negreta500' href='#preu' data-toggle='tab' aria-controls='preu' id='tab-preu'><i class='fa fa-credit-card'></i>Preu</a></li>
      // <li role='tab'><a class='text-center negreta500' href='#valoracions' data-toggle='tab' aria-controls='valoracions' id='tab-valoracions'><i class='fas fa-comments'></i>Valoracions</a></li>
      // </ul><div class='tab-content info-descripcio-tab'>";
      $mostrar .= $this->__mostrarDescripcio("1").$this->__mostrarPrograma("1");
      $mostrar .= $this->__mostrarDocents("1").$this->__mostrarDates("1");
      $mostrar .= $this->__mostrarPreu("1");
      // $mostrar .= $this->__mostrarValoracions("1");
      $mostrar .= "</div><div class='d-flex justify-content-between'>
      <p><a role='link' class='color-text'";
      $mostrar .= " href='https://www.prisma.cat/cursos' target='_self' title='Visualitza tots els cursos'>";
      $mostrar .= "<i class='fas fa-long-arrow-alt-left mr-2'></i>Mostra tots els cursos</a></p>";
      $mostrar .= "<p><a id='torna' class='color-text' href='#' role='link' title='Torna a dalt'>";
      $mostrar .= "<i class='fas fa-long-arrow-alt-up mr-2'></i>Torna a dalt</a></p></div></div>";

      $mostrar .= "<div id='accord' class='v_mob d-flex flex-column'><div class='panel-group' id='accordion' role='tablist' aria-multiselectable='true'>";
      $mostrar .= $this->__mostrarDescripcio("2").$this->__mostrarPrograma("2");
      $mostrar .= $this->__mostrarDocents("2").$this->__mostrarDates("2");
      $mostrar .= $this->__mostrarPreu("2");
      // $mostrar .= $this->__mostrarValoracions("2");
      $mostrar .= "</div><p><a role='link' class='color-text' target='_self' ";
      $mostrar .= "href='https://www.prisma.cat/cursos' title='Visualitza tots els cursos'>";
      $mostrar .= "<i class='fas fa-long-arrow-alt-left mr-2'></i>Mostra tots els cursos</a></p></div>";
      return $mostrar;
   }

   /**
   * @brief Mostra l'apartat amb el format d'un tab si $vista és 1 amb el contingut $contingutOrd,
   * altrament mostra un accordion amb el contingut $contingutMov
   * @return Text de l'apartat el format d'un tab si $vista és 1 amb el contingut $contingutOrd,
   * altrament mostra un accordion amb el contingut $contingutMov. Si $obert és 1 es mostra desplegat
   */
   private function __mostrarApartatVista($vista, $idTab, $contingutOrd, $obert, $idAccord, $nomApartat, $contingutMov) {
      $show = "";
      $collapsed = " collapsed";
      $ariaExpanded = "false";
      $style = " style='height: 0px;'";
      if ( $obert == "1" ) {
         $show = " active show";
         $collapsed = "";
         $ariaExpanded = "true";
         $style = "";
      }

      if ( $vista == "1" ) {
         $mostrar = "<div class='tab-pane fade".$show."' id='".$idTab."' ";
         $mostrar .= "role='tabpanel' aria-labelledby='tab-".$idTab."'>";
         $mostrar .= "<div class='info-descripcio-tab-left sub-content text-justify ".$idTab."'>";
         $mostrar .= $contingutOrd;
         $mostrar .= "</div></div>";
      }
      else {
         $mostrar .= "<div class='panel panel-default'><div class='panel-heading".$collapsed."' ";
         $mostrar .= "role='tab' id='heading-".$idAccord."' data-toggle='collapse' data-target='#collapse-".$idAccord."' ";
         $mostrar .= "href='#collapse-".$idAccord."' aria-expanded='".$ariaExpanded."' aria-controls='collapse-".$idAccord."' ";
         $mostrar .= "onclick='canviarEstat(\"".$idAccord."\")'><p class='panel-title d-flex flex-wrap align-items-center font-weight-bold'>";
         $mostrar .= $nomApartat."<i class='fas fa-plus ml-2'></i></p>";
         $mostrar .= "</div><div id='collapse-".$idAccord."' class='panel-collapse collapse ".$show."' role='tabpanel' ";
         $mostrar .= "aria-labelledby='heading-".$idAccord."' aria-expanded='".$ariaExpanded."' ".$style.">";
         $mostrar .= "<div class='panel-body ".$idTab." text-justify pb-3'>";
         $mostrar .= $contingutMov;
         $mostrar .= "</div></div></div>";
      }
      return $mostrar;
   }

   /**
   * @brief Mostra un accordion amb el contingut $contingut
   * @return Mostra un accordion amb el contingut $contingut. Si $obert és 1 es mostra desplegat
   */
   private function __mostrarAccordion($idTab, $obert, $idAccord, $nomApartat, $contingut) {
      $show = "";
      $collapsed = " collapsed";
      $ariaExpanded = "false";
      $style = " style='height: 0px;'";
      if ( $obert == "1" ) {
         $show = " active show";
         $collapsed = "";
         $ariaExpanded = "true";
         $style = "";
      }

      $mostrar .= "<div class='panel panel-default'><div class='panel-heading".$collapsed."' ";
      $mostrar .= "role='tab' id='heading-".$idAccord."' data-toggle='collapse' data-target='#collapse-".$idAccord."' ";
      $mostrar .= "href='#collapse-".$idAccord."' aria-expanded='".$ariaExpanded."' aria-controls='collapse-".$idAccord."' ";
      $mostrar .= "onclick='canviarEstat(\"".$idAccord."\")'><p class='panel-title d-flex flex-wrap align-items-center font-weight-bold'>";
      $mostrar .= $nomApartat."<i class='fas fa-plus ml-2'></i></p>";
      $mostrar .= "</div><div id='collapse-".$idAccord."' class='panel-collapse collapse ".$show."' role='tabpanel' ";
      $mostrar .= "aria-labelledby='heading-".$idAccord."' aria-expanded='".$ariaExpanded."' ".$style.">";
      $mostrar .= "<div class='panel-body ".$idTab." text-justify pb-3'>";
      $mostrar .= $contingut;
      $mostrar .= "</div></div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya descripció d'un pack
   * @return Mostra la pestanya descripció d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   private function __mostrarDescripcio( $vista ) {
      $presentacio = $this->__obtenirPresentacio()->obtenirText();
      $autoria = $this->__obtenirAutoria();
      $destinataris = $this->__obtenirDestinataris()->obtenirText();

      $edicions = $this->__obtenirEdicions();

      $textPres = "<h2>Presentació</h2>".$presentacio;

      $first = 1; $articleAnt = null; $textArticle = ""; $articles = []; $cntArticle = 0;
      for ($i=0; $i<count($edicions); $i++) {
         $edicio = $this->__obtenirEdicions()[$i];
         $nomCurs = $edicio->obtenirTitol()->obtenirText();
         $obert = 0;

         /* ########### video ########### */

         $video = $edicio->obtenirVideo();
         if ( $videos != null ) {
            if ( $first ) {
               $textVideoOrd .= "<div class='row align-items-center justify-content-center'>";
            }
           $textVideoOrd .= "<div class='col-12 col-md-6'>".$video->mostrarVideoInfo()."</div>";
           if ( !$this->__isMobile() ) {
              $textVideoMov .= $video->mostrarVideoInfo();
           }
            if ( $first ) {
               $textVideoOrd .= "</div>";
            }
            $first = 0;
         }

         /* ########### objectius ########### */

         $objectius = $edicio->obtenirObjectius()->obtenirText();

         $nomApartatObjOrd = "<i class='fa fa-bookmark mr-2'></i><span class='f-auto'>Objectius del curs ".$nomCurs."</span>";
         $nomApartatObjMov = "<p class='objectius h3 font-weight-bold'><i class='fa fa-bookmark mr-2'></i><span class='font-weight-bold'>Objectius del curs ".$nomCurs."</span></p>";
         $contentAccordionObj = "<div class='objectius'>".$objectius."</div>";

         $accObj .= $this->__mostrarAccordion("objectius".$i, $obert, "obj".$i, $nomApartatObjOrd, $contentAccordionObj);
         $textObj .= $nomApartatObjMov.$contentAccordionObj;

         /* ########### articles ########### */
         $article = $edicio->obtenirArticle();
         if ( $article != null ) {
            if ( $articleAnt == null || ( $articleAnt != null && $article != $articleAnt ) ) {
               $articles[$cntArticle] = $article;
               $cntArticle++;
               $articleAnt = $article;
            }
         }

      }

      if ( $articles != null && count($articles) > 0 ) {
         $textArticle = "<div class='article-relacionat'>";
         if ( count($articles) == 1 ) $numArticles = "aquest article";
         else if ( count($articles) > 1 ) $numArticles = "aquests articles";
         $textArticle .= "<p>I, relacionat amb el <em>pack</em>, us recomanem ".$numArticles." del blog de PrisMa <em>Educat</em>:";
         for ( $i = 0; $i < count($articles); $i++) {
            $textArticle .= $articles[$i]->mostrarCntArticle();
         }
         $textArticle .= "</div>";

      }


      $textVideoOrd = $textVideoMov = "";
      $videos = $this->obtenirVideos();
      if ( $videos != null ) {
         if ( $first ) {
            $textVideoOrd .= "<div class='row align-items-center justify-content-center'>";
         }
         for ($cntVideos = 0; $cntVideos < count($videos); $cntVideos++) {
           $textVideoOrd .= "<div class='col-12 col-md-6'>".$videos[$cntVideos]->mostrarVideoInfo()."</div>";
           if ( !$this->__isMobile() ) {
              $textVideoMov .= $videos[$cntVideos]->mostrarVideoInfo();
           }
         }
         if ( $first ) {
            $textVideoOrd .= "</div>";
         }
         $first = 0;
      }

      $textAutoria = "";
      if ($autoria != null)
         $textAutoria .= $autoria->obtenirText();

      $textDest = "<h2>Destinataris</h2>".$destinataris;

      if ( $textArticle == '' ) $classObj = 'pb-4';
      else $classObj = 'pb-2';
      $textObjOrd .= "<h2>Objectius</h2><div class='".$classObj."'>".$accObj."</div>";
      $textObjMov .= "<h2>Objectius</h2><div class='".$classObj."'>".$textObj."</div>";

      $contEq1 = $textPres;
      $contEq2 = $textAutoria.$textDest;
      $contEq3 = $textArticle;
      $contingutOrd .= $contEq1.$textVideoOrd.$contEq2.$textObjOrd.$contEq3;
      $contingutMov .= $contEq1.$textVideoMov.$contEq2.$textObjMov.$contEq3;

      $nomApartat = "<i class='fa fa-bookmark mr-2'></i><span class='f-auto'>Descripció</span>";
      $mostrar = $this->__mostrarApartatVista($vista, "descripcio", $contingutOrd, "1", "desc", $nomApartat, $contingutMov);
      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya programa d'un curs tal «INFORMACIO»
   * @return Mostra la pestanya programa d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   private function __mostrarPrograma( $vista ) {
      $edicions = $this->__obtenirEdicions();

      for ($i=0; $i<count($edicions); $i++) {
         $edicio = $this->__obtenirEdicions()[$i];
         $programa = $edicio->obtenirPrograma()->obtenirText();
         $nomCurs = $edicio->obtenirTitol()->obtenirText();

         $nomApartatOrd = "<i class='fa fa-cube mr-2'></i><span class='f-auto'>Programa del curs ".$nomCurs."</span>";
         $nomApartatMov = "<p class='programa h3 font-weight-bold'><i class='fa fa-cube mr-2'></i><span class='font-weight-bold'>Programa del curs ".$nomCurs."</span></p>";
         $contentAccordion = "<div class='objectius'>".$programa."</div>";

         $accordions .= $this->__mostrarAccordion("programa".$i, 0, "prog".$i, $nomApartatOrd, $contentAccordion);
         $textAcc .= $nomApartatMov.$contentAccordion;
      }

      $contingutOrd = "<h2>Programa</h2>".$accordions;

      $progMov = $this->__obtenirProgramaMin()->obtenirText();
      $contingutMov = "<h2>Programa</h2>".$progMov;

      $nomApartat = "<i class='fa fa-cube mr-2'></i><span class='f-auto'>Programa</span>";
      $mostrar = $this->__mostrarApartatVista($vista, "programa", $contingutOrd, "0", "prog", $nomApartat, $contingutMov);

      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya docents d'un pack
   * @return Mostra la pestanya docents d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   private function __mostrarDocents( $vista ) {
      $contDoc = "<h2>Docents</h2>";
      $nDoc = count($this->__obtenirDocents());
      $cnt = 0;
      while ($cnt < $nDoc) {
         $contDoc .= $this->__obtenirDocent($cnt)->mostrarTutorCurs();
         $cnt++;
      }
      $nomApartat = "<i class='fa fa-user mr-2'></i><span class='f-auto'>Docents</span>";
      $mostrar = $this->__mostrarApartatVista($vista, "docents", $contDoc, "0", "pon", $nomApartat, $contDoc);

      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya dates d'un pack
   * @return Mostra la pestanya dates d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   private function __mostrarDates( $vista ) {
      $cursEsc2='';
      $trobatSS=false;
      $trobatN=false;
      $teperfil=false;

      $edicions = $this->__obtenirEdicions();

      $contEd.="<ul class='llistes'>";

      $numero_edicions = count($edicions);

      $cnt = 0; $mesPendent='';
      while ($cnt < $numero_edicions) {
         $edicioActual=$edicions[$cnt];

         $cursEscAct=$edicioActual->obtenirCursEscolar()->obtenirText();
         $mesEdicioActual=$edicioActual->obtenirMes()->obtenirText();
         $anyEdicioActual=$edicioActual->obtenirAny()->obtenirNumero();
         $horesEdicioActual=$edicioActual->obtenirHores()->obtenirNumero();

         //Si és el primer registre assignar curs_escolar1 = CURS_ESCOLAR
         if ($cnt==0) {
            $cursEsc1=$cursEscAct;
            $edCursEsc1=$edicioActual;
         }
         else if ($cursEsc1 != $cursEscAct) {
            $cursEsc2=$cursEscAct;
            $edCursEsc2=$edicioActual;
         }

         /* Assignem la primera edicio */
         if ($cnt == 0) {
             $firstEd=$edicioActual;
         }

         //Si és l'últim registre assignar mes = MES actual i ANY = any actual
         if ($cnt == $numero_edicions-1) {
             $mes=$mesEdicioActual;
             $any=$anyEdicioActual;
             $ultimaEdicio=$edicioActual;
         }

         require_once 'ConnexioBBDD_PreparedStatment.php';
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();
         if (($mesEdicioActual=='03' || $mesEdicioActual=='04') && !$trobatSS) {
            //Buscar les dates d'inici i de fi de setmana santa
            $consultaDataIF = "SELECT DATAI, DATAF FROM festius_dates WHERE
               DATAI <= ? AND ? <= DATAF AND ID_FESTIU = 1 ORDER BY DATAI DESC";
            $stmtDataIF = $connexio->prepare($consultaDataIF);
            $stmtDataIF->bind_param("ss", $datai, $dataf);
            $datai = $edicioActual->obtenirDataInici()->obtenirText();
            $dataf = $edicioActual->obtenirDataFi()->obtenirText();
            $stmtDataIF->execute();
            $stmtDataIF->store_result();
            if ( $stmtDataIF->num_rows() > 0 )
               $trobatSS = true;
            $stmtDataIF->bind_result($datai, $dataf);
            $stmtDataIF->fetch();
            $connexio->closeStmt();
         }

         if ((($mesEdicioActual=='11' && $horesEdicioActual==100) || $mesEdicioActual=='12') && !$trobatN)
            $trobatN=true;

         if ($edicioActual->obtenirDataRes()==null) {
            if ($mesPendent!='')
               $mesPendent .= ' - ';
            $mesPendent .= $edicioActual->obtenirMes()->obtenirMesLlarg();
            $nomCursPendent .= $edicioActual->obtenirTitol()->obtenirText();
         }

         $contEd .= "<li>".$edicioActual->mostrarEdicioInfo()."</li>";
         if ( $edicioActual->obtenirCurs()->cursEsMixt() ) {
            $contEd .= $edicioActual->mostrarSessionsSincronesEdicioInfo();
         }

         $cnt++;
      }

      $contEd.="</ul>";

      $primera_lletra = substr($mesPendent, 0, 1);
      $conte = preg_match_all("/[aeiou]/", $primera_lletra);

      if ($conte == 1)
        $preposicio = "d'";
      else
        $preposicio = "de ";

      if ($mesPendent!='')
         $contEd.="<p><span class='ultimsdies font-weight-bold'>(*)</span> PrisMa, com a entitat organitzadora,
         ha sol&middot;licitat el reconeixement de l'<strong>edici&oacute; ".$preposicio.$mesPendent." </strong>
         del curs ".$nomCursPendent." al Departament d'Ensenyament. Les edicions anteriors tenen data de resoluci&oacute;
         i per tant, ja estan reconegudes com a Formaci&oacute; Permanent del Professorat.</p>";

      $cursReconegut=true;
      $exCursReconegut=0;
      $cns = "SELECT valor FROM params WHERE TIPUS=? AND VALOR=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)";
      $stmt = $connexio->prepare($cns);
      for ($i=0; $i<$numero_edicions; $i++) {
         $stmt->bind_param("ss", $tipus, $valor);
         $tipus = 'curs-no-reconegut';
         $valor = $edicions[$i]->obtenirCurs()->obtenirCodi()->obtenirText();
         $stmt->execute();
         $stmt->bind_result($valorParam);
         $stmt->store_result();
         if ( $stmt->num_rows() > 0 ) $cursReconegut=false;
         $connexio->closeStmt();
         if (!$cursReconegut) { //no hi ha cap curs reconegut
            $cns = "SELECT valor FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)";
            $stmt = $connexio->prepare($cns);
            $stmt->bind_param("s", $tipus);
            $tipus = 'text-curs-no-reconegut';
            $stmt->execute();
            $stmt->bind_result($valorParam);
            $stmt->fetch();
            $connexio->closeStmt();

            $contEd.="<p>".$valorParam." <span role='button' class='llista-espera font-weight-bold'
            data-toggle='modal' data-target='#modalTextNoReconegut'>
            Vols que t'avisem quan s'obrin les inscripcions?</span></p>";
            $contEd .= "<div class='modal fade in' id='modalTextNoReconegut' tabindex='-1' role='dialog' aria-labelledby='modalTitleNoRec' aria-hidden='true'>";
            $contEd .= "<div class='modal-dialog modal-dialog-centered modal-notify modal-success justify-content-center text-center' role='document'>";
            $contEd .= "<div class='modal-content w-100 border-0'><div class='modal-header border-0 text-white background-prisma'>";
            $contEd .= "<p class='modal-title modal-title-success text-white m-0' id='modalTitleNoRec'>Vols que t'avisem quan s'obrin les inscripcions?</p>";
            $contEd .= "<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='text-white'>×</span></button></div>";
            $contEd .= "<div class='modal-body' id='modalBodyNoRec'>";
            $contEd .= "<div class='formulari-llista-espera d-flex flex-column'>";
            $contEd .= "<p>Completa les dades següents i quan s'obrin les inscripcions ens posarem en contacte amb tu.</p>";
            $contEd .= "<div class='d-flex flex-row w-100'>";
            $contEd .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $contEd .= "<label for='nom-no-rec'><span class='camp'>Nom</span><span class='req'>*</span></label>";
            $contEd .= "<input type='text' class='form-control' id='nom-no-rec' aria-required='' required=''>";
            $contEd .= "<span id='nom_no_rec_erroni'></span></div></div>";
            $contEd .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $contEd .= "<label for='cog-no-rec'><span class='camp'>Cognoms</span><span class='req'>*</span></label>";
            $contEd .= "<input type='text' class='form-control' id='cog-no-rec' aria-required='' required=''>";
            $contEd .= "<span id='cog_no_rec_erroni'></span></div></div>";
            $contEd .= "</div>";
            $contEd .= "<div class='d-flex flex-row w-100'>";
            $contEd .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $contEd .= "<label for='email-no-rec'><span class='camp'>Adreça electrònica</span><span class='req'>*</span></label>";
            $contEd .= "<input type='email' class='form-control' id='email-no-rec' aria-required='' required=''>";
            $contEd .= "<span id='correu_no_rec_erroni'></span></div></div>";
            $contEd .= "<div class='col-6'><div class='form-group field-wrap position-relative m-0'>";
            $contEd .= "<label for='telf-no-rec'><span class='camp'>Telèfon</span><span class='req'>*</span></label>";
            $contEd .= "<input type='tel' class='form-control' id='telf-no-rec' aria-required='' required='' maxlength='9'>";
            $contEd .= "<span id='telf_no_rec_erroni'></span></div></div>";
            $contEd .= "</div>";
            $contEd .= "<div class='d-flex flex-row w-100'>";
            $contEd .= "<div class='form-group field-wrap position-relative w-100 col-12 m-0'>";
            $contEd .= "<label for='obs-no-rec'><span class='camp'>Comentaris</span></label>";
            $contEd .= "<textarea type='text' class='form-control' id='obs-no-rec'></textarea>";
            $contEd .= "</div>";
            $contEd .= "</div>";
            $contEd .= "<label for='comprovaSpam-no-rec' class='comprovaSpam'>Si veus això, no omplis el camp!</label>";
            $contEd .= "<input id='comprovaSpam-no-rec' class='comprovaSpam' value=''>";
            $contEd .= "<input id='curs' name='curs' class='comprovaSpam' value='".$this->obtenirCodi()->obtenirText()."'>";
            $contEd .= "</div>
               <div id='loading-wrapper' style='display: none'>
                  <div id='loading-text'>Enviant...</div>
                  <div id='loading-content'></div>
               </div>
               <div id='modalSuccessBodyNoRec' style='display: none'>
            </div>

            </div>";
            $contEd .= "<div class='modal-footer justify-content-center text-center border-0 pt-0'>";
            $contEd .= "<button role='button' id='form_enviar_dades' class='boto-blau ";
            $contEd .= "border-0 border-radius-2 text-center negreta500 text-white m-0'>";
            $contEd .= "Envia</button>";
            $contEd .= "</div></div></div></div>";
         }
         else {
            $exCursReconegut = 1;
         }
      }
      $connexio->desconectarBD();

      if ( $exCursReconegut ) {
         /*$contEd.="<p>Les dates de les edicions subsegüents s’aniran concretant amb almenys dos o tres mesos ";
         $contEd.="d’antelació.</p>*/
		 $contEd.="<p>Cursos reconeguts com a activitats de <strong><a rel='noopener' target='_blank' ";
         $contEd.="href='http://xtec.gencat.cat/ca/formacio/formacio-altres-institucions/activitats-reconegudes/' ";
         $contEd.="title=\"Activiats reconegudes com a formació permanent del professorat pel Departament ";
         $contEd.="d'Educació\">formació permanent del professorat</a></strong> pel Departament d'Educació ";
         $contEd.="de la Generalitat de Catalunya d'acord amb l'<em>Ordre ENS/248/2012, de 20 d'agost de 2012, ";
         $contEd.="per la qual s'estableixen els requisits i el procediment per reconèixer ";
         $contEd.="activitats de formació permanent adreçades al professorat no universitari</em>.</p>";
      }

      $mostraAvis='';
      if ($trobatN) {
         $mostraAvis='NADAL'; //Mostrar_avis NADAL
         if($trobatSS)
             $mostraAvis.=' o SETMANA SANTA';
      }

      if ($trobatSS)
         $mostraAvis='SETMANA SANTA'; //Mostrar_avis SEMANA SANTA

      if($mostraAvis!='') {
         $contEd.="<p>Les edicions que coincideixen amb ".$mostraAvis." s'allargaran, ";
         $contEd.="atès que no hi ha tutorització durant les festes. Tot i això, el ";
         $contEd.="Campus estarà obert i hi haurà horari d'atenció els dies laborables.</p>";
      }

      /*Si només es mostra un sol curs escolar, s'ha de comprovar si l'última edició del curs_escolar té perfil
      // En cas de tenir perfil, s'indica el perfil professional que acredita, juntament amb l'enllaç corresponent
      i el curs escolar. Si són dos cursos escolars, es fa exactament el mateix per cada curs escolar.*/
      /* if ($cursEsc2=='') {
         $perfilEd=$edCursEsc1->obtenirPerfils();
         $contEd.=$this->__mostrarPerfilEdicions($perfilEd, $cursEsc1);
      }
      else {
         $perfilEd1=$edCursEsc1->obtenirPerfils();
         $perfilEd2=$edCursEsc2->obtenirPerfils();

         $equals = true;

         if (count($perfilEd1)!=count($perfilEd2))
             $equals = false;
         else {
             $cntNumPerfils=0;
             while ($cntNumPerfils < count($perfilEd2) && $equals) {
               $nom_perfil_curt1=$perfilEd1[$cntNumPerfils]->obtenirNomCurt()->convertirMin();
               $nom_perfil_curt2=$perfilEd2[$cntNumPerfils]->obtenirNomCurt()->convertirMin();

               if ($nom_perfil_curt1 != $nom_perfil_curt2)
                    $equals = false;
               $cntNumPerfils++;
             }
         }

         if ($equals) {
             $cursEsc="dels <strong>anys escolars ".$cursEsc1." i ".$cursEsc2."</strong>";
             $contEd.=$this->__mostrarPerfilEdicions($perfilEd2, $cursEsc);
         }
         else {
             $cursEsc1="de l'<strong>any escolar ".$cursEsc1."</strong>";
             $cursEsc2="de l'<strong>any escolar ".$cursEsc2."</strong>";
             $contEd.=$this->__mostrarPerfilEdicions($perfilEd1, $cursEsc1);
             $contEd.=$this->__mostrarPerfilEdicions($perfilEd2, $cursEsc2);
         }
      }*/

      /* Comprovem si té algun perfil pendent. Si és així, s'afageix els perfils pendents */
      /* $cntPerf=0;
      if ( $this->perfil_pendent == null or $this->perfil_pendent == '' )
         $numPerfilsPendents = 0;
      else
         $numPerfilsPendents = count($this->perfil_pendent);

      for ($cntPerf=0; $cntPerf < $numPerfilsPendents; $cntPerf++) {
         $shortnamePerfil = $this->perfil_pendent[$cntPerf]->obtenirNomCurt()->obtenirText();

         require_once 'Text.php';
         $textClassPerfil = new Text($shortnamePerfil);
         $textClassPerfil->obtenirNomCurt();
         $classPerfil = $textClassPerfil->obtenirText();

         $textPerfil = "<strong class='".$classPerfil."'>";
         $textPerfil .= $this->perfil_pendent[$cntPerf]->obtenirNomLlarg()->obtenirText();
         $textPerfil .= "</strong>";
         if ($cntPerf > 0) {
            if ($cntPerf < $numPerfilsPendents)
               $textsPerfil .= " i ";
            else
               $textsPerfil .= ", ";
         }
         $textsPerfil .= $textPerfil;
      }
      if ($numPerfilsPendents > 0) {
         $linkPerfil = "https://www.prisma.cat/perfils-professionals/";

         $mesActual = date("n");
         $anyActual = date("Y");
         $anyAnterior = $anyActual - 1;
         $anySeguent = $anyActual + 1;

         if ( $mesActual >= 9 && $mesActual <= 12 ) //curs escolar pendent és l'anterior
            $cursEscPerfPend = $anyActual."/".$anySeguent;
         else
            $cursEscPerfPend = $anyAnterior."/".$anyActual;

         $contEd.="<p>Les edicions de l'<strong>any escolar ".$cursEscPerfPend."</strong>
         d'aquest curs estan pendents d'acreditar el perfil professional ".$textsPerfil.".
         Podeu consultar-ne tota la informació a la nostra pàgina  d'<strong>
         <a href='".$linkPerfil."' target='_self' title='Acreditació de perfils
         professionals'>acreditació de perfils professionals</a></strong>.</p>";
      }*/

      /* if ($firstEd->obtenirCodiFiss()!=null && $ultimaEdicio->obtenirCodiFiss()!=null) {
         $contEd.="<p>Cursos reconeguts com a activitat de <strong><a rel='noopener' target='_blank' ";
         $contEd.="href='https://www.prisma.cat/activitats-formacio-interes-serveis-socials'";
         $contEd.="title=\"Activiats reconegudes com a formació d’interès en serveis socials pel Departament ";
         $contEd.="de Treball, Afers Socials i Famílies\">formació d’interès en serveis socials</a></strong> ";
         $contEd.="pel Departament de Treball, Afers Socials i Famílies de la Generalitat de Catalunya.</p>";
      }
      else if (
         ( $firstEd->obtenirCodiFiss()!=null && $ultimaEdicio->obtenirCodiFiss()==null ) ||
         ( $firstEd->obtenirCodiFiss()==null && $ultimaEdicio->obtenirCodiFiss()!=null )
      ) {
         //Cal buscar la primera edició de FISS => edStartFiss => de juny de 2019
         // Cal buscar la última edició de FISS => edLastFiss => desembre de 2021


         $connexio = new ConnexioBBDDSTMT();
      	$connexio->connectarBD();

         $cnsFirst = "SELECT DATAI FROM curs WHERE FISS IS NOT NULL AND CURS= ? AND ESTAT != 'T' ORDER BY DATAI ASC LIMIT 1";
         $cnsLast = "SELECT DATAI FROM curs WHERE FISS IS NOT NULL AND CURS= ? AND ESTAT != 'T' ORDER BY DATAI DESC LIMIT 1";

         $codiCurs = $this->obtenirCodi()->obtenirText();

         $stmt = $connexio->prepare($cnsFirst);
         $stmt->bind_param("s", $codiCurs);
         $stmt->execute();
         $stmt->bind_result($dataStartEd);
         $stmt->fetch();
         $connexio->closeStmt();

         $stmt = $connexio->prepare($cnsLast);
         $stmt->bind_param("s", $codiCurs);
         $stmt->execute();
         $stmt->bind_result($dateEndEd);
         $stmt->fetch();
         $connexio->closeStmt();

         $connexio->desconectarBD();

         $monthStart = explode('-', $dataStartEd)[1];
         $yearStart = explode('-', $dataStartEd)[0];

         $monthLast = explode('-', $dateEndEd)[1];
         $yearLast = explode('-', $dateEndEd)[0];

         $textDataStart = new Text( $monthStart );
         $firstEdFiss = $textDataStart->obtenirDeMesLlarg();

         $textDataMaj = new Text($textDataStart->obtenirMesLlarg());
         $edFiss = $textDataMaj->convertirMajPrimLletra();

         $textDataLast = new Text( $monthLast );
         $lastEdFiss = $textDataLast->obtenirMesLlarg();

         if ( $yearStart != $yearLast ) {
            $sntEd = "<strong>".$firstEdFiss." de ".$yearStart."</strong> fins a <strong>".$lastEdFiss." de ".$yearLast."</strong>";
         }
         else if ( $yearStart == $yearLast && $monthStart != $monthLast )  {
            $sntEd = "<strong>".$firstEdFiss."</strong> fins a <strong>".$lastEdFiss." de ".$yearLast."</strong>";
         }
         else {
            $sntEd = "<strong>".$edFiss." de ".$yearStart."</strong>";
         }

         $contEd.="<p>Les edicions des ".$sntEd." d'aquest curs estan reconegudes
         com a activitat de <strong>formació d'interès en serveis socials</strong>
         pel Departament de Treball, Afers Socials i Famílies de la Generalitat de Catalunya.";

         if ( $firstEd->obtenirCodiFiss()!=null && $ultimaEdicio->obtenirCodiFiss()==null ) {
            $anyUltimaEdicio=$ultimaEdicio->obtenirAny()->obtenirNumero();
            $contEd.=" Les edicions de l'<strong>any escolar ".$anyUltimaEdicio."</strong> encara estan pendents d’aquest reconeixement. ";
         }

         $contEd.=" Podeu consultar-ne tota la informació a la nostra pàgina
         <strong><em><a rel='noopener' target='_blank' href='https://www.prisma.cat/activitats-formacio-interes-serveis-socials'
         title=\"Activiats reconegudes com a formació d’interès en serveis socials
         pel Departament de Treball, Afers Socials i Famílies\">Activitats de
         formació d’interès en serveis socials</a></em></strong>.</p>";
      }*/
      //
      //       $i=0; $teEdRec = 1;
      //       while ( $i<count($cursTitols) && $teEdRec ) {
      //          $curs = new Curs($cursTitols[$i][0], $this->dispositiu);
      //          if ( $curs->__teEdicionsReconegudes() == 0 )
      //             $teEdRec = 0;
      //          $i++
      //       }
      //

      $contingut = "<h2>Dates</h2>";

      $contingut .="<p>Les dates d'inici i fi de les edicions dels cursos inclosos són:</p>".$contEd;

      $nomApartat = "<i class='fa fa-calendar mr-2'></i><span class='f-auto'>Data</span>";
      $mostrar = $this->__mostrarApartatVista($vista, "dates", $contingut, "0", "ed", $nomApartat, $contingut);

      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya preu d'un pack
   * @return Mostra la pestanya preu d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   private function __mostrarPreu( $vista ) {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $cnsPreus = "SELECT IMPORT FROM preu WHERE ID=? AND DATAI <=CURRENT_DATE
                    AND (DATAF IS NULL OR CURRENT_DATE<=DATAF)";

      if ( $stmt = $connexio->prepare($cnsPreus) ) {
         $stmt->bind_param("d", $idPreu);

         $preuOrig = 0;

         for ($i=0; $i < count($this->__obtenirEdicions()); $i++) {
            $edicio = $this->__obtenirEdicions()[$i];
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
            $textPreu.="<p><strong class='preu-normal px-2 py-1'>
               <span class='text-danger tatxat pr-2'>".$importOrigFormCorrecteOK." €</span>
               ".$importFormatCorrecte." €</strong>
            </p>";
         }
         $connexio->closeStmt();

      }
      else {
         throw new Exception('',2625);
      }

      //Buscar si hi ha el text corresponet si està actiu
      $consText="SELECT VALOR FROM params WHERE DATAI<=CURRENT_TIMESTAMP AND
      (DATAF IS NULL OR CURRENT_DATE<=DATAF) AND TIPUS=?";
      $stmtText = $connexio->prepare($consText);
      $stmtText->bind_param("s", $tipus);
      $tipus='info-descomptes-adicionals'; //si el tipus és text dels descomptes adicionals
      $stmtText->execute();
      $stmtText->store_result();
      if ($stmtText->num_rows() > 0) {
         $stmtText->bind_result($valor);
         $stmtText->fetch();
         $textPreu.=$valor;
      }
      $tipus='info-forma-pagament-sense-fraccio'; //si el tipus és text de la forma de pagament
      $stmtText->execute();
      $stmtText->store_result();
      if ($stmtText->num_rows() > 0) {
         $stmtText->bind_result($valor);
         $stmtText->fetch();
         $textPreu.=$valor;
      }
      $tipus='info-pagament-segur'; //si el tipus és del pagament segur
      $stmtText->execute();
      $stmtText->store_result();
      if ($stmtText->num_rows() > 0) {
         $stmtText->bind_result($valor);
         $stmtText->fetch();
         $textPreu.=$valor;
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();

      if ( $vista == "1" ) {
         $mostrar="<div class='tab-pane fade' id='preu' role='tabpanel' aria-labelledby='tab-preu'>";
         $mostrar.="<div class='info-descripcio-tab-left pb-2'><h2>Preu</h2>";
         $mostrar.="<div>".$textPreu."</div></div></div>";
      }
      else {
         $mostrar.="<div class='panel panel-default'><div class='panel-heading collapsed' id='heading-preu' ";
         $mostrar.="role='tab' data-toggle='collapse' data-target='#collapse-preu' href='#collapse-preu' ";
         $mostrar.="aria-expanded='false' aria-controls='collapse-preu' onclick='canviarEstat(\"preu\")'>";
         $mostrar.="<p class='panel-title d-flex flex-wrap align-items-center font-weight-bold'><i class='fa fa-credit-card mr-2'></i><span class='flex-1-0-auto'>Preu</span><i class='fas fa-plus ml-2'></i></p>";
         $mostrar.="</div><div id='collapse-preu' class='panel-collapse collapse' role='tabpanel' ";
         $mostrar.="aria-labelledby='heading-preu' aria-expanded='false' style='height: 0px;'>";
         $mostrar.="<div class='panel-body border-0 preu'><h2>Preu</h2>".$textPreu."</div>";
         $mostrar.="<div class='clear-both'></div></div></div>";
      }
      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya valoracions d'un pack
   * @return Mostra la pestanya valoracions d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   private function __mostrarValoracions( $vista ) {
      $contingut="<h2>Valoracions</h2>".$this->__obtenirValoracions()->obtenirText();

      $nomApartat = "<i class='fas fa-comments mr-2'></i><span class='flex-1-0-auto'>Valoracions</span>";
      $mostrar = $this->__mostrarApartatVista($vista, "valoracions", $contingut, "0", "val", $nomApartat, $contingut);

      return $mostrar;
   }

   /**
   * @brief Mostra l'apartat Més informació d'un pack
   * @return Mostra l'apartat Més informació d'un pack tal i com es mostra a la pestanya d'Informació d'un pack
   */
   public function mostrarMesInformacio() {
     //  $hores=$this->obtenirHores()->obtenirNumero();
      $codiCursMin=$this->obtenirIdPack()->obtenirText();
     //
      $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

      $urlInsc="https://www.prisma.cat/inscripcions/packs/".$extUrl;
     //  $urlPerfil="https://www.prisma.cat/perfils-professionals/".$extUrl;
      $urlRec="https://www.prisma.cat/reconeixements-certificacio";
      $urlMet="https://www.prisma.cat/metodologia";
      $urlMetMixt="https://www.prisma.cat/metodologia-mixt-linia";
      $urlComp="https%3A%2F%2Fwww.prisma.cat%2Fcursos%2F".$extUrl;
      $textTw=$this->obtenirTitol()->replace(' ','%20');
     //
      $mostrar="<div class='compartir modal fade' id='compartir_".$codiCursMin."' role='dialog'>";
      $mostrar.="<div class='modal-dialog modal-dialog-centered'><div class='modal-content w-100'>";
      $mostrar.="<div class='modal-header'><p class='modal-title float-left font-weight-bold'>";
      $mostrar.="Comparteix l'enllaç d'aquest curs</p><button role='button' type='button' ";
      $mostrar.="class='modal-close position-absolute border-0' data-dismiss='modal' aria-label='Close'>";
      $mostrar.="<span aria-hidden='true'>×</span></button></div><div class='modal-body'>";
      $mostrar.="<div class='icones d-flex justify-content-around text-center py-4'>";
      /*Botó Facebook */
      $mostrar.="<a id='fb' rel='noreferrer' class='color-text'
         onclick=\"window.open('http://www.facebook.com/sharer.php?u=".$urlComp."',
         'ventanacompartir', 'toolbar=0, status=0, width=650, height=450');\">
         <picture>
         	<source type='image/webp' data-srcset='https://www.prisma.cat/img/social/share_facebook.webp'>
         	<source type='image/jpeg' data-srcset='https://www.prisma.cat/img/social/share_facebook.jpg'>
         	<img data-src='https://www.prisma.cat/img/social/share_facebook.jpg' src='https://www.prisma.cat/img/social/share_facebook.jpg'
         	class='lazyload'/>
         </picture>
         <p class='mt-2'>Facebook</p>
      </a>";
      /*Twitter */
      $mostrar.="<a id='tw' rel='noreferrer' class='color-text' target='_blank'
         rel='noopener' href=\"http://twitter.com/home?status=".$textTw."%20".$urlComp."\">
         <picture>
         	<source type='image/webp' data-srcset='https://www.prisma.cat/img/social/share_twitter.webp'>
         	<source type='image/jpeg' data-srcset='https://www.prisma.cat/img/social/share_twitter.jpg'>
         	<img data-src='https://www.prisma.cat/img/social/share_twitter.jpg' src='https://www.prisma.cat/img/social/share_twitter.jpg'
         	class='lazyload'/>
         </picture>
         <p class='mt-2'>Twitter</p>
      </a>";
      /*Botó Whatsapp */
      $mostrar.="<a id='ws' rel='noreferrer' class='color-text' target='_blank' rel='noopener'
         href=\"https://api.whatsapp.com/send?text=".$urlComp."\" data-action='share/whatsapp/share'>
         <picture>
         	<source type='image/webp' data-srcset='https://www.prisma.cat/img/social/share_whatsapp.webp'>
         	<source type='image/jpeg' data-srcset='https://www.prisma.cat/img/social/share_whatsapp.jpg'>
         	<img data-src='https://www.prisma.cat/img/social/share_whatsapp.jpg' src='https://www.prisma.cat/img/social/share_whatsapp.jpg'
         	class='lazyload'/>
         </picture>
         <p class='mt-2'>Whatsapp</p>
      </a>";
      $mostrar.="</div>";
      /*Input amb la url per poder copiar-lo */
      $mostrar.="<div class='cnt-copy d-flex w-100'><input id='textCopiar' class='flex-1-0-auto' type='text' value='".$linkCurs."'/>";
      $mostrar.="<button id='btnCopiar' class='float-right font-weight-bold position-relative text-center' ";
      $mostrar.="onclick='copiarAlPortapapeles()'>COPIAR</button></div>";
      $mostrar.="<div id='alerta' class='alert text-center invisible'></div><div style='clear: both;'></div>";
      $mostrar.="</div></div></div></div>";

      $mostrar.="<div class='theiaStickySidebar w-100 position-absolute'>";
      $mostrar.="<div class='more-info' id='mes_informacio'>";
      $classEl="negreta500";

      $mostrar.="<h2 class='h1 subtitle border-radius-2 mt-0 mt-lg-5'>Més informació</h2><ul>";

      $textHores = "<li class='d-flex flex-row align-items-center'>
      <i class='far fa-clock'></i><h3>Durada</h3>
      <div class='d-flex flex-column'>";
      $textNivells = "<li class='d-flex flex-row align-items-center'>
      <i class='fas fa-signal'></i><h3>Nivell</h3>
      <div class='d-flex flex-column'>";
      $cntEd = 0;
      while ($cntEd < count($this->__obtenirEdicions())) {
         $edicio = $this->__obtenirEdicions()[$cntEd];

         /* Text hores */
         if ($cntEd == 0) $niv1 = "niv1";
         else $niv1 = "";

         $textHores .= "<span class='niv-apartat ".$niv1." negreta500 text-right'>".$edicio->obtenirHores()->obtenirNumero()." hores</span>";

         $cntEd++;
      }
      /* Text nivells */
      $cntNiv = 0;
      while ($cntNiv < count($this->obtenirNivells())) {
         if ($cntNiv == 0) $niv1 = "niv1";
         else $niv1 = "";

         $textNivells .= "<span class='niv-apartat ".$niv1." negreta500 text-right'>".$this->obtenirNivells()[$cntNiv]->obtenirText()."</span>";

         $cntNiv++;
      }

      $textHores .= "</div></li>";
      $textNivells .= "</div></li>";

      $mostrar .= $textHores.$textNivells;

      $cntEd = 0; $algunCursEsMixt = 0;
      while ($cntEd < count($this->__obtenirEdicions()) && !$algunCursEsMixt ) {
         $edicio = $this->__obtenirEdicions()[$cntEd];
         if ( $edicio->obtenirCurs()->cursEsMixt() ) $algunCursEsMixt = 1;
         $cntEd++;
      }
      $mostrar.="<li class='d-flex flex-row align-items-center'><i class='fas fa-cog'></i><h3>Metodologia</h3>";
      $mostrar.="<button role='button' class='mesinfo border-radius-2 text-center ";
      $mostrar.="position-relative negreta500' title='Mostra més informació de la metodologia' ";
      $mostrar.="onclick=\"location.href='".$urlMet."'\">+ info</button></li>";
      if ( $algunCursEsMixt ) {
        $mostrar.="<li class='d-flex flex-row align-items-center'><i class='fas fa-cog'></i><h3>Metodologia Mixt</h3>";
        $mostrar.="<button role='button' class='mesinfo border-radius-2 text-center ";
        $mostrar.="position-relative negreta500' title='Mostra més informació de la metodologia dels cursos mixtos' ";
        $mostrar.="onclick=\"location.href='".$urlMetMixt."'\">+ info</button></li>";
      }
      $mostrar.="<li class='d-flex flex-row align-items-center'><i class='fas fa-award'></i><h3>Reconeixement</h3>";
      $mostrar.="<button role='button' class='mesinfo border-radius-2 text-center ";
      $mostrar.="position-relative negreta500' title='Mostra més informació del reconeixement' ";
      $mostrar.="onclick=\"location.href='".$urlRec."'\">+ info</button></li>";

      /*$cntPerf=0;
      $trobat=false;
      while ($this->obtenirPerfil($cntPerf)!=null && !$trobat) {
         $perfilEd = $this->obtenirPerfil($cntPerf);
         $shortnamePerfil = $perfilEd->obtenirNomCurt()->obtenirText();

         require_once 'Text.php';
         $textClassPerfil = new Text($shortnamePerfil);
         $textClassPerfil->obtenirNomCurt();
         $classPerfil = $textClassPerfil->obtenirText();

         $mostrar.="<li class='d-flex flex-row align-items-center'><i class='fas fa-tag'></i>
         <h3>Edicions amb perfil</h3>
         <button class='mesinfo position-relative border-radius-2 text-center negreta500'
         title='Mostra més informació de les edicions del curs amb perfil'
         onclick=\"location.href='".$urlPerfil."'\"> + info </button></li>";
         $cntPerf++;
         $trobat=true;
      }*/

      /*$cntPerf=0;
      while ($this->obtenirPerfilPendent($cntPerf)!=null && !$trobat) {
         $perfilEd = $this->obtenirPerfilPendent($cntPerf);
         $shortnamePerfil = $perfilEd->obtenirNomCurt()->obtenirText();

         require_once 'Text.php';
         $textClassPerfil = new Text($shortnamePerfil);
         $textClassPerfil->obtenirNomCurt();
         $classPerfil = $textClassPerfil->obtenirText();

         $mostrar.="<li class='d-flex flex-row align-items-center'><i class='fas fa-tag'></i>
         <h3>Edicions amb perfil</h3>
         <button class='mesinfo position-relative border-radius-2 text-center negreta500'
         title='Mostra més informació de les edicions del curs amb perfil'
         onclick=\"location.href='".$urlPerfil."'\"> + info </button></li>";
         $cntPerf++;
         $trobat=true;
      }*/
      $inscripcioOberta = 1;

      for ( $cntEd = 0; $cntEd < count($this->__obtenirEdicions()); $cntEd++) {
         $edicio = $this->__obtenirEdicions()[$cntEd];
         $curs = $edicio->obtenirCurs();

         require_once 'ConnexioBBDD_PreparedStatment.php';
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();

         $cnsParam = "SELECT ID FROM params WHERE TIPUS=? AND VALOR=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)";
         $stmt = $connexio->prepare($cnsParam);
         $stmt->bind_param("ss", $tipus, $codiCurs);
         $tipus='curs-no-reconegut';
         $codiCurs=$curs->obtenirCodi()->obtenirText();
         $stmt->execute();
         $stmt->bind_result($idParam);
         $stmt->store_result();
         if ($stmt->num_rows() > 0 && $inscripcioOberta) $inscripcioOberta=0;
         else $inscripcioOberta=1;

         $connexio->closeStmt();
         $connexio->desconectarBD();
      }
      $mostrar.="</ul>
      <div class='d-flex flex-column justify-content-center align-items-center'>";
      $mostrar.="<button role='button' class='inscripcio border-0 border-radius-2 ";
      $mostrar.="text-white text-center position-relative negreta500' ";

      if ( $inscripcioOberta ) {
         $mostrar.="onclick=\"location.href='".$urlInsc."'\" title=\"Inscriu-te al curs ";
         $mostrar.=$this->obtenirTitol()->obtenirText()."\"><i class='fas fa-edit'></i>";
         $mostrar.="Inscripció</button>";
      }
      else {
         $mostrar.="title=\"Inscripcions no disponibles\"><i class='fas fa-edit'></i>";
         $mostrar.="Inscripció no disponible</button>";
      }

      $mostrar.="<div class='v_mob icones d-flex flex-row justify-content-between w-100 py-3'>";
      /*Botó copiar link */
      $mostrar.="<a id='cp' rel='noreferrer' class='color-text' ";
      $mostrar.="onclick=\"copiarAlPortapapeles()\">";
      $mostrar.="<input id='textCopiar' type='hidden' value='".$linkCurs."'/>";
      $mostrar.="<i class='fas fa-copy'></i></a>";
      /*Botó Whatsapp */
      $mostrar.="<a id='ws' rel='noreferrer' class='color-text' target='_blank' ";
      $mostrar.="rel='noopener' href=\"https://api.whatsapp.com/send?text=".$urlComp."\" ";
      $mostrar.="data-action='share/whatsapp/share'><i class='fab fa-whatsapp'></i></a>";
      /*Twitter */
      $mostrar.="<a id='tw' rel='noreferrer' class='color-text' target='_blank' ";
      $mostrar.="rel='noopener' href=\"http://twitter.com/home?status=".$textTw."%20".$urlComp."\">";
      $mostrar.="<i class='fab fa-twitter'></i></a>";
      /*Botó Facebook */
      $mostrar.="<a id='fb' rel='noreferrer' class='color-text' ";
      $mostrar.="onclick=\"window.open('http://www.facebook.com/sharer.php?u=".$urlComp."',";
      $mostrar.="'ventanacompartir', 'toolbar=0, status=0, width=650, height=450');\">";
      $mostrar.="<i class='fab fa-facebook-square'></i></a>";
      /*Botó enviar per mail */
      $mostrar.="<a id='ml' rel='noreferrer' class='color-text' target='_blank' ";
      $mostrar.="rel='noopener' href=\"mailto:?Subject=Curs%20".$this->obtenirTitol()->obtenirText()."\">";
      $mostrar.="<i class='fas fa-envelope'></i></a>";
      $mostrar.="</div><p class='v_mob text-center comparteix'>Comparteix aquest curs</p>";

      $mostrar.="<span class='v_ord' data-toggle='modal' data-target='#compartir_".$codiCursMin."'>";
      $mostrar.="<button role='button' id='boto_compartir_".$codiCursMin."' class='comparteix negreta500 ";
      $mostrar.="text-center border-0' aria-label='Comparteix aquest curs' type='button' ";
      $mostrar.="data-html='true' title=\"Comparteix el curs ".$this->obtenirTitol()->obtenirText()."\" ";
      $mostrar.="data-target='#compartir_".$codiCursMin."' data-placement='bottom'><i class='fas fa-share'></i>";
      $mostrar.="Comparteix aquest curs</button></span></div></div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra l'apartat fix al peu de pàgina
   * @return Mostra l'apartat fix al peu de pàgina
   */
   public function mostrarPeuFix() {
      $codiCursMin=$this->obtenirIdPack()->obtenirText();

      // $hores=$this->obtenirHores()->obtenirNumero();
      // $preu=$this->obtenirPreu()->obtenirNumero();
      $titol=$this->obtenirTitol()->obtenirText();

      $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

      $urlInsc="https://www.prisma.cat/inscripcions/packs/".$extUrl;

      $mostrar="<div class='peufix d-flex flex-column w-100 position-fixed'>
      <div class='d-flex flex-row'>
         <button role='button' class='regala position-relative m-0 py-2 border-0 border-radius-2 text-center ";
      $mostrar.="negreta500' onclick=\"location.href='tel:+34-972-21-75-65'\" ";
      $mostrar.="title=\"Trucan's\"><i class='fa fa-phone mr-2'></i>Truca'ns</button>";

      $mostrar.="<button role='button' title=\"Contacte\" class='regala position-relative m-0 py-2 ";
      $mostrar.="border-0 border-radius-2 text-center negreta500' ";
      $mostrar.="onclick=\"location.href='mailto:secretaria@prisma.cat?Subject=Informació%20curs%20";
      $mostrar.=$codiCursMin."'\"><i class='fas fa-at mr-2'></i>Contacte</button></div>";

      $mostrar.="<button role='button' class='inscripcio m-0 border-0 border-radius-2 negreta500 ";

      $inscripcioOberta = 1;

      for ( $cntEd = 0; $cntEd < count($this->__obtenirEdicions()); $cntEd++) {
         $edicio = $this->__obtenirEdicions()[$cntEd];
         $curs = $edicio->obtenirCurs();

         require_once 'ConnexioBBDD_PreparedStatment.php';
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();

         $cnsParam = "SELECT ID FROM params WHERE TIPUS=? AND VALOR=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)";
         $stmt = $connexio->prepare($cnsParam);
         $stmt->bind_param("ss", $tipus, $codiCurs);
         $tipus='curs-no-reconegut';
         $codiCurs=$curs->obtenirCodi()->obtenirText();

         $stmt->execute();
         $stmt->bind_result($idParam);
         $stmt->store_result();
         if ($stmt->num_rows() > 0 && $inscripcioOberta) $inscripcioOberta=0;
         else $inscripcioOberta=1;

         $connexio->closeStmt();
         $connexio->desconectarBD();
      }

      if ($inscripcioOberta) {
         $mostrar.="text-white text-center position-relative' onclick=\"location.href='".$urlInsc."'\" ";
         $mostrar.="title=\"Inscriu-te al curs ".$this->obtenirTitol()->obtenirText()."\">";
         $mostrar.="<i class='fas fa-edit'></i>Inscripció</button></div>";
	  }
	  else {
        $mostrar.="text-white text-center position-relative' title=\"Inscripcions no disponibles\"><i class='fas fa-edit'></i>";
        $mostrar.="Inscripció no disponible</button></div>";
	  }

      return $mostrar;
   }

   public function etiquetaPack() {
      $etiqueta = "";
      if ( $this->etiquetaPack != null) {
         $etiqueta.="<div class='nou pack text-white float-left'>".$this->etiquetaPack->obtenirTextHTML()."</div>";
         $etiqueta.="<div class='nou novetat float-left'>2a EDICIÓ</div>";
      }
      return $etiqueta;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

}
?>
