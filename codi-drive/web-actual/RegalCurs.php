<?php
/**
* @class InscripcioCurs
* @brief Conté tota la informació relacionada amb una InscripcioCurs.
*/
class RegalCurs{
   private $curs; /** Curs Curs del regal marcat */

   private $url; /** URL L'enllaç del regal marcat. Si no n'hi ha, valdrà null  */

   private $nomDesti; /** Text Nom Per a qui la targta regal */
   private $nomOrigen; /** Text Nom De part de qui la targta regal */
   private $dedicatoria; /** Text Dedicatoria la targta regal */

   private $estil; /** Numro Esti   l de la targta regal */

   private $hores; /** Numero Les hores del regal marcat ex: 40 */
   private $dispositiu; /** string Estat del regal */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/

   public function __construct($dispositiu) {
      $this->dispositiu=$dispositiu;

      $this->codi = null;
      $this->titol = null;
      $this->hores = null;
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció «1701»
   */
   private function obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',1701);
      return $this->titol;
   }

   /*
   * @brief Obtens el codi del curs
   * @return Si el curs té un codi curs, retorna el codi del curs del curs. Altrament, null.
   * @throws Si el curs no té un codi curs, envia l'excepció «1702»
   */
   private function obtenirCodiCurs() {
      if ($this->codi==null)
         throw new Exception('',1702);
      return $this->codi;
   }

   /*
   * @brief Obtens les hores del curs
   * @return Si el curs té unes hores, retorna les hores del curs. Altrament, null.
   * @throws Si el curs no té unes ures, envia l'excepció «1703»
   */
   private function obtenirHores() {
      if ($this->hores==null)
         throw new Exception('',1703);
      return $this->hores;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Mostra la pàgina de regala
   * @return Retorna el contingut de la pàgina de regala
   */
   public function mostrarPaginaRegal() {
      $mostrar ="<div class='container'><div class='row'><div class='col-12'>";
      $mostrar.=$this->__mostrarTitol();
      $mostrar.=$this->__mostrarBanner();
      $mostrar.=$this->__mostrarContingutInici();
      $mostrar.=$this->__modalCercantCursos();
      $mostrar.=$this->__modalLoading();
      $mostrar.=$this->__modalError();
      $mostrar.="</div></div></div>";
      return $mostrar;
   }

   /**
   * @brief Mostra la informació del titol
   * @return Mostra la informació del titol
   */
   private function __mostrarTitol() {
      $mostrar = "<div class='titol my-4'>";
      $mostrar.="<h1>Regala un curs!</h1>"; // Normal

      /* $mostrar.="<div class='d-flex flex-column flex-sm-row align-items-start align-items-sm-end py-2'>
         <h1 class='m-0'>Regala un curs!</h1>
         <div class='text-white font-weight-bold ml-2'
            style='background: #D91313;border-radius: 4px;font-family: Roboto,sans-serif;
            line-height: 18px;padding: 3px 6px; margin-bottom: 5px'>25% DE DESCOMPTE
         </div>
      </div>";*/

      $mostrar.="<div class='d-flex flex-column flex-sm-row align-items-left'>";
      $mostrar.="<div class='subtitol flex-grow-1 py-2'>Sorprèn els teus amics, familiars o companys</div>";
      $mostrar.="<button role='button' class='mesinfo border-radius-2 text-center ";
      $mostrar.="position-relative flex-shrink-1 py-1 px-2 negreta500' ";
      $mostrar.="title='Bescanvia la teva targeta regal' ";
      $mostrar.="onclick=\"location.href='https://www.prisma.cat/bescanvia-regal'\">";
      $mostrar.="Vols bescanviar la teva targeta regal?</button>";
      $mostrar.="</div>";
      $mostrar.="</div>";

      return $mostrar;
   }

   /**
   * @brief Mostra la imatge allargada del regal
   * @return Mostra la imatge allargada del regal
   */
   private function __mostrarBanner() {
      $altImg="Regala un curs!";
      $linkImg="https://www.prisma.cat/img/portades/regala-curs-2025.jpg"; // Banner normal
      // $linkImg="https://www.prisma.cat/img/portades/regala-curs-nadal-2025.png"; //Banner de Nadal
      $linkImgWeb=substr($linkImg, 0, -4).".webp";

      $mostrar = "<div class='info-banner mb-4'><picture>";
      $mostrar .= "<source type='image/webp' class='w-100 border-radius-2 banner-img' ";
      $mostrar .= "data-srcset=\"".$linkImgWeb."\" alt=\"".$altImg."\"/>";
      $mostrar .= "<source type='image/jpeg' class='w-100 border-radius-2 banner-img' ";
      $mostrar .= "data-srcset=\"".$linkImg."\" alt=\"".$altImg."\"/>";
      $mostrar .= "<img role='img' class='w-100 border-radius-2 banner-img lazyload' ";
      $mostrar .= "data-src=\"".$linkImg."\"  alt=\"".$altImg."\"/>";
      $mostrar .= "</picture></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra el contingut de la pàgina regala un curs
   * @return Mostra el contingut de la pàgina regala un curs
   */
   private function __mostrarContingutInici() {
      $mostrar = "<div class='separacio-peu border border-radius-2 bg-white px-4 py-2'>";
      $mostrar .= "<div id='cntPage' class='cntPage'>";
      $mostrar .= "<h2>Vols fer un regal original?</h2>";
      $mostrar .= "<p>Us oferim la possibilitat de sorprendre els vostres amics, familiars o companys regalant-los una experiència formativa: adquiriu una targeta regal personalitzada d’un dels nostres cursos perquè en gaudeixin quan ho desitgin.</p>";
      $mostrar .= "<p>Empleneu el formulari  següent i envieu-nos la comanda. Us farem arribar un correu electrònic amb les dades de pagament i, un cop l'hàgiu formalitzat, en menys d'un dia laboral rebreu la vostra targeta regal amb les  <strong>instruccions per bescanviar-la</strong>.</p>";
      /*NADAL*/
      //$mostrar .= "<p><i class='fas fa-exclamation-circle'></i> <span class='font-weight-bold'>Del 20 de novembre de 2025 fins al 6 de gener de 2026</span> (ambdós dies inclosos) podeu aprofitar-vos d'un <strong>25% de descompte</strong> sobre el preu del curs.</p>";

      $mostrar .= "<h2>Targeta regal</h2>";
      $mostrar .= "<p>Trieu el nombre d'hores corresponent al curs que voleu regalar:</p>";
      $texthores="";
      require_once 'ConnexioBBDD_PreparedStatment.php';
      require_once 'Numero.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      //buscar totes les hores disponibles
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='dies-inscriu-cursos';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $diesObets=0;
      while ($stmtParam->fetch()) {
         $valors = explode('|',$valor);

         $hores = intval($valors[0]);
         $diesOberts = $valors[1];

         if ($hores!=0 && $hores != 10) {
            $vectHores[] = $hores;
            $vectDiesOberts[] = $diesOberts;
         }
      }
      $connexio->closeStmt();

      $connexio2 = new ConnexioBBDDSTMT();
      $connexio2->connectarBD();
      $cnsExsiteixCurs = "SELECT ID_PREU FROM curs WHERE
        DATEDIFF(DATE_ADD(DATAI, INTERVAL ? DAY),CURRENT_DATE)>0 AND PUBLIC=1 AND HORES=?
        AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
        AND HORES != 10 AND ESTAT != '0' AND PUBLIC = 1
        ORDER BY ANY, MES LIMIT 1";
      $stmtEx=$connexio->prepare($cnsExsiteixCurs);
      $stmtEx->bind_param("dd", $diesObertsI, $horesI);

      $i = 0; $horesNoExisteixen = 0;
      while ( $i < count($vectHores) ) {
         $diesObertsI = $vectDiesOberts[$i];
         $horesI = $vectHores[$i];
         $stmtEx->execute();
         $stmtEx->store_result();
         if ( $stmtEx->num_rows() <= 0 ) {
            // echo "elimino hores ".$vectHores[$i]." ja que no hi ha cap curs d'aquestes hores<br>";
            array_splice( $vectHores , $i, 1);
         }
         else $i++;
      }

      sort($vectHores, SORT_NUMERIC);

      $numCategHores = count($vectHores);

      // var_dump($vectHores);

      if ( $numCategHores > 5 ) {
         $numDivisible = round( $numCategHores / 2, 0, PHP_ROUND_HALF_UP);
      }

      $cnsPreu = "SELECT PREU, PERCENTATGE FROM descomptes WHERE ID_PREU=? AND TIPUS=? AND CURS = 'TOTS' AND
        DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF)";
      $stmtPreu=$connexio2->prepare($cnsPreu);
      $stmtPreu->bind_param("dd", $idPreu, $tipus);
      $tipus=0;

      for ($i=0; $i<$numCategHores; $i++) {
         $diesObertsI = $vectDiesOberts[$i];
         $horesI = $vectHores[$i];
         $stmtEx->execute();
         $stmtEx->store_result();
         if ( $stmtEx->num_rows() > 0 ) {
            $stmtEx->bind_result($idPreu);
            $stmtEx->fetch();

            $stmtPreu->execute();
            $stmtPreu->bind_result($preu, $percentatge);
            $stmtPreu->fetch();

            $objPreu = new Numero($preu);
      		$preuFormatCorrecte = $objPreu->mostrarNumeroDecimalsSense0();

            $margleLG = "ml-lg-2 mr-lg-2";
            if ( $i==0 || ( $numCategHores > 5 && ($i == 0 || $i == $numDivisible || $i == $numDivisible*2)) ) $margleLG = "ml-lg-0 mr-lg-2";
            else if ( $i==$numCategHores-1 ||  ( $numCategHores > 5 && ( $i == ($numDivisible-1) || $i == (($numDivisible*2)-1) || $i == (($numDivisible*3)-1) || $i == count($vectHores)-1 ) ) ) $margleLG = "ml-lg-2 mr-lg-0";
            if ( $numCategHores > 5 && ($i == 0 || $i == $numDivisible || $i == $numDivisible*2) ) $texthores.="<div class='d-flex flex-column flex-sm-row w-100'>";
            $texthores.="<button id='hores-".$horesI."' onclick='mostraCursos(".$horesI.")' ";
            $texthores.="class='mesinfo position-relative font-weight-bold border-0 border-radius-2 w-100 ";
            $texthores.="flex-shrink-1 px-2 py-2 mr-0 mr-sm-2 ".$margleLG." ml-sm-0 mb-4'>";
            $texthores.=$horesI." hores (";
            if ($percentatge<=0)
               $texthores.=$preuFormatCorrecte." €";
            else {
               $preuDesc = $preu - ($preu*$percentatge/100);

               $objPreuDesc = new Numero($preuDesc);
         		$preuDescFormatCorrecte = $objPreuDesc->mostrarNumeroDecimalsSense0();

               $texthores.="<span class='tatxat font-weight-bold text-danger'>".$preuFormatCorrecte." €</span> ".$preuDescFormatCorrecte." €";
            }
            $texthores.=")</button>";
            if ( $numCategHores > 5 && ( $i == ($numDivisible-1) || $i == (($numDivisible*2)-1) || $i == (($numDivisible*3)-1) || $i == count($vectHores)-1 ) ) {
               $texthores.="</div>";
            }
         }
      }
      $connexio2->closeStmt();
      $connexio2->desconectarBD();
      $connexio->closeStmt();
      $connexio->desconectarBD();

      //mostro les hores
      if ($texthores!='') {

         if ( $numCategHores <= 5 ) $flexRow = 'flex-md-row';
         else $flexRow = 'flex-xl-row';
         $mostrar .= "<div id='cnt-hores' class='d-flex flex-column ".$flexRow."'>".$texthores."</div>";
         $mostrar .= "<div id='cnt-cursos' class='d-flex flex-wrap'></div>";
      }
      $mostrar.="</div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra els cursos de $hores hores
   * @return Mostra els cursos de $hores hores
   */
   public function mostraCursos($hores) {
     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();

     $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
     DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) AND VALOR LIKE ?";
     $stmtParam = $connexio->prepare($cnsParams);
     $stmtParam->bind_param("ss", $tipus, $valorInd);
     $tipus='dies-inscriu-cursos';
     $valorInd='%'.$hores.'%';
     $stmtParam->execute();
     $stmtParam->bind_result($valor);
     $stmtParam->fetch();
     $valors = explode('|',$valor);
     $diesOberts = $valors[1];
     $connexio->closeStmt();

     require_once 'Curs.php';

     $cnsExsiteixCurs = "SELECT CURS FROM curs WHERE
      DATAI+?>CURRENT_DATE AND PUBLIC=1 AND HORES=?
      AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
      GROUP BY CURS ORDER BY NOM_CURS";
     $stmtEx=$connexio->prepare($cnsExsiteixCurs);
     $stmtEx->bind_param("dd", $diesOberts, $hores);
     $stmtEx->execute();
     $stmtEx->store_result();
     if ( $stmtEx->num_rows() > 0 ) {
        $stmtEx->bind_result($codiCurs);
        $mostrar .= $this->__mostrarCursHores($hores);
        // echo $hores."<br />";
        while ($stmtEx->fetch()) {
           // echo $codiCurs."<br />";
           $curs = new Curs($codiCurs, $this->dispositiu);
           if ( $curs->obtenirEstat() == 1 )
           $mostrar .= $curs->mostrarCursRegal();
        }
     }
     $connexio->closeStmt();
     $connexio->desconectarBD();
     return $mostrar;
   }

   /**
   * @brief Mostra el curs $hores en format curs regal
   * @return Mostra el curs $hores en format curs regal
   */
   private function __mostrarCursHores($hores) {
      $linkImg = "https://www.prisma.cat/img/cursos/".$hores."_hores.jpg";
      $linkImgWeb=substr($linkImg, 0, -4).".webp";
      $dscImg = "Regala un curs de ".$hores." hores";

      $botoOnClick = "<div class='curs-regal-boto-tastet position-absolute'>
      <div class='curs-regal-text-tastet negret500 border-radius-2 bg-dark border-0 py-1 px-2 text-center'
      title=\"Selecciona el curs ".$titol."\"
      aria-label=\"Selecciona el curs ".$titol."\"
      data-keyboard='true'>Selecciona aquest curs</div></div>";

      $mostrar.="<div class='col-12 col-sm-6 col-lg-3 pl-2 pr-2 mb-4'>
      <div id='curs-regal-".$hores."' class='curs-regal border rounded'>
         <div class='curs-regal-overlay position-relative'>
            <picture>
               <source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\" class='prisma-related-course-image w-100'\">
               <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\" class='prisma-related-course-image w-100'\">
               <img data-src='".$linkImg."' alt=\"".$dscImg."\" class='prisma-related-course-image w-100 lazyload'></picture>
            </picture>".$botoOnClick."
         </div>
         <div class='curs-regal-titol bg-white'>
            <div class='espai-titol-etiqueta pr-3 pl-3 pt-2'>
               <div class='titol flex-grow-1 flex-shrink-0'>
                  <a role='link' class='color-text' href='https://www.prisma.cat/cursos' title='Cursos en línea que ofereix PrisMa'>
                     <div class='font-weight-bold 500'>Qualsevol curs de ".$hores." hores</div>
                  </a>
               </div>
               <div class='perfil-curs-related'></div>
            </div>
         </div>
         <div class='curs-regal-footer'>
            <button class='mesinfo position-relative font-weight-bold 500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2' target='_self' onclick=\"mostraInfoCurs('https://www.prisma.cat/cursos')\">
            Informació del cursos<i class='fas fa-long-arrow-alt-right ml-2'></i></button>
         </div>
      </div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra el preu corresponent del regal de la pròxima edició del curs amb codi $codiCurs
   * @return Mostra el preu corresponent del regal de la pròxima edició del curs amb codi $codiCurs
   */
   public function obtenirPreuHoresNomCursRegal($codiCurs) {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      if (intval($codiCurs)==0) {
         /* Es busca les hores i el id_preu de la pròxima edició oberta del curs */
         $consultaHoresPreu = "SELECT HORES, NOM_CURS, ID_PREU FROM curs WHERE
            DATAI+8>CURRENT_DATE AND CURS LIKE ? AND PUBLIC=1
            AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
            ORDER BY ANY, MES LIMIT 1";
         $stmtHoresPreu=$connexio->prepare($consultaHoresPreu);
         $stmtHoresPreu->bind_param("s", $codiCurs);
         $stmtHoresPreu->execute();
         $stmtHoresPreu->store_result();

         if ( $stmtHoresPreu->num_rows() > 0 ) {
            $stmtHoresPreu->bind_result($hores, $nomCurs, $idPreu);
            $stmtHoresPreu->fetch();
            $connexio->closeStmt();
        }
        else {
            $cnsHoresLastEd="SELECT HORES
            FROM curs WHERE CURS LIKE ? AND PUBLIC=1
            AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
            ORDER BY ANY DESC, MES DESC LIMIT 1";
            $stmtLastEd = $connexio->prepare($cnsHoresLastEd);
            $stmtLastEd->bind_param("s", $codiCurs);
            $stmtLastEd->execute();
            $stmtLastEd->bind_result($hores);
            $stmtLastEd->fetch();
            $connexio->closeStmt();

            $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
            $stmtParam = $connexio->prepare($cnsParams);
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
             // echo "diesOB:".$diesObets;

             $consultaHoresPreu = "SELECT HORES, NOM_CURS, ID_PREU FROM curs WHERE
                DATAI+?>CURRENT_DATE AND CURS LIKE ? AND PUBLIC=1
                AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
                ORDER BY ANY, MES LIMIT 1";
             $stmtHoresPreu=$connexio->prepare($consultaHoresPreu);
             $stmtHoresPreu->bind_param("ds", $diesObets, $codi);
             $stmtHoresPreu->execute();
             $stmtHoresPreu->store_result();
             if ( $stmtHoresPreu->num_rows() > 0 ) {
                $this->estat=1;
                $stmtHoresPreu->bind_result($hores, $nomCurs, $idPreu);
                $stmtHoresPreu->fetch();
             }
             $connexio->closeStmt();
        }
      }
      else {
         $hores = $codiCurs;

         $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
         DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) AND VALOR LIKE ?";
         $stmtParam = $connexio->prepare($cnsParams);
         $stmtParam->bind_param("ss", $tipus, $valorInd);
         $tipus='dies-inscriu-cursos';
         $valorInd='%'.$hores.'%';
         $stmtParam->execute();
         $stmtParam->bind_result($valor);
         $stmtParam->fetch();
         $valors = explode('|',$valor);
         $diesOberts = $valors[1];
         $connexio->closeStmt();

         $cnsExsiteixCurs = "SELECT NOM_CURS, ID_PREU FROM curs WHERE
           DATAI+?>CURRENT_DATE AND PUBLIC=1 AND HORES=?
           AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
           ORDER BY ANY, MES LIMIT 1";
         $stmtEx=$connexio->prepare($cnsExsiteixCurs);
         $stmtEx->bind_param("dd", $diesOberts, $hores);
         $stmtEx->execute();
         $stmtEx->bind_result($nomCurs, $idPreu);
         $stmtEx->fetch();
         $connexio->closeStmt();
      }

      //A partir del id_preu i del codicurs busco el preu a regalar
      $consDesc = "SELECT PREU, PERCENTATGE FROM descomptes WHERE DATAI<=CURRENT_TIMESTAMP AND
                  (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL) AND ID_PREU=? AND
                  TIPUS=? AND (CURS=? OR CURS='TOTS') ORDER BY ORDRE";
      $stmtDesc = $connexio->prepare($consDesc);
      $stmtDesc->bind_param("dds", $idPreu, $tipus, $codiCurs);
      $tipus=0;
      $stmtDesc->execute();
      $stmtDesc->bind_result($preu, $percentatge);
      $stmtDesc->fetch();
      $connexio->closeStmt();

      $connexio->desconectarBD();

      $res = $preu."|".$hores."|".$nomCurs."|".$percentatge;

      return $res;
   }

   /*
   * @brief Retorna una alerta ssi existeix a la BD que ha d'apareixer aquesta alerta
   * @return Reviso si el curs té una alerta per posar a la inscripció.
   Si el curs disposa d'una alerta, es torna una alerta en un contenidor amb una
   estetica amb una exclamació i el missatge que existeix al base de dades
   */
   private function alertaInformacio($codi) {
      $textCodiCurs = new Text($codi);
      $codiCurs = $textCodiCurs->convertirMaj();

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      // Cercar paramatre X per veure si el curs actual té una alerta d'inscripció
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("sss", $tipus, $valor, $orderBy);
      $tipus = 'alerta-info';
      $valor = $codiCurs.'%';
      $orderBy='VALOR';
      $stmtParam->execute();
      $stmtParam->store_result();
      // Si té una alerta d'inscripció
      if ( $stmtParam->num_rows() > 0 ) {
         $stmtParam->bind_result($valorRes);
         $stmtParam->fetch();
         $arrValors = explode('|',$valorRes);
         $mostrar = $this->__mostrarAlerta( $arrValors[1] );
      }
      else {
         $mostrar = '';
      }
      $connexio->closeStmt();

      return $mostrar;
   }

   /*
   * @brief Retorna una alerta ssi existeix a la BD que ha d'apareixer aquesta alerta
   * @return Reviso si el curs té una alerta per posar a la inscripció.
   Si el curs disposa d'una alerta, es torna una alerta en un contenidor amb una
   estetica amb una exclamació i el missatge que existeix al base de dades
   */
   private function alertaInscripcio($codi) {
      $textCodiCurs = new Text($codi);
      $codiCurs = $textCodiCurs->convertirMaj();

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
         $mostrar = $this->__mostrarAlerta( $arrValors[1] );
      }
      else {
         $mostrar = '';
      }
      $connexio->closeStmt();

      return $mostrar;
   }

   /*
   * @brief Retorna una alerta amb el missatge $missatgeAlerta
   * @return Retorna una alerta en un contenidor amb una estetica amb una exclamació i el missatge $missatgeAlerta
   */
   private function __mostrarAlerta( $missatgeAlerta ) {
      $mostrar = "<div style='background: #e8ecf5 !important; border: none'
      class='prisma-contact border-radius-2 px-3 pb-1 pt-3 my-3'>";
      $mostrar .= $missatgeAlerta;
      $mostrar .= "</div>";

      return $mostrar;
   }

   /**
   * @brief Mostra el formulari amb les dades de la persona a qui li vols regalar el curs.
   * @return Mostra el formulari del curs $codiCurs amb les dades de la persona a qui li vols regalar el curs
   * on $origen es el valor de a qui li vols regalar, $desti es el valor de la persona
   * que et regala el curs, $dedicatoria es el valor de la dedicatoria del curs,
   * $hores es el nombre d'hores de la pròxima edició del curs amb codi $codiCurs,
   * $preu és el preu corresponent del regal de la pròxima edició del curs amb codi $codiCurs
   */
   public function mostrarFormulariAfortunat($codiCurs, $origen, $desti, $dedicatoria, $hores, $preu, $percentatge) {
      $mostrar ="<h2>Targeta regal</h2>";

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consultaCurs = "SELECT TITOL FROM informacio WHERE CODI_CURS=? AND ESTAT=1";
      $stmtInfo=$connexio->prepare($consultaCurs);
      $stmtInfo->bind_param("s", $codiCurs);
      $stmtInfo->execute();
      $stmtInfo->bind_result($nomCurs);
      $stmtInfo->fetch();
      $connexio->closeStmt();

      $connexio->desconectarBD();

      if (intval($codiCurs)!=0) $textNomCurs = "un <strong>curs de ".$codiCurs." hores</strong>";
      else $textNomCurs = "el curs <strong>".$nomCurs."</strong>";

      $preuReal = $preu;
      $textPreu = $preuReal." €";
      if ($percentatge>0) {
         $preuReal = $preu - ($preu*$percentatge/100);
         $textPreu = "<span class='tatxat'>".$preu." €</span> ".$preuReal." €";
      }

      $mostrar .= "<p>Has triat que vols regalar ".$textNomCurs." (".$textPreu.").</p>";
      $mostrar .= $this->alertaInformacio($codiCurs);

      $mostrar.="<p>Emplena el formulari amb les dades de la targeta.</p>";

      $mostrar.="<div class='d-flex flex-column align-items-center justify-content-center'>";

      $mostrar.="<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>";
      $mostrar.="<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'><div class='form-group field-wrap position-relative'>";
      $mostrar.="<label class='position-absolute mb-0'><span class='camp'>Per a qui</span><span class='req font-weight-bold'>*</span></label>";
      $mostrar.="<input type='text' class='form-control' id='desti' name='desti' autofocus='' value='".$desti."'>";
      $mostrar.="<span id='desti_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
      $mostrar.="</div></div>";
      $mostrar.="<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'><div class='form-group field-wrap position-relative'>";
      $mostrar.="<label class='position-absolute mb-0'><span class='camp'>De part de qui</span></label>";
      $mostrar.="<input type='text' class='form-control' id='origen' name='origen' value='".$origen."'>";
      $mostrar.="</div></div></div>";

      $mostrar.="<div class='d-flex flex-row align-items-center justify-content-center w-100'>";
      $mostrar.="<div class='col-12 px-0'><div class='form-group field-wrap position-relative'>";
      $mostrar.="<label class='position-absolute mb-0'><span class='camp'>Dedicatòria</span></label>";
      $mostrar.="<textarea type='text' class='form-control' id='dedicatoria' name='dedicatoria'>".$dedicatoria."</textarea>";
      $mostrar.="</div></div></div>";

      $mostrar.="<div class='d-flex w-100'><div class='form-group'>";
      $mostrar.="<font id='text_dedicatoria_ma'>Si vols escriure la dedicatòria a mà, deixa aquest camp  en blanc.</font>";
      $mostrar.="</div></div>";

      $mostrar.="<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100 botons'>";
      $mostrar.="<button id='enrereForm' class='boto-disable position-relative border-0 border-radius-2 w-100 mr-0 mr-md-2 px-4 py-2 my-2'>Vull triar un altre curs</button>";
      $mostrar.="<button id='continuaForm' class='boto-blau position-relative text-white border-0 border-radius-2 w-100  px-4 py-2 my-2'>Previsualitza la targeta regal</button>";
      $mostrar.="</div>";

      $mostrar.="</div>";
      return $mostrar;
   }

   /**
   * @brief Previsualització de la targeta regal
   * @return Previsualització de la targeta regal amb l'estil $estil amb la informació $codiCurs, $origen, $desti i $dedicatoria
   * Si el $codiRegal és buit, es crea un $codiRegal aleatoriament.
   */
   public function mostrarPrevisualitzacio($codiCurs, $nEstil, $codiRegal, $origen, $desti, $dedicatoria) {

      //si $codiRegal=='', es genera el $codiRegal
      if ($codiRegal=='') {
         $codi = base_convert(uniqid(), 16, 36);
         $textCodiRegal = new Text($codi);
         $codiRegal = $textCodiRegal->convertirMaj();
      }

      //buscar el nom del curs del codi $codiCurs

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      //es un curs en concret
      if (intval($codiCurs)==0) {
         $consultaCurs = "SELECT TITOL FROM informacio WHERE CODI_CURS=? AND ESTAT=1";
         $stmtInfo=$connexio->prepare($consultaCurs);
         $stmtInfo->bind_param("s", $codiCurs);
         $stmtInfo->execute();
         $stmtInfo->bind_result($nomCurs);
         $stmtInfo->fetch();
         $connexio->closeStmt();
      }
      else {
         $nomCurs = 'Curs de '.$codiCurs.' hores';
      }

      $marge = "mr-3";
      $numEstils = 3;
      $pathRegal = "https://www.prisma.cat/img/regal";

      $mostrar = "<h2>Targeta regal</h2>";
      $mostrar .= "<p>Escolliu un estil:</p>";
      $mostrar .= "<div class='cnt-regal mb-3'>";

      if ($nEstil=='') $nEstil = "estil-4";

      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
      DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='estil-regal';
      $orderBy='ID';
      $stmtParam->execute();
      $stmtParam->store_result();
      $numEstils = $stmtParam->num_rows();
      $stmtParam->bind_result($idEstil);
      $i=1;
      $mostrar .= "<div class='d-flex mb-3'>";
      while ($stmtParam->fetch()) {
         //Per cada estils des de params, buscar el nom dels estil
         $mostrar .= "<button id='".$idEstil."' class='estil mesinfo ";
         if ( $i == $numEstils) $marge = "mr-0";
         $mostrar .= $marge." border-radius-2 text-center position-relative ";
         $mostrar .= "w-100 flex-shrink-1 py-1 px-2 font-weight-bold 500'>";
         $mostrar .= "Estil ".$i."</button>";
         $i++;
      }
      $mostrar .= "</div>";
      $connexio->desconectarBD();

      $classNomCurs = $this->__mostrarClaseTamanyNomCurs($nomCurs);

      $mostrar .= "<div class='regalar-curs ".$nEstil." d-flex flex-column w-100 position-relative'>";
         $pathPicture = $pathRegal."/val-regal-".$nEstil;
         $mostrar .= "<picture>";
         $mostrar .= "<source type='image/webp' class='w-100 val-regal-webp' data-srcset='".$pathPicture.".webp' srcset='".$pathPicture.".webp' alt='Val regal' />";
         $mostrar .= "<source type='image/jpeg' class='w-100 val-regal-jpg' data-srcset='".$pathPicture.".jpg' srcset='".$pathPicture.".jpg' alt='Val regal' />";
         $mostrar .= "<img class='w-100 val-regal lazyloaded' src='".$pathPicture.".jpg' alt='Val regal' />";
         $mostrar .= "</picture>";
         $mostrar .= "<div class='cnt-text-regal d-flex flex-column w-100 position-absolute h-50 px-2 py-2'>";
            $mostrar .= "<div class='cnt-nom d-flex w-100 align-items-center justify-content-center'>".$desti."</div>";
            $mostrar .= "<div class='cnt-text-dedicatoria d-flex w-100 h-100 px-md-4 px-sm-3 px-2 pl-1'>";
               $mostrar .= "<div class='cnt-text d-flex flex-column w-50 pt-4 align-items-center text-center'>";
                  $mostrar .= "<div class='cnt-dedicatoria d-flex pb-2'>".$dedicatoria."</div>";
                  $mostrar .= "<div class='cnt-origen d-flex'>".$origen."</div>";
               $mostrar .= "</div>";
               $mostrar .= "<div class='cnt-curs-regal d-flex flex-column w-50 pr-2'>";
                  $mostrar .= "<div class='cnt-codi-regal d-flex justify-content-center align-items-center'>".$codiRegal."</div>";
                  $mostrar .= "<div class='cnt-curs d-flex justify-content-center align-items-center text-center pr-2 ".$classNomCurs."'>".$nomCurs."</div>";
               $mostrar .= "</div>";
            $mostrar .= "</div>";
         $mostrar .= "</div>";
      $mostrar .= "</div>";

      $mostrar.="<div class='d-flex flex-column flex-md-row align-items-center ";
      $mostrar.="justify-content-center w-100 botons'>";
         $mostrar.="<button id='enrerePrev' class='boto-disable position-relative ";
         $mostrar.="border-0 border-radius-2 w-100 mr-0 mr-md-2 px-4 py-2 my-2'>";
         $mostrar.="Modifica la targeta regal</button>";
         $mostrar.="<button id='continuaPrev' class='boto-blau position-relative ";
         $mostrar.="text-white border-0 border-radius-2 w-100  px-4 py-2 my-2'>";
         $mostrar.="Continua</button>";
      $mostrar.="</div>";

      $mostrar .= "</div>";

      return $mostrar;
   }

   /**
   * @brief Mostra el formulari del comprador
   * @return Retorna el formulari del comprador
   */
   public function mostrarFormulariComprador() {
      $mostrar = "<h2>Dades del comprador</h2>";
      $mostrar .= "<p>Per acabar, omple el formulari amb les dades del comprador.</p>";

      $mostrar.="<div class='d-flex flex-column align-items-center justify-content-center'>";

      //Nom i cognoms
      $mostrar.="<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>";
      $mostrar.="<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'><div class='form-group field-wrap position-relative'>";
      $mostrar.="<label class='position-absolute mb-0'><span class='camp'>Nom</span><span class='req font-weight-bold'>*</span></label>";
      $mostrar.="<input type='text' class='form-control' id='nom' name='nom' autofocus=''>";
      $mostrar.="<span id='nom_cognom_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
      $mostrar.="</div></div>";
      $mostrar.="<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'><div class='form-group field-wrap position-relative'>";
      $mostrar.="<label class='position-absolute mb-0'><span class='camp'>Cognoms</span><span class='req font-weight-bold'>*</span></label>";
      $mostrar.="<input type='text' class='form-control' id='cog' name='cog'>";
      $mostrar.="<span id='cognom_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
      $mostrar.="</div></div></div>";

      //select, dni, telefon
      $mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>";
         $mostrar .= "<div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>";
            $mostrar .= "<div class='form-group field-wrap position-relative'>";
               $mostrar .= "<div id='doc' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>";
                  $mostrar .= "<span class='element-selected font-weight-normal w-100'>NIF/NIE</span>";
                  $mostrar .= "<ul class='select-list position-absolute ' style='display: none;'>";
                     $mostrar .= "<li class='border-bottom m-0' id='doc-dni'><a href='#'>NIF/NIE</a></li>";
                     $mostrar .= "<li class='border-bottom m-0' id='doc-passaport'><a href='#'>Altres</a></li>";
                  $mostrar .= "</ul>";
                  $mostrar .= "<i class='fa triangle-inferior fa-angle-down position-absolute'></i>";
               $mostrar .= "</div>";
            $mostrar .= "</div>";
         $mostrar .= "</div>";
         $mostrar .= "<div class='col-12 col-md-3 pl-0 pr-0 pr-md-2' id='input_doc'>";
            $mostrar .= "<div class='form-group field-wrap position-relative'>";
               $mostrar .= "<label class='position-absolute mb-0'><span class='camp'>DNI amb lletra</span>";
               $mostrar .= "<span class='req font-weight-bold'>*</span></label>";
               $mostrar .= "<input type='text' class='form-control' id='nif' name='nif'>";
               $mostrar .= "<span id='dni_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
            $mostrar .= "</div>";
         $mostrar .= "</div>";
         $mostrar .= "<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>";
            $mostrar .= "<div class='form-group field-wrap position-relative'>";
               $mostrar .= "<label class='position-absolute mb-0'><span class='camp'>Telèfon de contacte</span>";
               $mostrar .= "<span class='req font-weight-bold'>*</span></label>";
               $mostrar .= "<input type='tel' class='form-control' id='telf' name='telf' pattern='[6-9]{1}[0-9]{8}' maxlength='9' >";
               $mostrar .= "<span id='telf_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
            $mostrar .= "</div>";
         $mostrar .= "</div>";
      $mostrar .= "</div>";

      //correus
      $mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>";
         $mostrar .= "<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>";
            $mostrar .= "<div class='form-group field-wrap position-relative'>";
               $mostrar .= "<label class='position-absolute mb-0'><span class='camp'>Correu electrònic</span>";
               $mostrar .= "<span class='req font-weight-bold'>*</span></label>";
               $mostrar .= "<input type='email' class='form-control' id='email' name='email' >";
               $mostrar .= "<span id='correu_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
            $mostrar .= "</div>";
         $mostrar .= "</div>";
         $mostrar .= "<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>";
            $mostrar .= "<div class='form-group field-wrap position-relative'>";
               $mostrar .= "<label class='position-absolute mb-0'><span class='camp'>Confirmaci&oacute ";
               $mostrar .= "del correu electrònic</span><span class='req font-weight-bold'>*</span></label>";
               $mostrar .= "<input type='email' class='form-control' id='email_conf' name='email_conf' >";
               $mostrar .= "<span id='correu_conf_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
            $mostrar .= "</div>";
         $mostrar .= "</div>";
      $mostrar .= "</div>";
      $mostrar .= $this->__modalCorreuValid();

      //adreça
      $mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>";
         $mostrar .= "<div class='col-12 pl-0 pr-0 pr-md-2'>";
            $mostrar .= "<div class='form-group field-wrap position-relative'>";
               $mostrar .= "<label class='position-absolute mb-0'><span class='camp'>Adreça (carrer, número...)</span><span class='req font-weight-bold'>*</span></label>";
               $mostrar .= "<input type='text' class='form-control' id='adreca' name='adreca' maxlength='150' >";
               $mostrar .= "<span id='adreca_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
            $mostrar .= "</div>";
         $mostrar .= "</div>";
      $mostrar .= "</div>";

      //cp, poblacio
      $mostrar .= "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>";
         $mostrar .= "<div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>";
            $mostrar .= "<div class='cnt-cp form-group field-wrap position-relative' id='cp_box'>";
               $mostrar .= "<label class='position-absolute mb-0'><span class='camp'>Codi postal</span><span class='req font-weight-bold'>*</span></label>";
               $mostrar .= "<input type='text' class='form-control' id='cp' name='cp' maxlength='5' >";
               $mostrar .= "<span id='cp_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
            $mostrar .= "</div>";
         $mostrar .= "</div>";
         $mostrar .= "<div class='col-12 col-md-9 pl-0 pr-0 pr-md-2'>";
            $mostrar .= "<div class='cnt-poble form-group field-wrap' id='poble_box'>";
               $mostrar .= "<label class='position-absolute mb-0'><span class='camp'>Poblaci&oacute</span><span class='req font-weight-bold'>*</span></label>";
               $mostrar .= "<input type='text' class='form-control' id='poble' name='poble' maxlength='50'>";
               $mostrar .= "<span id='poble_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
               $mostrar .= "<ul id='llistat_poblacions' style='display: none' ";
               $mostrar .= "class='select-list position-absolute' role='listbox'></ul>";
            $mostrar .= "</div>";
         $mostrar .= "</div>";
      $mostrar .= "</div>";

      //Comentaris
      $mostrar.="<div class='d-flex flex-row align-items-center justify-content-center w-100'>";
      $mostrar.="<div class='col-12 px-0'><div class='form-group field-wrap position-relative'>";
      $mostrar.="<label class='position-absolute mb-0'><span class='camp'>Comentaris</span></label>";
      $mostrar.="<textarea type='text' class='form-control' id='comentaris' name='comentaris'></textarea>";
      $mostrar.="<span id='dedicatoria_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>";
      $mostrar.="</div></div></div>";

      $mostrar.="<div class='d-flex flex-column flex-md-row align-items-center ";
      $mostrar.="justify-content-center w-100 botons'>";
         $mostrar.="<button id='enrereFormCompr' class='boto-disable position-relative ";
         $mostrar.="border-0 border-radius-2 w-100 mr-0 mr-md-2 px-4 py-2 my-2'>";
         $mostrar.="Enrere</button>";
         $mostrar.="<button id='continuaFormCompr' class='boto-blau position-relative ";
         $mostrar.="text-white border-0 border-radius-2 w-100  px-4 py-2 my-2'>";
         $mostrar.="Envia dades</button>";
      $mostrar.="</div>";

      $mostrar.="</div>";

      return $mostrar;
   }

   /**
   * @brief Insereix a la base de dades el registre del regal, envia un missatge
   *        de la confirmació de regal i es crea la targeta regal
   * @return Insereix a la base de dades el registre del regal, envia un missatge
   *        de la confirmació de regal i es crea la targeta regal
   */
   public function enviarInscripcioRegal($nom, $cog, $dni, $telf, $email, $adreca, $cp, $poblacio, $comentaris, $codiCurs, $nomCurs, $preu, $percentatge, $hores, $codiRegal, $estilRegal, $origen, $desti, $dedicatoria) {
      require_once 'Text.php';
      require_once 'Numero.php';

      $textNom = new Text($nom);
      $textCog = new Text($cog);
      $textDocumentacio = new Text($dni);
   	$numTelf = new Numero($telf);
   	$textEmail = new Text($email);
   	$textAdreca = new Text($adreca);
   	$textCodiPostal = new Text($cp);
   	$textPoblacio = new Text($poblacio);
      if ($comentaris!='')
   	  $textComentaris = new Text($comentaris);
   	$textTitolCurs = new Text($nomCurs);
   	$numPreu = new Numero($preu);
   	$numPercentatge = new Numero($percentatge);
   	$numHores = new Numero($hores);
   	$textCodiRegal = new Text($codiRegal);
   	$numEstil = new Numero(substr($estilRegal,-1));
      if ($origen!='')
   	  $textOrigen = new Text($origen);
      if ($desti!='')
   	  $textDesti = new Text($desti);
     if ($dedicatoria!='')
         $textDedicatoria = new Text($dedicatoria);

      //insereix un registre a la bd
      $textNom->arreglarParaulaBD('noms');
   	$textCog->arreglarParaulaBD('noms');
   	$textDocumentacio->arreglarParaulaBD('text');
   	$textEmail->arreglarParaulaBD('email');
   	$textAdreca->arreglarParaulaBD('text');
   	$textCodiPostal->arreglarParaulaBD('text');
   	$textPoblacio->arreglarParaulaBD('text');
      $textTitolCurs->arreglarParaulaBD('text');
      $textCodiRegal->arreglarParaulaBD('text');
      if ($origen!='')
         $textOrigen->arreglarParaulaBD('text');
      if ($desti!='')
         $textDesti->arreglarParaulaBD('text');
      if ($dedicatoria!='')
         $textDedicatoria->arreglarParaulaBD('text');

      $textCodiCurs = new Text($codiCurs);
      $codiCursH = $textCodiCurs->convertirMaj();
      if (intval($codiCurs)!=0) $codiCursH=$codiCurs."H";

      $nomBD = $textNom->obtenirText();
      $cogBD = $textCog->obtenirText();
      $nomCognoms = $nomBD." ".$cogBD;
      $dniBD = $textDocumentacio->convertirMaj();
      $telfBD = $numTelf->obtenirNumero();
      $emailBD = $textEmail->obtenirText();
      $cpBD = $textCodiPostal->obtenirText();
      $adrecaBD = $textAdreca->obtenirText();
      $pobleBD = $textPoblacio->obtenirText();
      $nomCursBD = $textTitolCurs->obtenirText();
      $preuBD = $numPreu->obtenirNumero();
      $percentatgeBD = $numPercentatge->obtenirNumero();
      $destiBD = '';
      if ($desti!='')
         $destiBD = $textDesti->obtenirText();
      $dedicatoriaBD='';
      if ($dedicatoria!='')
         $dedicatoriaBD = $textDedicatoria->obtenirText();
      $origenBD='';
      if ($origen!='')
         $origenBD = $textOrigen->obtenirText();
      $codiRegalBD = $textCodiRegal->obtenirText();
      $estilBD = $numEstil->obtenirNumero();
      if ($textComentaris != null)
      $observacionsBD = $textComentaris->obtenirText();
      else
      $observacionsBD = '';

      /* ############################### CONNEXIÓ A BD ######################### */

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* ############################## KEY ENCRIPTACIÓ ######################## */
   	//consulta per buscar la key de prisma $key

   	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
   					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
   	$stmt=$connexio->prepare($cnsParam);
   	$stmt->bind_param("s", $tipusParam);
   	$tipusParam = 'keyEncriptar';
   	$stmt->execute();
   	$stmt->bind_result($keyEncr);
   	$stmt->fetch();
   	$connexio->closeStmt();

      $cipher = "AES-128-CBC";

      $ivlen = openssl_cipher_iv_length($cipher);
   	$iv = openssl_random_pseudo_bytes($ivlen);
   	$ciphertext_raw = openssl_encrypt($codiRegal, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
   	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
   	$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

   	$urlIdPag = "https://www.prisma.cat/regal/pagament/".$hashIdPag;

      /* ######################################################################### */
   	$textAlertaConfirmacioInscripcio = '';

   	// Cercar paramatre X per veure si el curs actual té una alerta d'inscripció
   	$cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
   	DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
   	$stmtParam = $connexio->prepare($cnsParams);
   	$stmtParam->bind_param("sss", $tipus, $valor, $orderBy);
   	$tipus = 'alerta-confirmació-inscripcio';
   	$valor = $codiCurs.'%';
   	$orderBy='VALOR';
   	$stmtParam->execute();
   	$stmtParam->store_result();
   	// Si té una alerta d'inscripció
   	if ( $stmtParam->num_rows() > 0 ) {
   		$stmtParam->bind_result($valorRes);
   		$stmtParam->fetch();
   		$arrValors = explode('|',$valorRes);
   		$textAlertaConfirmacioInscripcio = "<div style='background-color:#e8ecf5;border:1px solid #d7deee;
   		border-radius:2px;padding:5px 25px;margin-bottom:20px'>";
         $textAlertaConfirmacioInscripcio .= $arrValors[1];
         $textAlertaConfirmacioInscripcio .= "</div>";
   	}
   	else {
   		$textAlertaConfirmacioInscripcio = '';
   	}
   	$connexio->closeStmt();

      /* ############################# ENVIAR MSG CURT ########################## */
      $templates = new Template();

      //envia un missatge curt a gestio i botiga
      $preuRealBD = $preuBD;
      if ($percentatgeBD>0) {
         $preuRealBD = $preuBD - ($preuBD*$percentatgeBD/100);
      }

      $msg = $templates->getTemplate_Inscripcions_EnviamenRegalShort($codiCurs, $dedicatoria, $comentaris);
   	$names_template = array("[NOM_ALUMNE]", "[COG_ALUMNE]", "[DNI_ALUMNE]",
   		"[EMAIL_ALUMNE]",	"[TEL_ALUMNE]", "[ADRECA_ALUMNE]", "[CP_ALUMNE]",
   		"[POBLACIO_ALUMNE]", "[CODI_CURS]", "[TITOL]", "[DATA_ACTUAL]",
   		"[PAY]", "[DESTI]", "[DEDICATORIA]", "[ORIGEN]", "[ESTIL]",
         "[COMENTARIS_ALUMNE]", "[URL_PAGAMENT]");
   	$names_function   = array($nomBD, $cogBD, $dniBD, $emailBD, $telfBD, $adrecaBD,
      	$cpBD, $pobleBD, $codiCurs, $nomCursBD, date("d/m/Y"), $preuRealBD,
         $destiBD, $dedicatoriaBD, $origenBD, $estilBD, $observacionsBD, $urlIdPag);
   	$msg = str_replace($names_template, $names_function, $msg);

      $subjectMail = "Curs regal: ".$codiRegalBD;

      $mailCurtGestio = new Mail();
      $mailCurtGestio->addHeaders('PrisMa Gestio', 'gestio@prisma.cat', $emailBD);
      $mailCurtGestio->addSubject($subjectMail);
      $mailCurtGestio->addTo('gestio@prisma.cat');
      // $mailCurtGestio->addTo('meriem.prisma.cat@gmail.com');
      $mailCurtGestio->addMissatge($msg);
      $mailCurtGestio->sendMessage();

      $mailCurtBotiga = new Mail();
   	$mailCurtBotiga->addHeaders('PrisMa Gestio', 'gestio@prisma.cat', $emailBD);
   	$mailCurtBotiga->addSubject($subjectMail);
   	$mailCurtBotiga->addTo('botiga@prisma.cat');
   	// $mailCurtBotiga->addTo('meriem.prisma.cat@gmail.com');
   	$mailCurtBotiga->addMissatge($msg);
   	$mailCurtBotiga->sendMessage();

      $mailCurtWebMaster = new Mail();
   	$mailCurtWebMaster->addHeaders('PrisMa Gestio', 'gestio@prisma.cat', $emailBD);
   	$mailCurtWebMaster->addSubject($subjectMail);
   	$mailCurtWebMaster->addTo('webmaster@prisma.cat');
   	// $mailCurtWebMaster->addTo('meriem.prisma.cat@gmail.com');
   	$mailCurtWebMaster->addMissatge($msg);
   	$mailCurtWebMaster->sendMessage();

      /* ############################ INSERT BD ################################ */

      if (intval($codiCurs)!=0)
   	  $insertNomCursBD .= "Curs de ".$codiCurs." hores";
      else
         $insertNomCursBD .= $nomCursBD;

      $insertBD = "INSERT INTO regal (NOMC, NIFC, TELC, MAILC, CPC, ADRECAC,
                   POBLEC, DATA, CCURS, NOM_CURS, IMPORT, DESTI, DEDICATORIA,
         			 ORIGEN, CODI, ESTIL, OBSERVACIONS)
   	 				 VALUES (?,?,?,?,?,?,?,CURRENT_DATE,?,?,?,?,?,?,?,?,?)";
   	$stmtIns=$connexio->prepare($insertBD);
   	$stmtIns->bind_param("ssdssssssdssssds", $nomCognoms, $dniBD, $telfBD, $emailBD, $cpBD, $adrecaBD, $pobleBD, $codiCursH, $insertNomCursBD, $preuRealBD, $destiBD, $dedicatoriaBD, $origenBD, $codiRegalBD, $estilBD, $observacionsBD);
   	$stmtIns->execute();
      $idInserit = $connexio->lastInsertId();
   	$stmtIns->fetch();
   	$connexio->closeStmt();

      /* ############### BUSCAR USERNME I PASSWORD AUTENTIFICACIÓ ############# */

      //buscar el username i el password d'autentificació de prisma
   	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
   					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
   	$stmt=$connexio->prepare($cnsParam);
   	$stmt->bind_param("s", $tipusParam);
   	$tipusParam = 'autentificacioInscripcio';
   	$stmt->execute();
   	$stmt->bind_result($valor);
   	$stmt->fetch();
   	$autentificacioInscripcio = explode('|',$valor);
   	$username = $autentificacioInscripcio[0];
   	$password = $autentificacioInscripcio[1];
   	$connexio->closeStmt();

      $connexio->desconectarBD();

       /* ######################## ENVIAR MSG CONFIRMACIÓ ######################## */

      $nomFromHead = 'PrisMa Gestio';
    	$correuFromHead = 'gestio@prisma.cat';
    	$nomReplyHead = 'PrisMa Gestio';
    	$correuReplyHead = 'gestio@prisma.cat';
    	$nomTo = $nomCognoms;
    	$correuTo = $emailBD;
      // $correuTo = 'meriem.prisma.cat@gmail.com';

      if ( intval($codiCurs) != 0 )
         $subject = "Regala un curs de ".$codiCurs." hores. Comanda realitzada";
      else
         $subject = "Regala el curs ".$nomCursBD.". Comanda realitzada";

      $preuRealBD = $preuBD;
      if ($percentatgeBD>0) {
         $preuRealBD = $preuBD - ($preuBD*$percentatgeBD/100);
      }

      $msg = $templates->getTemplate_Inscripcions_Pagaments_MissatgeTextManeresPagar2();
   	$names_template = array("[URL_PAGAMENT]", "[TITOL]", "[CODI]", "[TYPE]");
   	$names_function   = array($urlIdPag, $titolCurs, $codiRegalBD, "regal");
   	$textManeresPagar = str_replace($names_template, $names_function, $msg);

      $msg = $templates->getTemplate_Inscripcions_EnviamentRegal($codiCurs, $percentatgeBD);
      $names_template = array("[NOM_ALUMNE]", "[CODI_CURS]", "[TITOL]",
         "[PAY_ORIG]", "[PAY]",
         "[TEXT_ALERT_CONF_INSCR]", "[TEXT_MANERES_PAGAR]");
      $names_function   = array($nom, $codiCurs, $nomCursBD, $preuBD, $preuRealBD,
         $textAlertaConfirmacioInscripcio, $textManeresPagar);
      $missatge = str_replace($names_template, $names_function, $msg);

      $mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
    										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
    										$subject, $missatge);

      $nomTo = 'PrisMa Gestio';
      $correuTo = 'gestio@prisma.cat';
      // $correuTo = 'meriem.prisma.cat@gmail.com';
   	$mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
   										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
   										$subject, $missatge);

      /* ######################## CREAR TARGETES REGAL ######################## */

      $order   = array("\r\n", "\n", "\r");
      $replace = '<br />';

      $html = "<html><head>
     	<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/estructura.min.css?ver=5.0'>
     	<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/regal.min.css'>
     	<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/regal_targetes.min.css'>
     	<link rel='stylesheet' href='https://fonts.googleapis.com/css?family=Roboto:400,400i,500,500i,700,700i&display=swap' />
      </head>
      <body>";
      $html = "<html><head>
         <style>
            html {
               margin: 0;
            }
            body {
               margin: 20mm 8mm 2mm 8mm;
            }

            /* ESTIL 1. TARGETA FÃSICA */
            .estil-1 .cnt-nom.fisic {
               color: #5A669F;
            }
            .estil-1 .cnt-dedicatoria.fisic {
               color: #5A669F;
            }
            .estil-1 .cnt-origen.fisic {
               color: #5A669F;
            }
            .estil-1 .cnt-codi-regal.fisic {
               color: #923A5B;
            }
            .estil-1 .cnt-curs.fisic {
               color: #FFFFFF;
            }

            /* ESTIL 1. TARGETA digital */
            .estil-1 .cnt-nom.digital {
               color: #5A669F;
            }
            .estil-1 .cnt-dedicatoria.digital {
               color: #5A669F;
            }
            .estil-1 .cnt-origen.digital {
               color: #5A669F;
            }
            .estil-1 .cnt-codi-regal.digital {
               color: #FFFFFF;
            }
            .estil-1 .cnt-curs.digital {
               color: #C3355D;
            }

            /* ESTIL 2. TARGETA FÃSICA */
            .estil-2 .cnt-nom.fisic {
               color: #5A669F;
            }
            .estil-2 .cnt-dedicatoria.fisic {
               color: #5A669F;
            }
            .estil-2 .cnt-origen.fisic {
               color: #5A669F;
            }
            .estil-2 .cnt-codi-regal.fisic {
               color: #923A5B;
            }
            .estil-2 .cnt-curs.fisic {
               color: #FFFFFF;
            }

            /* ESTIL 2. TARGETA digital */
            .estil-2 .cnt-nom.digital {
               color: #5A669F;
            }
            .estil-2 .cnt-dedicatoria.digital {
               color: #5A669F;
            }
            .estil-2 .cnt-origen.digital {
               color: #5A669F;
            }
            .estil-2 .cnt-codi-regal.digital {
               color: #FFFFFF;
            }
            .estil-2 .cnt-curs.digital {
               color: #C3355D;
            }

            /* ESTIL 3. TARGETA FÃSICA */
            .estil-3 .cnt-nom.fisic {
               color: #5A669F;
            }
            .estil-3 .cnt-dedicatoria.fisic {
               color: #5A669F;
            }
            .estil-3 .cnt-origen.fisic {
               color: #5A669F;
            }
            .estil-3 .cnt-codi-regal.fisic {
               color: #6C9359;
            }
            .estil-3 .cnt-curs.fisic {
               color: #FFFFFF;
            }

            /* ESTIL 3. TARGETA digital */
            .estil-3 .cnt-nom.digital {
               color: #5A669F;
            }
            .estil-3 .cnt-dedicatoria.digital {
               color: #5A669F;
            }
            .estil-3 .cnt-origen.digital {
               color: #5A669F;
            }
            .estil-3 .cnt-codi-regal.digital {
               color: #FFFFFF;
            }
            .estil-3 .cnt-curs.digital {
               color: #16954E;
            }

            /* ESTIL 4. TARGETA FÃSICA */
            .estil-4 .cnt-nom.fisic {
               color: #AC323A;
            }
            .estil-4 .cnt-dedicatoria.fisic {
               color: #5A669F;
            }
            .estil-4 .cnt-origen.fisic {
               color: #5A669F;
            }
            .estil-4 .cnt-codi-regal.fisic {
               color: #5B8D96;
            }
            .estil-4 .cnt-curs.fisic {
               color: #FFFFFF;
            }

            /* ESTIL 4. TARGETA digital */
            /* .estil-4 .cnt-nom.digital {
               color: #AC323A;
            }
            .estil-4 .cnt-dedicatoria.digital {
               color: #5A669F;
            }
            .estil-4 .cnt-origen.digital {
               color: #5A669F;
            }
            .estil-4 .cnt-codi-regal.digital {
               color: #FFFFFF;
            }
            .estil-4 .cnt-curs.digital {
               color: #5B8D96;
            } */
            .estil-4 .cnt-nom.digital,
            .estil-5 .cnt-nom.digital,
            .estil-6 .cnt-nom.digital {
               color: #282828;
            }
            .estil-4 .cnt-dedicatoria.digital,
            .estil-5 .cnt-dedicatoria.digital,
            .estil-6 .cnt-dedicatoria.digital {
               color: #282828;
            }
            .estil-4 .cnt-origen.digital,
            .estil-5 .cnt-origen.digital,
            .estil-6 .cnt-origen.digital {
               color: #282828;
            }
            .estil-4 .cnt-codi-regal.digital,
            .estil-5 .cnt-codi-regal.digital,
            .estil-6 .cnt-codi-regal.digital {
               color: #FFFFFF;
            }
            .estil-4 .cnt-curs.digital,
            .estil-5 .cnt-curs.digital,
            .estil-6 .cnt-curs.digital {
               color: #282828;
            }
            .cnt-nom {
               font-family: Gobold CUTS, Myriad Pro, Arial, Verdana;
               text-align: center;
            }
            .cnt-dedicatoria {
               font-family:  Myriad Pro, Arial, Verdana;
               text-align: center;
            }
            .cnt-nom.digital {
               font-size: 30px;
               padding-top: 19px;
               padding-left: 10px;
               padding-right: 10px;
               height: 60px;
            }
            .cnt-dedicatoria.digital {
               font-size: 22px;
               padding: 20px;
               margin-left: 40px;
               margin-top: 22px;
               width: 370px;
               height: 190px;
               position: absolute;
            }
            .cnt-codi-regal {
               font-family: Gobold Bold, Myriad Pro, Arial, Verdana;
               text-align: center;
            }
            .cnt-codi-regal.digital {
               font-size: 18px;
               margin-top: 24px;
               margin-left: 502px;
               padding-top: 93px;
               width: 130px;
               color: white;
            }
            .cnt-curs {
               font-family: Gobold CUTS, Myriad Pro, Arial, Verdana;
               text-align: center;
            }
            .cnt-curs.digital {
               font-size: 19px;
               margin-left: 406px;
               width: 340px;
               height: 73px;
               margin-top: 27px;
            }
         </style>
      </head><body>";
      $html_digital = $html;
      $html_impresa = $html;

      if (intval($codiCurs)!=0)
         $varNomCurs = "Curs de ".$codiCurs." hores";
      else
         $varNomCurs = $nomCursBD;

      $classNomCurs = $this->__mostrarClaseTamanyNomCurs($varNomCurs);

      $html_digital .= "<div class='regalar-curs ".$estilRegal." d-flex
         flex-column align-items-center justify-content-center position-relative' style=\"
          width: 780px;
          height: 695px;
          margin: 0 auto;
          background: url('https://www.prisma.cat/img/regal/val-regal-".$estilRegal.".jpg');
          background-size: contain;
          background-repeat: no-repeat;\">

         <p class='cnt-nom digital position-absolute' sytle='font-size: 30px;
          padding-top: 19px;
          padding-left: 10px;
          padding-right: 10px;
          height: 60px;'>".$desti."</p>
         <p class='cnt-dedicatoria digital position-absolute'>".str_replace($order, $replace, $dedicatoria)."<br>
         <span class='cnt-origen digital'>".$origen."</span></p>
         <p class='cnt-codi-regal digital position-absolute'>".$codiRegal."</p>
         <p class='cnt-curs digital position-absolute ".$classNomCurs."'>".$varNomCurs."</p>
      </div>";

      $html = "</body></html>";
      $html_digital .= $html;
      $html_impresa .= $html;

      // echo $html_digital;

      //generar PDF
      $options_digital = new \Dompdf\Options();
      $options_digital->set('isRemoteEnabled', true);
      $dompdf_digital = new \Dompdf\Dompdf($options_digital);
      $dompdf_digital->set_paper("A4", "landscape");
      $dompdf_digital->load_html($html_digital);
      $dompdf_digital->render();
      $pdf_digital = $dompdf_digital->output();
      $filename_digital = "../targetes-regal/".$codiRegal."_targeta_regal_versio_digital.pdf";
      file_put_contents($filename_digital, $pdf_digital);

      $ivlen = openssl_cipher_iv_length($cipher);
   	$iv = openssl_random_pseudo_bytes($ivlen);
   	$ciphertext_raw = openssl_encrypt($idInserit, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
   	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
   	$hashIdInserit = base64_encode( $iv.$hmac.$ciphertext_raw );

      echo $hashIdInserit;
   }

   /*
   * @brief Mostra una classe segons el tamany de $nomCurs
   * @return Mostra una classe segons el tamany de $nomCurs
   */
   private function __mostrarClaseTamanyNomCurs($nomCurs) {
      if (strlen($nomCurs)>0 && strlen($nomCurs)<=22) $classNomCurs= 'tamany-1';
      else if (strlen($nomCurs)>22 && strlen($nomCurs)<=50) $classNomCurs= 'tamany-2';
      else if (strlen($nomCurs)>50 && strlen($nomCurs)<=65) $classNomCurs= 'tamany-3';
      else if (strlen($nomCurs)>65) $classNomCurs= 'tamany-4';

      return $classNomCurs;
   }

   /*
   * @brief Mostra un modal de càrrega "buscant"
   * @return Retorna un modal de càrrega "buscant"
   */
   private function __modalCercantCursos() {
      $mostrar = "<div class='modal carrega' id='modalCercantCursos' tabindex='-1' role='dialog'
      aria-labelledby='modalCercantCursos' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div class='loading-wrapper'>
                     <div class='loading-text'>Cercant cursos...</div>
                     <div class='loading-content'></div>
                  </div>
               </div>
            </div>
         </div>
    	</div>";

   	return $mostrar;
   }

   /*
   * @brief Mostra un modal de càrrega "Espera un moment"
   * @return Retorna un modal de càrrega "Espera un moment"
   */
   private function __modalLoading() {
      $mostrar = "<div class='modal carrega' id='modalLoading' tabindex='-1' role='dialog'
      aria-labelledby='modalLoading' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div class='loading-wrapper'>
                     <div class='loading-text'>Espera un moment...</div>
                     <div class='loading-content'></div>
                  </div>
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
   					<button role='button' data-dismiss='modal' class='btn btn-warning border-0 border-radius-2 text-center font-weight-bold 500 m-0 mr-3'>D'acord</button>
   				</div>
   			</div>
   		</div>
   	</div>";

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
}

?>
