<?php
/**
   * @class Filtres
   * @brief Conté  els filtres
*/
class Filtres {

   private $hores; /**< Array Hores disponibles dels cursos */
   private $temes; /**< Array Temàtiques disponibles dels cursos */
   private $nivells; /**< Array Nivells disponibles dels cursos */
   private $edicions; /**< Array Edicions disponibles dels cursos */
   private $altres; /**< Array */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /**
   * @brief Constructor de la classe.
   * @return S'han creat els filtres
   */
   public function __construct() {
      $this->hores=[];
      $this->temes=[];
      $this->nivells=[];
      $this->edicions=[];
      $this->altres=[];

      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

      /* Busco les hores que existeixen */
      $cnsHores="SELECT HORES FROM curs as c WHERE GTAF IS NOT NULL AND GTAF!='' AND
         (DATAI + 32 > CURRENT_DATE)
         AND CURS NOT LIKE '%JOR%' AND CURS NOT LIKE '%0%' AND c.ESTAT != '0' AND c.PUBLIC = 1
         GROUP BY HORES ORDER BY HORES";
      $stmt = $connexio->prepare($cnsHores);
      $stmt->execute();
      $stmt->bind_result($hores);
      $cnt = 0;
      while ($stmt->fetch()) {
         $this->hores[$cnt] = $hores;
         $cnt++;
      }
      $connexio->closeStmt();

      /* Busco els temes que existeixen */
      $cnsTemes='SELECT ID FROM temes WHERE ESTAT = 1 ORDER BY ORDRE';
      $stmt = $connexio->prepare($cnsTemes);
      $stmt->execute();
      $stmt->bind_result($idTema);
      $cnt = 0;
      while ($stmt->fetch()) {
         $this->temes[$cnt] = $idTema;
         $cnt++;
      }
      $connexio->closeStmt();

      /* Busco els nivells que existeixen */
      $cnsNivells='SELECT ID FROM nivells GROUP BY NOM ORDER BY ORDRE';
      $stmt = $connexio->prepare($cnsNivells);
      $stmt->execute();
      $stmt->bind_result($idNivell);
      $cnt = 0;
      while ($stmt->fetch()) {
         $this->nivells[$cnt] = $idNivell;
         $cnt++;
      }
      $connexio->closeStmt();

      /* Busco el limit d'edicions a mostrar */
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='limit-editions';
      $orderBy='DATAI';
      $stmtParam->execute();
      $stmtParam->bind_result($limitEd);
      $stmtParam->fetch();

      /* Busco els dies que poden estar obert els cursos després de la data d'inscripció */
      $tipus='dies-inscriu-cursos';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $diesOberts=[];
      $pos=0;
      while ($stmtParam->fetch()) {
         $valors = explode('|',$valor);
         if (count($valors) != 2)
            throw new Exception('',714);
         else {
            $cnt = 0;
            while ( $cnt < count($diesOberts) && $diesOberts[$cnt]!=$valors[1])
               $cnt++;
            if ($cnt==count($diesOberts)) {
               $diesOberts[$pos] = $valors[1];
               $pos++;
            }
         }

      }
      $connexio->closeStmt();

      $dateDifss="";
      for ($i=0; $i<count($diesOberts); $i++) {
         if ($i>0) $dateDifss.=" OR ";
         $dateDifss.="DATEDIFF(DATAI + ".$diesOberts[$i].",CURRENT_DATE)>0";
      }

      // /* Busco les edicions disponibles */
      $cnsEd = "SELECT MES, ANY FROM curs AS c INNER JOIN aula AS a ON
         c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
         INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE PUBLIC=1 AND
         c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1
         AND (".$dateDifss.") AND c.CURS NOT LIKE '%JOR%' AND
         (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
         OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
         AND ORDRE_TUTOR=1)) GROUP BY ANY, MES ORDER BY ANY, MES LIMIT 12";
      $stmtEd = $connexio->prepare($cnsEd);
      // $stmtEd->bind_param("d", $limitEd);
      $stmtEd->execute();
      $stmtEd->bind_result($mesEd, $anyEd);
      $cntEd = 0;
      require_once 'Text.php';
      while ( $stmtEd->fetch() && $cntEd < $limitEd ){
         $this->edicions[$cntEd] = new Text($mesEd."|".$anyEd);
         $cntEd++;
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();
   }

   public function assignarEdicionsEstiu() {
      $this->edicions=[];

      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

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
         if (count($valors) != 2)
            throw new Exception('',714);
         else {
            $cnt = 0;
            while ( $cnt < count($diesOberts) && $diesOberts[$cnt]!=$valors[1])
               $cnt++;
            if ($cnt==count($diesOberts)) {
               $diesOberts[$pos] = $valors[1];
               $pos++;
            }
         }

      }
      $connexio->closeStmt();

      $dateDifss="";
      for ($i=0; $i<count($diesOberts); $i++) {
         if ($i>0) $dateDifss.=" OR ";
         // $dateDifss.="DATEDIFF(DATAI + ".$diesOberts[$i].",CURRENT_DATE)>0";
         $dateDifss.="DATEDIFF(DATE_ADD(DATAI, INTERVAL ".$diesOberts[$i]." DAY),CURRENT_DATE)>0";
      }

      // /* Busco les edicions disponibles */
      $cnsEd = "SELECT MES, ANY FROM curs AS c INNER JOIN aula AS a ON
         c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
         INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE PUBLIC=1 AND
         c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1
         AND (c.MES = '07' OR c.MES = '08') AND c.ANY >= ?
         AND (".$dateDifss.") AND c.CURS NOT LIKE '%JOR%' AND
         (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
         OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
         AND ORDRE_TUTOR=1)) GROUP BY ANY, MES ORDER BY ANY, MES LIMIT 12";
      $stmtEd = $connexio->prepare($cnsEd);
      $stmtEd->bind_param("d", $anyActual);
      $anyActual = date("Y");
      $stmtEd->execute();
      $stmtEd->bind_result($mesEd, $anyEd);
      $cntEd = 0;
      require_once 'Text.php';
      while ( $stmtEd->fetch() ){
         $this->edicions[$cntEd] = new Text($mesEd."|".$anyEd);
         $cntEd++;
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();
   }

   public function assignarEdicionsEscolaEstiu() {
      $this->edicions=[];

      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

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
         if (count($valors) != 2)
            throw new Exception('',714);
         else {
            $cnt = 0;
            while ( $cnt < count($diesOberts) && $diesOberts[$cnt]!=$valors[1])
               $cnt++;
            if ($cnt==count($diesOberts)) {
               $diesOberts[$pos] = $valors[1];
               $pos++;
            }
         }

      }
      $connexio->closeStmt();

      $dateDifss="";
      for ($i=0; $i<count($diesOberts); $i++) {
         if ($i>0) $dateDifss.=" OR ";
         // $dateDifss.="DATEDIFF(DATAI + ".$diesOberts[$i].",CURRENT_DATE)>0";
         $dateDifss.="DATEDIFF(DATE_ADD(DATAI, INTERVAL ".$diesOberts[$i]." DAY),CURRENT_DATE)>0";
      }

      // /* Busco les edicions disponibles */
      $cnsEd = "SELECT MES, ANY FROM curs AS c INNER JOIN aula AS a ON
         c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
         INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE PUBLIC=1 AND
         c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1
         AND (c.MES = '07' OR c.MES = '08') AND c.ANY >= ?
         AND (".$dateDifss.") AND c.CURS NOT LIKE '%JOR%' AND
         (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
         OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
         AND ORDRE_TUTOR=1)) GROUP BY ANY, MES ORDER BY ANY, MES LIMIT 12";
      $stmtEd = $connexio->prepare($cnsEd);
      $stmtEd->bind_param("d", $anyActual);
      $anyActual = date("Y");
      $stmtEd->execute();
      $stmtEd->bind_result($mesEd, $anyEd);
      $cntEd = 0;
      require_once 'Text.php';
      while ( $stmtEd->fetch() ){
         $this->edicions[$cntEd] = new Text($mesEd."|".$anyEd);
         $cntEd++;
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /**
   * @brief Mostrar els filtres laterals de durada, tematiques, nivells en la versio ordinador
   * @return Mostrar els filtres laterals de durada, tematiques, nivells i
   * els filtres superiors de ordena i del filtre d'edicions
   */
   public function mostrarFiltresLaterals() {
      $objTxt = new Text("PACKS");
      $txt = $objTxt->obtenirTextHTML();
      $objTxt2 = new Text("CDD");
      $txtCDD = $objTxt2->obtenirTextHTML();
      $objTxt4 = new Text("MIXTOS");
      $txtMixtos = $objTxt4->obtenirTextHTML();
      $objTxt3 = new Text("SUBVENCIONATS");
      $txtSubv = $objTxt3->obtenirTextHTML();

      /* Versió ordinador */
      $mostrar="<div class='sticky-sidebar position-relative'>
      <div class='theiaStickySidebar w-100 h-100 position-absolute'>
      <div id='filtres' class='ps2'>
      <h3>Durada </h3>".$this->__mostrarFiltresHores();
      $mostrar.="<h3>Temàtiques </h3>".$this->__mostrarFiltresTematiques();
      $mostrar.="<h3>Nivells </h3>".$this->__mostrarFiltresNivells();
      /*$mostrar.="<h3>Altres </h3>";
      $mostrar .= "<label>
        <input type='checkbox' class='altres' id='edicions_totes' value='1'>
        <span class='checkmark border'></span>
        <span>Incloure cursos només estiu</span>
      </label>";*/

      $mostrar.="<button href='#' class='eliminar-filtres position-relative text-white border-0 border-radius-2 w-100'>ELIMINAR FILTRES</button>
      </div></div></div>";

      $nousBotons = "";
      /* Existeixen novetats */
      if ( $this->exCursNou() == '1' )
         $nousBotons .= "<div class='novetats boto-header ml-0 mr-0 mt-0 mb-0 w-auto px-2 border-0 ml-2'>NOVETATS!</div>";
      /* Existeixen pcks */
      //$nousBotons .= "<div class='packs boto-header text-white ml-2 mr-0 mt-0 mb-0 w-auto px-2 border-0'>".$txt."</div>";
      // $nousBotons .= "<div class='subvencio boto-header text-white ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtSubv."</div>";
      /* Existeixen cursos amb cdd */
      if ( $this->exCursCdd() == '1' )
         $nousBotons .= "<div class='cdd boto-header text-white ml-2 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtCDD."</div>";
      //$nousBotons .= "<div class='mixtos boto-header text-white ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtMixtos."</div>";

      $mostrar.="<div class='filter-capcalera'><div class='cnt-filter-button flex-1-0-auto'>
      <button class='show-filters border-0'><i class='fas fa-bars'></i></button></div>
      ".$nousBotons."
      </div>";

      $mostrar.="<div id='filtres-mobil' class='w-100 h-100 position-fixed'>
      <h3>Ordena per </h3>
      <div class='filtres-superior d-flex'>
         <div id='ordreMov' class='filtres d-flex flex-column justify-content-center text-center w-100' style='z-index: 20; max-width: inherit;'>
         <span class='element-selected text-center'>Alfabèticament A-Z</span>
         <ul class='select-list' style='display: none;'>".$this->__mostrarFiltresOrdre()."</ul>
         <i class='fa fa-angle-down triangle-inferior'></i></div>
      </div>";

      $mostrar.="<h3>Selecciona l'edició</h3>
      <div class='filtres-superior d-flex'>
         <div id='edicionsMov' class='filtres d-flex flex-column justify-content-center text-center w-100' style='max-width: inherit;'>
         <span class='element-selected'>Qualsevol edició</span>
         <ul class='select-list' style='display: none;'>".$this->__mostrarFiltresEdicions()."</ul>
         <i class='fa fa-angle-down triangle-inferior'></i></div>
      </div>";

      $mostrar.="<h3>Durada </h3>".$this->__mostrarFiltresHores();
      $mostrar.="<h3>Temàtiques </h3>".$this->__mostrarFiltresTematiques();
      $mostrar.="<h3>Nivells </h3>".$this->__mostrarFiltresNivells();
      /*$mostrar.="<h3>Altres </h3>";
      $mostrar .= "<label>
        <input type='checkbox' class='altres' id='edicions_totes' value='1'>
        <span class='checkmark border'></span>
        <span>Incloure cursos només estiu</span>
      </label>";*/
      $mostrar.="
      <div class='d-flex'>
         <button href='#' class='eliminar-filtres position-relative text-white border-0 border-radius-2 w-100'>ELIMINAR FILTRES</button>
         <button href='#' class='aplicar-filtres position-relative text-white border-0 border-radius-2 w-100'>APLICAR FILTRES</button>
      </div>
      </div>";

      return $mostrar;
   }

   /**
   * @brief Mostra els filtres superiors si $midaPantalla >= $midaMin2
   * @return Mostra els filtres superiors d'ordena i del filtre d'edicions
   */
   public function mostrarFiltresSuperiors() {
      $objTxt = new Text('PACKS');
      $txt = $objTxt->obtenirTextHTML();
      $objTxt2 = new Text("CDD");
      $txtCDD = $objTxt2->obtenirTextHTML();
      $objTxt4 = new Text("MIXTOS");
      $txtMixtos = $objTxt4->obtenirTextHTML();
      $objTxt3 = new Text("SUBVENCIONATS");
      $txtSubv = $objTxt3->obtenirTextHTML();

      $nousBotons = "";
      /* Existeixen novetats */
      if ( $this->exCursNou() == '1' )
         $nousBotons .= "<button href='#' class='novetats boto-header ml-2 mr-0 mt-0 mb-0 w-auto px-2 border-0'>NOVETATS!</button>";
      /* Existeixen pcks */
      $nousBotons .= "<a href='https://www.prisma.cat/packs' class='packs boto-header text-white ml-2 mr-0 mt-0 mb-0 w-auto px-2 pt-2 border-0'>".$txt."</a>";

      // $nousBotons .= "<button href='#' class='subvencio boto-header text-white ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtSubv."</button>";
      if ( $this->exCursCdd() == '1' )
         $nousBotons .= "<button href='#' class='cdd boto-header text-white ml-2 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtCDD."</button>";
      //$nousBotons .= "<button href='#' class='mixtos boto-header text-white ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtMixtos."</button>";

      $mostrar.="<div class='filtres-superior d-flex text-right'>
      ".$nousBotons."
      <div id='ordre' class='filtres d-flex flex-column justify-content-center text-center mr-2'>
      <span class='element-selected text-center'>Ordena per</span>
      <ul class='select-list' style='display: none;'>";
      $mostrar.=$this->__mostrarFiltresOrdre();
      $mostrar.="</ul><i class='fa fa-angle-down triangle-inferior'></i></div>
      <div id='edicions' class='filtres d-flex flex-column justify-content-center text-center'>
      <span class='element-selected'>Selecciona l'edició</span>
      <ul class='select-list' style='display: none;'>";
      $mostrar.=$this->__mostrarFiltresEdicions();
      $mostrar.="</ul><i class='fa fa-angle-down triangle-inferior'></i></div>
      </div>";
      return $mostrar;
   }

   /**
   * @brief Mostra els filtres superiors si $midaPantalla >= $midaMin2
   * @return Mostra els filtres superiors d'ordena i del filtre d'edicions
   */
   public function mostrarFiltresSuperiorsFixed() {
      $objTxt = new Text('PACKS');
      $txt = $objTxt->obtenirTextHTML();
      $objTxt2 = new Text("CDD");
      $txtCDD = $objTxt2->obtenirTextHTML();
      $objTxt4 = new Text("MIXTOS");
      $txtMixtos = $objTxt4->obtenirTextHTML();
      $objTxt3 = new Text("SUBVENCIONATS");
      $txtSubv = $objTxt3->obtenirTextHTML();

      $nousBotons = "";
      if ( $this->exCursNou() == '1' )
         $nousBotons .= "<div class='novetats boto-header ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0 ml-2'>NOVETATS!</div>";
      /* Existeixen pcks */
      //$nousBotons .= "<div class='packs boto-header text-white ml-2 mr-0 mt-0 mb-0 w-auto px-2 border-0'>".$txt."</div>";
      // $nousBotons .= "<div class='subvencio boto-header text-white ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtSubv."</div>";
      if ( $this->exCursCdd() == '1' )
      $nousBotons .= "<div class='cdd boto-header text-white ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtCDD."</div>";
      //$nousBotons .= "<div class='mixtos boto-header text-white ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtMixtos."</div>";

      //$mostrar.="<div class='filter-capcalera filter-fixed'>
      //   <div class='filter-capcalera'><div class='cnt-filter-button flex-1-0-auto'>
      //      <button class='show-filters border-0'><i class='fas fa-bars'></i></button></div>
      //      <div class='novetats boto-header ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0 ml-2'>NOVETATS!</div>
      //      <div class='packs boto-header text-white ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txt."</div>
      //   </div>
      //</div>";
      $mostrar.="<div class='filter-capcalera filter-fixed'>
         <div class='filter-capcalera'><div class='cnt-filter-button flex-1-0-auto'>
            <button class='show-filters border-0'><i class='fas fa-bars'></i></button></div>
            ".$nousBotons."
         </div>
      </div>";
      return $mostrar;
   }

   function exCursNou() {
      require_once 'Curs.php';
      $existeixCursNou = '0';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $cns="SELECT CODI_CURS FROM informacio as i WHERE i.ESTAT=1 AND TIPUS_CURS != 'R' AND TIPUS_CURS != 'C' GROUP BY CODI_CURS";
      if ( $stmt = $connexio->prepare($cns) ) {
      	$stmt->execute();
      	$stmt->bind_result($codiCurs);
      	while ($stmt->fetch() && $existeixCursNou == '0') {
      		$curs = new Curs($codiCurs, $dispositiu);
      		if ( $curs->obtenirEstat()==1 ) {
      			if ( $curs->__esCursNou() || $curs->etiquetaCursAmbDescompte() != '' ) {
      				$existeixCursNou = '1';
      			}
      		}
      	}
      	$connexio->closeStmt();
      }
      return $existeixCursNou;
   }

   function exCursCdd() {
      require_once 'Curs.php';
      $existeixCursCDD = '0';

      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $cns="SELECT CODI_CURS FROM informacio as i WHERE i.ESTAT=1 AND TIPUS_CURS != 'R' AND TIPUS_CURS != 'C' GROUP BY CODI_CURS";
      if ( $stmt = $connexio->prepare($cns) ) {
      	$stmt->execute();
      	$stmt->bind_result($codiCurs);
      	while ($stmt->fetch() && $existeixCursCDD == '0') {
      		$curs = new Curs($codiCurs, $dispositiu);
      		if ( $curs->obtenirEstat()==1 ) {
      			if ( $curs->esCDD()  ) {
      				$existeixCursCDD = '1';
      			}
      		}
      	}
      	$connexio->closeStmt();
      }
      return $existeixCursCDD;
   }

   /**
   * @brief Mostra els filtres d'hores
   * @return Mostra els filtres d'hores
   */
   private function __mostrarFiltresHores() {
      for ($i=0; $i<count($this->hores); $i++) {
        $h = $this->hores[$i];
         $mostrar .= "<label>
            <input type='checkbox' class='hores' id='hores_".$h."' value='".$h."'>
            <span class='checkmark border'></span>
            <span>".$h." hores</span>
         </label>";
      }
      return $mostrar;
   }
   /**
   * @brief Mostra els filtres de tematiques
   * @return Mostra els filtres de tematiques
   */
   private function __mostrarFiltresTematiques() {
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();
      for ($i=0; $i<count($this->temes); $i++) {
         $idTemaActual = $this->temes[$i];

         $cns='SELECT NOM FROM temes WHERE ID=?';
         $stmt = $connexio->prepare($cns);
         $stmt->bind_param("d", $idTemaActual);
         $stmt->execute();
         $stmt->bind_result($tema);
         $stmt->fetch();
         $connexio->closeStmt();

         $textTema = new Text($tema);
         $textTema->obtenirNomCurt();
         $temaNCurt = $textTema->obtenirText();

         $mostrar .= "<label>
         <input type='checkbox' class='temes' id='tema_".$temaNCurt."' value='".$idTemaActual."|".$temaNCurt."'>
         <span class='checkmark border'></span>
         <span>".$tema."</span>
         </label>";
      }
      $connexio->desconectarBD();
      return $mostrar;
   }
   /**
   * @brief Mostra els filtres de nivells
   * @return Mostra els filtres de nivells
   */
   private function __mostrarFiltresNivells() {
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();
      for ($i=0; $i<count($this->nivells); $i++) {
         $idNivellActual = $this->nivells[$i];

         $cns='SELECT NOM FROM nivells WHERE ID=?';
         $stmt = $connexio->prepare($cns);
         $stmt->bind_param("d", $idNivellActual);
         $stmt->execute();
         $stmt->bind_result($nivell);
         $stmt->fetch();
         $connexio->closeStmt();

         $textNivell = new Text($nivell);
         $textNivell->obtenirNomCurt();
         $nivellNCurt = $textNivell->obtenirText();

         $mostrar .= "<label>
         <input type='checkbox' class='nivells' id='tema_".$nivellNCurt."' value='".$idNivellActual."|".$nivellNCurt."'>
         <span class='checkmark border'></span>
         <span>".$nivell."</span>
         </label>";
      }
      $connexio->desconectarBD();
      return $mostrar;
   }
   /**
   * @brief Mostra els filtres d'ordenacions
   * @return Mostra els filtres d'ordenacions
   */
   private function __mostrarFiltresOrdre() {
      $mostrar.="<li class='border-bottom ordre' id='ordre|TITOL|ASC'><a href='#'>Alfabèticament A-Z</a></li>
      <li class='border-bottom ordre' id='ordre|TITOL|DESC'><a href='#'>Alfabèticament Z-A</a></li>
      <li class='border-bottom ordre' id='ordre|ANY,MES|ASC'><a href='#'>Més recents</a></li>";
      return $mostrar;
   }
   /**
   * @brief Mostra els filtres d'edicions
   * @return Mostra els filtres d'edicions
   */
   private function __mostrarFiltresEdicions() {
      for ($i=0; $i<count($this->edicions); $i++) {
         $edi = $this->edicions[$i]->obtenirText();
         $ed=explode('|',$edi);
         $edicions.="<li class='border-bottom edicio ed_".$ed[0]."_".$ed[1]."' id='ed|".$ed[0]."|".$ed[1]."'><a href='#'>";
         $textMes=new Text($ed[0]);
         $edi=$textMes->obtenirMesLlarg();
         $textMes=new Text($edi);
         $edicions.=$textMes->convertirMajPrimLletra();
         $edicions.="</a></li>";
      }
      $mostrar = "<li class='border-bottom edicio' id='ed|00|2020'><a href='#'>";
      $mostrar .= "Qualsevol edició</a></li>".$edicions;
      return $mostrar;
   }

}
?>
