<?php
/**
   * @class Filtres
   * @brief Conté  els filtres
*/
class Filtres {

   private $perfils; /**< Array Perfils disponibles dels cursos */
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
      $this->perfils=[];
      $this->temes=[];
      $this->nivells=[];
      $this->edicions=[];
      $this->altres=[];

      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

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
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS = ? AND
            DATAI<=CURRENT_TIMESTAMP AND (DATAF IS NULL OR CURRENT_TIMESTAMP<=DATAF) ORDER BY ?";
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
         if (count($valors) !== 2 || !is_numeric($valors[1])) {
            throw new Exception('', 714);
         }
         $diesOberts[] = (int)$valors[1];
      }
      $connexio->closeStmt();

      $diesObert = !empty($diesOberts) ? max($diesOberts) : 0;

      /* Busco les edicions disponibles */
      $cnsEd = "SELECT DISTINCT c.MES, c.ANY FROM curs AS c
      INNER JOIN aula AS a ON a.ID_AULA = c.ID_AULA
      INNER JOIN rel_cuho AS r ON r.ID_CUHO = a.ID_CUHO
      INNER JOIN honoraris AS h ON h.ID = r.ID_HONO
      WHERE c.PUBLIC = 1 AND c.CURS <> 'PROVA'
      AND c.CURS NOT LIKE '%0%' AND c.CURS NOT LIKE '%JOR%'
      AND c.ESTAT <> '0' AND r.ACTIU = 1
      AND DATE_ADD(c.DATAI, INTERVAL ? DAY) > CURRENT_DATE
      AND (a.ID_CUHO IN (17, 13) OR ( a.ID_CUHO <> 17 AND a.AULA = 'A'
      OR ( a.ID_CUHO <> 17 AND h.DNI_TUTOR = 'GENERIC' )
      AND h.DNI_TUTOR <> 'GENERIC' AND h.perfil = 'tutor' AND h.ORDRE_TUTOR = 1))
      ORDER BY c.ANY, c.MES LIMIT 12";
      $stmtEd = $connexio->prepare($cnsEd);
      $stmtEd->bind_param("d", $diesObert);
      $stmtEd->execute();
      $stmtEd->bind_result($mesEd, $anyEd);
      $cntEd = 0;
      require_once 'Text.php'; $filtreEdicionsPerfils = '';
      while ( $stmtEd->fetch() && $cntEd < $limitEd ){
         $this->edicions[$cntEd] = new Text($mesEd."|".$anyEd);
         if ( $filtreEdicionsPerfils != '' ) $filtreEdicionsPerfils .= " OR ";
         $filtreEdicionsPerfils .= "(c.ANY = ".$anyEd." AND c.MES = '".$mesEd."')";
         $cntEd++;
      }
      $connexio->closeStmt();

      /* ################################################################################### */
      /* ####  Busco els perfils que existeixen actualment encara que estiguin pendents #### */
      /* ################################################################################### */
      $num_mes_actual = date("m");
      $num_any_actual = date("Y");

      $any_anterior = date("Y") - 1;
      $any_seguent = date("Y") + 1;

      if ($num_mes_actual>=1 && $num_mes_actual<9) $cursEscolar = $any_anterior."/".date("Y");
      else $cursEscolar = date("Y")."/".$any_seguent;

      $perfilsProvisionals = [];

      // consulto els perfils que tenem disponibles aquest  curs escolar.
      $cnsPerfils = 'SELECT ID_PERFIL FROM perfils
         INNER JOIN perfils_list ON perfils_list.ID = perfils.ID_PERFIL
         WHERE curs_escolar LIKE ?
         GROUP BY ID_PERFIL ORDER BY perfils_list.NOM;';
      $stmt = $connexio->prepare($cnsPerfils);
      $stmt->bind_param("s", $cursEscolar);
      $stmt->execute();
      $stmt->store_result();
      $cntProv = 0;
      if ($stmt->num_rows() > 0) {
         $stmt->bind_result($idPerfil);
         while ($stmt->fetch()) {
            $perfilsProvisionals[$cntProv] = $idPerfil;
            $cntProv++;
         }
      }
      else {
         $connexio->closeStmt();
         // si no n'hi ha cap, consulto els perfils que tenim disponibles el curs escolar anterior.
         $cursEscolar = $any_anterior."/".date("Y");
         $cntProv = 0;
         $stmt = $connexio->prepare($cnsPerfils);
         $stmt->bind_param("s", $cursEscolar);
         $stmt->execute();
         $stmt->bind_result($idPerfil);
         while ($stmt->fetch()) {
            $perfilsProvisionals[$cntProv] = $idPerfil;
            $cntProv++;
         }
      }
      $connexio->closeStmt();

      $cnt = 0;
      // reviso si existeix algun curs amb aquest perfil
      $cnsRevisarPerfil = "SELECT DISTINCT p.codi FROM perfils p
      WHERE p.curs_escolar = ? AND p.id_perfil = ? AND EXISTS (
         SELECT 1 FROM informacio i
         INNER JOIN curs c ON c.CURS = i.CODI_CURS
         INNER JOIN filtres f ON f.ID_INFO = i.ID
         WHERE i.CODI_CURS = p.codi
           AND f.DATAI <= NOW() AND (f.DATAF IS NULL OR NOW() <= f.DATAF)
           AND i.ESTAT = 1 AND c.PUBLIC = 1
           AND c.ESTAT NOT IN ('0', 'T') AND i.TIPUS_CURS NOT IN ('T', 'C')
           AND (".$filtreEdicionsPerfils.")
      )";

      $stmt = $connexio->prepare($cnsRevisarPerfil);
      $stmt->bind_param("ss", $cursEscolar, $idPerfil);
      // per cada perfil escolar que tinc
      for ( $i = 0; $i < $cntProv; $i++ ) {
         $idPerfil = $perfilsProvisionals[$i];
         // reviso si existeix algun curs amb aquest perfil
         $stmt->execute();
         $stmt->store_result();
         if ($stmt->num_rows() > 0)  {
            // si existeix algun curs, el guardo a la llista
            $this->perfils[$cnt] = $idPerfil;
            $cnt++;
         }
      }
      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   public function assignarEdicionsEstiu() {
      $this->edicions=[];

      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Busco els dies que poden estar obert els cursos després de la data d'inscripció */
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS = ? AND
            DATAI<=CURRENT_TIMESTAMP AND (DATAF IS NULL OR CURRENT_TIMESTAMP<=DATAF) ORDER BY ?";
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
         if (count($valors) !== 2 || !is_numeric($valors[1])) {
            throw new Exception('', 714);
         }
         $diesOberts[] = (int)$valors[1];
      }
      $connexio->closeStmt();

      $diesObert = !empty($diesOberts) ? max($diesOberts) : 0;

      /* Busco les edicions disponibles */
      $cnsEd = "SELECT DISTINCT c.MES, c.ANY FROM curs AS c
      INNER JOIN aula AS a ON a.ID_AULA = c.ID_AULA
      INNER JOIN rel_cuho AS r ON r.ID_CUHO = a.ID_CUHO
      INNER JOIN honoraris AS h ON h.ID = r.ID_HONO
      WHERE c.PUBLIC = 1 AND c.CURS <> 'PROVA'
      AND DATE_ADD(c.DATAI, INTERVAL ? DAY) > CURRENT_DATE
      AND (c.MES = '07' OR c.MES = '08') AND c.ANY >= ?
      AND c.CURS NOT LIKE '%0%' AND c.CURS NOT LIKE '%JOR%'
      AND c.ESTAT <> '0' AND r.ACTIU = 1
      AND (a.ID_CUHO IN (17, 13) OR ( a.ID_CUHO <> 17 AND a.AULA = 'A'
      OR ( a.ID_CUHO <> 17 AND h.DNI_TUTOR = 'GENERIC' )
      AND h.DNI_TUTOR <> 'GENERIC' AND h.perfil = 'tutor' AND h.ORDRE_TUTOR = 1))
      ORDER BY c.ANY, c.MES LIMIT 12";
      $stmtEd = $connexio->prepare($cnsEd);
      $stmtEd->bind_param("dd", $diesObert, $anyActual);
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
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS = ? AND
            DATAI<=CURRENT_TIMESTAMP AND (DATAF IS NULL OR CURRENT_TIMESTAMP<=DATAF) ORDER BY ?";
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
         if (count($valors) !== 2 || !is_numeric($valors[1])) {
            throw new Exception('', 714);
         }
         $diesOberts[] = (int)$valors[1];
      }
      $connexio->closeStmt();

      $diesObert = !empty($diesOberts) ? max($diesOberts) : 0;

      /* Busco les edicions disponibles */
      $cnsEd = "SELECT DISTINCT c.MES, c.ANY FROM curs AS c
      INNER JOIN aula AS a ON a.ID_AULA = c.ID_AULA
      INNER JOIN rel_cuho AS r ON r.ID_CUHO = a.ID_CUHO
      INNER JOIN honoraris AS h ON h.ID = r.ID_HONO
      WHERE c.PUBLIC = 1 AND c.CURS <> 'PROVA'
      AND DATE_ADD(c.DATAI, INTERVAL ? DAY) > CURRENT_DATE
      AND (c.MES = '07' OR c.MES = '08') AND c.ANY >= ?
      AND c.CURS NOT LIKE '%0%' AND c.CURS NOT LIKE '%JOR%'
      AND c.ESTAT <> '0' AND r.ACTIU = 1
      AND (a.ID_CUHO IN (17, 13) OR ( a.ID_CUHO <> 17 AND a.AULA = 'A'
      OR ( a.ID_CUHO <> 17 AND h.DNI_TUTOR = 'GENERIC' )
      AND h.DNI_TUTOR <> 'GENERIC' AND h.perfil = 'tutor' AND h.ORDRE_TUTOR = 1))
      ORDER BY c.ANY, c.MES LIMIT 12";
      $stmtEd = $connexio->prepare($cnsEd);
      $stmtEd->bind_param("dd", $diesObert, $anyActual);
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
      $objTxt2 = new Text("CDD");
      $txtCDD = $objTxt2->obtenirTextHTML();
      $objTxt4 = new Text("MIXTOS");
      $txtMixtos = $objTxt4->obtenirTextHTML();

      /* Versió ordinador */
      $mostrar="<div class='sticky-sidebar position-relative'>
      <div class='theiaStickySidebar w-100 h-100 position-absolute'>
      <div id='filtres' class='ps2'>";
      $mostrar.="<h3>Perfils professionals </h3>".$this->__mostrarFiltresPerfils();
      $mostrar.="<h3>Temàtiques </h3>".$this->__mostrarFiltresTematiques();
      $mostrar.="<h3>Nivells </h3>".$this->__mostrarFiltresNivells();

      $mostrar.="<button href='#' class='eliminar-filtres position-relative text-white border-0 border-radius-2 w-100'>ELIMINAR FILTRES</button>
      </div></div></div>";

      $nousBotons = "";
      /* Existeixen novetats */
      if ( $this->exPackNou() == '1' )
         $nousBotons .= "<div class='novetats boto-header ml-0 mr-0 mt-0 mb-0 w-auto px-2 border-0 ml-2'>NOVETATS!</div>";
      /* Existeixen cursos amb cdd */
      if ( $this->exPackCdd() == '1' )
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

      $mostrar.="<h3>Perfils professionals </h3>".$this->__mostrarFiltresPerfils();
      $mostrar.="<h3>Temàtiques </h3>".$this->__mostrarFiltresTematiques();
      $mostrar.="<h3>Nivells </h3>".$this->__mostrarFiltresNivells();

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
      $objTxt2 = new Text("CDD");
      $txtCDD = $objTxt2->obtenirTextHTML();
      $objTxt4 = new Text("MIXTOS");
      $txtMixtos = $objTxt4->obtenirTextHTML();

      $nousBotons = "";
      /* Existeixen novetats */
      if ( $this->exPackNou() == '1' )
         $nousBotons .= "<button href='#' class='novetats boto-header ml-2 mr-0 mt-0 mb-0 w-auto px-2 border-0'>NOVETATS!</button>";
      if ( $this->exPackCdd() == '1' )
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
      $objTxt2 = new Text("CDD");
      $txtCDD = $objTxt2->obtenirTextHTML();
      $objTxt4 = new Text("MIXTOS");
      $txtMixtos = $objTxt4->obtenirTextHTML();

      $nousBotons = "";
      if ( $this->exPackNou() == '1' )
         $nousBotons .= "<div class='novetats boto-header ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0 ml-2'>NOVETATS!</div>";
      if ( $this->exPackCdd() == '1' )
      $nousBotons .= "<div class='cdd boto-header text-white ml-0 mr-2 mt-0 mb-0 w-auto px-2 border-0'>".$txtCDD."</div>";

      $mostrar.="<div class='filter-capcalera filter-fixed'>
         <div class='filter-capcalera'><div class='cnt-filter-button flex-1-0-auto'>
            <button class='show-filters border-0'><i class='fas fa-bars'></i></button></div>
            ".$nousBotons."
         </div>
      </div>";
      return $mostrar;
   }

   function exPackNou() {
      require_once 'Pack.php';
      $esPackNou = '0';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();
      $connexio2 = new ConnexioBBDDSTMT();
      $connexio2->connectarBD();
      $connexio3 = new ConnexioBBDDSTMT();
      $connexio3->connectarBD();

      $cnsPacks = "SELECT i.ID_PACK FROM info_pack as i
      INNER JOIN packs as p ON p.ID_PACK = i.ID_PACK
      INNER JOIN curs as c ON p.ID_CURS=c.ID_CURS
      WHERE i.ESTAT=1 AND c.PUBLIC=1 AND p.PUBLIC=1 AND (c.ESTAT!='0' AND c.ESTAT!='T')
      ORDER BY DATA_CREATE DESC";

      $cnsDatai="SELECT DATA_CREATE FROM info_pack WHERE ID_PACK=?";

      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";

      if ( $stmt = $connexio->prepare($cnsPacks) ) {
         if ( $stmtDate=$connexio2->prepare($cnsDatai) ) {
            $stmtDate->bind_param("d", $idPack);
            if ( $stmtParam = $connexio3->prepare($cnsParams) ) {
               $stmtParam->bind_param("ss", $tipus, $orderBy);

               $tipus='etiqueta-curs-nou';
               $orderBy='DATAI';

            	$stmt->execute();
            	$stmt->bind_result($idPack);
            	while ( $stmt->fetch() && $esPackNou == '0' ) {
                  $stmtDate->execute();
                  $stmtDate->bind_result($dataiPrimeraEdicio);
                  $stmtDate->fetch();

                  $stmtParam->execute();
                  $stmtParam->bind_result($valor);
                  $stmtParam->fetch();

                  $dataDeixaDeSerNou = strtotime($valor, strtotime($dataiPrimeraEdicio));

                  if ($dataDeixaDeSerNou > strtotime(date("Y-m-d")) ) {
                     $esPackNou = '1';
                  }
            	}
            	$connexio->closeStmt();
            }
            else {
               throw new Exception('',5001);
            }
            $connexio3->closeStmt();
         }
         else {
            throw new Exception('',5002);
         }
         $connexio2->closeStmt();
      }
      else {
         throw new Exception('',5003);
      }
      $connexio->desconectarBD();
      return $esPackNou;
   }

   function exPackCdd() {
      $existeixCursCDD = '0';

      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $cnsPacks = "SELECT DISTINCT i.ID_PACK FROM info_pack as i
      INNER JOIN packs as p ON p.ID_PACK = i.ID_PACK
      INNER JOIN curs as c ON p.ID_CURS=c.ID_CURS
      WHERE i.ESTAT=1 AND c.PUBLIC=1 AND p.PUBLIC=1 AND (c.ESTAT!='0' AND c.ESTAT!='T')
       AND ETIQ_CDD != ''";

      if ( $stmt = $connexio->prepare($cnsPacks) ) {
         	$stmt->execute();
         	$stmt->bind_result($idPack);
            $stmt->store_result();
            if ($stmt->num_rows() > 0) {
               $existeixCursCDD = '1';
         	}
         	$connexio->closeStmt();
      }
      else {
         throw new Exception('',5004);
      }
      $connexio->desconectarBD();
      return $existeixCursCDD;
   }

   /**
   * @brief Mostra els filtres de perfils
   * @return Mostra els filtres de perfils
   */
   private function __mostrarFiltresPerfils() {
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();
      for ($i=0; $i<count($this->perfils); $i++) {
         $idPerfilActual = $this->perfils[$i];

         $cns='SELECT NOM, NCURT FROM perfils_list WHERE ID=?';
         $stmt = $connexio->prepare($cns);
         $stmt->bind_param("d", $idPerfilActual);
         $stmt->execute();
         $stmt->bind_result($nomPerfilLlarg, $nomPerfilCurt);
         $stmt->fetch();
         $connexio->closeStmt();

         $textPerfilCurt = new Text($nomPerfilCurt);
         $textPerfilCurt->obtenirNomCurt();
         $perfilNCurt = $textPerfilCurt->obtenirText();

         $textPerfil = new Text($nomPerfilLlarg);
         $nomPerfil = $textPerfil->obtenirTextHTML();

         $mostrar .= "<label>
         <input type='checkbox' class='perfils' id='perfil_".$perfilNCurt."' value='".$idPerfilActual."|".$perfilNCurt."'>
         <span class='checkmark border'></span>
         <span>".$nomPerfil."</span>
         </label>";
      }
      $connexio->desconectarBD();
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
