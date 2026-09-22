<?php

require_once 'CursSubvencio.php';

/**
   * @class Info
   * @brief Conté tota la informació extesa sobre un Curs
*/
class Info extends Curs {
   private $presentacio; /**< string Presentació del curs */
   private $autoria; /**< string Autoria del curs */
   private $destinataris; /**< string Destinataris del curs */
   private $objectius; /**< string Objectius del curs */
   private $programa; /**< string Programa del curs */
   private $valoracions; /**< string Valoracions del curs */
   private $article; /**< Article Article relacionat amb el curs */
   private $cursos_relacionats; /**< LlistatCursos Els cursos relacionats amb el curs */
   private $docents; /**< Array Els docents que porten el curs */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @param $url URL que correspon a la informació del curs
   * @return Crees una Info
   */
   public function __construct($url ,$dispositiu) {
      $this->url = $url;
      $this->dispositiu = $dispositiu;
      $this->estat=1;
	  //$this->disponibilitat=1;

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /*Es busca la presentacio, autoria, destinataris, objectius, prgrama,
      valoracions i el id_article del registre que la ID_AMIGABLE de la taula
      INFORMACIO correspon a la ID amigable obtinguda anteriorment */
      $consultaInfo = "SELECT ID, CODI_CURS, TITOL, SHORT_DESC, ID_VIDEO, ID_IMG_LARGE,
      ID_IMG_SMALL, PRESENTACIO, AUTORIA, DESTINATARIS, OBJECTIUS, PROGRAMA,
      VALORACIONS, ID_ARTICLE, TIPUS_CURS FROM informacio WHERE ID_AMIGABLE=? AND ESTAT=1";
      $stmInfo = $connexio->prepare($consultaInfo);
      $stmInfo->bind_param("d", $idAmigable);
      $idAmigable = $url->obtenirID();
      $stmInfo->execute();
      $stmInfo->store_result();
      if ( $stmInfo->num_rows() <= 0 ) {
         $connexio->closeStmt();
         $this->estat=0;
         $consultaExisteixInfo = "SELECT ID FROM informacio WHERE ID_AMIGABLE=? AND ESTAT=0";
         $stmInfo = $connexio->prepare($consultaExisteixInfo);
         $stmInfo->bind_param("d", $idAmigable);
         $stmInfo->execute();
         $stmInfo->store_result();
         if ( $stmInfo->num_rows() > 0 )
            throw new Exception('',710);
         else
            throw new Exception('',701);
         $connexio->closeStmt();
      }
      $stmInfo->bind_result($idInfo, $codi, $titol, $desc, $idVideo, $idImgLarge, $idImgSmall, $pres, $autoria, $dest, $obj, $prog, $val, $idArticle, $tipusCurs);
      $stmInfo->fetch();
      $connexio->closeStmt();

      require_once 'Text.php';
      if ($tipusCurs!=null AND $tipusCurs!='')
         $this->tipus = new Text($tipusCurs);
      else
         $this->tipus = null;
      if ($codi!=null AND $codi!='')
         $this->codi = new Text($codi);
      else
         $this->codi = null;
      if ($titol!=null AND $titol!='')
         $this->titol = new Text($titol);
      else
         $this->titol = null;
      if ($desc!=null AND $desc!='')
         $this->short_desc = new Text($desc);
      else
         $this->short_desc = null;
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
      if ($obj!=null AND $obj!='')
         $this->objectius = new Text($obj);
      else
         $this->objectius = null;
      if ($prog!=null AND $prog!='')
         $this->programa = new Text($prog);
      else
         $this->programa = null;
      if ($val!=null AND $val!='')
         $this->valoracions = new Text($val);
      else
         $this->valoracions = null;
      require_once 'Video.php';
      if ($idVideo!=null AND $idVideo!='')
         $this->video = new Video($idVideo);
      else
         $this->video = null;
      require_once 'Imatge.php';
      if ($idImgLarge!=null AND $idImgLarge!='')
         $this->img_large = new Imatge($idImgLarge);
      else
         $this->img_large = null;
      if ($idImgSmall!=null AND $idImgSmall!='')
         $this->img_small = new Imatge($idImgSmall);
      else
         $this->img_small = null;
      require_once 'Article.php';
      if ($idArticle!=null AND $idArticle!='')
         $this->article = new Article($idArticle, $this->dispositiu);
      else
         $this->article = null;


      $this->estat=0;

      $dates = "";
      /* Busco els dies que poden estar obert els cursos després de la data d'inscripció */
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='dies-inscriu-cursos';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $diesOberts=[];
      $pos=0;
      while ($stmtParam->fetch()) {
         $valors = explode('|',$valor);
         if (count($valors) == 2) {
            if ( $dates != '' ) $dates .= " OR ";
            $dates .= "(DATAI + ".$valors[1]." > CURRENT_DATE AND c.HORES=".$valors[0].")";
         }
      }
      $connexio->closeStmt();
      if ( $dates != '' ) $dates = "(".$dates.")";

      /*Es busca les hores i el id_preu de la pròxima edició oberta del curs */
      $consultaHoresPreu = "SELECT HORES, CURS_ESCOLAR, GTAF, ID_PREU, CDD FROM curs
         as c WHERE (CURS LIKE ? AND GTAF IS NOT NULL AND GTAF!='' AND
         (".$dates.") AND (CURS NOT LIKE '%JOR%')
         AND (CURS NOT LIKE '%0%')) AND ESTAT!=0 ORDER BY ANY, MES LIMIT 1";
      $stmtHoresPreu = $connexio->prepare($consultaHoresPreu);
      $stmtHoresPreu->bind_param("s", $codi);
      $codi = $this->codi->obtenirText();
      $stmtHoresPreu->execute();
      $stmtHoresPreu->store_result();

      if ( $stmtHoresPreu->num_rows() > 0 ) {
         $stmtHoresPreu->bind_result($hores, $cursEscolar, $gtaf, $idPreu, $cdd);
         $stmtHoresPreu->fetch();
         $connexio->closeStmt();
         $this->estat=1;
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
         // echo "h:".$hores;

         $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
         DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
         $stmtParam = $connexio->prepare($cnsParams);
         // echo $cnsParams."<br />";
         $stmtParam->bind_param("ss", $tipus, $orderBy);

         $tipus='limit-filtre-info-packs';
         $orderBy='DATAI';
         $stmtParam->execute();
         $stmtParam->bind_result($limitFiltrePacks);
         $stmtParam->fetch();

         $tipus='dies-inscriu-cursos';
         $orderBy='VALOR';
         $stmtParam->execute();
         $stmtParam->bind_result($valor);
         $diesObets=0;
         while ($stmtParam->fetch()) {
             $valors = explode('|',$valor);
             // echo $valors[0]." ".$valors[1]."<br />";
             if (count($valors) != 2)
                $diesObets=0;
             else if (intval($valors[0])>0 && $valors[0]==$hores) //si el valor és un numero i les hores son iguals al curs
                $diesObets = $valors[1];
             else if (intval($valors[0])<=0 && $valors[0]==$codi) //si el valor no és un numero i el codi és igual al curs
                $diesObets = $valors[1];
          }
          $connexio->closeStmt();
          // echo "diesOB:".$diesObets;

          $consultaHoresPreu = "SELECT HORES, CURS_ESCOLAR, GTAF, ID_PREU, CDD FROM curs WHERE
             DATAI+?>CURRENT_DATE AND CURS LIKE ? AND PUBLIC=1
             AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
             ORDER BY ANY, MES LIMIT 1";
          $stmtHoresPreu=$connexio->prepare($consultaHoresPreu);
          $stmtHoresPreu->bind_param("ds", $diesObets, $codi);
          $stmtHoresPreu->execute();
          $stmtHoresPreu->store_result();
          if ( $stmtHoresPreu->num_rows() > 0 ) {
             $this->estat = 1;

            $stmtHoresPreu->bind_result($hores, $cursEscolar, $gtaf, $idPreu, $cdd);
            $stmtHoresPreu->fetch();
            // echo $hores." ".$cursEscolar." ".$gtaf." ".$idPreu;
          }
          $connexio->closeStmt();
      }
      if ($this->estat==1) {
         require_once 'Numero.php';
         if ($hores!=null and $hores!='')
            $this->hores = new Numero($hores);
         else
            $this->hores = null;

         if ($idPreu!=null and $idPreu!='')
            $this->id_preu = new Numero($idPreu);
         else
            $this->id_preu = null;

        if ($cdd!=null and $cdd!='')
            $this->cdd = $cdd;
        else
            $this->cdd = 0;

         $consultaPerfil = "SELECT ID_PERFIL FROM perfils WHERE CODI LIKE ?
            AND HORES=? AND CURS_ESCOLAR=? AND CODI_GTAF=? GROUP BY ID_PERFIL";
         $stmtPerfil = $connexio->prepare($consultaPerfil);
         $stmtPerfil->bind_param("sdss", $codi, $hores, $cursEscolar, $gtaf);
         $hores = $this->hores->obtenirNumero();
         $stmtPerfil->execute();
         $stmtPerfil->bind_result($idPerfil);
         $cnt_perfils = 0;
         require_once 'Perfil.php';
         $idPerfils = null;
         while ( $stmtPerfil->fetch() ) {
            $idPerfils[] = $idPerfil;
            $this->perfil[$cnt_perfils] = new Perfil($idPerfil);
            $cnt_perfils++;
         }
         $connexio->closeStmt();

         /*Es busca els nivells, el tema i els cursos relacionats de la info */
         $consultaNivellTemaRel = "SELECT f.ID_NIVELL, f.ID_REL FROM filtres AS f WHERE ID_INFO LIKE ? AND f.DATAI<=CURRENT_TIME AND (f.DATAF IS NULL OR CURRENT_TIME<=f.DATAF)";
         $stmtNivTemaRel = $connexio->prepare($consultaNivellTemaRel);
         $stmtNivTemaRel->bind_param("d", $idInfo);
         $stmtNivTemaRel->execute();
         $stmtNivTemaRel->store_result();
         $stmtNivTemaRel->bind_result($idNivell, $idRel);
         if ( $stmtNivTemaRel->num_rows() <= 0 )
            throw new Exception('',711);
         else {
            $stmtNivTemaRel->fetch();
            if ($idNivell=='' or $idNivell==null)
               throw new Exception('',712);
            else if ($idRel=='' or $idRel==null)
               throw new Exception('',713);
         }
         $connexio->closeStmt();

         /*Es busca els nivells de la info */
         $ids_nivells = explode('|',$idNivell);
         $cnt_nivells = 0;
         $consultaNivell = "SELECT NOM_INFO, ORDRE FROM nivells WHERE ID=?";
         $stmtNiv = $connexio->prepare($consultaNivell);
         $stmtNiv->bind_param("d", $idNivellActual);
         while ($cnt_nivells < count($ids_nivells)) {
            $idNivellActual = $ids_nivells[$cnt_nivells];
            $stmtNiv->execute();
            $stmtNiv->bind_result($nomNiv, $ordreNiv);
            $stmtNiv->fetch();
            // Assignem a l'array $ordre_nivells com a clau l'ordre del nivell i com a valor el nom del nivell
            $ordre_nivells[$ordreNiv] = $nomNiv;
            $cnt_nivells++;
         }
         $connexio->closeStmt();
         // Ordenem l'array per la clau, de manera que, obtenim els nivells ordenats per ORDRE
         ksort($ordre_nivells);
         // Construïm els nivells amb els valors del nom dels nivells
         $i = 0;
         foreach($ordre_nivells as $clau => $valor){
            $this->nivells[$i] = new Text($valor);
            $i++;
         }
         $consultaPerfPend = "SELECT i.ID_PERFILS_PENDENT FROM (informacio
            AS i INNER JOIN filtres AS f ON i.ID=f.ID_INFO) WHERE i.CODI_CURS LIKE ?
            and i.ESTAT=1 AND f.DATAI<=CURRENT_TIME AND (f.DATAF IS NULL OR CURRENT_TIME<=f.DATAF)";
         $stmtPerf = $connexio->prepare($consultaPerfPend);
         $stmtPerf->bind_param("s", $codi);
         $stmtPerf->execute();
         $stmtPerf->bind_result($idPerfilPendent);
         $stmtPerf->fetch();
         $connexio->closeStmt();

         // echo $consultaPerfPend.$codi;

         //Es comprova si hi ha algun perfil pendent que no hi és a la taula perfils
         // echo "IDPERFILPENDENT ".$codi.":".$idPerfilPendent."<br>";
         $idPerfilsPendentsComprovar = explode('|',$idPerfilPendent);
         // print_r($idPerfilsPendentsComprovar);
         $this->perfil_pendent = [];
         if ( $idPerfilPendent == null or $idPerfilPendent == '' )
            $numPerfilsPendents = 0;
         else
            $numPerfilsPendents = count($idPerfilsPendentsComprovar);
         if ( $idPerfils == null or $idPerfils == '' )
            $numPerfils = 0;
         else
            $numPerfils = count($idPerfils);

         $cntPerfil = 0;
         for ($i=0; $i<$numPerfilsPendents; $i++) {
            // echo $codi." PERFIL PENDENT: ".$idPerfilsPendentsComprovar[$i]."<br />";
            $trobat = false;
            $j = 0;
            while ( $j < $numPerfils && !$trobat ) {
               // echo $codi." PERFIL: ".$idPerfils[$i]."<br />";
               if ( $idPerfilsPendentsComprovar[$i] == $idPerfils[$j] )
                  $trobat = true;
               else
                  $j++;
            }

            if ( !$trobat ) {
               // echo $codi." ".$idPerfilsPendentsComprovar[$i]." ";
               $this->perfil_pendent[$cntPerfil] = new Perfil($idPerfilsPendentsComprovar[$i]);
               $cntPerfil++;
            }
         }

         // $consultaNumEd = "SELECT VALOR FROM params WHERE DATAI<=CURRENT_TIMESTAMP
         //    AND (DATAF IS NULL OR CURRENT_DATE<=DATAF) AND TIPUS=?";
         $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
               DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
         $stmtParam = $connexio->prepare($cnsParams);
         $stmtParam->bind_param("ss", $tipus, $orderBy);

         $tipus='limit-filtre-info-packs';
         $orderBy='DATAI';
         $stmtParam->execute();
         $stmtParam->bind_result($limitFiltrePacks);
         $stmtParam->fetch();

         $tipus='limit-editions';
         if ( $this->cursEsSubvencionat() ) $tipus = "limit-editions-subv";
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
               throw new Exception('',714);
            else if (intval($valors[0])>0 && $valors[0]== $hores) //si el valor és un numero i les hores son iguals al curs
               $diesObets = $valors[1];
            else if (intval($valors[0])<=0 && $valors[0]== $codi) //si el valor no és un numero i el codi és igual al curs
               $diesObets = $valors[1];
         }
         $connexio->closeStmt();
         if ($diesObets==0)
            throw new Exception('',714);

         /*Es busca els cursos relacionats de la info */
         $idsRel = explode('|',$idRel);
         require_once 'CursSubvencio.php';
         require_once 'Pack.php';
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
                  throw new Exception('',0714);
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
         require_once 'LlistatCursosSubvencio.php';
         $this->cursos_relacionats = new LlistatCursos($cursosRel);
         /*Consulta els docents que tutoritzen el curs */
         $consultaDoc = "SELECT DNI_TUTOR FROM honoraris AS h WHERE CURS LIKE ? AND
            h.ESTAT=1 AND PERFIL='tutor' AND DNI_TUTOR REGEXP '^[0123456789XYZ]'
            GROUP BY DNI_TUTOR ORDER BY ORDRE_TUTOR ASC";
         $stmtDoc = $connexio->prepare($consultaDoc);
         $stmtDoc->bind_param("s", $codi);
         $stmtDoc->execute();
         $stmtDoc->bind_result($dniTutor);
         $cntDoc = 0;
         while ( $stmtDoc->fetch() ) {
            $ordreDoc[$cntDoc] = $dniTutor;
            $cntDoc++;
         }
         $connexio->closeStmt();
         $consultaUrlDoc = "SELECT ID_URL FROM personal WHERE DNI=?";
         $stmtUrlDoc = $connexio->prepare($consultaUrlDoc);
         $stmtUrlDoc->bind_param("s", $dniDoc);
         $cntDoc = 0;
         while ($cntDoc < count($ordreDoc)) {
            $dniDoc = $ordreDoc[$cntDoc];
            $stmtUrlDoc->execute();
            $stmtUrlDoc->bind_result($idUrlDoc);
            $stmtUrlDoc->fetch();
            $urlDoc = new Url($idUrlDoc);
            require_once 'TutorSubvencio.php';
            $this->docents[$cntDoc] = new Tutor($urlDoc, $this->dispositiu);
            $cntDoc++;
         }
         $connexio->closeStmt();



         /*Es busca les 3 proximes edicions obertes del curs */
         $cnsEd = "SELECT DATAI, DATAF, CURS_ESCOLAR, FISS, GTAF, DATA_RESOL, ANY, MES, c.CURS,
            DNI_TUTOR, HORES, c.ESTAT, c.CDD FROM curs AS c INNER JOIN aula AS a ON
            c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
            INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE c.CURS=? AND PUBLIC=1
            AND c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1 AND
            (DATEDIFF(DATAI + ?,CURRENT_DATE)>0) AND c.CURS NOT LIKE '%JOR%' AND
            (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
            OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
            AND ORDRE_TUTOR is not NULL)) GROUP BY MES ORDER BY ANY, MES LIMIT ?";
         $stmtEd = $connexio->prepare($cnsEd);
         $stmtEd->bind_param("sds", $codi, $diesObets, $limitEd);
         $stmtEd->execute();
         $stmtEd->bind_result($datai, $dataf, $cursEscolar, $fiss, $gtaf, $dataRes, $any, $mesDesc, $curs, $dni, $hores, $estatEd, $cdd);
         require_once 'EdicioSubvencio.php';
         $cntEd = 0;
         while ( $stmtEd->fetch() ){
            $this->edicions[$cntEd] = new Edicio($curs, $tipusCurs, $this->dispositiu, $any, $mesDesc, $hores, $datai, $dataf, $cursEscolar, $gtaf, $dataRes, $fiss, $estatEd, $cdd);
            $cntEd++;
         }
         $connexio->closeStmt();
      }
      else {
         throw new Exception('',710);
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
         throw new Exception('', 707);
      return $this->cursos_relacionats;
   }

   /*
   * @brief Obtens un el curs relacionats
   * @return Obtens el curs relacionat corresponent a la posicio $posicio de la llista de cursos relacionats
   */
   public function obtenirCursRelacionat($posicio) {
      if ($this->obtenirCursosRelacionats() == null)
         throw new Exception('', 707);
      else if ($posicio>=0 && $posicio < $this->obtenirCursosRelacionats()->obtenirNumeroElements())
         return $this->obtenirCursosRelacionats()->obtenirCurs($posicio);
      else
         throw new Exception('', 707);
   }


   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /**
   * @brief Mostra la informació d'un curs
   * @return Mostra la informació d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   public function mostrarInfo() {
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
   * @brief Mostra la informació del titol d'un curs tal «INFORMACIO»
   * @return Mostra la informació del titol d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   public function mostrarTitolInfo() {
      $mostrar = "<div class='info-titol' id='titol'>";
      $mostrar .= "<h1>".$this->obtenirTitol()->obtenirTextHTML()."</h1>";

      if ($this->__isMobile() || $vista == "2") {
         $mostrar .= "<div class='d-flex flex-row flex-wrap align-items-center'>
         <div class='curs-linia'>Curs en línia</div>";
         $mostrar .= $this->__etiquetaCursNou();
         $mostrar .= $this->__etiquetaCursAmbDescompteTitol();
         if ( $this->tipus->obtenirText() == "S" ) {
           $mostrar .= $this->__etiquetaCursSubvencionat();
           $mostrar .= $this->__etiquetaCDD3();
        }
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
            align-items-center justify-content-between p-0 ml-0 mb-0'
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
            align-items-center justify-content-between p-0 ml-0 mb-0'
            onclick=\"location.href='https://www.prisma.cat/perfils-professionals'\">";
            $mostrar .= "<span class='px-2'>".$perfilEd->obtenirNomCurt()->obtenirText()."</span>
            <div class='perfilPendent'><div class='border m-0'></div><div class='nom position-absolute'>Pendent</div></div>
            </div>";
            $cntPerf++;
         }
        // echo "1";

         $mostrar .= "<div class='cnt-hores'>";
         $mostrar .= "<i class='far fa-clock mr-1'></i>".$this->obtenirHores()->obtenirNumero()." h</div>
         <div class='cnt-niv'>";
         $cnt_nivells = 0;
         if (count($this->nivells)==1)
            $mostrar .= "<i class='fas fa-signal mr-1'></i>".$this->obtenirNivell($cnt_nivells)->obtenirText();
         else {
            $mostrar .= "<i class='fas fa-signal mr-1'></i>";
            while ($cnt_nivells < count($this->nivells)) {
               if ($cnt_nivells == 0)
                  $mostrar .= $this->obtenirNivell($cnt_nivells)->obtenirText();
               else
                  $mostrar .= "<span class='nivells'>|</span><span class='nivells'>".$this->obtenirNivell($cnt_nivells)->obtenirText()."</span>";
               $cnt_nivells++;
            }
         }
         $mostrar .= "</div>";
      }
      else {
         $mostrar .= "<div class='d-flex flex-row flex-wrap align-items-start align-items-sm-center'>
         <div class='curs-linia mb-3 mb-sm-0'>Curs en línia</div>";
         $mostrar .= $this->__etiquetaCursNou();
         $mostrar .= $this->__etiquetaCursAmbDescompteTitol();

         if ( $this->tipus->obtenirText() == "S" ) {
            $mostrar .= $this->__etiquetaCDD3();
            $mostrar .= $this->__etiquetaCursSubvencionat();
         }
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
         }
      }
      $mostrar .= "</div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra la imatge allargada d'un curs tal «INFORMACIO»
   * @return Mostra la imatge allargada d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   public function mostrarImgLargeInfo() {
      if ( (!$this->__isMobile()) || ($this->__isMobile() && $this->obtenirVideo()== null) ) {
         if (!$this->__isMobile())
            $mostrar = "<div class='info-banner mb-4' id='img_llarga'><picture>";
         else
            $mostrar = "<div class='info-banner mb-0' id='img_llarga'><picture>";

         $altImg=$this->obtenirImgLarge()->obtenirAlt();
         $linkImg="https://www.prisma.cat".$this->obtenirImgLarge()->obtenirLink();
         $linkImgWeb=substr($linkImg, 0, -4).".webp";

         $linkImgOrig = substr($linkImg, 0, -4);
         $linkImgWeb = $linkImgOrig.".webp";

         $linkImg400JPG=$linkImgOrig."-400.jpg";
         $linkImg700JPG=$linkImgOrig."-700.jpg";
         $linkImg750JPG=$linkImgOrig."-750.jpg";
         $linkImg400Webp=$linkImgOrig."-400.webp";
         $linkImg700Webp=$linkImgOrig."-700.webp";
         $linkImg750Webp=$linkImgOrig."-750.webp";

         $mostrar = "<div class='info-banner mb-4'><picture>
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
      }
      else
         //$mostrar = $this->obtenirVideo()->mostrarVideoInfoMbl();
		 $mostrar = $this->obtenirVideo()->mostrarVideo();
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs tal «INFORMACIO»
   * @return Mostra la informació d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   public function mostrarInformacioInfo() {
      $mostrar = "<div class='v_ord'>";
      $mostrar .= "<ul class='nav nav-tabs nav-justified info-descripcio-nav' role='tablist' aria-label='Contingut del curs'>";
      $mostrar .= "<li role='tab'><a class='text-center negreta500 active' href='#descripcio' data-toggle='tab' aria-controls='descripcio' id='tab-descripcio'><i class='fa fa-bookmark'></i>Descripció</a></li>";
      $mostrar .= "<li role='tab'><a class='text-center negreta500' href='#programa' data-toggle='tab' aria-controls='programa' id='tab-programa'><i class='fa fa-cube'></i>Programa</a></li>";
      $mostrar .= "<li role='tab'><a class='text-center negreta500' href='#docents' data-toggle='tab' aria-controls='docents' id='tab-docents'><i class='fa fa-user'></i>Docents</a></li>";
      $mostrar .= "<li role='tab'><a class='text-center negreta500' href='#edicions' data-toggle='tab' aria-controls='edicions' id='tab-edicions'><i class='fat fa-calendar'></i>Edicions</a></li>";
      $mostrar .= "<li role='tab'><a class='text-center negreta500' href='#preu' data-toggle='tab' aria-controls='preu' id='tab-preu'><i class='fa fa-credit-card'></i>Preu</a></li>";
      $mostrar .= "<li role='tab'><a class='text-center negreta500' href='#valoracions' data-toggle='tab' aria-controls='valoracions' id='tab-valoracions'><i class='fas fa-comments'></i>Valoracions</a></li>";
      $mostrar .= "</ul><div class='tab-content info-descripcio-tab'>";
      $mostrar .= $this->__mostrarDescripcio("1").$this->__mostrarPrograma("1");
      $mostrar .= $this->__mostrarDocents("1").$this->__mostrarEdicions("1");
      $mostrar .= $this->__mostrarPreu("1").$this->__mostrarValoracions("1");
      $mostrar .= "</div><div class='d-flex justify-content-between'>
      <p><a role='link' class='color-text'";
      $mostrar .= " href='https://www.prisma.cat/cursos' target='_self' title='Visualitza tots els cursos'>";
      $mostrar .= "<i class='fas fa-long-arrow-alt-left mr-2'></i>Mostra tots els cursos</a></p>";
      $mostrar .= "<p><a id='torna' class='color-text' href='#' role='link' title='Torna a dalt'>";
      $mostrar .= "<i class='fas fa-long-arrow-alt-up mr-2'></i>Torna a dalt</a></p></div></div>";

      $mostrar .= "<div id='accord' class='v_mob d-flex flex-column'><div class='panel-group' id='accordion' role='tablist' aria-multiselectable='true'>";
      $mostrar .= $this->__mostrarDescripcio("2").$this->__mostrarPrograma("2");
      $mostrar .= $this->__mostrarDocents("2").$this->__mostrarEdicions("2");
      $mostrar .= $this->__mostrarPreu("2").$this->__mostrarValoracions("2");
      $mostrar .= "</div><p><a role='link' class='color-text' target='_self' ";
      $mostrar .= "href='https://www.prisma.cat/cursos' title='Visualitza tots els cursos'>";
      $mostrar .= "<i class='fas fa-long-arrow-alt-left mr-2'></i>Mostra tots els cursos</a></p></div>";
      return $mostrar;
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
   /*
   * @brief Obtens la presentacio
   * @return Obtens la presentacio
   * @throws Si el curs no té una presentacio, envia l'excepció «No existeix la presentacio del curs»
   */
   private function __obtenirPresentacio() {
      if ($this->presentacio==null)
         throw new Exception('', 702);
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
         throw new Exception('', 703);
      return $this->destinataris;
   }

   /*
   * @brief Obtens els objectius
   * @return Obtens els objectius
   * @throws Si el curs no té un objectiu, envia l'excepció «No existeix els objectius del curs»
   */
   public function obtenirObjectius() {
      if ($this->objectius==null)
         throw new Exception('', 704);
      return $this->objectius;
   }

   /*
   * @brief Obtens el programa
   * @return Obtens el programa
   * @throws Si el curs no té un programa, envia l'excepció «No existeix el programa del curs»
   */
   public function obtenirPrograma() {
      if ($this->programa==null)
         throw new Exception('', 705);
      return $this->programa;
   }

   /*
   * @brief Obtens les valoracions
   * @return Obtens les valoracions
   * @throws Si el curs no té una valoraciço, envia l'excepció «No existeix les valoracions del curs»
   */
   private function __obtenirValoracions() {
      if ($this->valoracions==null)
         throw new Exception('', 706);
      return $this->valoracions;
   }

   /*
   * @brief Obtens l'article
   * @return Obtens l'article
   */
   public function obtenirArticle() {
      return $this->article;
   }

   /*
   * @brief Obtens els docents
   * @return Obtens els docents
   * @throws Si el curs no té una llista de docents, envia l'excepció «No existeixen docents del curs»
   */
   private function __obtenirDocents() {
      if ($this->docents==null)
         throw new Exception('', 708);
      return $this->docents;
   }

   /*
   * @brief Obtens el docent
   * @return Obtens el docent corresponent a la posicio $posicio de la llista de docents
   */
   private function __obtenirDocent($posicio) {
      if ($this->__obtenirDocents() == null and count($this->__obtenirDocents())==0)
         throw new Exception('', 708);
      else if ($posicio>=0 && $posicio < count($this->__obtenirDocents()))
         return $this->docents[$posicio];
      else
         throw new Exception('', 708);
   }

   /*
   * @brief Obtens les edicions
   * @return Obtens les edicions del curs
   * @throws Si el curs no té una llista d'edicions, envia l'excepció «No existeixen edicions del curs»
   */
   private function __obtenirEdicions() {
      if ($this->edicions==null)
         throw new Exception('', 709);
      return $this->edicions;
   }

   /*
   * @brief Obtens una edició del curs
   * @return Obtens l'edicio corresponent a la posicio $posicio de la llista d'edicions
   */
   private function __obtenirEdicio($posicio) {
      if ($this->__obtenirEdicions() == null and count($this->__obtenirEdicions())==0)
         throw new Exception('', 709);
      else if ($posicio>=0 && $posicio < count($this->__obtenirEdicions()))
         return $this->edicions[$posicio];
      else
         throw new Exception('', 709);
   }

   /*
   * @brief Consulta si es visualitza des d'un mòbil
   * @return Obtens TRUE si es visualitza des d'un mòbil, altramanet retorna FALSE
   */
   private function __isMobile() {
      if ($this->dispositiu == "mobil") return true;
      else return false;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/


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
   * @brief Mostra la pestanya descripció d'un curs tal «INFORMACIO»
   * @return Mostra la pestanya descripció d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   private function __mostrarDescripcio( $vista ) {
      $presentacio = $this->__obtenirPresentacio()->obtenirText();
      $video = $this->obtenirVideo();
      $autoria = $this->__obtenirAutoria();
      $destinataris = $this->__obtenirDestinataris()->obtenirText();
      $objectius = $this->obtenirObjectius()->obtenirText();
      $article = $this->obtenirArticle();

      $contingutOrd = $contingutMov = "<h2>Presentació</h2>".$presentacio;
      if ($video != null) {
         $contingutOrd .= $video->mostrarVideoInfo();
         if ( !$this->__isMobile() ) {
            $contingutMov .= $video->mostrarVideoInfo();
         }
      }

      if ($autoria != null)
         $contingut .= $autoria->obtenirText();

      $contingut .= "<h2>Destinataris</h2>".$destinataris;
      $contingut .= "<h2>Objectius</h2><div class='objectius'>".$objectius."</div>";

      if ($article != null)
          $contingut .= $article->mostrarArticle();

      $contingutOrd .= $contingut;
      $contingutMov .= $contingut;

      $nomApartat = "<i class='fa fa-bookmark mr-2'></i><span class='flex-1-0-auto'>Descripció</span>";
      $mostrar = $this->__mostrarApartatVista($vista, "descripcio", $contingutOrd, "1", "desc", $nomApartat, $contingutMov);
      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya programa d'un curs tal «INFORMACIO»
   * @return Mostra la pestanya programa d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   private function __mostrarPrograma( $vista ) {
      $contingutOrd = $contingutMov = "<h2>Programa</h2>".$this->obtenirPrograma()->obtenirText();
      $nomApartat = "<i class='fa fa-bookmark mr-2'></i><span class='flex-1-0-auto'>Programa</span>";
      $mostrar = $this->__mostrarApartatVista($vista, "programa", $contingutOrd, "0", "prog", $nomApartat, $contingutMov);

      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya docents d'un curs tal «INFORMACIO»
   * @return Mostra la pestanya docents d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   private function __mostrarDocents( $vista ) {
      $contingut_docents = "<h2>Docents</h2>";
      $numero_docents = count($this->__obtenirDocents());
      $cnt = 0;
      while ($cnt < $numero_docents) {
         $contingut_docents .= $this->__obtenirDocent($cnt)->mostrarTutorCurs();
         $cnt++;
      }
      $nomApartat = "<i class='fa fa-user mr-2'></i><span class='flex-1-0-auto'>Docents</span>";
      $mostrar = $this->__mostrarApartatVista($vista, "docents", $contingut_docents, "0", "pon", $nomApartat, $contingut_docents);

      return $mostrar;
   }

   /*
   * @brief Mostra la frase dels perfils a la pestanya Edicions
   * @return Mostra la frase de "Les edicions de l'any YYYY/YYYY estan acollides al perfil XXXX. Per més informació...
   */
   private function __mostrarPerfilEdicions($perfilEd, $curs_escolar) {
      $cnt = 0;
      $numPerfils = count($perfilEd);

      while ($cnt < $numPerfils) {
         $nom_perfil_curt = $perfilEd[$cnt]->obtenirNomCurt()->obtenirText();

         require_once 'Text.php';
         $textClassPerfil = new Text($nom_perfil_curt);
         $textClassPerfil->obtenirNomCurt();
         $classPerfil = $textClassPerfil->obtenirText();

         $textPerfil = "<strong><span class='".$classPerfil."'>";
         $textPerfil .= $perfilEd[$cnt]->obtenirNomLlarg()->obtenirText();
         $textPerfil .= "</span></strong>";
         if ($cnt != 0)
            $textsPerfil .= " i ";
         $textsPerfil .= $textPerfil;
         $cnt++;
      }

      if ($numPerfils > 0) {
         $linkPerfil = "https://www.prisma.cat/perfils-professionals/";
         $mostrar.="<p>Les edicions de l'<strong>any escolar ".$curs_escolar."</strong> d'aquest curs estan ";
         $mostrar.="acollides al perfil professional ".$textsPerfil.". ";
         $mostrar.="Podeu consultar-ne tota la informació a la nostra pàgina ";
         $mostrar.="d'<strong><a href='".$linkPerfil."' target='_self' ";
         $mostrar.="itle='Acreditació de perfils professionals'>";
         $mostrar.="acreditació de perfils professionals</a></strong>.</p>";
      }

      return $mostrar;
   }

   /*
   * @brief Mostra la frase de l'acreditacio CDD a la pestanya Edicions
   * @return Mostra la frase de "Les edicions de l'any YYYY/YYYY estan acollides al CDD. Per més informació...
   */
   private function __mostrarCDDEdicions($curs_escolar) {
      if ( $this->esCDD() > 0 ) {
         $linkCDD = "https://www.prisma.cat/competencia-digital/";
         $mostrar.="<p>Les edicions de l'<strong>any escolar ".$curs_escolar."</strong> d'aquest curs estan ";
         $mostrar.="acollides a l'acreditació de la <strong>Competència Digital Docent</strong> (CDD). ";
         $mostrar.="Podeu consultar-ne tota la informació a la nostra pàgina ";
         $mostrar.="d'<strong><a href='".$linkCDD."' target='_self' ";
         $mostrar.="itle='Acreditació de la Competència Digital Docent'>";
         $mostrar.="acreditació de la Competència Digital Docent</a></strong>.</p>";
      }

      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya edicions d'un curs tal «INFORMACIO»
   * @return Mostra la pestanya edicions d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   private function __mostrarEdicions( $vista ) {
      $cursEsc2='';
      $trobatSS=false;
      $trobatN=false;
      $teperfil=false;

      $contEd.="<ul class='llistes'>";

      $numero_edicions = count($this->__obtenirEdicions());
      $cnt = 0; $mesPendent='';
      while ($cnt < $numero_edicions) {
         $edicioActual=$this->__obtenirEdicio($cnt);
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
         }
         $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
         $elemLink=explode('/',$linkCurs);
         $nomCurs=$elemLink[count($elemLink)-1];

         $contEd .= "<li>".$edicioActual->mostrarEdicioInfo($nomCurs)."</li>";
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
         del curs al Departament d'Ensenyament. Les edicions anteriors tenen data de resoluci&oacute;
         i per tant, ja estan reconegudes com a Formaci&oacute; Permanent del Professorat.</p>";

      $cursReconegut=true;
      $cns = "SELECT valor FROM params WHERE TIPUS=? AND VALOR=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)";
      $stmt = $connexio->prepare($cns);
      $stmt->bind_param("ss", $tipus, $valor);
      $tipus = 'curs-no-reconegut';
      $valor = $this->obtenirCodi()->obtenirText();
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

         require_once 'ConnexioBBDD_PreparedStatment.php';
         $connexio2 = new ConnexioBBDDSTMT();
         $connexio2->connectarBD();
         $cnsParam2 = "SELECT ID FROM params WHERE TIPUS=? AND VALOR=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)";
         $stmt2 = $connexio2->prepare($cnsParam2);
         $stmt2->bind_param("ss", $tipus2, $codiCurs2);
         $tipus2='curs-no-reconegut';
         $codiCurs2=$this->obtenirCodi()->obtenirText();
         $stmt2->execute();
         $stmt2->bind_result($idParam2);
         $stmt2->store_result();
         if ($stmt2->num_rows() > 0) $inscripcioOberta2=false;
         else $inscripcioOberta2=true;
         $connexio2->closeStmt();
         $connexio2->desconectarBD();

         $curs = new Curs($codiCurs2, $this->dispositiu);

         if ( $this->placesExharuidesSubv() && !($curs->__teEdicionsReconegudes() == 0 && !$inscripcioOberta2) ) {
            $contEd.="<p>
               Actualment les places d'aquest curs estan exhaurides.
               Tot i això, si hi estàs interessat, pots posar-te en contacte amb nosaltres a través del
               <a class='font-weight-bold' target='_self' href='https://www.prisma.cat/contacte'>formulari de contacte</a>
               i, en cas que s'alliberi una plaça del curs, <strong>Secretaria</strong> es posarà en contacte amb tu.</p>";
         }
         else {
            $contEd.="<p>Les dates de les edicions subsegüents s’aniran concretant amb almenys dos o tres mesos d’antelació.</p>";
         }
         $contEd.="<p>Curs reconegut com a activitat de <strong><a rel='noopener' target='_blank' ";
         $contEd.="href='http://xtec.gencat.cat/ca/formacio/formacio-altres-institucions/activitats-reconegudes/' ";
         $contEd.="title=\"Activiats reconegudes com a formació permanent del professorat pel Departament ";
         $contEd.="d'Educació\">formació permanent del professorat</a></strong> pel Departament d'Educació ";
         $contEd.="de la Generalitat de Catalunya d'acord amb l'<em>Ordre ENS/248/2012, de 20 d'agost de 2012, ";
         $contEd.="per la qual s'estableixen els requisits i el procediment per reconèixer ";
         $contEd.="activitats de formació permanent adreçades al professorat no universitari</em>.</p>";
      }
      $connexio->desconectarBD();

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
      En cas de tenir perfil, s'indica el perfil professional que acredita, juntament amb l'enllaç corresponent
      i el curs escolar. Si són dos cursos escolars, es fa exactament el mateix per cada curs escolar.*/
      if ($cursEsc2=='') {
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
      }

      /* Comprovem si té algun perfil pendent. Si és així, s'afageix els perfils pendents */
      $cntPerf=0;
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

         if ( $mesActual >= 7 && $mesActual <= 12 ) //curs escolar pendent és l'anterior
            $cursEscPerfPend = $anyActual."/".$anySeguent;
         else
            $cursEscPerfPend = $anyAnterior."/".$anyActual;

         $contEd.="<p>Les edicions de l'<strong>any escolar ".$cursEscPerfPend."</strong>
         d'aquest curs estan pendents d'acreditar el perfil professional ".$textsPerfil.".
         Podeu consultar-ne tota la informació a la nostra pàgina
         d'<strong><a href='".$linkPerfil."' target='_self' title='Acreditació de perfils
         professionals'>acreditació de perfils professionals</a></strong>.</p>";
      }

      /* --------------------------   ACREDITACIÓ  CDD  -------------------------- */
      if ($cursEsc2=='') {
         $cdd=$edCursEsc1->esCDD();
         $contEd.=$this->__mostrarCDDEdicions($cursEsc1);
      }
      else {
         $cddEd1=$edCursEsc1->esCDD();
         $cddEd2=$edCursEsc2->esCDD();

         if ( $cddEd1 == 1 && $cddEd2 == 1 ) {
             $cursEsc="dels <strong>anys escolars ".$cursEsc1." i ".$cursEsc2."</strong>";
             $contEd.=$this->__mostrarCDDEdicions($cursEsc);
         }
         else if ( $cddEd1 == 1 && $cddEd2 == 0 ) {
           $cursEsc1="de l'<strong>any escolar ".$cursEsc1."</strong>";
           $contEd.=$this->__mostrarCDDEdicions($cursEsc1);
         }
         else if ( $cddEd1 == 0 && $cddEd2 == 1 ) {
           $cursEsc2="de l'<strong>any escolar ".$cursEsc2."</strong>";
           $contEd.=$this->__mostrarCDDEdicions($cursEsc2);
         }
      }

      /* -------------------------- FI ACREDITACIÓ  CDD -------------------------- */


      if ($firstEd->obtenirCodiFiss()!=null && $ultimaEdicio->obtenirCodiFiss()!=null) {
         $contEd.="<p>Curs reconegut com a activitat de <strong><a rel='noopener' target='_blank' ";
         $contEd.="href='https://www.prisma.cat/activitats-formacio-interes-serveis-socials'";
         $contEd.="title=\"Activiats reconegudes com a formació d’interès en serveis socials pel Departament ";
         $contEd.="de Treball, Afers Socials i Famílies\">formació d’interès en serveis socials</a></strong> ";
         $contEd.="pel Departament de Treball, Afers Socials i Famílies de la Generalitat de Catalunya.</p>";
      }
      else if (
         ( $firstEd->obtenirCodiFiss()!=null && $ultimaEdicio->obtenirCodiFiss()==null ) ||
         ( $firstEd->obtenirCodiFiss()==null && $ultimaEdicio->obtenirCodiFiss()!=null )
      ) {
         /*
            Cal buscar la primera edició de FISS => edStartFiss => de juny de 2019
            Cal buscar la última edició de FISS => edLastFiss => desembre de 2021
         */

         $connexio = new ConnexioBBDDSTMT();
      	 $connexio->connectarBD();

         $cnsFirst = "SELECT DATAI FROM curs WHERE FISS IS NOT NULL AND CURS= ? AND ESTAT != 'T' ORDER BY DATAI ASC LIMIT 1";
         $cnsLast = "SELECT DATAI FROM curs WHERE FISS IS NOT NULL AND CURS= ? AND ESTAT != 'T' ORDER BY DATAI DESC LIMIT 1";
         $cnsNoTeMesRecFISS = "SELECT id FROM params WHERE tipus = 'noTidraMesEdicionsFISS' AND VALOR= ? AND DATAI <= CURRENT_TIME AND (CURRENT_TIME <= DATAF OR DATAF IS NULL)";

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

        $pendentRecFISS = 0;

        $stmt = $connexio->prepare($cnsNoTeMesRecFISS);
	      $stmt->bind_param("s", $codiCurs);
	      $stmt->execute();
        if ($stmt->num_rows() > 0) {
	         $pendentRecFISS = 1;
         }

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

         if ( $pendentRecFISS ) {
           if ( $firstEd->obtenirCodiFiss()!=null && $ultimaEdicio->obtenirCodiFiss()==null ) {
              $anyUltimaEdicio=$ultimaEdicio->obtenirAny()->obtenirNumero();
              $contEd.=" Les edicions de l'<strong>any ".$anyUltimaEdicio."</strong> encara estan pendents d’aquest reconeixement. ";
           }

           $contEd.=" Podeu consultar-ne tota la informació a la nostra pàgina
           <strong><em><a rel='noopener' target='_blank' href='https://www.prisma.cat/activitats-formacio-interes-serveis-socials'
           title=\"Activiats reconegudes com a formació d’interès en serveis socials
           pel Departament de Treball, Afers Socials i Famílies\">Activitats de
           formació d’interès en serveis socials</a></em></strong>.</p>";
         }
      }

	  $curs = new Curs($this->obtenirCodi()->obtenirText(), $this->dispositiu);

      if ( $curs->__teEdicionsReconegudes() == 0 )
        $contingut ="<h2>Properes edicions</h2>";
      else
        $contingut ="<h2>Edicions disponibles</h2>
        <p>Les dates d'inici i fi de curs de les edicions següents són:</p>".$contEd;

       $nomApartat = "<i class='fa fa-calendar mr-2'></i><span class='flex-1-0-auto'>Edicions</span>";
       $mostrar = $this->__mostrarApartatVista($vista, "edicions", $contingut, "0", "ed", $nomApartat, $contingut);

      return $mostrar;
   }

   /**
   * @brief Mostra la pestanya preu d'un curs tal «INFORMACIO»
   * @return Mostra la pestanya preu d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   * Mostra els diferents preus que estan associats el id_preu del curs.
   * Si és un curs, apareixeran els preus amb preu normal i preu amb descompte. Si el id_preu curs
   * té una jornada correlacionada (un curs té una jornada correlacionada si existeix un codi curs
   * que tingui el codi curs actual i algun numero. per ex. REG éstà correlaccionat a REG01).
   * Si el curs té algun descompte actiu, es mira si el descompte és per percentatge o per preu fix.
   * Si és per percentatge, se li aplica el percentatge de descompte a cada preu.
   * Si és per preu fix, s'afegeix com un preu més, el preu fix amb descompte.
   * Independentment del tipus de descompte, s'ha de mirar que el descompte sigui hàbil per el curs
   * actual, ja sigui perquè és el curs amb descompte, perquè s'apliqui a tots els cursos o a les
   * hores del curs corresponent. També s'ha de mirar que el descompte es realitzi a tots els mesos
   * o en el mes d'alguna edició activa en aquest curs.
   */
   private function __mostrarPreu( $vista ) {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consPreus = "SELECT IMPORT FROM preu WHERE ID=? AND DATAI <=CURRENT_DATE
                    AND (DATAF IS NULL OR CURRENT_DATE<=DATAF)";
      $stmtPreus = $connexio->prepare($consPreus);
      $stmtPreus->bind_param("d", $idPreu);
      $idPreu=$this->obtenirPreu()->obtenirNumero();
      $stmtPreus->execute();
      $stmtPreus->store_result();
      if ($stmtPreus->num_rows() > 0) {
         $stmtPreus->bind_result($import);
         $stmtPreus->fetch();
         if ( $import > 0 ) {
            $objImp = new Numero($import);
            $importFormatCorrecte = $objImp->mostrarNumeroDecimalsSense0();
            if ( $this->cursEsSubvencionat() )
               $textPreu.="<p><strong class='preu-normal px-2 py-1'><span style='text-decoration: line-through;color: var(--bg-danger);'>".$importFormatCorrecte." €</span> Subvencionat</strong></p>";
            else
               $textPreu.="<p><strong class='preu-normal px-2 py-1'>".$importFormatCorrecte." €</strong></p>";
         }
         else {
            $textPreu.="<p><strong class='preu-normal px-2 py-1'>Gratuït</strong></p>";
         }
      }
      $connexio->closeStmt();

      if ( !$this->cursEsSubvencionat() ) {
         $consDesc = "SELECT TIPUS, DESCRIPCIO, MES, CURS, PERCENTATGE, PREU
                     FROM descomptes WHERE DATAI<=CURRENT_TIMESTAMP AND
                     (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL) AND ID_PREU=? AND
                     (CURS=? OR CURS=? OR CURS='TOTS') ORDER BY ORDRE";
         $stmtDesc = $connexio->prepare($consDesc);
         $stmtDesc->bind_param("dsd", $idPreu, $cursDesc, $horesDesc);
         $cursDesc=$this->obtenirCodi()->obtenirText();
         $horesDesc=$this->obtenirHores()->obtenirNumero();
         $stmtDesc->execute();
         $stmtDesc->store_result();
         if ($stmtDesc->num_rows() > 0) {
            $textPreu.="<h3><span class='font-weight-bold'>Descomptes</span><p></p></h3>";
            $textPreu.="<ul class='preus'>";
            $stmtDesc->bind_result($tipus, $descripcio, $mes, $curs, $percentatge, $preu);
            $descomptes=[]; $i=0;
            while ($stmtDesc->fetch()) {
               //Comprovar que el descompte es pot aplicar en les edicions obertes
               $edicioOberta=false;
               if ($mes != 'TOTS') {
                  $numEd = count($this->__obtenirEdicions());
                  $cnt=0;
                  while ($cnt < $numEd && !$edicioOberta) {
                     if ($this->__obtenirEdicio($cnt)->obtenirMes()->obtenirText() == $mes)
                        $edicioOberta=true;
                     $cnt++;
                  }
               }

               //S'aplica el descompte a les edicions obertes
               if ($mes == 'TOTS' or $edicioOberta) {
                  //Empleno el vector dels descomptes.
                  if ($tipus>0 && $tipus<=10) { //Tipus pre-definit
                     $descomptes[$i]=[[$mes], $descripcio, $percentatge, $preu];
                  }
                  else if ($tipus>10) {
                     $cntVectDesc=0;
                     $exDecVect=false;
                     //Miro si existeix el descompte a la taula descomptes
                     while ($cntVectDesc<=count($descomptes) && !$exDecVect) {
                        if ($descomptes[$cntVectDesc][2]==$percentatge and $descomptes[$cntVectDesc][3]==$preu)
                           $exDecVect=true;
                        else
                           $cntVectDesc++;
                     }

                     if ($exDecVect) $descomptes[$cntVectDesc][0][]=$mes;
                     else $descomptes[$i]=[[$mes], $descripcio, $percentatge, $preu];

                  }
                  $i++;
               }
            }

            for ($i=0; $i<count($descomptes); $i++) {
               $desc=$descomptes[$i][1];
               $preu=$descomptes[$i][3];


               if ($desc!=null and $desc!='')
                  $textDesc=$desc;
               else {
                  $numMesos=count($descomptes[$i][0]);
                  $percent=$descomptes[$i][2];
                  $mesTextDesc='';

                  //Calcular el text dels mesos que s'ha de mostrar
                  $pos=0; $exMesTots=false;
                  while ($pos<$numMesos && !$exMesTots) {
                     if ($descomptes[$i][0][$pos] == 'TOTS')
                        $exMesTots=true;
                     else {
                        if ($pos>0 && $pos==$numMesos-1)
                           $mesTextDesc.=' i ';
                        else if ($pos>0 && $pos<$numMesos-1)
                           $mesTextDesc.=', ';
                        $edicioDesc = new Text($descomptes[$i][0][$pos]);
                        $textEdicioLlarg = $edicioDesc->obtenirMesLlarg();
                        $objTextMes = new Text($textEdicioLlarg);
                        $mesTextDesc.=$objTextMes->convertirMin();
                     }
                     $pos++;
                  }

                  //Calcular el text que s'ha de mostrar
                  $textDesc = "Descompte del ".$percent."% a ";
                  if ($numMesos>1) $textDesc .= "les edicions de ".$mesTextDesc;
                  else if ($exMesTots) $textDesc .= "totes les edicions obertes";
                  else $textDesc .= "l'edició de ".$mesTextDesc;
                  $textDesc .= ".";
               }

               $objPreu = new Numero($preu);
               echo "p".$preu."<br />";
               $preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();
               $textPreu.="<li><span class='font-weight-bold'>".$preuFormatCorrecte." €</span> - ".$textDesc."</li>";
            }

            $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
            $elemLink=explode('/',$linkCurs);
            $extUrl=$elemLink[count($elemLink)-1];
            $urlDescGrup="https://www.prisma.cat/descompte-curs-grup/".$extUrl;

            $textPreu.="<li><a href='".$urlDescGrup."' title='Descomptes per a grups' class='font-weight-bold'>Descomptes per a grups<i class='fas fa-arrow-right ml-2'></i></a></li>";
            $textPreu.="</ul>";
         }
         $connexio->closeStmt();

         $textPreu.="<p>Descomptes no acumulables.</p>";

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
         $tipus='info-forma-pagament'; //si el tipus és text de la forma de pagament
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
      }
      else {
         $textPreu.="<p>Només per a docents de centres públics i de centres privats sostinguts amb fons públics.</p>";
         $textPreu.="<p>Finançat amb fons públics per la Unió Europea (Next Generation EU) en el marc del Pla de Recuperació, Transformació i Resiliència (PRTR).</p>";
         $textPreu.="<div>
         <img class='w-100 my-2' src='https://www.prisma.cat/img/generalitat-catalunya.png' style='max-width: 250px'>
         <img class='w-100 my-2 px-2 ml-2' src='https://www.prisma.cat/img/next-generation.png' style='max-width: 250px'>
         </div>";

      }

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
   * @brief Mostra la pestanya valoracions d'un curs tal «INFORMACIO»
   * @return Mostra la pestanya valoracions d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   private function __mostrarValoracions( $vista ) {
      $contingut="<h2>Valoracions</h2>".$this->__obtenirValoracions()->obtenirText();

      $nomApartat = "<i class='fas fa-comments mr-2'></i><span class='flex-1-0-auto'>Valoracions</span>";
      $mostrar = $this->__mostrarApartatVista($vista, "valoracions", $contingut, "0", "val", $nomApartat, $contingut);

      return $mostrar;
   }

   /**
   * @brief Mostra l'apartat Més informació d'un curs tal «INFORMACIO»
   * @return Mostra l'apartat Més informació d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   public function mostrarMesInformacio() {
      $hores=$this->obtenirHores()->obtenirNumero();
      $codiCursMin=$this->obtenirCodi()->convertirMin();

      $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

      $urlInsc="https://www.prisma.cat/inscripcions/".$extUrl;
      $urlReg="https://www.prisma.cat/regal/".$extUrl;
      $urlDescGrup="https://www.prisma.cat/descompte-curs-grup/".$extUrl;
      $urlPerfil="https://www.prisma.cat/perfils-professionals/".$extUrl;
      $urlCDD="https://www.prisma.cat/competencia-digital";
      $urlRec="https://www.prisma.cat/reconeixements-certificacio";
      $urlMet="https://www.prisma.cat/metodologia";
      if ( $this->cursEsSubvencionat() ) $urlMet = "https://www.prisma.cat/metodologia-subvencionats";
      $urlComp="https%3A%2F%2Fwww.prisma.cat%2Fcursos%2F".$extUrl;
      $textTw=$this->obtenirTitol()->replace(' ','%20');

      $mostrar="<div class='compartir modal fade' id='compartir_".$codiCursMin."' role='dialog'>";
      $mostrar.="<div class='modal-dialog modal-dialog-centered'><div class='modal-content w-100'>";
      $mostrar.="<div class='modal-header'><p class='modal-title float-left font-weight-bold'>";
      $mostrar.="Comparteix l'enllaç d'aquest curs</p><button role='button' type='button' ";
      $mostrar.="class='modal-close position-absolute border-0' data-dismiss='modal' aria-label='Close'>";
      $mostrar.="<span aria-hidden='true'>×</span></button></div><div class='modal-body'>";
      $mostrar.="<div class='icones d-flex justify-content-around text-center py-4'>";
      /*Botó Facebook */
      $mostrar.="<a id='fb' rel='noreferrer' class='color-text' ";
      $mostrar.="onclick=\"window.open('http://www.facebook.com/sharer.php?u=".$urlComp."',";
      $mostrar.="'ventanacompartir', 'toolbar=0, status=0, width=650, height=450');\">";
      $mostrar.="<img src='https://www.prisma.cat/img/social/share_facebook.png'><p class='mt-2'>Facebook</p></a>";
      /*Twitter */
      $mostrar.="<a id='tw' rel='noreferrer' class='color-text' target='_blank' ";
      $mostrar.="href=\"http://twitter.com/home?status=".$textTw."%20".$urlComp."\">";
      $mostrar.="<img src='https://www.prisma.cat/img/social/share_twitter.png'><p class='mt-2'>Twitter</p></a>";
      /*Botó Whatsapp */
      $mostrar.="<a id='ws' rel='noreferrer' class='color-text' target='_blank' ";
      $mostrar.="href=\"https://api.whatsapp.com/send?text=".$urlComp."\" data-action='share/whatsapp/share'>";
      $mostrar.="<img src='https://www.prisma.cat/img/social/share_whatsapp.png'><p class='mt-2'>Whatsapp</p></a>";
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
      $mostrar.="<li class='d-flex flex-row align-items-center'><i class='far fa-clock'></i><h3>Durada</h3>";
      $mostrar.="<span class='".$classEl."'>".$hores." hores</span></li>";
      $cntNiv = 0;
      if (count($this->nivells)==1) {
         $mostrar.="<li class='d-flex flex-row align-items-center'><i class='fas fa-signal'></i><h3>Nivell</h3>";
         $mostrar.="<span class='".$classEl."'>".$this->obtenirNivell($cntNiv)->obtenirText()."</span>";
      }
      else {
         $mostrar.="<li class='d-flex flex-row align-items-center nivells'><i class='fas fa-signal'></i><h3>Nivells</h3><div class='d-flex flex-column'> ";
         while ($cntNiv < count($this->nivells)) {
            $niv=$this->obtenirNivell($cntNiv)->obtenirText();
             if ($cntNiv == 0)
               $mostrar .= "<span class='niv-apartat niv1 ".$classEl." text-right'>".$niv."</span>";
             else
               $mostrar .= "<span class='niv-apartat ".$classEl." text-right'>".$niv."</span>";
             $cntNiv++;
         }
         $mostrar .= "</div></li>";
      }
      $mostrar.="<li class='d-flex flex-row align-items-center'><i class='fas fa-cog'></i><h3>Metodologia</h3>";
      $mostrar.="<button role='button' class='mesinfo border-radius-2 text-center ";
      $mostrar.="position-relative negreta500' title='Mostra més informació de la metodologia' ";
      $mostrar.="onclick=\"location.href='".$urlMet."'\">+ info</button></li>";
      $mostrar.="<li class='d-flex flex-row align-items-center'><i class='fas fa-award'></i><h3>Reconeixement</h3>";
      $mostrar.="<button role='button' class='mesinfo border-radius-2 text-center ";
      $mostrar.="position-relative negreta500' title='Mostra més informació del reconeixement' ";
      $mostrar.="onclick=\"location.href='".$urlRec."'\">+ info</button></li>";

      if ( $this->esCDD() ) {
         $mostrar.="<li class='d-flex flex-row align-items-center'><i class='fas fa-laptop-code'></i><h3>Acreditació CDD</h3>";
         $mostrar.="<button role='button' class='mesinfo border-radius-2 text-center ";
         $mostrar.="position-relative negreta500' title='Mostra l'acreditació CDD' ";
         $mostrar.="onclick=\"location.href='".$urlCDD."'\">+ info</button></li>";
      }

      $cntPerf=0;
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
      }
      $cntPerf=0;
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
      }

	  $curs = new Curs($codiCursMin, $this->dispositiu);

     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();
     $cnsParam = "SELECT ID FROM params WHERE TIPUS=? AND VALOR=? AND DATAI<=CURRENT_TIME AND (CURRENT_TIME<=DATAF OR DATAF IS NULL)";
     $stmt = $connexio->prepare($cnsParam);
     $stmt->bind_param("ss", $tipus, $codiCurs);
     $tipus='curs-no-reconegut';
     $codiCurs=$this->obtenirCodi()->obtenirText();
     $stmt->execute();
     $stmt->bind_result($idParam);
     $stmt->store_result();
     if ($stmt->num_rows() > 0) $inscripcioOberta=false;
     else $inscripcioOberta=true;
     $connexio->closeStmt();
     $connexio->desconectarBD();

      $mostrar.="</ul><div class='d-flex flex-column justify-content-center align-items-center'>";
      if ( $this->placesExharuidesSubv() ) {
         $mostrar.="<div role='button' class='inscripcio border-0 border-radius-2 ";
          $mostrar.="text-white text-center position-relative negreta500' ";
          if ($curs->__teEdicionsReconegudes() == 0 && !$inscripcioOberta) {
    	 	 $mostrar.="title=\"Inscripcions no disponibles\"><i class='fas fa-edit'></i>";
    		 $mostrar.="Inscripció no disponible</div>";
    	  }
    	  else {
    		$mostrar.="onclick=\"location.href='".$urlInsc."'\" title=\"Inscriu-te al curs ";
            $mostrar.=$this->obtenirTitol()->obtenirText()."\"><i class='fas fa-edit'></i>";
       	    $mostrar.="Inscripcions completes</div>";
    	  }
      }
      else {
   	  $mostrar.="<button role='button' class='inscripcio border-0 border-radius-2 ";
         $mostrar.="text-white text-center position-relative negreta500' ";
         if ($curs->__teEdicionsReconegudes() == 0 && !$inscripcioOberta) {
   	 	 $mostrar.="title=\"Inscripcions no disponibles\"><i class='fas fa-edit'></i>";
   		 $mostrar.="Inscripció no disponible</button>";
   	  }
   	  else {
   		$mostrar.="onclick=\"location.href='".$urlInsc."'\" title=\"Inscriu-te al curs ";
           $mostrar.=$this->obtenirTitol()->obtenirText()."\"><i class='fas fa-edit'></i>";
      	    $mostrar.="Inscripció</button>";
   	  }
	  }
     if ( $this->tipus->obtenirText() != "S" ) {

      $mostrar.="<button role='button' class='regala border-0 border-radius-2 text-center ";
      $mostrar.="position-relative negreta500' onclick=\"location.href='".$urlReg."'\" ";
      $mostrar.="title=\"Regala el curs ".$this->obtenirTitol()->obtenirText()."\">";
      $mostrar.="<i class='fas fa-gift'></i>Regala aquest curs</button>";
      $mostrar.="<button role='button' class='regala border-0 border-radius-2 text-center ";
      $mostrar.="position-relative negreta500' onclick=\"location.href='".$urlDescGrup."'\" ";
      $mostrar.="title=\"Descompte per a grups ".$this->obtenirTitol()->obtenirText()."\">";
      $mostrar.="<i class='fas fa-users'></i>Descompte per a grups</button>";
   }
   else {
      $mostrar.="<img class='w-100 my-2' src='https://upload.wikimedia.org/wikipedia/commons/thumb/6/62/Logotipo_de_la_Generalitat_de_Catalunya.svg/1200px-Logotipo_de_la_Generalitat_de_Catalunya.svg.png'>";
      $mostrar.="<img class='w-100 my-2 px-2' src='https://www.prisma.cat/img/next-generation.png'>";

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
   * @brief Mostra l'apartat els quatre cursos relacionats d'un curs tal «INFORMACIO»
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
   * @brief Mostra l'apartat fix al peu de pàgina
   * @return Mostra l'apartat fix al peu de pàgina
   */
   public function mostrarPeuFix() {
      $codiCursMin=$this->obtenirCodi()->obtenirText();
      $hores=$this->obtenirHores()->obtenirNumero();
      $preu=$this->obtenirPreu()->obtenirNumero();
      $titol=$this->obtenirTitol()->obtenirText();

      $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

      $urlInsc="https://www.prisma.cat/inscripcions/".$extUrl;
      $urlReg="https://www.prisma.cat/regal/".$extUrl;

      $mostrar="<div class='peufix d-flex flex-column w-100 position-fixed'>
      <div class='d-flex flex-row'>
      <button role='button' class='regala position-relative m-0 py-2 border-0 border-radius-2 text-center negreta500'
      onclick=\"location.href='".$urlReg."'\" title=\"Regala el curs ".$this->obtenirTitol()->obtenirText()."\">";
      $mostrar.="<i class='fas fa-gift mr-2'></i>Regala'l</button>";
      $mostrar.="<button role='button' class='regala position-relative m-0 py-2 border-0 border-radius-2 text-center ";
      $mostrar.="negreta500' onclick=\"location.href='tel:+34-972-21-75-65'\" ";
      $mostrar.="title=\"Trucan's\"><i class='fa fa-phone mr-2'></i>Truca'ns</button>";
      $mostrar.="<button role='button' title=\"Contacte\" class='regala position-relative m-0 py-2 ";
      $mostrar.="border-0 border-radius-2 text-center negreta500' ";
      $mostrar.="onclick=\"location.href='mailto:secretaria@prisma.cat?Subject=Informació%20curs%20";
      $mostrar.=$codiCursMin."'\"><i class='fas fa-at mr-2'></i>Contacte</button></div>";
      $mostrar.="<button role='button' class='inscripcio m-0 border-0 border-radius-2 negreta500 ";

	  $curs = new Curs($codiCursMin, $this->dispositiu);

      if ($curs->__teEdicionsReconegudes() == 0) {
	 	 $mostrar.="text-white text-center position-relative' title=\"Inscripcions no disponibles\"><i class='fas fa-edit'></i>";
		 $mostrar.="Inscripció no disponible</button></div>";
	  }
	  else {
	  	$mostrar.="text-white text-center position-relative' onclick=\"location.href='".$urlInsc."'\" ";
      $mostrar.="title=\"Inscriu-te al curs ".$this->obtenirTitol()->obtenirText()."\">";
      $mostrar.="<i class='fas fa-edit'></i>Inscripció</button></div>";
	  }

      return $mostrar;
   }

   /**
   * @brief Mostra la etiqueta del curs amb descompte
   * @return Si el curs té algun descompte actiu, mostra la etiqueta del descompte
   * del mes (p. ex. 20% DE DESCOMPTE A L'EDICIÓ DE MAIG) o un descompte en general (p. ex. 20% de descompte).
   * Un curs té un descompte actiu si existeix un descompte on la data d'inici és
   * abans d'avui, la data de fi és després d'avui, el mes del descompte és TOTS o
   * alguna edició que estigui oberta i el curs del descompte és TOTS o el curs actual
   */
   private function __etiquetaCursAmbDescompteTitol() {
      $etiqueta='';

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);

      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='limit-editions';
      $orderBy='DATAI';
      $stmtParam->execute();
      $stmtParam->bind_result($limitEd);
      $stmtParam->fetch();
      $tipus='dies-inscriu-cursos';
      $orderBy='VALOR';
      $codiCurs=$this->obtenirCodi()->obtenirText();
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $hores=$this->obtenirHores()->obtenirNumero();
      $diesObets=0;
      while ($stmtParam->fetch()) {
         $valors = explode('|',$valor);
         if (count($valors) != 2)
            throw new Exception('',714);
         else if (intval($valors[0])>0 && $valors[0]==$hores) //si el valor és un numero i les hores son iguals al curs
            $diesObets = $valors[1];
         else if (intval($valors[0])<=0 && $valors[0]==$codiCurs) //si el valor no és un numero i el codi és igual al curs
            $diesObets = $valors[1];
      }
      $connexio->closeStmt();
      if ($diesObets==0)
         throw new Exception('',714);

      $consultaEd = "SELECT MES, DATA_RESOL FROM curs WHERE CURS=? AND PUBLIC = 1
         AND CURS!='PROVA' AND CURS NOT LIKE '%0%' AND CURS NOT LIKE '%JOR%' AND
         (DATEDIFF(DATAI + ?,CURRENT_DATE)>0) AND ESTAT!='0'
         ORDER BY ANY, MES LIMIT ?";
      $stmtEd = $connexio->prepare($consultaEd);
      $stmtEd->bind_param("sdd", $codiCurs, $diesObets, $limitEd);
      $stmtEd->execute();
      $stmtEd->bind_result($mesDesc, $dataResol);
      $cntEd = 0;
      while ( $stmtEd->fetch() ){
         $edicions[$cntEd] = $mesDesc;
         $cntEd++;
      }

      $connexio->closeStmt();

      $consDesc = "SELECT TIPUS, DESCRIPCIO, MES, CURS, PERCENTATGE, PREU
                  FROM descomptes WHERE DATAI<=CURRENT_TIMESTAMP AND TIPUS > 10 AND
                  (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL) AND ID_PREU=? AND
                  (CURS=? OR CURS=? OR CURS='TOTS') ORDER BY ORDRE";
      $stmtDesc = $connexio->prepare($consDesc);
      $stmtDesc->bind_param("dsd", $idPreu, $cursDesc, $horesDesc);
      $idPreu=$this->obtenirPreu()->obtenirNumero();
      $cursDesc=$this->obtenirCodi()->obtenirText();
      $horesDesc=$this->obtenirHores()->obtenirNumero();
      $stmtDesc->execute();
      $stmtDesc->store_result();
      $stmtDesc->bind_result($tipus, $descripcio, $mes, $curs, $percentatge, $preu);
      $descomptes=[]; $i=0;
      while ($stmtDesc->fetch()) {
         //Comprovar que el descompte es pot aplicar en les edicions obertes
         $edicioOberta=false;
         if ($mes != 'TOTS') {
            $numEd = count($edicions);
            $cnt=0;
            while ($cnt < $numEd && !$edicioOberta) {
               if ($edicions[$cnt] == $mes)
                  $edicioOberta=true;
               $cnt++;
            }
         }

         //S'aplica el descompte a les edicions obertes
         if ($mes == 'TOTS' or $edicioOberta) {
            //Empleno el vector dels descomptes.
            if ($tipus>0 && $tipus<=10) { //Tipus pre-definit
               $descomptes[$i]=[[$mes], $descripcio, $percentatge, $preu];
            }
            else {
               $cntVectDesc=0;
               $exDecVect=false;
               //Miro si existeix el descompte a la taula descomptes
               while ($cntVectDesc<=count($descomptes) && !$exDecVect) {
                  if ($descomptes[$cntVectDesc][2]==$percentatge and $descomptes[$cntVectDesc][3]==$preu)
                     $exDecVect=true;
                  else
                     $cntVectDesc++;
               }

               if ($exDecVect) $descomptes[$cntVectDesc][0][]=$mes;
               else {
                  $descomptes[$i]=[[$mes], $descripcio, $percentatge, $preu];
               }
            }
            $i++;
         }
      }

      for ($i=0; $i<count($descomptes); $i++) {
         $desc=$descomptes[$i][1];
         $preu=$descomptes[$i][3];

         $numMesos=count($descomptes[$i][0]);
         $percent=$descomptes[$i][2];
         $mesTextDesc='';
         $mesTextDesc2='';

         //Calcular el text dels mesos que s'ha de mostrar
         $pos=0; $exMesTots=false;
         while ($pos<$numMesos && !$exMesTots) {
            if ($descomptes[$i][0][$pos] == 'TOTS')
               $exMesTots=true;
            else {
               if ($pos>0 && $pos==$numMesos-1)
                  $mesTextDesc.=' i ';
               else if ($pos>0 && $pos<$numMesos-1)
                  $mesTextDesc.=', ';
               $edicioDesc = new Text($descomptes[$i][0][$pos]);
               $textEdicioLlarg = $edicioDesc->obtenirMesLlarg();
               $objTextMes = new Text($textEdicioLlarg);
               $mesTextDesc.=$objTextMes->convertirMaj();

               $textEdicioLlarg2 = $edicioDesc->obtenirDeMesLlarg();
               $objTextMes2 = new Text($textEdicioLlarg2);
               $mesTextDesc2.=$objTextMes2->convertirMaj();
            }
            $pos++;
         }
         if (!$exMesTots) $mesTextDesc = $mesTextDesc;

         if ($mes == 'TOTS')
            $etiqueta.="<div class='desc float-left'>".$percent."% DE DESCOMPTE".$mesTextDesc2."</div>";
         else
            $etiqueta.="<div class='desc float-left'>-".$percent."% ".$mesTextDesc."</div>";
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();

      return $etiqueta;
   }

}
?>
