<?php

require_once 'Info.php';

/**
   * @class InfoTaller
   * @brief Conté tota la informació extesa sobre una Info i és el cas personalitzat del taller de les infos
*/
class InfoTaller extends Info {
   private $dataI; /**< Date Data inici del taller */
   private $seu; /**< Text Ciutat on es realitza l'activitat. Ex. girona */
   private $lloc; /**< HTML Html del lloc */
   private $comarribar; /**< HTML Html de com arribar-hi */
   private $cons; /**< Consultes */
   private $apartats;
   private $mesInfo;

   /* ######################################################################### */
   /* ######################### FUNCIONS CONSTRUCTORS ######################### */
   /* ######################################################################### */

   public function __construct($url, $dispositiu) {
      $this->url = $url;
      $this->dispositiu = $dispositiu;
      $this->estat=1;
      $this->tipus = new Text("T");
      $this->subTitolPage = "Taller presencial";

      $this->cons = [
         "cnsInfo"            => "SELECT ID, CODI_CURS, TITOL, SHORT_DESC, ID_VIDEO, ID_IMG_LARGE,
                                  ID_IMG_SMALL, PRESENTACIO, AUTORIA, DESTINATARIS, OBJECTIUS, PROGRAMA
                                  FROM informacio WHERE ID_AMIGABLE=? AND ESTAT=1 AND TIPUS_CURS = 'T'",
         "cnsExistInfo"       => "SELECT ID FROM informacio WHERE ID_AMIGABLE=? AND ESTAT=0",
         "cnsInfoTaller"      => "SELECT HORES, ID_PREU, DATAI, CIUTAT, LLOC, COM_ARRIBAR
                                  FROM info_taller WHERE CODI_CURS = ? AND ESTAT = 1 AND DATAI >= CURRENT_DATE",
         "cnsLastInfoTaller"  => "SELECT ANY, MES, HORES, ID_PREU, DATAI, CIUTAT, LLOC, COM_ARRIBAR, FRACC
                                  FROM info_taller WHERE CODI_CURS = ? AND ESTAT = 1 ORDER BY DATAI DESC",
         "cnsCodi"            => "SELECT CODI_CURS FROM informacio WHERE ID=?",
         "cnsNivellTemaRel"   => "SELECT f.ID_NIVELL, f.ID_REL FROM filtres AS f
                                  WHERE ID_INFO LIKE ? AND f.DATAI<=CURRENT_TIME AND
                                  (f.DATAF IS NULL OR CURRENT_TIME<=f.DATAF)",
         "cnsDocents"         => "SELECT DNI_TUTOR FROM honoraris AS h WHERE CURS LIKE ? AND
                                  h.ESTAT=1 AND PERFIL='tutor' AND DNI_TUTOR REGEXP '^[0123456789XYZ]'
                                  GROUP BY DNI_TUTOR ORDER BY ORDRE_TUTOR ASC",
         "cnsUrlDocent"       => "SELECT ID_URL FROM personal WHERE DNI=?",
         "cnsNivell"          => "SELECT NOM_INFO, ORDRE FROM nivells WHERE ID=?",
         "cnsParam"           => "SELECT VALOR FROM params WHERE DATAI<=CURRENT_TIME AND
                                 (DATAF IS NULL OR CURRENT_TIME<=DATAF) AND TIPUS=?",
         "cnsPreus"           => "SELECT IMPORT FROM preu WHERE ID=? AND DATAI <=CURRENT_TIME
                                 AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)",
         "xx" => "
                                  "
      ];
      $this->apartats = [
         ['descripcio', 'fa-solid fa-bookmark', 'Descripció'],
         ['programa', 'fa-solid fa-cube', 'Programa'],
         ['docents', 'fa-solid fa-user', 'Docents'],
         ['edicio', 'fa-regular fa-calendar-days', 'Data i lloc'],
         ['preu', 'fa-regular fa-credit-card', 'Preu']
      ];

      $this->mesInfo = [
         [ 'fa-solid fa-clock', 'Durada' ],
         [ 'fa-solid fa-signal', 'Nivells' ],
         [ 'fa-solid fa-cog', 'Modalitat' ],
         [ 'fa-solid fa-location-dot', 'Seu' ],
      ];

      $idAmigable = $url->obtenirID();

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $stmt = $connexio->prepare( $this->cons["cnsInfo"] );
      $stmt->bind_param("d", $idAmigable);
      $stmt->execute();
      $stmt->store_result();
      if ( $stmt->num_rows() > 0 ) {
         $stmt->bind_result($idInfo, $codi, $titol, $desc, $idVideo, $idImgLarge, $idImgSmall,
            $pres, $autoria, $dest, $obj, $prog);
         $stmt->fetch();
         $connexio->closeStmt();
      }
      else {
         $connexio->closeStmt();
         $this->estat=0;
         $stmt = $connexio->prepare( $this->cons["cnsInfoTaller"] );
         $stmt->bind_param("d", $idAmigable);
         $stmt->execute();
         $stmt->store_result();
         if ( $stmt->num_rows() > 0 )
            throw new Exception('',3210);
         else
            throw new Exception('',3201);
         $connexio->closeStmt();
      }

      $this->sincron = null;
      $this->article = null;
      $this->valoracions = null;

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

      $stmt = $connexio->prepare( $this->cons["cnsInfoTaller"] );
      $stmt->bind_param("s", $codi);
      $stmt->execute();
      $stmt->store_result();
      if ( $stmt->num_rows() >= 0  ) {
         $stmt->bind_result($hores, $idPreu, $dataI, $seu, $htmlLloc, $htmlComArribar);
         $stmt->fetch();
         $connexio->closeStmt();
      }
      else {
         $connexio->closeStmt();
         $stmt = $connexio->prepare( $this->cons["cnsLastInfoTaller"] );
         $stmt->bind_param("s", $codi);
         $stmt->execute();
         $stmt->store_result();
         if ( $stmt->num_rows() > 0 ) {
            $stmt->bind_result($hores, $idPreu, $dataI, $seu, $htmlLloc, $htmlComArribar);
            $stmt->fetch();
            $connexio->closeStmt();
         }
         else
            throw new Exception('',3202);
         $connexio->closeStmt();
      }

      require_once 'Numero.php';
      if ($hores!=null and $hores!='')
         $this->hores = new Numero($hores);
      else
         $this->hores = null;

      if ($idPreu!=null and $idPreu!='')
         $this->id_preu = new Numero($idPreu);
      else
         $this->id_preu = null;

      $this->dataI = $dataI;

      if ( $seu!=null AND $seu!='' )
         $this->seu = new Text($seu);
      else
         $this->seu = null;

      $this->lloc = $htmlLloc;
      $this->comarribar = $htmlComArribar;

      // /* ######################################################################### */
      // /* ### Es busca els nivells, el tema i els cursos relacionats de la info ### */
      // /* ######################################################################### */
      $stmt = $connexio->prepare( $this->cons["cnsNivellTemaRel"] );
      $stmt->bind_param("d", $idInfo);
      $stmt->execute();
      $stmt->store_result();
      $stmt->bind_result($idNivell, $idRel);
      if ( $stmt->num_rows() <= 0 )
         throw new Exception('',711);
      else {
         $stmt->fetch();
         if ($idNivell=='' or $idNivell==null)
            throw new Exception('',712);
         else if ($idRel=='' or $idRel==null)
            throw new Exception('',713);
      }
      $connexio->closeStmt();

      // /* ######################################################################### */
      // /* ############## Es busca els cursos relacionats de la info  ############## */
      // /* ######################################################################### */
      $idsRel = explode('|',$idRel);
      $cntRel = 0;
      $cntRelReals = 0;
      require_once 'Pack.php';
      while ( $cntRel < count($idsRel) ) {
         $idInfo = $idsRel[$cntRel];
         if ( intval($idInfo) < 10000 ) {
            if ( $stmt = $connexio->prepare( $this->cons["cnsCodi"] ) ) {
               $stmt->bind_param("d", $idInfo);
               $stmt->execute();
               $stmt->bind_result($codiCurs);
               $stmt->fetch();
               $curs = new Curs($codiCurs, $this->dispositiu);
               if ( $curs->obtenirEstat() == 1 ) {
                  $cursosRel[$cntRelReals] = new Curs($codiCurs, $this->dispositiu);
                  $cntRelReals++;
               }
               $connexio->closeStmt();
            }
            else {
               throw new Exception('',3203);
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
      require_once 'LlistatCursos.php';
      $this->cursos_relacionats = new LlistatCursos($cursosRel);

      /* ######################################################################### */
      /* ############## Consulta els docents que tutoritzen el curs ############## */
      /* ######################################################################### */
      $stmt = $connexio->prepare( $this->cons["cnsDocents"] );
      $stmt->bind_param("s", $codi);
      $stmt->execute();
      $stmt->bind_result($dniTutor);
      $cntDoc = 0;
      while ( $stmt->fetch() ) {
         $ordreDoc[$cntDoc] = $dniTutor;
         $cntDoc++;
      }
      $connexio->closeStmt();

      $stmt = $connexio->prepare( $this->cons["cnsUrlDocent"] );
      $stmt->bind_param("s", $dniDoc);
      require_once 'Tutor.php';
      $cntDoc = 0;
      while ($cntDoc < count($ordreDoc)) {
         $dniDoc = $ordreDoc[$cntDoc];
         $stmt->execute();
         $stmt->bind_result($idUrlDoc);
         $stmt->fetch();
         $urlDoc = new Url($idUrlDoc);
         $this->docents[$cntDoc] = new Tutor($urlDoc, $this->dispositiu);
         $cntDoc++;
      }
      $connexio->closeStmt();

      /* ######################################################################### */
      /* #################### Es busca els nivells de la info #################### */
      /* ######################################################################### */

      $ids_nivells = explode('|',$idNivell);
      $cnt_nivells = 0;
      $stmt = $connexio->prepare( $this->cons["cnsNivell"] );
      $stmt->bind_param("d", $idNivellActual);
      while ($cnt_nivells < count($ids_nivells)) {
         $idNivellActual = $ids_nivells[$cnt_nivells];
         $stmt->execute();
         $stmt->bind_result($nomNiv, $ordreNiv);
         $stmt->fetch();
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
   }

   /* ######################################################################### */
   /* ######################       FUNCIONS VISTES       ###################### */
   /* ######################################################################### */

   /**
   * @brief Mostra l'apartat fix al peu de pàgina
   * @return Mostra l'apartat fix al peu de pàgina
   */
   public function mostrarPeuFix() {
      $codiCursMin=$this->obtenirCodi()->obtenirText();

      $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

      $urlInsc="https://www.prisma.cat/inscripcions/tallers/".$extUrl;

      $mostrar="<div class='peufix d-flex flex-column w-100 position-fixed'>
      <div class='d-flex flex-row'>";
      $mostrar.="<button role='button' class='regala position-relative m-0 py-2 border-0 border-radius-2 text-center ";
      $mostrar.="negreta500' onclick=\"location.href='tel:+34-972-21-75-65'\" ";
      $mostrar.="title=\"Trucan's\"><i class='fa fa-phone mr-2'></i>Truca'ns</button>";
      $mostrar.="<button role='button' title=\"Contacte\" class='regala position-relative m-0 py-2 ";
      $mostrar.="border-0 border-radius-2 text-center negreta500' ";
      $mostrar.="onclick=\"location.href='mailto:secretaria@prisma.cat?Subject=Informació%20curs%20";
      $mostrar.=$codiCursMin."'\"><i class='fas fa-at mr-2'></i>Contacte</button></div>";
      $mostrar.="<button role='button' class='inscripcio m-0 border-0 border-radius-2 negreta500 ";

      if ( $this->__exProxEdicio() == 0 && !$inscripcioOberta ) {
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
   * @brief Retorna les hores amb el titol Hores i la icona corresponent la vista ordinador
   * @return Retorna la vista de les hores per la vista ordinador
   */
   private function __getHTMLHores() {
      $hores = $this->obtenirHores()->obtenirNumero();
      $html.="<li class='d-flex flex-row align-items-center'>
      <i class='".$this->mesInfo[0][0]."'></i>
      <h3>".$this->mesInfo[0][1]."</h3>";
      $html.="<span class='negreta500'>".$hores." hores</span></li>";
      return $html;
   }

   /**
   * @brief Retorna les hores del taller en la vista mobil
   * @return xxx
   */
   private function __getHTMLHoresMobile() {
      $hores = $this->obtenirHores()->obtenirNumero();
      $html = "<div class='cnt-hores'><i class='far fa-clock mr-1'></i>".$hores." h</div>";
      return $html;
   }

   /**
   * @brief Retorna els diferents nivells separats en format llista amb el titol Nivells i la icona la vista ordinador
   * @return Retorna la vista dels nivells per la vista ordinador
   */
   private function __getHTMLNivells() {
      $cnt = 0; $numTotal = count($this->nivells);
      $classCnt = '';
      if ( $numTotal > 1 ) $classCnt = " nivells";
      $html = "<li class='d-flex flex-row align-items-center".$classCnt."'>
      <i class='".$this->mesInfo[1][0]."'></i>
      <h3>".$this->mesInfo[1][1]."</h3>";

      if ( $numTotal > 1 ) $html .= "<div class='d-flex flex-column'>";
      while ($cnt < $numTotal ) {
         $nivell = $this->obtenirNivell($cnt)->obtenirText();
         $classNiv = "niv-apartat negreta500 text-right";

         if ( $cnt == 0 && $numTotal > 1 ) $classNiv .= ' niv1';
         if ( $numTotal == 1 ) $classNiv = 'negreta500';

         $html .= "<span class='".$classNiv."'>".$nivell."</span>";
         $cnt++;
      }
      if ( $numTotal > 1 ) $html .= "</div>";
      $html .= "</li>";

      return $html;
   }

   /**
   * @brief Retorna els diferents nivells separats per | en la vista mobil
   * @return Retorna la vista dels nivells per la vista movil
   */
   private function __getHTMLNivellsMobile() {
      $cnt = 0; $numTotal = count($this->nivells);
      while ($cnt < $numTotal ) {
         $nivell = $this->obtenirNivell($cntNiv)->obtenirText();
         if ( $cnt > 0 && $numTotal > 1 ) $html .= "<span class='nivells'>|</span><span class='nivells'>";
         $html .= $nivell;
         if ( $cnt > 0 && $numTotal > 1 ) $html .= "</span>";
         $cnt++;
      }
      $html = "<div class='cnt-niv'><i class='far fa-clock mr-1'></i>".$html." h</div>";
      return $html;
   }

   /**
   * @brief Mostra la vista de la capçalera de la versió ordinador i tablet
   * @return Mostra la vista de la capçalera de la versió ordinador i tablet
   */
   private function __getVistaHeadTitle() {
      $subTitle = $this->__getSubtitle();
      $html = "<div class='d-flex flex-row flex-wrap align-items-start align-items-sm-center'>
      <div class='curs-linia mb-3 mb-sm-0'>".$subTitle."</div>";
      $html .= $this->__etiquetaNovetat();
      $html .= $this->__etiquetaTaller();
      $html .= '</div>';
      return $html;
   }

   /**
   * @brief Mostra la vista de la capçalera de la versió mobil
   * @return Mostra la vista de la capçalera de la versió mobil
   */
   private function __getVistaHeadTitleMobile() {
      $subTitle = $this->__getSubtitle();
      $html = "<div class='d-flex flex-row flex-wrap align-items-center'>
      <div class='curs-linia'>".$subTitle."</div>";
      $html .= $this->__etiquetaNovetat();
      $html .= $this->__etiquetaTaller();
      $html .= $this->__getHTMLHoresMobile();
      // $html .= $this->__getHTMLNivellsMobile();
      $html .= '</div>';
      return $html;
   }

   /**
   * @brief La imatge de capçalera amb totes les seves versions
   * @return Retorna la vista de la imatge amb les diferents versions pel
   *   responsive per carregar les imatges pertinents segons la mida de la pantalla
   */
   public function __getVistaHeadVisualImg() {
      $altImg=$this->obtenirImgLarge()->obtenirAlt();
      $linkImg="https://www.prisma.cat".$this->obtenirImgLarge()->obtenirLink();

      $linkImgOrig = substr($linkImg, 0, -4);
      $linkImgWeb = $linkImgOrig.".webp";

      $html = "<div class='info-banner mb-4 position-relative'><picture>
         <source media='(max-width: 576px)' type='image/webp'
            data-srcset='".$this->__linkImg(576,'webp',$linkImgOrig)."'
            srcset='".$this->__linkImg(576,'webp',$linkImgOrig)."'>
         <source media='(max-width: 576px)' type='image/jpeg'
            data-srcset='".$this->__linkImg(576,'jpg',$linkImgOrig)."'
            srcset='".$this->__linkImg(576,'jpg',$linkImgOrig)."'>
         <source media='(max-width: 768px)' type='image/webp'
            data-srcset='".$this->__linkImg(768,'webp',$linkImgOrig)."'
            srcset='".$this->__linkImg(768,'webp',$linkImgOrig)."'>
         <source media='(max-width: 768px)' type='image/jpeg'
            data-srcset='".$this->__linkImg(768,'jpg',$linkImgOrig)."'
            srcset='".$this->__linkImg(768,'jpg',$linkImgOrig)."'>
         <source media='(max-width: 1200px)' type='image/webp'
            data-srcset='".$this->__linkImg(1200,'webp',$linkImgOrig)."'
            srcset='".$this->__linkImg(1200,'webp',$linkImgOrig)."'>
         <source media='(max-width: 1200px)' type='image/jpeg'
            data-srcset='".$this->__linkImg(1200,'jpg',$linkImgOrig)."'
            srcset='".$this->__linkImg(1200,'jpg',$linkImgOrig)."'>
         <source media='(min-width: 1201px)' type='image/webp'
            data-srcset='".$this->__linkImg(1201,'webp',$linkImgOrig)."'
            srcset='".$this->__linkImg(1201,'webp',$linkImgOrig)."'>
         <source media='(min-width: 1201px)' type='image/jpeg'
            data-srcset='".$this->__linkImg(1201,'jpg',$linkImgOrig)."'
            srcset='".$this->__linkImg(1201,'jpg',$linkImgOrig)."'>
         <source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\">
         <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\">
         <img data-src='".$linkImg."' alt=\"".$dscImg."\" src='".$linkImg."' class='w-100 border-radius-2 lazyload' />
      </picture></div>";

      return $html;
   }

   /**
   * @brief Retorna la vista del video
   * @return Retorna la vista del video
   */
   public function __getVistaHeadVisualVideo() {
      $html = $this->obtenirVideo()->mostrarVideo();
      return $html;
   }

   /**
   * @brief Mostra la informació del titol d'un curs tal «INFORMACIO»
   * @return Mostra la informació del titol d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   public function mostrarTitolInfo() {

      $html = "<div class='info-titol' id='titol'>";
      $html .= "<h1>".$this->obtenirTitol()->obtenirTextHTML()."</h1>";

      if ($this->__isMobile() || $vista == "2")
         $html .= $this->__getVistaHeadTitleMobile();
      else {
         $html .= $this->__getVistaHeadTitle();
      }

      $html .= "</div>";
      return $html;
   }

   /**
   * @brief Mostra la imatge allargada d'un curs tal «INFORMACIO»
   * @return Mostra la imatge allargada d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   public function mostrarImgLargeInfo() {
      if ( !$this->__isMobile() || ($this->__isMobile() && $this->obtenirVideo()== null) )
         $html = $this->__getVistaHeadVisualImg();
      else
         $html = $this->__getVistaHeadVisualVideo();
      return $html;
   }

   private function __vistaApartats($vista) {
      $html .= $this->__mostrarDescripcio($vista);
      $html .= $this->__mostrarPrograma($vista);
      $html .= $this->__mostrarDocents($vista);
      $html .= $this->__mostrarEdicio($vista);
      $html .= $this->__mostrarPreu($vista);
      return $html;
   }

   private function __getVistaContingut() {
      $html = "<div class='v_ord'>";
      $html .= "<ul class='nav nav-tabs nav-justified info-descripcio-nav taller' role='tablist' aria-label='Contingut del curs'>";
      for ( $i = 0; $i < count($this->apartats); $i++ ) {
         $classActive = '';
         if ( $i == 0 ) $classActive = ' active';
         $html .= "<li role='tab'><a class='text-center negreta500".$classActive."'
            href='#".$this->apartats[$i][0]."' data-toggle='tab'
            aria-controls='".$this->apartats[$i][0]."'
            id='tab-".$this->apartats[$i][0]."'>
            <i class='".$this->apartats[$i][1]."'></i>".$this->apartats[$i][2]."
         </a></li>";
      }
      $html .= "</ul><div class='tab-content info-descripcio-tab'>";
      $html .= $this->__vistaApartats("1");
      $html .= "</div>
      <div class='d-flex justify-content-between'>
      <p><a role='link' class='color-text' href='https://www.prisma.cat/cursos' target='_self' title='Visualitza tots els cursos'>";
      $html .= "<i class='fas fa-long-arrow-alt-left mr-2'></i>Mostra tots els cursos</a></p>";
      $html .= "<p><a id='torna' class='color-text' href='#' role='link' title='Torna a dalt'>";
      $html .= "<i class='fas fa-long-arrow-alt-up mr-2'></i>Torna a dalt</a></p></div></div>";

      return $html;
   }

   private function __getVistaContingutMobile() {
      $html .= "<div id='accord' class='v_mob d-flex flex-column'>
      <div class='panel-group' id='accordion' role='tablist' aria-multiselectable='true'>";
      $html .= $this->__vistaApartats("2");
      $html .= "</div><p><a role='link' class='color-text' target='_self' ";
      $html .= "href='https://www.prisma.cat/cursos' title='Visualitza tots els cursos'>";
      $html .= "<i class='fas fa-long-arrow-alt-left mr-2'></i>Mostra tots els cursos</a></p></div>";
      return $html;
   }

   /**
   * @brief Mostra la informació d'un curs tal «INFORMACIO»
   * @return Mostra la informació d'un curs tal i com es mostra a la pestanya d'Informació d'un curs
   */
   public function mostrarInformacioInfo() {
      $html .= $this->__getVistaContingut();
      $html .= $this->__getVistaContingutMobile();
      return $html;
   }

   /**
   * @brief xxx
   * @return xxx
   */
   public function __mostrarEdicio( $vista) {
      require_once 'Date.php';
      $objDateI = new Date( $this->dataI );

      if ( $this->__exProxEdicio() ) {
         $textEdicio .= "<h3>Data</h3>";
         $textEdicio .= "<p>El taller es realitzarà ".$objDateI->getPronomEl()."<span class='font-weight-bold'>".$objDateI->getDataLlarga()."</span> de 10 a 14 h</p>";
         $textEdicio .= "<h3>Lloc</h3>";
         $textEdicio .= $this->lloc;
         $textEdicio .= $this->comarribar;
      }
      else {
         $textEdicio .= "<p>Actualment no hi ha cap edició disponible.</p>";
         $textEdicio .= "<p>Si sou un grup de <span class='font-weight-bold'>10 persones o més</span> i voleu gaudir d’una edició privada d’aquest taller, <a href='https://www.prisma.cat/contacte' target='_blank' class='font-weight-bold'>contacteu amb nosaltres</a>! Junts trobarem la millor data, lloc i horari perquè pugueu explorar, millorar i protegir la vostra veu en un ambient pràctic i dinàmic.</p>";
      }

      if ( $vista == "1" ) {
         $html="<div class='tab-pane fade' id='edicio' role='tabpanel' aria-labelledby='tab-edicio'>";
         $html.="<div class='info-descripcio-tab-left pb-2'><h2>Data i lloc</h2>";
         $html.="<div>".$textEdicio."</div></div></div>";
      }
      else {
         $html.="<div class='panel panel-default'><div class='panel-heading collapsed' id='heading-edicio' ";
         $html.="role='tab' data-toggle='collapse' data-target='#collapse-edicio' href='#collapse-edicio' ";
         $html.="aria-expanded='false' aria-controls='collapse-edicio' onclick='canviarEstat(\"edicio\")'>";
         $html.="<p class='panel-title d-flex flex-wrap align-items-center font-weight-bold'><i class='fa-regular fa-calendar-days mr-2'></i><span class='flex-1-0-auto'>Data i lloc</span><i class='fas fa-plus ml-2'></i></p>";
         $html.="</div><div id='collapse-edicio' class='panel-collapse collapse' role='tabpanel' ";
         $html.="aria-labelledby='heading-edicio' aria-expanded='false' style='height: 0px;'>";
         $html.="<div class='panel-body border-0 edicio'><h2>Data i lloc</h2>".$textEdicio."</div>";
         $html.="<div class='clear-both'></div></div></div>";
      }

      return $html;
   }

   /**
   * @brief Mostra la info del preu i la forma de pagament
   * @return Mostra la info del preu i la forma de pagament segons la vista
   */
   public function __mostrarPreu( $vista ) {
      $preu = $this->__getPreu();

      $textPreu ="<p class='mb-4'><strong class='preu-normal px-2 py-1'>".$preu." €</strong></p>";
      $textPreu .= $this->getTextInfoPag();

      if ( $vista == "1" ) {
         $html="<div class='tab-pane fade' id='preu' role='tabpanel' aria-labelledby='tab-preu'>";
         $html.="<div class='info-descripcio-tab-left pb-2'><h2>Preu</h2>";
         $html.="<div>".$textPreu."</div></div></div>";
      }
      else {
         $html.="<div class='panel panel-default'><div class='panel-heading collapsed' id='heading-preu' ";
         $html.="role='tab' data-toggle='collapse' data-target='#collapse-preu' href='#collapse-preu' ";
         $html.="aria-expanded='false' aria-controls='collapse-preu' onclick='canviarEstat(\"preu\")'>";
         $html.="<p class='panel-title d-flex flex-wrap align-items-center font-weight-bold'><i class='fa fa-credit-card mr-2'></i><span class='flex-1-0-auto'>Preu</span><i class='fas fa-plus ml-2'></i></p>";
         $html.="</div><div id='collapse-preu' class='panel-collapse collapse' role='tabpanel' ";
         $html.="aria-labelledby='heading-preu' aria-expanded='false' style='height: 0px;'>";
         $html.="<div class='panel-body border-0 preu'><h2>Preu</h2>".$textPreu."</div>";
         $html.="<div class='clear-both'></div></div></div>";
      }

      return $html;
   }

   /**
   * @brief xxx
   * @return xxx
   */
   public function mostrarMesInformacio() {
      $codiCursMin=$this->obtenirCodi()->convertirMin();

      $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

      $urlInsc="https://www.prisma.cat/inscripcions/tallers/".$extUrl;
      $urlComp="https%3A%2F%2Fwww.prisma.cat%2Fcursos%2F".$extUrl;
      $textTw=$this->obtenirTitol()->replace(' ','%20');

      $html = '';

      $html="<div class='compartir modal fade' id='compartir_".$codiCursMin."' role='dialog'>";
      $html.="<div class='modal-dialog modal-dialog-centered'><div class='modal-content w-100'>";
      $html.="<div class='modal-header'><p class='modal-title float-left font-weight-bold'>";
      $html.="Comparteix l'enllaç d'aquest curs</p><button role='button' type='button' ";
      $html.="class='modal-close position-absolute border-0' data-dismiss='modal' aria-label='Close'>";
      $html.="<span aria-hidden='true'>×</span></button></div><div class='modal-body'>";
      $html.="<div class='icones d-flex justify-content-around text-center py-4'>";
      /*Botó Facebook */
      $html.="<a id='fb' rel='noreferrer' class='color-text' ";
      $html.="onclick=\"window.open('http://www.facebook.com/sharer.php?u=".$urlComp."',";
      $html.="'ventanacompartir', 'toolbar=0, status=0, width=650, height=450');\">";
      $html.="<img src='https://www.prisma.cat/img/social/share_facebook.png'><p class='mt-2'>Facebook</p></a>";
      /*Twitter */
      $html.="<a id='tw' rel='noreferrer' class='color-text' target='_blank' ";
      $html.="href=\"http://twitter.com/home?status=".$textTw."%20".$urlComp."\">";
      $html.="<img src='https://www.prisma.cat/img/social/share_twitter.png'><p class='mt-2'>Twitter</p></a>";
      /*Botó Whatsapp */
      $html.="<a id='ws' rel='noreferrer' class='color-text' target='_blank' ";
      $html.="href=\"https://api.whatsapp.com/send?text=".$urlComp."\" data-action='share/whatsapp/share'>";
      $html.="<img src='https://www.prisma.cat/img/social/share_whatsapp.png'><p class='mt-2'>Whatsapp</p></a>";
      $html.="</div>";
      /*Input amb la url per poder copiar-lo */
      $html.="<div class='cnt-copy d-flex w-100'><input id='textCopiar' class='flex-1-0-auto' type='text' value='".$linkCurs."'/>";
      $html.="<button id='btnCopiar' class='float-right font-weight-bold position-relative text-center' ";
      $html.="onclick='copiarAlPortapapeles()'>COPIAR</button></div>";
      $html.="<div id='alerta' class='alert text-center invisible'></div><div style='clear: both;'></div>";
      $html.="</div></div></div></div>";

      $html.="<div class='theiaStickySidebar w-100 position-absolute'>";
      $html.="<div class='more-info' id='mes_informacio'>";
      $classEl="negreta500";

      $html.="<h2 class='h1 subtitle border-radius-2 mt-0 mt-lg-5'>Més informació</h2><ul>";
      $html.=$this->__getHTMLHores();
      $html.=$this->__getHTMLNivells();
      $html.=$this->__getHTMLMod();
      $html.=$this->__getHTMLSeu();

      $html.="<button role='button' class='inscripcio border-0 border-radius-2 ";
      $html.="text-white text-center position-relative negreta500' ";
      if ( $this->__exProxEdicio() == 0 && !$inscripcioOberta) {
         $html.="title=\"Inscripcions no disponibles\"><i class='fas fa-edit'></i>";
         $html.="Inscripció no disponible</button>";
      }
      else {
         $html.="onclick=\"location.href='".$urlInsc."'\" title=\"Inscriu-te al curs ";
         $html.=$this->obtenirTitol()->obtenirText()."\"><i class='fas fa-edit'></i>";
         $html.="Inscripció</button>";
      }

      $html .= $this->getHTMLCopiarCurs();

      return $html;
   }

   /**
   * @brief xxx
   * @return xxx
   */
   private function __getHTMLMod() {
      $html.="<li class='d-flex flex-row align-items-center'>
      <i class='".$this->mesInfo[2][0]."'></i>
      <h3>".$this->mesInfo[2][1]."</h3>";
      $html.="<span class='negreta500'>Presencial</span></li>";
      return $html;
   }

   /**
   * @brief xxx
   * @return xxx
   */
   private function __getHTMLSeu() {
      $html.="<li class='d-flex flex-row align-items-center'>
      <i class='".$this->mesInfo[3][0]."'></i>
      <h3>".$this->mesInfo[3][1]."</h3>";
      $html.="<span class='negreta500'>".$this->__getSeu()."</span></li>";
      return $html;
   }

   /**
   * @brief xxx
   * @return xxx
   */
   private function getHTMLCopiarCurs() {
      $codiCursMin=$this->obtenirCodi()->convertirMin();
      $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];
      $urlComp="https%3A%2F%2Fwww.prisma.cat%2Fcursos%2F".$extUrl;
      $textTw=$this->obtenirTitol()->replace(' ','%20');

      $html = "<div class='v_mob icones d-flex flex-row justify-content-between w-100 py-3'>";
      /*Botó copiar link */
      $html.="<a id='cp' rel='noreferrer' class='color-text' ";
      $html.="onclick=\"copiarAlPortapapeles()\">";
      $html.="<input id='textCopiar' type='hidden' value='".$linkCurs."'/>";
      $html.="<i class='fas fa-copy'></i></a>";
      /*Botó Whatsapp */
      $html.="<a id='ws' rel='noreferrer' class='color-text' target='_blank' ";
      $html.="rel='noopener' href=\"https://api.whatsapp.com/send?text=".$urlComp."\" ";
      $html.="data-action='share/whatsapp/share'><i class='fab fa-whatsapp'></i></a>";
      /*Twitter */
      $html.="<a id='tw' rel='noreferrer' class='color-text' target='_blank' ";
      $html.="rel='noopener' href=\"http://twitter.com/home?status=".$textTw."%20".$urlComp."\">";
      $html.="<i class='fab fa-twitter'></i></a>";
      /*Botó Facebook */
      $html.="<a id='fb' rel='noreferrer' class='color-text' ";
      $html.="onclick=\"window.open('http://www.facebook.com/sharer.php?u=".$urlComp."',";
      $html.="'ventanacompartir', 'toolbar=0, status=0, width=650, height=450');\">";
      $html.="<i class='fab fa-facebook-square'></i></a>";
      /*Botó enviar per mail */
      $html.="<a id='ml' rel='noreferrer' class='color-text' target='_blank' ";
      $html.="rel='noopener' href=\"mailto:?Subject=Curs%20".$this->obtenirTitol()->obtenirText()."\">";
      $html.="<i class='fas fa-envelope'></i></a>";
      $html.="</div><p class='v_mob text-center comparteix'>Comparteix aquest curs</p>";

      $html.="<span class='v_ord' data-toggle='modal' data-target='#compartir_".$codiCursMin."'>";
      $html.="<button role='button' id='boto_compartir_".$codiCursMin."' class='comparteix negreta500 ";
      $html.="text-center border-0' aria-label='Comparteix aquest curs' type='button' ";
      $html.="data-html='true' title=\"Comparteix el curs ".$this->obtenirTitol()->obtenirText()."\" ";
      $html.="data-target='#compartir_".$codiCursMin."' data-placement='bottom'><i class='fas fa-share'></i>";
      $html.="Comparteix aquest curs</button></span></div></div></div>";

      return $html;
   }

   /* ######################################################################### */
   /* ###################### FUNCIONS CONSULTAR ATRIBUTS ###################### */
   /* ######################################################################### */

   private function __getSubtitle() {
      return $this->subTitolPage;
   }

   private function __getSeu() {
      return $this->seu->obtenirText();
   }

   private function __linkImg($maxwidth, $extension, $linkImgOrig) {
   	$mides = ["400", "700", "750", ""];

   	if ( $maxwidth == 576) $mida = $mides[0];
   	else if (  $maxwidth == 768 ) $mida = $mides[1];
   	else if (  $maxwidth == 1200 ) $mida = $mides[2];
   	else if (  $maxwidth == 1201 ) $mida = $mides[4];

   	if ( $mida != '' ) $mida = "-".$mida;

   	$link = $linkImgOrig.$mida.".".$extension;

   	return $link;

   }

   /**
   * @brief Retorna el preu
   * @return Retorna el preu
   */
   public function __getPreu() {
      $idPreu=$this->obtenirPreu()->obtenirNumero();
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $stmt = $connexio->prepare( $this->cons["cnsPreus"] );
      $stmt->bind_param("d", $idPreu);
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows() > 0) {
         $stmt->bind_result($import);
         $stmt->fetch();
         if ( $import > 0 ) {
            require_once 'Numero.php';
            $objImp = new Numero($import);
            $importFormatCorrecte = $objImp->mostrarNumeroDecimalsSense0();
         }
         else {
            $importFormatCorrecte = "Gratuït";
         }
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();
      return $importFormatCorrecte;
   }

   /**
   * @brief Retorna la info de pagament segur i la forma de pagament
   * @return Retorna la info de pagament segur i la forma de pagament
   */
   public function getTextInfoPag() {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $text = "";

      //Buscar si hi ha el text corresponet si està actiu
      $stmt = $connexio->prepare( $this->cons["cnsParam"] );
      $stmt->bind_param("s", $tipus);
      $tipus='info-places-limitades-taller'; //si el tipus és text de la forma de pagament
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows() > 0) {
         $stmt->bind_result($valor);
         $stmt->fetch();
         $text.=$valor;
      }
      $tipus='info-forma-pagament'; //si el tipus és text de la forma de pagament
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows() > 0) {
         $stmt->bind_result($valor);
         $stmt->fetch();
         $text.=$valor;
      }
      $tipus='info-pagament-segur'; //si el tipus és del pagament segur
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows() > 0) {
         $stmt->bind_result($valor);
         $stmt->fetch();
         $text.=$valor;
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();

      return $text;
   }

   /**
   * @brief  Indica si hi ha pròxima edició
   * @return Indica si hi ha pròxima edició
   */
   public function __exProxEdicio() {
      $existeix = 1;
      return $existeix;
   }

   /* ######################################################################### */
   /* ###################### FUNCIONS MODIFICAR ATRIBUTS ###################### */
   /* ######################################################################### */

   /**
   * @brief xxx
   * @return xxx
   */
   // public function xxxx() {
   //    //
   // }


}

?>
