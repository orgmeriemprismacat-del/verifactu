<?php
/**
   * @class Curs
   * @brief Conté tota la informació relacionada amb un Curs.
*/
class Curs {
   protected $codi; /**< Text Codi d'un curs ex: ACRE */
   protected $titol; /**< Text El titol d'un curs. ex. Alumnat amb Altes Capacitats */
   protected $shortDescription; /**< Text La descripció curta d'un curs */
   protected $hores; /**< Numero Les hores d'un curs ex: 40 */
   protected $tipus; /**< Numero Les hores d'un curs ex: 40 */
   protected $id_preu; /**< Numero El ID preu que correspon al curs. Per poder obtenir el preu d'un curs cal anar a buscar a la taula preus, tots els registres amb ID = ID_PREU  */
   protected $perfil; /**< Perfil El perfil del curs de les pròximes edicions */
   protected $perfil_pendent; /**< Perfil El perfil del curs de les pròximes edicions */
   protected $cdd; /**< CDD El perfil del curs de les pròximes edicions */
   protected $nivells; /**< Text Els nivells del curs */
   protected $idNivells; /**< Text Els nivells del curs */
   protected $tematiques; /**< Text Les tematiques del curs */
   protected $idTemes; /**< Text Les tematiques del curs */
   protected $video; /**< Video El video del curs. Si no n'hi ha, valdrà null  */
   protected $img_large; /**< Imatge La imatge allargada del curs. Si no n'hi ha, valdrà null  */
   protected $img_small; /**< Imatge La imatge rectangular del curs. Si no n'hi ha, valdrà null */
   protected $img_perfil; /**< Imatge La imatge rectangular del curs. Si no n'hi ha, valdrà null */
   protected $url; /**< URL L'enllaç del curs. Si no n'hi ha, valdrà null  */
   protected $dispositiu; /**< string Mobil si el dispositiu és mobil, altrament ordindador */
   protected $estat; /**< string Si el curs (info) està actiu o no */
   protected $edicions; /**< Array Les edicions del curs */
   protected $edicionsReconegudes; /**< Numero Les edicions del curs disponibles amb reconeixement */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /**
   * @brief Constructor de la classe. Crees el curs a partir de la url
   * @param $codi El codi corresponent al curs.
   * @return S'ha assignat en el curs, el codi de curs $codi, el titol i la descripció curta obtinguta
      a la taula INFORMACIO corresponent al codi_curs $codi i ESTAT = 1, les hores i l'id_preu
      obtingut a la taula CURSOS de la pròxima edició oberta corresponent al curs amb codi_curs
      $codi, els perfils que té la pròxima edició oberta de la taula PERFILS i les ids del video
      i de les imatges de la taula INFORMACIO corresponent al codi_curs $codi i ESTAT = 1
   */
   public function __construct($codi, $dispositiu) {
      require_once 'Text.php';
      $this->codi = new Text($codi);
      $this->dispositiu = $dispositiu;
	  $this->edicionsReconegudes = 0;

      /* Fem la connexió a la BD */
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Es busca el titol, la descripció curta, la url amigable corresponent a la informacio del curs,
      la id del video i la id de la imatge llarga i petita  del registre que a la taula INFORMACIO,
      el codi curs donat correspon amb el codi_curs de la taula */
      $consultaCurs = "SELECT TITOL, SHORT_DESC, ID_VIDEO, ID_IMG_LARGE, ID_IMG_PERFIL,
         ID_IMG_SMALL, ID_AMIGABLE, TIPUS_CURS FROM informacio WHERE CODI_CURS=? AND ESTAT=1";
      $stmtInfo=$connexio->prepare($consultaCurs);
      $stmtInfo->bind_param("s", $codi);
      $stmtInfo->execute();
      $stmtInfo->bind_result($titol, $shortDesc, $idVideo, $idImgLarge, $idImgPerf, $idImgSmall, $idAmig, $tipusCurs);
      $stmtInfo->store_result();
      if ($stmtInfo->num_rows() > 0) {
         $stmtInfo->fetch();
         $connexio->closeStmt();
         if ($tipusCurs!=null AND $tipusCurs!='')
            $this->tipus = new Text($tipusCurs);
         else
            $this->tipus = null;
         if ($titol!=null AND $titol!='')
            $this->titol = new Text($titol);
         else
            $this->titol = null;
         if ($shortDesc!=null AND $shortDesc!='')
            $this->short_desc = new Text($shortDesc);
         else
            $this->short_desc = null;
         require_once 'Url.php';
         if ($idAmig!=null AND $idAmig!='')
            $this->url = new Url($idAmig);
         else
            $this->url = null;
         require_once 'Video.php';
         if ($idVideo!=null and $idVideo!='')
            $this->video = new Video($idVideo);
         else
            $this->video = null;
         require_once 'Imatge.php';
         if ($idImgLarge!=null and $idImgLarge!='')
            $this->img_large = new Imatge($idImgLarge);
         else
            $this->img_large = null;
         if ($idImgSmall!=null and $idImgSmall!='')
            $this->img_small = new Imatge($idImgSmall);
         else
            $this->img_small = null;
         if ($idImgPerf!=null and $idImgPerf!='')
            $this->img_perfil = new Imatge($idImgPerf);
         else
            $this->img_perfil = null;

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

         /* Es busca les hores i el id_preu de la pròxima edició oberta del curs */
         $consultaHoresPreu = "SELECT HORES, CURS_ESCOLAR, GTAF, ID_PREU, CDD FROM curs
            as c WHERE (CURS LIKE ? AND GTAF IS NOT NULL AND GTAF!='' AND
            (".$dates.") AND (CURS NOT LIKE '%JOR%')
            AND (CURS NOT LIKE '%0%')) AND ESTAT!=0 ORDER BY ANY, MES LIMIT 1";
         $stmtHoresPreu = $connexio->prepare($consultaHoresPreu);
         $stmtHoresPreu->bind_param("s", $codi);
         $stmtHoresPreu->execute();
         $stmtHoresPreu->store_result();

         if ( $stmtHoresPreu->num_rows() > 0 ) {
            $stmtHoresPreu->bind_result($hores, $cursEscolar, $gtaf, $idPreu, $cdd);
            $stmtHoresPreu->fetch();
            $connexio->closeStmt();
            $this->estat = 1;
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

             $consultaHoresPreu = "SELECT HORES, CURS_ESCOLAR, GTAF, ID_PREU, CDD FROM curs WHERE
                DATAI+?>CURRENT_DATE AND CURS LIKE ? AND PUBLIC=1
                AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
                ORDER BY ANY, MES LIMIT 1";
             $stmtHoresPreu=$connexio->prepare($consultaHoresPreu);
             $stmtHoresPreu->bind_param("ds", $diesObets, $codi);
             $stmtHoresPreu->execute();
             $stmtHoresPreu->store_result();
             if ( $stmtHoresPreu->num_rows() > 0 ) {
                $this->estat=1;
                $stmtHoresPreu->bind_result($hores, $cursEscolar, $gtaf, $idPreu, $cdd);
                $stmtHoresPreu->fetch();
                // echo $hores." ".$cursEscolar." ".$gtaf." ".$idPreu;
             }
             $connexio->closeStmt();
        }

        if ( $this->estat==1) {
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

            /* Es busca els perfils de la pròxima edició oberta del curs */
            $consultaPerfil = "SELECT ID_PERFIL FROM perfils WHERE (CODI LIKE ?
               AND HORES=? AND CURS_ESCOLAR=? AND CODI_GTAF=?) GROUP BY ID_PERFIL";
            $stmtPerfil=$connexio->prepare($consultaPerfil);
            $stmtPerfil->bind_param("sdss", $codi, $hores, $cursEscolar, $gtaf);
            $stmtPerfil->execute();
            $stmtPerfil->bind_result($idPerfil);
            $cntPerfils=0;
            require_once 'Perfil.php';
            $idPerfils = null;
            while ($stmtPerfil->fetch()) {
               $idPerfils[] = $idPerfil;
               $this->perfil[$cntPerfils] = new Perfil($idPerfil);
               $cntPerfils++;
            }
            $connexio->closeStmt();

            /* Es busca els nivells, les tematiques i els perfils pendents del curs */
            //$consultaNivellTema = "SELECT f.ID_NIVELL, f.ID_TEMA, i.ID_PERFILS_PENDENT FROM informacio AS i INNER
             //  JOIN filtres AS f ON i.ID=f.ID_INFO WHERE i.CODI_CURS LIKE ? AND i.ESTAT=1 AND f.DATAI<=CURRENT_TIME AND (f.DATAF IS NULL OR CURRENT_TIME<=f.DATAF)";
			$consultaNivellTema = "SELECT f.ID_NIVELL, f.ID_TEMA, i.ID_PERFILS_PENDENT FROM informacio AS i INNER
               JOIN filtres AS f ON i.ID=f.ID_INFO WHERE i.CODI_CURS LIKE ? AND i.ESTAT=1";
            $stmtNivTema = $connexio->prepare($consultaNivellTema);
            $stmtNivTema->bind_param("s", $codi);
            $stmtNivTema->execute();
            $stmtNivTema->store_result();
            $stmtNivTema->bind_result($idNivell, $idTema, $idPerfilPendent);
            if ( $stmtNivTema->num_rows() <= 0 )
               throw new Exception('',610);
            else {
               $stmtNivTema->fetch();
               if ($idNivell=='' or $idNivell==null)
                  throw new Exception('',611);
               else if ($idTema=='' or $idTema==null)
                  throw new Exception('',612);
            }
            $connexio->closeStmt();

            $ids_nivells = explode('|',$idNivell);
            $this->idNivells = $ids_nivells;
            $cnt_nivells = 0;
			   $consultaNivell = "SELECT ID_NIVELL, NOM_INFO, ORDRE FROM nivells WHERE ID=?";
            $stmtNiv = $connexio->prepare($consultaNivell);
            $stmtNiv->bind_param("d", $idNivellActual);

            while ($cnt_nivells < count($ids_nivells)) {
               $idNivellActual = $ids_nivells[$cnt_nivells];
               $stmtNiv->execute();
               $stmtNiv->bind_result($idNivellGen, $nomNiv, $ordreNiv);
               $stmtNiv->fetch();
               $this->idNivells[$cnt_nivells] = $idNivellGen;
               $ordre_nivells[$ordreNiv]=$nomNiv;
               $cnt_nivells++;
            }
            $connexio->closeStmt();
            ksort($ordre_nivells);
            $i = 0;
            foreach($ordre_nivells as $clau => $valor){
               $this->nivells[$i] = new Text($valor);
               $i++;
            }

            $idTemes = explode('|',$idTema);
            $this->idTemes = $idTemes;
            $cntTemes = 0;
            $consultaTema = "SELECT NOM, ORDRE FROM temes WHERE ID=?";
            $stmtTema = $connexio->prepare($consultaTema);
            $stmtTema->bind_param("d", $idTemaActual);
            while ($cntTemes < count($idTemes)) {
               $idTemaActual = $idTemes[$cntTemes];
               $stmtTema->execute();
               $stmtTema->bind_result($nomTema, $ordreTema);
               $stmtTema->fetch();
               $ordre_temes[$ordreTema]=$nomTema;
               $cntTemes++;
            }
            $connexio->closeStmt();
            ksort($ordre_temes);
            $i = 0;
            foreach($ordre_temes as $clau => $valor){
               $this->tematiques[$i] = new Text($valor);
               $i++;
            }

            //Es comprova si hi ha algun perfil pendent que no hi és a la taula perfils
            // echo "IDPERFILPENDENT ".$codi.":".$idPerfilPendent."<br>";
            $idPerfilsPendentsComprovar = explode('|',$idPerfilPendent);
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
         }
         else {
            $this->estat=0;
            $connexio->closeStmt();
         }
      }
      else {
         $this->estat=0;
         $connexio->closeStmt();
      }

      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /**
   * @brief Obtens el codi del curs
   * @return El codi del curs.
   * @throws Si el curs no té un codi, envia l'excepció «No existeix el codi curs»
   */
   public function obtenirCodi() {
      if ($this->codi==null)
         throw new Exception('',601);
      return $this->codi;
   }

   /*
   * @brief Obtens la imatge allargada
   * @return Si el curs té una imatge gran, retorna la imatge gran del curs, és a dir la caratula del
      curs. Altrament, null.
   * @throws Si el curs no té una imatge gran, envia l'excepció «No existeix la imatge gran del curs»
   */
   public function obtenirImgLarge() {
      if ($this->img_large==null)
         throw new Exception('',603);
      return $this->img_large;
   }

   /*
   * @brief Obtens l'estat del curs
   * @return L'estat del curs
   */
   public function obtenirEstat() {
      return $this->estat;
   }

   /*
   * @brief Obtens el id preu del curss
   * @return El id preudel curs
   * @throws Si el curs no té un id_preu, envia l'excepció «No existeix el id preu del curs»
   */
   public function obtenirPreu() {
      if ($this->id_preu==null)
         throw new Exception('',608);
      return $this->id_preu;
   }

   /*
   * @brief Obtens la imatge rectangular
   * @return Si el curs té una imatge petita, retorna la imatge petita del curs, és a dir la caratula del
      curs. Altrament, null.
   * @throws Si el curs no té una imatge petita, envia l'excepció «No existeix la imatge petita del curs»
   */
   protected function obtenirImgSmall() {
      if ($this->img_small==null) {
         throw new Exception('',602);
      }
      return $this->img_small;
   }

   /*
   * @brief Obtens la imatge allargada
   * @return Si el curs té una imatge gran, retorna la imatge gran del curs, és a dir la caratula del
      curs. Altrament, null.
   * @throws Si el curs no té una imatge gran, envia l'excepció «No existeix la imatge gran del curs»
   */
   protected function obtenirImgPerfil() {
      if ($this->img_perfil==null)
         throw new Exception('',604);
      return $this->img_perfil;
   }

   /*
   * @brief Obtens la imatge rectangular
   * @return Si el curs té un video, retorna el video del curs. Altrament, null.
   * @throws Si el curs no té un video, envia l'excepció «No existeix el video del curs»
   */
   public function obtenirVideo() {
      return $this->video;
   }

   /*
   * @brief Obtens la Url del curs
   * @return Si el curs té una url, retorna l'enllaç amigable al curs. Altrament, null.
   * @throws Si el curs no té un enllaç, envia l'excepció «No existeix l'enllaç del curs»
   */
   public function obtenirUrl() {
      if ($this->codi==null)
         throw new Exception('',605);
      return $this->url;
   }

   /*
   * @brief Obtens el titol del curs
   * @return Si el curs té un titol, retorna el nom del curs. Altrament, null.
   * @throws Si el curs no té un titol, envia l'excepció «No existeix el nom del curs»
   */
   public function obtenirTitol() {
      if ($this->titol==null)
         throw new Exception('',606);
      return $this->titol;
   }

   /*
   * @brief Obtens el perfil del curs
   * @param $posicio La posicio de la llista de perfils corresponent al curs.
   * @return Si el curs té un perfil i la posicio existeix a la llista de perfils, retorna el perfil del curs. Altrament, null.
   */
   public function obtenirPerfil($posicio) {
      if ($this->perfil == null and count($this->perfil)==0)
         return null;
      else if ($posicio>=0 && $posicio < count($this->perfil))
         return $this->perfil[$posicio];
      else
         return null;
   }

   /*
   * @brief Obtens el perfil pendent del curs
   * @param $posicio La posicio de la llista de perfils corresponent al curs.
   * @return Si el curs té un  pendent i la posicio existeix a la llista de perfils, retorna el perfil del curs. Altrament, null.
   */
   public function obtenirPerfilPendent($posicio) {
      if ($this->perfil_pendent == null and count($this->perfil_pendent)==0)
         return null;
      else if ($posicio>=0 && $posicio < count($this->perfil_pendent))
         return $this->perfil_pendent[$posicio];
      else
         return null;
   }

   /*
   * @brief Obtens el nivell del curs
   * @param $posicio La posicio de la llista de nivells corresponent al curs.
   * @return Si el curs té un nivell i la posicio existeix a la llista de nivells, retorna el perfil del curs. Altrament, null.
   */
   public function obtenirNivells() {
      return $this->idNivells;
   }

   /*
   * @brief Obtens el nivell del curs
   * @param $posicio La posicio de la llista de nivells corresponent al curs.
   * @return Si el curs té un nivell i la posicio existeix a la llista de nivells, retorna el perfil del curs. Altrament, null.
   */
   public function obtenirTemes() {
      return $this->idTemes;
   }

   /*
   * @brief Obtens el nivell del curs
   * @param $posicio La posicio de la llista de nivells corresponent al curs.
   * @return Si el curs té un nivell i la posicio existeix a la llista de nivells, retorna el perfil del curs. Altrament, null.
   */
   protected function obtenirNivell($posicio) {
      if ($this->nivells == null and count($this->nivells)==0)
         return null;
      else if ($posicio>=0 && $posicio < count($this->nivells))
         return $this->nivells[$posicio];
      else
         return null;
   }

   /*
   * @brief Obtens les hores del curs
   * @return Les hores del curs
   * @throws Si el curs no té unes hores, envia l'excepció «No existeix les hores del curs»
   */
   public function obtenirHores() {
      if ($this->hores==null)
         throw new Exception('',607);
      return $this->hores;
   }

   /*
   * @brief Obtens la descripció curta
   * @return La descripció curta
   * @throws Si el curs no té una descripció curta, envia l'excepció «No existeix la descripcio curta del curs»
   */
   protected function obtenirShortDesc() {
      if ($this->short_desc==null)
         throw new Exception('',609);
      return $this->short_desc;
   }

   public function cursEsSubvencionat() {
     return ( $this->tipus->obtenirText() == 'S' );
   }

   public function cursEsNormal() {
     return ( $this->tipus->obtenirText()  == 'N' );
   }

   public function cursEsRepte() {
     return ( $this->tipus->obtenirText()  == 'R' );
   }

   /*
   * @brief Obtens true si les inscripcions de l'any $any i del mes $edicio estan
   obertes. Altrament, retorna false
   * @return Obtens true si les inscripcions de l'any $any i del mes $edicio estan
   obertes. Altrament, retorna false
   */
   public function inscripcionsObertes($any, $mes) {
      $oberta = true;
      $codiCurs = $this->codi->obtenirText();
      $hores = $this->hores->obtenirNumero();

      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

      /* Busco els dies que poden estar obert els cursos després de la data d'inscripció */
      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND VALOR LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("sss", $tipus, $valor, $orderBy);
      $tipus='dies-inscriu-cursos';
      $valor=$hores."%";
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

      // /* Busco si l'edicio està disponible */
      $cnsEd = "SELECT MES, ANY FROM curs AS c INNER JOIN aula AS a ON
         c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
         INNER JOIN honoraris AS h ON r.ID_HONO=h.ID WHERE PUBLIC=1 AND
         c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1
         AND (".$dateDifss.") AND c.CURS NOT LIKE '%JOR%' AND c.PUBLIC = 1 AND
         (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
         OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
         )) AND ANY=? AND MES=? AND c.CURS=? GROUP BY ANY, MES";
      $stmtEd = $connexio->prepare($cnsEd);
      $stmtEd->bind_param("dss", $any, $mes, $codiCurs);
      $stmtEd->execute();
      $stmtEd->store_result();
      if ($stmtEd->num_rows() > 0) {
         $oberta = true;
      }
      else {
         $oberta = false;
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();
      return $oberta;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
   /**
   * @brief Mostra la informació d'un curs (cas 3 en una fila)
   * @return Mostra la informació d'un curs (cas 3 en una fila)
   */
   public function mostrarCurs() {
      $mostrar = "<div class='col-md-4'>".$this->__mostrarCurs("h2")."</div>";
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (cas 4 en una fila)
   * @return Mostra la informació d'un curs (cas 4 en una fila)
   */
   public function mostrarCursRelacionat() {
      $mostrar = "<div class='col-12 col-sm-6 col-lg-3'>".$this->__mostrarCurs("div class='h3 font-weight-bold'")."</div>";
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (cas 3 en una fila)
   * @return Mostra la informació d'un curs (cas 3 en una fila)
   */
   public function mostrarCursosFiltres($head) {
      // ECHO $this->obtenirCodi()->convertirMin()." ".$this->dispositiu;
      $mostrar = "<div class='col-12 col-sm-6 col-lg-4'>".$this->__mostrarCurs($head)."</div>";
      return $mostrar;
   }

   /**
   * @brief Mostra el modal de veure un tastet
   * @return  Mostra la informació breu d'un curs
   */
   public function crearTastet(){
       /* $informacio = new Info($this->obtenirUrl()->obtenirLink(),$this->dispositiu);
      	  $numero_edicions = count($informacio->__obtenirEdicions());

         $comptadorPendents = 0;
      */

      $mostrar='';
      if ($this->dispositiu=='ordinador') {
         $codiCursMin=$this->obtenirCodi()->convertirMin();
         $hores=$this->obtenirHores()->obtenirNumero();
         $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
         $titol=$this->obtenirTitol()->obtenirText();
         $shortDesc=$this->obtenirShortDesc()->obtenirText();

         $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
         $elemLink=explode('/',$linkCurs);
         $extUrl=$elemLink[count($elemLink)-1];

         $urlInsc="https://www.prisma.cat/inscripcions/".$extUrl;
         $urlReg="https://www.prisma.cat/regal/".$extUrl;
         $urlPerfil="https://www.prisma.cat/perfils-professionals/".$extUrl;

         $etiquetaNou=$this->__etiquetaCursNou();
         $etiquetaDesc=$this->etiquetaCursAmbDescompte();
         $etiquetaCDD=$this->__etiquetaCDD2();
         $etiquetaSubv=$this->__etiquetaCursSubvencionat();
         $etiquetes = $etiquetaNou.$etiquetaDes.$etiquetaSubv.$etiquetaCDD;
         $video=$this->obtenirVideo();

         $botoClose="<button role='button'type='button' data-dismiss='modal' aria-label='Close' ";
         $botoClose.="class='modal-close border-0 position-absolute'>×</button>";
         if ($video != null)
            $videoOimg=$botoClose.$video->mostrarVideoTastet();
         else {
            $videoOimg="<div class='img-modal'>".$botoClose."<img role='img' class='w-100' ";
            $videoOimg.="src='https://www.prisma.cat".$this->obtenirImgLarge()->obtenirLink()."' ";
            $videoOimg.="alt=\"".$this->obtenirImgLarge()->obtenirAlt()."\"></div>";
         }

         $mostrar = "<div class='veure-tastet modal fade' id='veure_tastet_".$codiCursMin."' ";
         $mostrar .= "role='dialog'><div class='modal-dialog modal-dialog-centered'>";
         $mostrar .= "<div class='modal-content border-0'><div class='modal-body'>".$videoOimg;
         $mostrar .= "<div class='d-flex flex-row'><div class='col-md-8'><div class='modal-titol-curs ";
         $mostrar .= "border-0 font-weight-bold'>".$etiquetes.$titolHTML."</div>";
         $mostrar .= "<p class='modal-descripcio-curta'>".$shortDesc."</p></div>";
         $mostrar .= "<div class='col-md-4 d-flex flex-column border-left my-2'>";

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

         if ($this->__teEdicionsReconegudes() == 0 && !$inscripcioOberta) {
            $mostrar.="<a role='button' class='inscripcio border-0
            border-radius-2 text-white position-relative text-center negreta500'
            title=\"Inscripcions no disponibles\" style='display:none'></a>";
         }
         else {
            $mostrar .= "<button role='button' class='inscripcio border-0
            border-radius-2 text-white position-relative text-center negreta500 position-relative text-center border-0 text-white'
            title=\"Inscriu-te al curs ".$titol."\" onclick=\"location.href='".$urlInsc."'\">
            <i class='fas fa-edit'></i> Inscripció</button>";
         }
         if ( !$this->esCursSubvencionat() ) {
           $mostrar .= "<button role='button' class='regala border-radius-2 position-relative text-center negreta500' ";
           $mostrar .= "title=\"Regala el curs ".$titol."\" onclick=\"location.href='".$urlReg."'\">";
           $mostrar .= "<i class='fas fa-gift'></i> Regala aquest curs</button>";
         }
         $mostrar .= "<ul>";
         $mostrar .= "<li class='d-flex flex-row align-items-center'><i class='far fa-clock'></i><span class='text'>Durada</span>";
         $mostrar .= "<span class='negreta500'>".$hores." hores</span></li>";

         $cntNiv = 0;
         if (count($this->nivells)==1) {
            $mostrar .= "<li class='d-flex flex-row align-items-center'><i class='fas fa-signal'></i><span class='text'>Nivell</span> ";
            $mostrar .= "<span class='negreta500'>".$this->obtenirNivell($cntNiv)->obtenirText()."</span> ";
         }
         else {
            $mostrar .= "<li class='d-flex flex-row align-items-center nivells'><i class='fas fa-signal'></i>";
            $mostrar .= "<span class='text'>Nivells</span><div class='d-flex flex-column'>";
            while ($cntNiv < count($this->nivells)) {
               $niv=$this->obtenirNivell($cntNiv)->obtenirText();
               if ($cntNiv == 0)
                  $mostrar .= "<span class='niv-apartat niv1 negreta500'>".$niv."</span>";
               else
                  $mostrar .= "<span class='niv-apartat negreta500'>".$niv."</span>";
               $cntNiv++;
            }
            $mostrar .= "</div></li>";
         }

         require_once 'ConnexioBBDD_PreparedStatment.php';
         $connexio = new ConnexioBBDDSTMT();
         $connexio->connectarBD();

         $consPreus = "SELECT IMPORT FROM preu WHERE ID=? AND DATAI <=CURRENT_DATE
                       AND (DATAF IS NULL OR CURRENT_DATE<=DATAF)";
         $stmtPreus = $connexio->prepare($consPreus);
         $stmtPreus->bind_param("d", $idPreu);
         $idPreu=$this->obtenirPreu()->obtenirNumero();
         $stmtPreus->execute();
         $stmtPreus->bind_result($preuCar);
         $stmtPreus->fetch();
         $connexio->closeStmt();

         if ( $import > 0 ) {
           $consDesc = "SELECT PREU FROM descomptes WHERE DATAI<=CURRENT_TIMESTAMP AND
                       (CURRENT_TIMESTAMP<=DATAF OR DATAF IS NULL) AND ID_PREU=? AND
                       TIPUS=? AND (CURS=? OR CURS=? OR CURS='TOTS') ORDER BY ORDRE";
           $stmtDesc = $connexio->prepare($consDesc);
           $stmtDesc->bind_param("ddsd", $idPreu, $tipus, $cursDesc, $horesDesc);
           $tipus=1;
           $cursDesc=$this->obtenirCodi()->obtenirText();
           $horesDesc=$this->obtenirHores()->obtenirNumero();
           $stmtDesc->execute();
           $stmtDesc->bind_result($preuDesc);
           $stmtDesc->fetch();
           $connexio->closeStmt();

           $objPreuDesc = new Numero($preuDesc);
           $preuDescFormatCorrecte = $objPreuDesc->mostrarNumeroDecimalsSense0();

           $objPreuCar = new Numero($preuCar);
           $preuCarFormatCorrecte = $objPreuCar->mostrarNumeroDecimalsSense0();

           $mostrar .= "<li class='d-flex flex-row align-items-center'><i class='far fa-credit-card'></i><span class='text'>Preu</span>";
           $mostrar .= "<span class='negreta500'>".$preuDescFormatCorrecte."-".$preuCarFormatCorrecte." €</span>";
         }
         else {
           $mostrar .= "<li class='d-flex flex-row align-items-center'><i class='far fa-credit-card'></i><span class='text'>Preu</span>";
           $mostrar .= "<span class='negreta500'>Subvencionat</span></li>";
         }

         $mostrar .= $this->filaTaulaCursAmbPerfil();

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
   * @brief Mostra la informació d'un curs (pestanya Docent)
   * @return Mostra la informació d'un curs a la pestanya d'un tutor
   */
   public function mostrarCursTutor($dni_tutor, $midaPantalla) {
      $linkUrl=$this->obtenirUrl()->obtenirLink();
      $linkCurs="https://www.prisma.cat".$linkUrl;
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

      $linkPerfil="https://www.prisma.cat/perfils-professionals/".$extUrl."";
      $linkImg="https://www.prisma.cat".$this->obtenirImgSmall()->obtenirLink();
      $shortDesc=$this->obtenirShortDesc()->obtenirText();
      $dscImg=$this->obtenirImgSmall()->obtenirAlt();
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();
      $codiCursMin=$this->obtenirCodi()->convertirMin();
      $etiquetaNou=$this->__etiquetaCursNou();
      $etiquetaDesc=$this->etiquetaCursAmbDescompte();
      $etiquetaCDD=$this->__etiquetaCDD();
      $etiquetaSubv=$this->__etiquetaCursSubvencionat();

      $etiquetes = $etiquetaNou.$etiquetaDes.$etiquetaSubv.$etiquetaCDD;

      $mostrar = "<div class='row curs-tutoritat-tutor-container border-radius-2
      d-flex flex-column flex-sm-row m-0 mb-2'>";
      $mostrar.="<div class='col-12 col-sm-7 col-lg-8 curs-tutoritat-tutor d-flex flex-column'>
      <div class='clear-both'>".$etiquetes."<a role='link' class='color-text'
      href='".$linkCurs."' target='_self' class='color-text'
      title=\"Informació del curs de PrisMa «".$titol."»\">
      <h3 class='media-heading'>".$titolHTML."</h3></a></div>
      <div class='event-schedule d-flex flex-row align-items-center'>
      <i class='far fa-clock'></i>";
      $mostrar.="<span>".$this->obtenirHores()->obtenirNumero()." h</span>";
      $mostrar.="<i class='fas fa-signal'></i>";

      $cntNiv = 0;
      while ($cntNiv < count($this->nivells)) {
         $niv=$this->obtenirNivell($cntNiv)->obtenirText();
         if ($cntNiv!=0)
            $mostrar .= "<span class='mx-1'>|</span>";
         if ( $cntNiv == count($this->nivells) - 1)
             $mostrar .= "<span>".$niv."</span>";
         else
            $mostrar .= "<span class='m-0'>".$niv."</span>";
         $cntNiv++;
      }

      $mostrar .= $this->etiquetaCursAmbPerfil();
      $mostrar .= $this->etiquetaCursAmbPerfilPendent();

      $mostrar .= "<div class='clear-both'></div></div>";

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consultaHono="SELECT DNI_TUTOR, NOM, COGNOMS, ID_URL FROM honoraris AS h
         INNER JOIN personal AS p ON h.DNI_TUTOR=p.DNI WHERE CURS LIKE ? AND h.ESTAT=1
         AND PERFIL='tutor' AND DNI_TUTOR REGEXP '^[0123456789XYZ]' AND DNI_TUTOR<>?
         GROUP BY h.ORDRE_TUTOR ORDER BY h.ORDRE_TUTOR";
      $stmtHono=$connexio->prepare($consultaHono);
      $stmtHono->bind_param("ss", $codi, $dni_tutor);
      $codi=$this->codi->obtenirText();
      $stmtHono->execute();
      $stmtHono->store_result();
      $numDoc=$stmtHono->num_rows();

      if ($midaPantalla >= 1200) { //versio gran
         $caractersMaximDiversosTutors=420;
         $caractersMaximUnSolTutor=545;
      }
      else if ($midaPantalla >= 992 && $midaPantalla < 1200) { //versio mitjana
         $caractersMaximDiversosTutors=265;
         $caractersMaximUnSolTutor=385;
      }
      else if ($midaPantalla >= 768 && $midaPantalla < 992) { //versio mitjana
         $caractersMaximDiversosTutors=225;
         $caractersMaximUnSolTutor=235;
      }
      else if ($midaPantalla >= 576 && $midaPantalla < 768) { //versio mitjana
         $caractersMaximDiversosTutors=165;
         $caractersMaximUnSolTutor=235;
      }
      else { //versió movil
         $caractersMaximDiversosTutors=1000;
         $caractersMaximUnSolTutor=1000;
      }

      if (strlen($titol) >= 75) {
         $caractersMaximDiversosTutors-=95;
         $caractersMaximUnSolTutor-=95;
      }

      if ($numDoc > 0) {
         $primer=true;
         $tutors='';

         $stmtHono->bind_result($dniTut, $nom, $cognoms, $idUrl);

         while ($stmtHono->fetch()) {
            if ($dniTut != $dni_tutor){
               $tutor = $nom." ".$cognoms;
               $urlTut = new Url($idUrl);
               if ($numDoc == 1 || $primer == true)
                  $primer = false;
               else
                  $tutors .= ", ";
               $tutors .= "<strong><a role='link' target='_self' href='https://www.prisma.cat";
               $tutors .= $urlTut->obtenirLink()."' title=\"".$tutor."\">".$tutor."</a></strong>";
            }
         }

         $connexio->closeStmt();

         if (strlen($shortDesc) <= $caractersMaximDiversosTutors)
            $descMesInfo = $shortDesc;
         else {
            /* Retallo $shortDesc a 280 caracters */
            $retall = substr($shortDesc, 0, $caractersMaximDiversosTutors-1);
            /* Explode espai en blanc */
            $paraules = explode(' ',$retall);
            $num_paraules = count($paraules);
            /* Afegir a $descMesInfo cada paraula del explode excepte la última paraula */
            for ($i = 0; $i < $num_paraules-1; $i++) {
               if ($i==0) $descMesInfo = $paraules[$i];
               else if ($i != $num_paraules-1) $descMesInfo .= " ".$paraules[$i];
            }
            /* Afegir a $descripcio ... al final*/
            $descMesInfo .= " [...]";
         }

         $mostrar .= "<p class='descripcio-curta mt-2 flex-1-0-auto'>".$descMesInfo."</p>";
         $mostrar .= "<p class='altres-tutoritzacio p-0 mb-2'>També el ";
         if ($numDoc==1)
            $mostrar .= "pot ";
         else
            $mostrar .= "poden ";
          $mostrar .= "tutoritzar: ".$tutors."</p>";
      }
      if ($numDoc==0) {
         $connexio->closeStmt();
         if (strlen($shortDesc) <= $caractersMaximUnSolTutor)
            $descMesInfo = $shortDesc;
         else  {
            /*Retallo $shortDesc a 385 caracters*/
            $retall = substr($shortDesc, 0, $caractersMaximUnSolTutor-1);
            /* Explode espai en blanc */
            $paraules = explode(' ',$retall);
            $num_paraules = count($paraules);
            /* Afegir a $descMesInfo cada paraula del explode excepte la última paraula */
            for ($i = 0; $i < $num_paraules-1; $i++) {
               if ($i==0) $descMesInfo = $paraules[$i];
               else if($i != $num_paraules) $descMesInfo .= " ".$paraules[$i];
            }
            $descMesInfo .= " [...]";
         }

         $mostrar .= "<p class='descripcio-curta mt-2'>".$descMesInfo."</p>";
      }
      $connexio->desconectarBD();

      $mostrar.="</div><div class='col-12 col-sm-5 col-lg-4 p-0 d-flex flex-column'>
      <div class='prisma-tutor-overlay h-100'>";
      $mostrar.="<img role='img' src='".$linkImg."' alt=\"".$dscImg."\" ";
      $mostrar.="class='prisma-tutor-image prisma-event-image w-100'";

      if ($this->dispositiu=='ordinador') {
         $mostrar .= "onclick=\"mostrarTaset('".$codiCursMin."',0)\">
         <div class='prisma-tutor-middle position-absolute'>
         <button role='button' id='boto_veure_tastet_".$codiCursMin."'
         class='prisma-tutor-text negreta500 border-0' aria-label=\"Mostra tastet ".$titol."\"
         type='button' title=\"Mostra un tastet del curs «".$titol."»\"
         data-html='true' onclick=\"mostrarTaset('".$codiCursMin."',0)\"
         data-placement='bottom' data-keyboard='true'>Mostra'n un tastet</button></div>";
      }
      else
         $mostrar .= "onclick=\"location.href='https://www.prisma.cat".$linkUrl."'\" >";
      $mostrar .= "</div></div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (pestanya PERFIL)
   * @return Mostra la informació d'un curs a la pestanya d'un perfil
   */
   public function mostrarCursPerfil($midaPantalla) {
      $linkUrl=$this->obtenirUrl()->obtenirLink();

      $linkCurs="https://www.prisma.cat".$linkUrl;
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

      $linkPerfil="https://www.prisma.cat/perfils-professionals/".$extUrl."";
      $linkImg="https://www.prisma.cat".$this->obtenirImgPerfil()->obtenirLink();
      $shortDesc=$this->obtenirShortDesc()->obtenirText();
      $dscImg=$this->obtenirImgPerfil()->obtenirAlt();
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();
      $codiCursMin=$this->obtenirCodi()->convertirMin();
      $etiquetaNou=$this->__etiquetaCursNou();
      $etiquetaSubv=$this->__etiquetaCursSubvencionat();
      $etiquetes = $etiquetaNou.$etiquetaDes.$etiquetaSubv.$etiquetaCDD;
      //   $etiquetaDesc=$this->etiquetaCuetiquetesrsAmbDescompte();

      if ($midaPantalla >= 1200) //versio gran
         $caractersMaxim = 82;
      else if ($midaPantalla >= 992 && $midaPantalla < 1200) //versio mitjana
         $caractersMaxim = 75;
      else //versió movil
         $caractersMaxim = 100;

      if ($this->__esCursNou() != "")
         $caractersMaxim -= 6;

      $classeImatgeGran = ''; //si el titol ocupa dues linies, necessitem que la imatge sigui més gran
      if (strlen($titol) > $caractersMaxim)
         $classeImatgeGran = " imatge-gran";

      $mostrar.="<div class='curs-perfil-container border-radius-2'>";
      $mostrar.="<div class='col-md-8 curs-perfil'><div class='clear-both'>".$etiquetes."<a role='link' ";
      $mostrar.="target='_self' class='color-text' href='".$linkCurs."' ";
      $mostrar.="title=\"Informació del curs de PrisMa «".$titol."»\">";
      $mostrar.="<h4 class='media-heading negreta500'>".$titolHTML."</h4></a></div>";
      $mostrar.="<div class='event-schedule'><div class='element'>";
      $mostrar.="<i class='far fa-clock'></i><span>";
      //   $mostrar.=$this->obtenirHores()->obtenirNumero()." h</span></div>";


      $mostrar.="</span></div>";

      $mostrar.="<div class='element'><i class='fas fa-signal font-size-small'></i>";

      $cntNiv=0;
      while ($cntNiv < count($this->nivells)) {
         $niv=$this->obtenirNivell($cntNiv)->obtenirText();
         if ($cntNiv!=0)
            $mostrar.= " | ";
         $mostrar.="<span class='mx-1'>".$niv."</span>";
         $cntNiv++;
      }

      $mostrar .= "</div><div class='element'><i class='fas fa-tag'></i>";
      $cntPerfil=0;

      while ($this->obtenirPerfil($cntPerfil)!=null) {
         $perfil=$this->obtenirPerfil($cntPerfil);
         $nomPerfilCurt=$perfil->obtenirNomCurt()->obtenirText();

         require_once 'Text.php';
         $textClassPerfil = new Text($nomPerfilCurt);
         $textClassPerfil->netejarAccents();
         $classPerfil = $textClassPerfil->convertirMin();
         if ($cntPerfil!=0)
            $mostrar.=' | ';
         $mostrar.='<span>'.$perfil->obtenirNomCurt()->obtenirText().'</span>';
         $cntPerfil++;
      }
      $mostrar.='</div>';

      $mostrar.="<a role='link' class='consulta-edicions float-left' ";
      $mostrar.="title=\"Totes les edicions que acrediten algun perfil del curs de ";
      $mostrar.="PrisMa «".$titol."»\" href=\"".$linkPerfil."\">Consulta les edicions</a>";
      $mostrar.="</div></div>";
      $mostrar.="<div class='col-md-4 prisma-perfil-overlay'>";
      $mostrar.="<img role='img' src='".$linkImg."' alt=\"".$dscImg."\" ";
      $mostrar.="class='prisma-tutor-image prisma-event-image ".$classeImatgeGran."'";

      if ($this->dispositiu=='ordinador') {
         $mostrar.="onclick=\"mostrarTaset('".$codiCursMin."',0)\">";
         $mostrar.="<div class='prisma-perfil-middle position-absolute'>";
         $mostrar.="<button role='button' id='boto_veure_tastet_".$codiCursMin."' ";
         $mostrar.="class='prisma-perfil-text' aria-label=\"Mostra tastet ".$titol."\" ";
         $mostrar.="type='button' data-html='true' title=\"Tastet del curs ".$titol."\" ";
         $mostrar.="onclick=\"mostrarTaset('".$codiCursMin."',0)\" data-placement='bottom' ";
         $mostrar.="data-keyboard='true'>Mostra'n un tastet</button></div>";
      }
      else
         $mostrar.="onclick=\"location.href='https://www.prisma.cat".$linkUrl."'\">";

      $mostrar .= "</div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (pestanya PERFIL)
   * @return Mostra la informació d'un curs a la pestanya d'un perfil
   */
   public function mostrarCursSubvencionat($midaPantalla) {
      $linkUrl=$this->obtenirUrl()->obtenirLink();

      $linkCurs="https://www.prisma.cat".$linkUrl;
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];

      $linkPerfil="https://www.prisma.cat/perfils-professionals/".$extUrl."";
      $linkImg="https://www.prisma.cat".$this->obtenirImgPerfil()->obtenirLink();
      $shortDesc=$this->obtenirShortDesc()->obtenirText();
      $dscImg=$this->obtenirImgPerfil()->obtenirAlt();
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();
      $codiCursMin=$this->obtenirCodi()->convertirMin();
      $etiquetaNou=$this->__etiquetaCursNou();
      $etiquetaCDD=$this->__etiquetaCDD2();
      $etiquetaSubv=$this->__etiquetaCursSubvencionat();
      $etiquetaSubvComplet=$this->__etiquetaCursPlacesExhauridesSubvencionat();
      $etiquetes = $etiquetaNou.$etiquetaSubv.$etiquetaSubvComplet.$etiquetaCDD;
      //   $etiquetaDesc=$this->etiquetaCursAmbDescompte();

      if ($midaPantalla >= 1200) //versio gran
         $caractersMaxim = 82;
      else if ($midaPantalla >= 992 && $midaPantalla < 1200) //versio mitjana
         $caractersMaxim = 75;
      else //versió movil
         $caractersMaxim = 100;

      if ($this->__esCursNou() != "")
         $caractersMaxim -= 6;

      $classeImatgeGran = ''; //si el titol ocupa dues linies, necessitem que la imatge sigui més gran
      if (strlen($titol) > $caractersMaxim)
         $classeImatgeGran = " imatge-gran";

      $mostrar.="<div class='curs-sub-container border-radius-2 d-flex flex-wrap mb-3 '>";
      $mostrar.="<div class='col-12 col-md-8 curs-sub pt-2 pr-2 pb-1 pl-3'><div class='mb-2 mt-1'>
      <a role='link' ";
      $mostrar.="target='_self' class='color-text' href='".$linkCurs."' ";
      $mostrar.="title=\"Informació del curs de PrisMa «".$titol."»\">";
      $mostrar.="<h4 class='font-weight-bold my-0'>".$titolHTML."</h4></a></div>";
      $mostrar.="<div class='event-schedule d-flex flex-wrap mb-2'><div class='element mr-3'>";
      $mostrar.="<i class='far fa-clock mr-2'></i><span>";
     $mostrar.=$this->obtenirHores()->obtenirNumero()." h</span></div>";

      $mostrar.="<div class='element mr-3'><i class='fas fa-signal font-size-small mr-2'></i>";

      $cntNiv=0;
      while ($cntNiv < count($this->nivells)) {
         $niv=$this->obtenirNivell($cntNiv)->obtenirText();
         if ($cntNiv!=0)
            $mostrar.= " | ";
         $mostrar.="<span class='mx-1'>".$niv."</span>";
         $cntNiv++;
      }

      $mostrar .= "</div><div class='element mr-3'><i class='fas fa-tag mr-2'></i>";
      $cntPerfil=0;

      while ($this->obtenirPerfil($cntPerfil)!=null) {
         $perfil=$this->obtenirPerfil($cntPerfil);
         $nomPerfilCurt=$perfil->obtenirNomCurt()->obtenirText();

         require_once 'Text.php';
         $textClassPerfil = new Text($nomPerfilCurt);
         $textClassPerfil->netejarAccents();
         $classPerfil = $textClassPerfil->convertirMin();
         if ($cntPerfil!=0)
            $mostrar.=' | ';
         $mostrar.='<span>'.$perfil->obtenirNomCurt()->obtenirText().'</span>';
         $cntPerfil++;
      }
      $mostrar.='</div>';

      $mostrar.="<a role='link' class='consulta-edicions float-left' ";
      $mostrar.="title=\"Totes les edicions que acrediten algun perfil del curs de ";
      $mostrar.="PrisMa «".$titol."»\" href=\"".$linkPerfil."\">Consulta les edicions</a>";
      $mostrar.="</div></div>";
      $mostrar.="<div class='col-12 col-md-4 prisma-sub-overlay p-0'>";
      $mostrar.="<img role='img' src='".$linkImg."' alt=\"".$dscImg."\" ";
      $mostrar.="class='w-100 h-100 prisma-tutor-image prisma-event-image ".$classeImatgeGran."'";

      $mostrar.="onclick=\"mostrarTaset('".$codiCursMin."',0)\">";
      $mostrar.="<div class='prisma-sub-middle position-absolute'>";
      $mostrar.="<button role='button' id='boto_veure_tastet_".$codiCursMin."' ";
      $mostrar.="class='prisma-sub-text' aria-label=\"Mostra tastet ".$titol."\" ";
      $mostrar.="type='button' data-html='true' title=\"Tastet del curs ".$titol."\" ";
      $mostrar.="onclick=\"mostrarTaset('".$codiCursMin."',0)\" data-placement='bottom' ";
      $mostrar.="data-keyboard='true'>Mostra'n un tastet</button></div>";

      $mostrar .= "</div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (cas 3 en una fila)
   * @return Mostra la informació d'un curs (cas 3 en una fila)
   */
   public function mostrarCursRegal() {
      $linkImg="https://www.prisma.cat".$this->obtenirImgSmall()->obtenirLink();
      $linkImgWeb=substr($linkImg, 0, -4).".webp";
      $dscImg=$this->obtenirImgSmall()->obtenirAlt();

      $linkUrl=$this->obtenirUrl()->obtenirLink();
      $codiMin=$this->obtenirCodi()->convertirMin();
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();

      /* Si fa quatre mesos de la data d'inici de la primera edició, curs nou = true*/
      $etiquetaNou=$this->__etiquetaCursNou();
      $etiquetaDesc=$this->etiquetaCursAmbDescompte();
      $etiquetaCDD=$this->__etiquetaCDD2();
      $etiquetaSubv=$this->__etiquetaCursSubvencionat();

      $etiquetes = $etiquetaNou.$etiquetaDes.$etiquetaSubv.$etiquetaCDD;

      $mostrar.="<div class='col-12 col-sm-6 col-lg-3 pl-2 pr-2 mb-4'>
      <div id='curs-regal-".$codiMin."' class='curs-regal border rounded'>
      <div class='curs-regal-overlay'>";
         /* Quan encara no ha passat la primera edicio i fins a dues setmanes abans de l'inici
         del curs apareix "A partir de X" on X es el mes escrit i es la primera edicio  */
      $mostrar.=$this->__etiquetaNoHaComencat();
      $mostrar.="<picture>";
         $botoOnClick = "<div class='curs-regal-boto-tastet position-absolute'>
         <div class='curs-regal-text-tastet negret500 border-radius-2 bg-dark border-0 py-1 px-2 text-center'
         title=\"Selecciona el curs ".$titol."\"
         aria-label=\"Selecciona el curs ".$titol."\"
         data-keyboard='true'>Selecciona aquest curs</div></div>";
      $mostrar.="<source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\"
      class='prisma-related-course-image w-100'\" onclick=\"".$onclick."\">
      <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\"
      class='prisma-related-course-image w-100'\" onclick=\"".$onclick."\">
      <img data-src='".$linkImg."' alt=\"".$dscImg."\" class='prisma-related-course-image
      w-100 lazyload' onclick=\"".$onclick."\"></picture>".$botoOnClick."</div>";

      $mostrar.="<div class='curs-regal-titol bg-white'>
      <div class='espai-titol-etiqueta d-flex flex-column pr-3 pl-3 pt-2'>
      <div class='titol flex-grow-1'>".$etiquetes;
      $mostrar.="<a role='link' class='color-text' href='https://www.prisma.cat".$linkUrl."' title=\"Mostra la informació del curs ".$titol."\">
      <div class='negreta500'>".$titolHTML."</div></a></div>";
      if ( $this->perfil_pendent == null)
		  $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-column flex-md-row'>";
     else
		  $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-column'>";

      $mostrar .= $this->etiquetaCursAmbPerfil();
      $mostrar .= $this->etiquetaCursAmbPerfilPendent();
      $mostrar.="</div></div></div>";
      $mostrar.="<div class='curs-regal-footer'>
      <button class='mesinfo position-relative negreta500 border-0 border-radius-2
      w-100 flex-shrink-1 px-2 py-2' target='_self' onclick=\"mostraInfoCurs('https://www.prisma.cat".$linkUrl."')\">Informació del curs
      <i class='fas fa-long-arrow-alt-right ml-2'></i></button>
      </div>
      </div></div>";
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (cas 3 en una fila)
   * @return Mostra la informació d'un curs (cas 3 en una fila)
   */
   public function mostrarCursBescanvia() {
      $linkImg="https://www.prisma.cat".$this->obtenirImgSmall()->obtenirLink();
      $linkImgWeb=substr($linkImg, 0, -4).".webp";
      $dscImg=$this->obtenirImgSmall()->obtenirAlt();

      $linkUrl=$this->obtenirUrl()->obtenirLink();
      $codiMin=$this->obtenirCodi()->convertirMin();
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();

      /* Si fa quatre mesos de la data d'inici de la primera edició, curs nou = true*/
      $etiquetaNou=$this->__etiquetaCursNou();
      $etiquetaDesc=$this->etiquetaCursAmbDescompte();
      $etiquetaCDD=$this->__etiquetaCDD2();
      $etiquetaSubv=$this->__etiquetaCursSubvencionat();

      $etiquetes = $etiquetaNou.$etiquetaDes.$etiquetaSubv.$etiquetaCDD;

      $mostrar.="<div class='col-12 col-sm-6 col-lg-3 pl-2 pr-2 mb-4'>";
         $mostrar.="<div class='curs-regal border rounded'>";
            $mostrar.="<div class='curs-regal-overlay'>";
               /* Quan encara no ha passat la primera edicio i fins a dues setmanes abans de l'inici
               del curs apareix "A partir de X" on X es el mes escrit i es la primera edicio  */
               $mostrar.=$this->__etiquetaNoHaComencat();
               $mostrar.="<picture>";
               if ($this->dispositiu=='ordinador') {
                  $onclick = "mostrarTaset('".$codiMin."',0)";
                  $botoOnClick = "<div class='curs-regal-boto-tastet position-absolute'>
                  <button role='button' id='boto_veure_tastet_".$codiMin."'
                  class='curs-regal-text-tastet negreta500 border-radius-2 bg-dark border-0 py-1 px-2'
                  type='button' data-html='true' data-placement='bottom'
                  title=\"Mostra el tastet del curs ".$titol."\"
                  onclick=\"mostrarTaset('".$codiMin."',0)\"
                  aria-label=\"Mostra tastet ".$titol."\"
                  data-keyboard='true'>Mostra'n un tastet</button></div>";
               }
               else {
                  $onclick = "location.href='https://www.prisma.cat".$linkUrl."'";
                  $botoOnClick = "";
               }

                  $mostrar.="<source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\" class='prisma-related-course-image w-100'\" onclick=\"".$onclick."\">";
                  $mostrar.="<source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\" class='prisma-related-course-image w-100'\" onclick=\"".$onclick."\">";
                  $mostrar.="<img data-src='".$linkImg."' alt=\"".$dscImg."\" class='prisma-related-course-image w-100 lazyload' onclick=\"".$onclick."\"></picture>";
               $mostrar.="</picture>";
               $mostrar.=$botoOnClick;
            $mostrar.="</div>";
            $mostrar.="<div class='curs-regal-titol bg-white'>";
               $mostrar.="<div class='espai-titol-etiqueta d-flex flex-column pr-3 pl-3 pt-2'>";
                  $mostrar.="<div class='titol flex-grow-1'>".$etiquetes;
                     $mostrar.="<a role='link' class='color-text' href='https://www.prisma.cat".$linkUrl."' title=\"Mostra la informació del curs ".$titol."\">";
                        $mostrar.="<div class='negreta500'>".$titolHTML."</div>";
                     $mostrar.="</a>";
                  $mostrar.="</div>";
                  if ( $this->perfil_pendent == null)
   					  $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-column flex-md-row'>";
                 else
   					  $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-column'>";
                 $mostrar .= $this->etiquetaCursAmbPerfil();
                 $mostrar .= $this->etiquetaCursAmbPerfilPendent();
                  $mostrar.="</div>";
               $mostrar.="</div>";
            $mostrar.="</div>";
            $mostrar.="<div class='curs-regal-footer'>";
               $mostrar.="<button class='mesinfo position-relative negreta500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2' target='_self' onclick=\"escolleixCurs('".$codiMin."')\">Tria'l</button>";
            $mostrar.="</div>";
         $mostrar.="</div>";
      $mostrar.="</div>";
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs de la pàgina inscripció del bescanvia
   * @return Mostra la informació d'un curs de la pàgina inscripció del bescanvia
   */
   public function mostrarCursBescanviaInscripcio($midaPantalla) {
      $linkImg="https://www.prisma.cat".$this->obtenirImgSmall()->obtenirLink();
      $linkImgWeb=substr($linkImg, 0, -4).".webp";
      $dscImg=$this->obtenirImgSmall()->obtenirAlt();

      $linkUrl=$this->obtenirUrl()->obtenirLink();
      $codiMin=$this->obtenirCodi()->convertirMin();
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();
      $shortDesc=$this->obtenirShortDesc()->obtenirText();

      /* Si fa quatre mesos de la data d'inici de la primera edició, curs nou = true*/
      $etiquetaNou=$this->__etiquetaCursNou();
      $etiquetaDesc=$this->etiquetaCursAmbDescompte();

      $mostrar.="<div class='col-12 pl-0 pr-0 mb-4'>";
         $mostrar.="<div class='curs-bescanvia d-flex flex-column flex-md-row border rounded'>";
            $mostrar.="<div class='curs-bescanvia-titol col-12 col-md-8 pr-0 pl-0 bg-white'>";
               $mostrar.="<div class='espai-titol-etiqueta  d-flex flex-column pr-3 pl-3 pt-3'>";
                  $mostrar.="<div class='titol flex-grow-1'>".$etiquetaNou.$etiquetaDesc;
                     $mostrar.="<a role='link' class='color-text' href='https://www.prisma.cat".$linkUrl."' title=\"Mostra la informació del curs ".$titol."\">";
                        $mostrar.="<div class='font-weight-bold mb-2'>".$titolHTML."</div>";
                     $mostrar.="</a>";
                  $mostrar.="</div>";
                  $mostrar.="<div class='d-flex align-items-center'>";

                     $mostrar.="<i class='far fa-clock mr-1'></i>";
                     $mostrar.="<span>".$this->obtenirHores()->obtenirNumero()." h</span>";

                     $mostrar.="<i class='fas fa-signal mr-1 ml-2'></i>";
                     $cntNiv = 0;
                     while ($cntNiv < count($this->nivells)) {
                        $niv=$this->obtenirNivell($cntNiv)->obtenirText();
                        if ($cntNiv!=0)
                           $mostrar .= " | ";
                        $mostrar .= "<span class='mx-1'>".$niv."</span>";
                        $cntNiv++;
                     }

                  $mostrar .= $this->etiquetaCursAmbPerfil();
                  $mostrar .= $this->etiquetaCursAmbPerfilPendent();

                  $mostrar.="</div>";
                  $mostrar.="<div class='d-flex flex-column w-100 mt-2'>";
                     require_once 'ConnexioBBDD_PreparedStatment.php';
                     $connexio = new ConnexioBBDDSTMT();
                     $connexio->connectarBD();

                     $consultaHono="SELECT DNI_TUTOR, NOM, COGNOMS, ID_URL FROM honoraris AS h
                        INNER JOIN personal AS p ON h.DNI_TUTOR=p.DNI WHERE CURS LIKE ? AND h.ESTAT=1
                        AND PERFIL='tutor' AND DNI_TUTOR REGEXP '^[0123456789XYZ]'
                        GROUP BY h.ORDRE_TUTOR ORDER BY h.ORDRE_TUTOR";

                     $stmtHono=$connexio->prepare($consultaHono);
                     $stmtHono->bind_param("s", $codi);
                     $codi=$this->obtenirCodi()->obtenirText();

                     $stmtHono->execute();
                     $stmtHono->store_result();
                     $numDoc=$stmtHono->num_rows();

                     if ($midaPantalla >= 1200) { //versio gran
                        $caractersMaximDiversosTutors=420;
                     }
                     else if ($midaPantalla >= 992 && $midaPantalla < 1200) { //versio mitjana
                        $caractersMaximDiversosTutors=265;
                     }
                     else { //versió movil
                        $caractersMaximDiversosTutors=1000;
                     }

                     if (strlen($titol) >= 75) {
                        $caractersMaximDiversosTutors-=95;
                     }

                     $primer=true;
                     $tutors='';

                     $stmtHono->bind_result($dniTut, $nom, $cognoms, $idUrl);
                     $nDoc=1;
                     while ($stmtHono->fetch()) {
                        $tutor = $nom." ".$cognoms;
                        $urlTut = new Url($idUrl);
                        if ($numDoc == 1 || $primer == true)
                           $primer = false;
                        else if ($numDoc > 1 && $nDoc==$numDoc)
                           $tutors .= " i ";
                        else
                           $tutors .= ", ";
                        $tutors .= "<strong><a role='link' target='_self' href='https://www.prisma.cat";
                        $tutors .= $urlTut->obtenirLink()."' title=\"".$tutor."\">".$tutor."</a></strong>";
                        $nDoc++;
                     }

                     $connexio->closeStmt();

                     // if (strlen($shortDesc) <= $caractersMaximDiversosTutors)
                        $descMesInfo = $shortDesc;
                     // else {
                     //    /* Retallo $shortDesc a 280 caracters */
                     //    $retall = substr($shortDesc, 0, $caractersMaximDiversosTutors-1);
                     //    /* Explode espai en blanc */
                     //    $paraules = explode(' ',$retall);
                     //    $num_paraules = count($paraules);
                     //    /* Afegir a $descMesInfo cada paraula del explode excepte la última paraula */
                     //    for ($i = 0; $i < $num_paraules-1; $i++) {
                     //       if ($i==0) $descMesInfo = $paraules[$i];
                     //       else if ($i != $num_paraules-1) $descMesInfo .= " ".$paraules[$i];
                     //    }
                     //    /* Afegir a $descripcio ... al final*/
                     //    $descMesInfo .= " [...]";
                     // }

                     $mostrar .= "<p class='descripcio-curta'>".$descMesInfo."</p>";
                     $mostrar .= "<p class='altres-tutoritzacio p-0'>El ";
                     if ($numDoc==1)
                        $mostrar .= "pot ";
                     else
                        $mostrar .= "poden ";
                      $mostrar .= "tutoritzar: ".$tutors."</p>";
                     $connexio->desconectarBD();
                  $mostrar.="</div>";
               $mostrar.="</div>";
            $mostrar.="</div>";
            $mostrar.="<div class='curs-bescanvia-overlay col-12 col-md-4 pr-0 pl-0 position-relative'>";
               /* Quan encara no ha passat la primera edicio i fins a dues setmanes abans de l'inici
               del curs apareix "A partir de X" on X es el mes escrit i es la primera edicio  */
               $mostrar.=$this->__etiquetaNoHaComencat();
               $mostrar.="<picture>";
               if ($this->dispositiu=='ordinador') {
                  $onclick = "mostrarTaset('".$codiMin."',0)";
                  $botoOnClick = "<div class='curs-regal-boto-tastet position-absolute'>
                  <button role='button' id='boto_veure_tastet_".$codiMin."'
                  class='curs-regal-text-tastet border-radius-2 bg-dark border-0 py-1 px-2'
                  type='button' data-html='true' data-placement='bottom'
                  title=\"Mostra el tastet del curs ".$titol."\"
                  onclick=\"mostrarTaset('".$codiMin."',0)\"
                  aria-label=\"Mostra tastet ".$titol."\"
                  data-keyboard='true'>Mostra'n un tastet</button></div>";
               }
               else {
                  $onclick = "location.href='https://www.prisma.cat".$linkUrl."'";
                  $botoOnClick = "";
               }

               $mostrar.="<source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\" class='prisma-related-bescanvia-image w-100'\" onclick=\"".$onclick."\">";
               $mostrar.="<source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\" class='prisma-related-bescanvia-image w-100'\" onclick=\"".$onclick."\">";
               $mostrar.="<img data-src='".$linkImg."' alt=\"".$dscImg."\" class='prisma-related-bescanvia-image w-100 lazyload' onclick=\"".$onclick."\"></picture>";
               $mostrar.="</picture>";
               $mostrar.=$botoOnClick;
            $mostrar.="</div>";
         $mostrar.="</div>";
      $mostrar.="</div>";
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (cas 3 en una fila)
   * @return Mostra la informació d'un curs (cas 3 en una fila)
   */
   public function mostrarCursDescompteGrup() {
      $linkImg="https://www.prisma.cat".$this->obtenirImgSmall()->obtenirLink();
   	$linkImgWeb=substr($linkImg, 0, -4).".webp";
   	$dscImg=$this->obtenirImgSmall()->obtenirAlt();

   	$linkUrl=$this->obtenirUrl()->obtenirLink();
   	$codiMin=$this->obtenirCodi()->convertirMin();
   	$titolHTML=$this->obtenirTitol()->obtenirTextHTML();
   	$titol=$this->obtenirTitol()->obtenirText();

   	/* Si fa quatre mesos de la data d'inici de la primera edició, curs nou = true*/
   	$etiquetaNou=$this->__etiquetaCursNou();
   	$etiquetaDesc=$this->etiquetaCursAmbDescompte();

   	$mostrar.="<div class='col-12 col-sm-6 col-lg-3 pl-2 pr-2 mb-4'>";
   		$mostrar.="<div id='descompte-grup-".$codiMin."' class='curs-descompte-grup border rounded'>";
   			$mostrar.="<div class='curs-descompte-grup-overlay'>";
   				/* Quan encara no ha passat la primera edicio i fins a dues setmanes abans de l'inici
   				del curs apareix "A partir de X" on X es el mes escrit i es la primera edicio  */
   				$mostrar.=$this->__etiquetaNoHaComencat();
   				$mostrar.="<picture>";
   					$botoOnClick = "<div class='curs-descompte-grup-boto-tastet position-absolute'>
   					<div class='curs-descompte-grup-text-tastet negreta500 border-radius-2 bg-dark border-0 py-1 px-2 text-center'
   					title=\"Selecciona el curs ".$titol."\"
   					aria-label=\"Selecciona el curs ".$titol."\"
   					data-keyboard='true'>Selecciona aquest curs</div></div>";

   					$mostrar.="<source type='image/webp' data-srcset='".$linkImgWeb."' alt=\"".$dscImg."\" class='prisma-related-course-image w-100'\">";
   					$mostrar.="<source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\" class='prisma-related-course-image w-100'\">";
   					$mostrar.="<img data-src='".$linkImg."' alt=\"".$dscImg."\" class='prisma-related-course-image w-100 lazyload'></picture>";
   				$mostrar.="</picture>";
   				$mostrar.=$botoOnClick;
   			$mostrar.="</div>";
   			$mostrar.="<div class='curs-descompte-grup-titol bg-white'>";
   				$mostrar.="<div class='espai-titol-etiqueta d-flex flex-column pr-3 pl-3 pt-2'>";
   					$mostrar.="<div class='titol flex-grow-1'>".$etiquetaNou.$etiquetaDesc;
   							$mostrar.="<div class='negreta500'>".$titolHTML."</div>";
   					$mostrar.="</div>";
                  if ( $this->perfil_pendent == null)
   					  $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-column flex-md-row'>";
                 else
   					  $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-column'>";
   					$mostrar .= $this->etiquetaCursAmbPerfil();
   					$mostrar .= $this->etiquetaCursAmbPerfilPendent();
   					$mostrar.="</div>";
   				$mostrar.="</div>";
   			$mostrar.="</div>";
   			$mostrar.="<div class='curs-descompte-grup-footer'>";
   				$mostrar.="<button class='mesinfo position-relative negreta500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2' target='_self' onclick=\"mostraInfoCurs('https://www.prisma.cat".$linkUrl."')\">Informació del curs<i class='fas fa-long-arrow-alt-right ml-2'></i></button>";
   			$mostrar.="</div>";
   		$mostrar.="</div>";
   	$mostrar.="</div>";
   	return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (cas 4 en una fila i bloc perfils professionals )
   * @return Mostra la informació d'un curs (cas 4 en una fila i bloc perfils professionals )
   */
   public function mostrarCursBlocPerfils() {
      $linkImg="https://www.prisma.cat".$this->obtenirImgSmall()->obtenirLink();
      $dscImg=$this->obtenirImgSmall()->obtenirAlt();
      $codiMin=$this->obtenirCodi()->convertirMin();
      $idtastet="veure_tastet_".$codiMin;
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();
      $linkUrl=$this->obtenirUrl()->obtenirLink();

      $codi=$this->obtenirCodi()->obtenirText();

      $mostrar="<div class='col-12 col-sm-6 col-lg-3'><div class='prisma-related-course border-radius-2'>
      <div class='prisma-related-course-overlay'>";

      /* Quan encara no ha passat la primera edicio i fins a dues setmanes abans de l'inici
      del curs apareix "A partir de X" on X es el mes escrit i es la primera edicio  */
      $mostrar.=$this->__etiquetaNoHaComencat();
      /* Si fa quatre mesos de la data d'inici de la primera edició, curs nou = true*/
      $etiquetaNou=$this->__etiquetaCursNou();
      $etiquetaDesc=$this->etiquetaCursAmbDescompte();
      $etiquetaCDD=$this->__etiquetaCDD2();
      $etiquetaSubv=$this->__etiquetaCursSubvencionat();

      $etiquetes = $etiquetaNou.$etiquetaDes.$etiquetaSubv.$etiquetaCDD;

      $linkImgOrig = substr($linkImg, 0, -4);
      $linkImgWeb = $linkImgOrig.".webp";

      $linkImg540JPG=$linkImgOrig."-345.jpg";
      $linkImg345JPG=$linkImgOrig."-345.jpg";
      $linkImg210JPG=$linkImgOrig."-210.jpg";
      $linkImg260JPG=$linkImgOrig."-260.jpg";
      $linkImg540Webp=$linkImgOrig."-345.webp";
      $linkImg345Webp=$linkImgOrig."-345.webp";
      $linkImg210Webp=$linkImgOrig."-210.webp";
      $linkImg260Webp=$linkImgOrig."-260.webp";

      if ($this->dispositiu=='ordinador')
         $onclick = " onclick=\"mostrarTaset('".$codiMin."',0)\"";
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
         $mostrar .= "<div class='prisma-course-middle position-absolute'>
         <button role='button' id='boto_veure_tastet_".$codiMin."'
         class='prisma-course-text border-radius-2' data-placement='bottom'
         type='button' data-html='true' title=\"Mostra el tastet del curs ".$titol."\"
         onclick=\"mostrarTaset('".$codiMin."',0)\" aria-label=\"Mostra tastet ".$titol."\"
         data-keyboard='true'>Mostra'n un tastet</button></div>";
      }

      $mostrar .= "</div><div class='related-course-content px-3 border-radius-2'>";
      $mostrar .= "<div class='espai-titol-etiqueta d-flex flex-column'><div class='titol flex-1-0-auto'>".$etiquetaNou.$etiquetaDesc;
      $mostrar .= "<a role='link' class='color-text' href='https://www.prisma.cat".$linkUrl."' ";
      $mostrar .= "title=\"Mostra la informació del curs ".$titol."\">";
      $mostrar .= "<div class='h3 font-weight-bold'>".$etiquetes.$titolHTML."</div class='h3'></a></div>";
      $mostrar .= "<div class='perfil-curs-related mt-2 d-flex flex-wrap flex-row'>";

      $mostrar .= $this->etiquetaCursAmbPerfil();
      $mostrar .= $this->etiquetaCursAmbPerfilPendent();

      $mostrar .= "</div></div><div class='related-course-footer clear-both d-flex flex-row align-items-center'>";
      $mostrar .= "<span class='hores flex-1-0-auto'><i class='fas fa-clock'></i>";
      $mostrar .= $this->obtenirHores()->obtenirNumero()." h</span>";
      $mostrar .= "<span class='mes-informacio'>";
      $mostrar .= "<a role='link' class='mes-informacio' href='".$linkUrl."' ";
      $mostrar .= "target='".$this->obtenirUrl()->obtenirTarget()."' ";
      $mostrar .= "title=\"Mostra més informació del curs ".$titol."\">Més informació ";
      $mostrar .= "<i class='fas fa-long-arrow-alt-right'></i></a></span>";
      $mostrar .= "<div style='clear: both;'></div></div></div></div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs (cas 4 en una fila i pàgina trobades)
   * @return Mostra la informació d'un curs (cas 4 en una fila i pàgina trobades)
   */
   public function mostrarCursRelTrobada() {
      $linkImg="https://www.prisma.cat".$this->obtenirImgSmall()->obtenirLink();
      $dscImg=$this->obtenirImgSmall()->obtenirAlt();
      $codiMin=$this->obtenirCodi()->convertirMin();
      $idtastet="veure_tastet_".$codiMin;
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();
      $linkUrl=$this->obtenirUrl()->obtenirLink();

      $codi=$this->obtenirCodi()->obtenirText();

      $mostrar="<div class='col-12 col-sm-6 col-lg-3 p-2'>
      <div class='rel-curs border-radius-2 mb-4'>
      <div class='rel-curs-img position-relative'>";

      /* Quan encara no ha passat la primera edicio i fins a dues setmanes abans de l'inici
      del curs apareix "A partir de X" on X es el mes escrit i es la primera edicio  */
      $mostrar.=$this->__etiquetaNoHaComencat();
      /* Si fa quatre mesos de la data d'inici de la primera edició, curs nou = true*/
      $etiquetaNou=$this->__etiquetaCursNou();
      $etiquetaDesc=$this->etiquetaCursAmbDescompte();
      $etiquetaCDD=$this->__etiquetaCDD2();
      $etiquetaSubv=$this->__etiquetaCursSubvencionat();

      $etiquetes = $etiquetaNou.$etiquetaDes.$etiquetaSubv.$etiquetaCDD;

      $linkImgWeb=substr($linkImg, 0, -4).".webp";

      $mostrar .= "<picture><source type='image/webp' data-srcset='".$linkImgWeb."'
      alt=\"".$dscImg."\" class='w-100' ";
      if ($this->dispositiu=='ordinador') {
         $mostrar .= "onclick=\"mostrarTaset('".$codiMin."',0)\">
         <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\" class='w-100'
         onclick=\"mostrarTaset('".$codiMin."',0)\">";
         $mostrar .= "<img data-src='".$linkImg."' alt=\"".$dscImg."\" class='w-100 lazyload' ";
         $mostrar .= "onclick=\"mostrarTaset('".$codiMin."',0)\"></picture>";
         $mostrar .= "<div class='position-absolute'>";
         $mostrar .= "<button role='button' id='boto_veure_tastet_".$codiMin."' ";
         $mostrar .= "class='border-0 text-white border-radius-2 px-2 py-1' data-placement='bottom' ";
         $mostrar .= "type='button' data-html='true' title=\"Mostra el tastet del curs ".$titol."\" ";
         $mostrar .= "onclick=\"mostrarTaset('".$codiMin."',0)\" aria-label=\"Mostra tastet ".$titol."\" ";
         $mostrar .= "data-keyboard='true'>Mostra'n un tastet</button></div>";
      }
      else {
         $mostrar .= "onclick=\"location.href='https://www.prisma.cat".$linkUrl."'\">
         <source type='image/jpeg' data-srcset='".$linkImg."' alt=\"".$dscImg."\" class='w-100'
         onclick=\"location.href='https://www.prisma.cat".$linkUrl."'\">";
         $mostrar .= "<img data-src='".$linkImg."' alt=\"".$dscImg."\" class='w-100 lazyload' ";
         $mostrar .= "onclick=\"location.href='https://www.prisma.cat".$linkUrl."'\"></picture>";
      }
      $mostrar .= "</div>
      <div class='rel-curs-body bg-white px-3 pt-2 pb-1 border-radius-2'>
      <div class='d-flex flex-column pt-1'>
      <div class='titol flex-1-0-auto'>".$etiquetes."
      <a role='link' class='color-text' href='https://www.prisma.cat".$linkUrl."'
      title=\"Mostra la informació del curs ".$titol."\">
      <div class='h3 font-weight-bold'>".$titolHTML."</div>
      </a></div>";
      if ( $this->perfil_pendent == null)
        $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-md-row flex-wrap'>";
     else
        $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-md-row flex-wrap'>";
      $mostrar .= $this->etiquetaCursAmbPerfil();
      $mostrar .= $this->etiquetaCursAmbPerfilPendent();
      $mostrar .= "</div></div><div class='rel-curs-footer d-flex flex-row flex-wrap py-2'>";
      $mostrar .= "<span class='flex-1-0-auto mr-1'><i class='fas fa-clock mr-2'></i>";
      $mostrar .= $this->obtenirHores()->obtenirNumero()." h</span>";
      $mostrar .= "<span class='negreta500'>";
      $mostrar .= "<a role='link' class='m-0' href='".$linkUrl."' ";
      $mostrar .= "target='".$this->obtenirUrl()->obtenirTarget()."' ";
      $mostrar .= "title=\"Mostra més informació del curs ".$titol."\">Més informació ";
      $mostrar .= "<i class='fas fa-long-arrow-alt-right'></i></a></span>";
      $mostrar .= "<div style='clear: both;'></div></div></div></div></div>";

      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un curs
   * @return Mostra la informació d'un curs
   */
   private function __mostrarCurs($tagTitol) {
      $linkImg="https://www.prisma.cat".$this->obtenirImgSmall()->obtenirLink();
      $dscImg=$this->obtenirImgSmall()->obtenirAlt();
      $codiMin=$this->obtenirCodi()->convertirMin();
      $idtastet="veure_tastet_".$codiMin;
      $titolHTML=$this->obtenirTitol()->obtenirTextHTML();
      $titol=$this->obtenirTitol()->obtenirText();
      $linkUrl=$this->obtenirUrl()->obtenirLink();

      $codi=$this->obtenirCodi()->obtenirText();

      $mostrar="<div class='prisma-related-course border-radius-2'>
      <div class='prisma-related-course-overlay'>";

      /* Quan encara no ha passat la primera edicio i fins a dues setmanes abans de l'inici
      del curs apareix "A partir de X" on X es el mes escrit i es la primera edicio  */
      $mostrar.=$this->__etiquetaNoHaComencat();
      /* Si fa quatre mesos de la data d'inici de la primera edició, curs nou = true*/
      $etiquetaNou=$this->__etiquetaCursNou();
      $etiquetaDesc=$this->etiquetaCursAmbDescompte();
      $etiquetaCDD=$this->__etiquetaCDD2();
      $etiquetaSubv=$this->__etiquetaCursSubvencionat();

      $etiquetes = $etiquetaNou.$etiquetaDes.$etiquetaSubv.$etiquetaCDD;

      $linkImgOrig = substr($linkImg, 0, -4);
      $linkImgWeb = $linkImgOrig.".webp";

      $linkImg540JPG=$linkImgOrig."-345.jpg";
      $linkImg345JPG=$linkImgOrig."-345.jpg";
      $linkImg210JPG=$linkImgOrig."-210.jpg";
      $linkImg260JPG=$linkImgOrig."-260.jpg";
      $linkImg540Webp=$linkImgOrig."-345.webp";
      $linkImg345Webp=$linkImgOrig."-345.webp";
      $linkImg210Webp=$linkImgOrig."-210.webp";
      $linkImg260Webp=$linkImgOrig."-260.webp";

      if ($this->dispositiu=='ordinador')
         $onclick = " onclick=\"mostrarTaset('".$codiMin."',0)\"";
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
         $mostrar .= "<div class='prisma-course-middle position-absolute'>
         <button role='button' id='boto_veure_tastet_".$codiMin."'
         class='prisma-course-text border-radius-2' data-placement='bottom'
         type='button' data-html='true' title=\"Mostra el tastet del curs ".$titol."\"
         onclick=\"mostrarTaset('".$codiMin."',0)\" aria-label=\"Mostra tastet ".$titol."\"
         data-keyboard='true'>Mostra'n un tastet</button></div>";
      }
      $mostrar .= "</div><div class='related-course-content px-3 border-radius-2'>";
      $mostrar .= "<div class='espai-titol-etiqueta d-flex flex-column'><div class='titol flex-1-0-auto'>".$etiquetes;
      $mostrar .= "<a role='link' class='color-text' href='https://www.prisma.cat".$linkUrl."' ";
      $mostrar .= "title=\"Mostra la informació del curs ".$titol."\">";
      $mostrar .= "<".$tagTitol.">".$titolHTML."</".$tagTitol."></a></div>";
      $mostrar.="<div class='perfil-curs-related mt-2 d-flex flex-row flex-wrap'>";
      $mostrar .= $this->etiquetaCursAmbPerfil();
      $mostrar .= $this->etiquetaCursAmbPerfilPendent();
      $mostrar .= "</div></div><div class='related-course-footer clear-both d-flex flex-row align-items-center justify-content-between'>";
      $mostrar .= "<span class='hores'><i class='fas fa-clock'></i>";
      $mostrar .= $this->obtenirHores()->obtenirNumero()." h</span>";
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
      /* Fem la connexió a la BD */
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();
      $cursNou = false;
      $noDerivaCurs = true;
      /* busco la primer data d'inici i el mes.
         Si la (data d'inici - dues setmanes) és posterior al current_date, $curs_a_partir = true;
         Si la (data d'inici + quatre mesos) és posterior al current date, $curs_nou = true; */
      $cnsDatai="SELECT DATAI, MES FROM curs WHERE CURS=? AND ESTAT!=0 ORDER BY ANY, MES LIMIT 1";
      $stmt=$connexio->prepare($cnsDatai);
      $stmt->bind_param("s", $codi);
      $codi=$this->obtenirCodi()->obtenirText();
      $stmt->execute();
      $stmt->bind_result($dataiPrimeraEdicio, $mes);
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

      $cnsDeriva="SELECT DERIVA FROM informacio WHERE CODI_CURS=?";
      $stmt=$connexio->prepare($cnsDeriva);
      $stmt->bind_param("s", $codi);
      $codi=$this->obtenirCodi()->obtenirText();
      $stmt->execute();
      $stmt->bind_result($deriva);
      $stmt->fetch();
      $connexio->closeStmt();
      if ($deriva!=null AND $deriva!='')
         $noDerivaCurs=false;
      $connexio->desconectarBD();

      if ($dataDeixaDeSerNou > strtotime(date("Y-m-d")) && $noDerivaCurs) {
         $cursNou = true;
      }
      return $cursNou;
   }

   /**
   * @brief Comprova si el curs té CDD
   * @return Retirna si el curs té CDD
   */
   public function esCursSubvencionat() {
     $esCursSub = 0;
     if ( $this->tipus != null && $this->tipus->obtenirText() == 'S' )
        $esCursSub = 1;
      return $esCursSub;
   }

   /**
   * @brief Comprova si el curs té CDD
   * @return Retirna si el curs té CDD
   */
   public function placesExharuidesSubv() {
      $complet = 0;

      $codiCurs = $this->obtenirCodi()->obtenirText();

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

   /**
   * @brief Comprova si el curs té CDD
   * @return Retirna si el curs té CDD
   */
   public function esCDD() {
      return $this->cdd != 0;
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

   /**
   * @brief Mostra la etiqueta del curs nou
   * @return Si el curs és nou, mostra la etiqueta del curs nou
   */
   protected function __etiquetaCursNou() {
      $etiquetaNou='';
      if ($this->__esCursNou())
         $etiquetaNou = "<div class='nou float-left'>NOU</div>";
      return $etiquetaNou;
   }

   /**
   * @brief Mostra la etiqueta del curs nou
   * @return Si el curs és nou, mostra la etiqueta del curs nou
   */
   protected function __etiquetaCursSubvencionat() {
     $etiqueta = '';
      if ( $this->esCursSubvencionat() )
        $etiqueta = "<div class='nou subvencio text-white float-left'>SUBVENCIÓ</div>";
      return $etiqueta;
   }
   protected function __etiquetaCursPlacesExhauridesSubvencionat() {
     $etiqueta = '';
      if ( $this->esCursSubvencionat() && $this->placesExharuidesSubv())
        $etiqueta = "<div class='nou subvencio text-white float-left' style='background: var(--bg-danger)!important;'>COMPLET</div>";
      return $etiqueta;
   }

   /**
   * @brief Mostra la etiqueta en format banderola
   * @return Si el curs te CDD, mostra la etiqueta de «CDD» en format banderola
   */
   protected function __etiquetaCDD() {
      $etiqueta = '';
      if ( $this->esCDD() ) {
         $objTxt = new Text("Acreditació CDD");
         $txt = $objTxt->obtenirTextHTML();
         $etiqueta = "<div class='etiquetaCDD position-absolute'>
         <div class='nom'>".$txt."</div></div>";

         $nivellCDD = $this->getNivellCDD();

        if ( $nivellCDD != '' ) {
          $objTxt = new Text($nivellCDD);
          $txt = $objTxt->obtenirTextHTML();
          $etiqueta .= "<div class='etiquetaCDD position-absolute'>
          <div class='nom'>".$txt."</div></div>";
        }

      }

      return $etiqueta;
   }

   /**
   * @brief Mostra la etiqueta en format etiqueta NOU
   * @return Si el curs te CDD, mostra la etiqueta de «CDD» en format  etiqueta NOU
   */
   protected function __etiquetaCDD2() {
      $etiqueta = '';
      if ( $this->esCDD() ) {
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
      if ( $this->esCDD() ) {
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
   * @brief Mostra la etiqueta del curs amb descompte
   * @return Si el curs té algun descompte actiu, mostra la etiqueta del descompte
   del mes (p. ex. -20% MAIG) o un descompte en general (p. ex. -20%).
   Un curs té un descompte actiu si existeix un descompte on la data d'inici és
   abans d'avui, la data de fi és després d'avui, el mes del descompte és TOTS o
   alguna edició que estigui oberta i el curs del descompte és TOTS o el curs actual
   */
   public function etiquetaCursAmbDescompte() {
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
               // echo "edicio cont:".$edicions[$cnt];
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
                  // echo "d:".$mes.$descripcio.$percentatge."<br />";
               }
            }
            $i++;
         }
      }

      for ($i=0; $i<count($descomptes); $i++) {
         $desc=$descomptes[$i][1];
         $preu=$descomptes[$i][3];

         // echo "d2:".$desc.$preu."<br />";

         // if ($desc!=null and $desc!='')
            // $textDesc=$desc;
         // else {
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
                  $mesTextDesc.=$objTextMes->convertirMaj();
               }
               $pos++;
            }
         // }

         $etiqueta.="<div class='desc float-left'>-".$percent."% ".$mesTextDesc."</div>";
      }
      $connexio->closeStmt();

      $connexio->desconectarBD();

      return $etiqueta;
   }

   /**
   * @brief Mostra la etiqueta del curs amb els perfils del curs
   * @return Si el curs té algun perfil actiu, mostra la etiqueta del perfil
   */
   protected function etiquetaCursAmbPerfil() {
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
         align-items-center justify-content-between p-0 ml-0 mb-2'
         onclick=\"location.href='https://www.prisma.cat/perfils-professionals'\">";
         $mostrar .= "<span class='px-2'>".$perfilEd->obtenirNomCurt()->obtenirText()."</span></div>";
         $cntPerf++;
      }

      return $mostrar;
   }

   /**
   * @brief Mostra la etiqueta del curs amb els perfils pendets del curs
   * @return Si el curs té algun perfil pendent, mostra la etiqueta del perfil
   */
   protected function etiquetaCursAmbPerfilPendent() {
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
         align-items-center justify-content-between p-0 ml-0 mb-2'
         onclick=\"location.href='https://www.prisma.cat/perfils-professionals'\">";
         $mostrar .= "<span class='px-2'>".$perfilEd->obtenirNomCurt()->obtenirText()."</span>
         <div class='perfilPendent'><div class='border m-0'></div><div class='nom position-absolute'>Pendent</div></div>
         </div>";
         $cntPerf++;
      }

      return $mostrar;
   }

   /**
   * @brief Mostra la fila d'una taula del curs amb els perfils del curs
   * @return Si el curs té algun perfil actiu, mostra la fila del perfil d'una taula del curs
   */
   protected function filaTaulaCursAmbPerfil() {
      $linkCurs="https://www.prisma.cat".$this->obtenirUrl()->obtenirLink();
      $elemLink=explode('/',$linkCurs);
      $extUrl=$elemLink[count($elemLink)-1];
      $urlPerfil="https://www.prisma.cat/perfils-professionals/".$extUrl;

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
         <span class='text'>Edicions amb perfil</span>
         <button class='mesinfo position-relative negreta500'
         title='Totes les edicions que acrediten algun perfil del curs de PrisMa'
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
         <span class='text'>Edicions amb perfil</span>
         <button class='mesinfo position-relative negreta500'
         title='Totes les edicions que acrediten algun perfil del curs de PrisMa'
         onclick=\"location.href='".$urlPerfil."'\"> + info </button></li>";
         $cntPerf++;
         $trobat=true;
      }

      return $mostrar;
   }

   /**
   * @brief Mostra la etiqueta del «A partir de»
   * @return Si el curs encara no ha començat, mostra la etiqueta de «A partir de»
   */
   private function __etiquetaNoHaComencat($textNoComencat) {
      $etiqueta='';

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $consultaData = "SELECT DATAI, MES FROM curs WHERE CURS=? AND ESTAT!='0' ORDER BY ANY, MES LIMIT 1";
      $stmtData = $connexio->prepare($consultaData);
      $stmtData->bind_param("s", $codi);
      $codi=$this->obtenirCodi()->obtenirText();
      $stmtData->execute();
      $stmtData->bind_result($datai, $mes);
      $stmtData->fetch();
      $connexio->closeStmt();

      $cnsParams="SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
            DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?";
      $stmtParam = $connexio->prepare($cnsParams);
      $stmtParam->bind_param("ss", $tipus, $orderBy);
      $tipus='etiqueta-a-partir-de';
      $orderBy='DATAI';
      $stmtParam->execute();
      $stmtParam->bind_result($valor);
      $stmtParam->fetch();
      $connexio->closeStmt();

      $connexio->desconectarBD();

      $deixaDataNoComenca = strtotime($valor, strtotime($datai));
      $cursNoHaComencat=false;
      if ($deixaDataNoComenca > strtotime(date("Y-m-d"))) $cursNoHaComencat=true;

      $mesFirstEd = new Text($mes);
      $textMes=$mesFirstEd->obtenirDeMesLlarg();
      $textPartirDe = new Text("A partir ");
      $textPartirDe->afegirFinal($textMes);
      $textNoComencat=$textPartirDe->obtenirText();

      if ($cursNoHaComencat) {
         $etiqueta.="<div class='noComencat'>";
         $etiqueta.="<div class='border'></div>";
         $etiqueta.="<div class='nom'>".$textNoComencat."</div>";
         $etiqueta.="</div>";
      }
      return $etiqueta;
   }

   /**
   * @brief Serveix per mostrar o amagar el botó d'inscripcions del tastet
   * @return Retorna el nombre de les edicions amb data de resolució disponibles
   */
   public function __teEdicionsReconegudes() {
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

	  $consultaEdRec = "SELECT DATA_RESOL FROM curs WHERE CURS=? AND PUBLIC = 1
         AND CURS!='PROVA' AND CURS NOT LIKE '%0%' AND CURS NOT LIKE '%JOR%' AND
         (DATEDIFF(DATAI + ?,CURRENT_DATE)>0) AND ESTAT!='0'
         ORDER BY ANY, MES LIMIT ?";
      $stmtEdRec = $connexio->prepare($consultaEdRec);
      $stmtEdRec->bind_param("sdd", $codiCurs, $diesObets, $limitEd);
      $stmtEdRec->execute();
      $stmtEdRec->bind_result($dataResol);
      while ( $stmtEdRec->fetch() ){
		 if ($dataResol != null)
			$this->edicionsReconegudes++;
      }
      $connexio->closeStmt();

      return $this->edicionsReconegudes;
   }
}
?>
