<?php

/**
* @class DescompteAmic
* @brief Conté tota la informació relacionada amb una Descompte per a amics.
*/

class DescompteAmic {
	// private $curs; /** Curs Curs del descompte per grups marcat */
	// private $hores; /** Numero Les hores del descompte per grup marcat ex: 40 */
   private $dispositiu; /** string Dispositiu amb el que s'accedeix a la web */
   // private $nomCurs; /** Text Nom del curs que s'aplicarà el descompte */
   // private $preuSenseDescompte; /** Numero Preu del curs sense descomptes */
   private $hores; /** Array Llista  on cada element és un array que conté el nombre mínim de participants, el nombre màxim de participnts i el preu amb descompte que s'aplica en aquest tram */
	private $dadesPersona1; /** Array Llista  on cada element és un array que conté les dades de l'alumne */
	private $dadesPersona2; /** Array Llista  on cada element és un array que conté les dades de l'alumne */
	// private $dates; /** String Les dates de l'edició que es vol inscriure */
	// private $edicio; /** Array Llistat de dos posicions on la primera posicio indica l'any i la segona posicio el mes de l'edició escollida  */
	// private $conegut; /** String Com has conegut a PrisMa */

	/*********************************** FUNCIONS CONSTRUCTORS ***********************************/

	public function __construct( $dispositiu ) {
		$this->dispositiu = $dispositiu;
		$this->hores = [];
		$this->dadesPersona1 = [
         "",
         0,
         ""
      ];
		$this->dadesPersona2 = [
         "",
         0,
         ""
      ];

      $this->cnsBD = [
         "cnsParams"          => "SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
                                  DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) AND VALOR LIKE ?",
         "cnsParams2"          => "SELECT VALOR FROM params WHERE TIPUS LIKE ? AND
                                  DATAI<=CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME<=DATAF) ORDER BY ?",
         "nomCurs"            => "SELECT TITOL FROM informacio WHERE
                                  CODI_CURS LIKE ? AND ESTAT = 1",
         "dadesCurs"            => "SELECT DATAI, DATAF, ID_PREU FROM curs WHERE
                                  ANY = ? AND MES = ? AND
                                  CURS LIKE ? AND ESTAT = 1",
         "dadesCurs2"            => "SELECT DATAI, DATAF, ID_PREU, HORES, DATA_RESOL FROM curs WHERE
                                  ANY = ? AND MES = ? AND CURS LIKE ? AND ESTAT = 1",
         "getPreuCar"		   => "SELECT IMPORT FROM preu WHERE ID = ?
         								 AND DATAI <= CURRENT_TIMESTAMP AND
         								(DATAF IS NULL OR DATAF >= CURRENT_TIMESTAMP)",
         "getLastIdPag"		   => "SELECT IDPAG FROM inscripcions ORDER BY IDPAG DESC LIMIT 1",
         "insertBD"		   => "INSERT INTO inscripcions (ANY, MES, CURS, DATA_INSC, NOM, COGNOMS,
       					 CORREU, DNI, ADRECA, Codi_Postal, POBLACIO, PERFIL, TITULACIO,
       					 TELEFON, OBSERVACIONS, COMENTARIS, FRACCIONAT, INSC_MAILING,
       					 A_PAGAR, USUARI, IDPAG, 	TIPUS_INSC, PERENNE, CONEGUT)
       					 VALUES (?,?,?,CURRENT_TIME,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
         "insertRespGrupBD"		   => "INSERT INTO respGrups (NOM, COGNOMS, DNI, TELEFON, CORREU,
     						 ADRECA, CODI_POSTAL, POBLACIO, IDPAG)
     						 VALUES (?,?,?,?,?,?,?,?,?)",
         "cursosByHores"      => "SELECT CURS FROM curs WHERE
                                  DATAI+?>CURRENT_DATE AND PUBLIC=1 AND HORES=?
                                  AND ANY = '2023' AND (MES = '07' OR MES = '08')
                                  AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
                                  GROUP BY CURS ORDER BY NOM_CURS",
         "cursosNo30hores"    => "SELECT CURS FROM curs WHERE
                                  DATAI+?>CURRENT_DATE AND PUBLIC=1 AND HORES != '30'
                                  AND ANY = '2023' AND (MES = '07' OR MES = '08')
                                  AND (CURS NOT LIKE '%JOR%') AND (CURS NOT LIKE '%0%')
                                  GROUP BY CURS ORDER BY NOM_CURS",
         "edicionsDispo"      => "SELECT DATAI, DATAF, ANY, MES, c.ESTAT FROM curs AS c INNER JOIN aula AS a ON
                                 c.ID_AULA=a.ID_AULA INNER JOIN rel_cuho AS r ON r.ID_CUHO=a.ID_CUHO
                                 INNER JOIN honoraris AS h ON r.ID_HONO=h.ID
                                 WHERE c.CURS=? AND PUBLIC=1
                                 AND ANY = '2023' AND (MES = '07' OR MES = '08')
                                 AND c.CURS!='PROVA' AND c.CURS NOT LIKE '%0%' AND c.ESTAT!='0' AND r.ACTIU=1 AND
                                 (
                                    (DATEDIFF(DATAI + ?,CURRENT_DATE)>0 AND HORES = 30) OR
                                    (DATEDIFF(DATAI + ?,CURRENT_DATE)>0 AND HORES = 40) OR
                                    (DATEDIFF(DATAI + ?,CURRENT_DATE)>0 AND HORES = 60) OR
                                    (DATEDIFF(DATAI + ?,CURRENT_DATE)>0 AND HORES = 100)
                                 ) AND c.CURS NOT LIKE '%JOR%' AND
                                 (a.ID_CUHO=17 OR a.ID_CUHO=13 OR (a.ID_CUHO!=17 AND h.DNI_TUTOR='GENERIC')
                                 OR (a.ID_CUHO!=17 AND  h.DNI_TUTOR!='GENERIC' AND AULA='A' AND perfil='tutor'
                                 AND ORDRE_TUTOR is not NULL)) GROUP BY ANY, MES ORDER BY ANY, MES LIMIT ?",
      ];
	}

	/*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
   /* #################################  FUNCIONS CONSULTAR DADES  ################################# */

   private function __insertBD( $codiCurs, $any, $mes, $idPag, $urlIdPag, $dni, $nom, $cognoms,
   $telefon, $email, $adreca, $cp, $poblacio, $perfil, $titulacio, $comentaris, $conegut ) {
     $priceOffer =  $this->__getPriceOffer( $any, $mes, $codiCurs );
     $carnetJove = '';
     $currentDate = 'CURRENT_DATE';
     $perenne = 'X';
     $mailing = '0';
     $pagFracc = 0;
     $tipusInsc = 'G';

   	 $usuari = '';
   	 $clean = preg_replace('~[^0-9]+~', '', $dni);
   	 preg_match_all('!\d+!', $clean, $numero);
   	 $usuari = implode(' ', $numero[0]);


     $textPerfil = new Text( $perfil );
     $textTitulacio = new Text( $titulacio );
   	if ( $comentaris != '')
   		$textComentaris = new Text( $comentaris );
   	else
   		$textComentaris = null;

   	$textPerfil->arreglarParaulaBD('text_no_mod');
   	$textTitulacio->arreglarParaulaBD('text_no_mod');
    if ($textComentaris != null) $textComentaris->arreglarParaulaBD('text');

     $titulacions = $textTitulacio->obtenirText();
     $perfils = $textPerfil->obtenirText();
     if ($textComentaris != null)
       $comentaris = $textComentaris->obtenirText();

     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();
     $insertBD = "INSERT INTO inscripcions (ANY, MES, CURS, DATA_INSC, NOM, COGNOMS,
             CORREU, DNI, ADRECA, Codi_Postal, POBLACIO, PERFIL, TITULACIO,
             TELEFON, OBSERVACIONS, COMENTARIS, FRACCIONAT, INSC_MAILING,
             A_PAGAR, USUARI, IDPAG, 	TIPUS_INSC, PERENNE, CONEGUT)
             VALUES (?,?,?,CURRENT_TIME,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
     // if ( $stmt=$connexio->prepare( $this->cnsBD["insertBD"] ) ) {
     if ( $stmt=$connexio->prepare( $insertBD ) ) {
        $stmt->bind_param("dsssssssssssdssdsdddsss", $any, $mes, $codiCurs, $nom,
          $cognoms, $email, $dni, $adreca, $cp, $poblacio,
          $perfil, $titulacio, $telefon, $carnetJove, $comentaris, $pagFracc,
          $mailing, $priceOffer, $usuari, $idPag, $tipusInsc, $perenne, $conegut);
        $stmt->execute();
        $idInserit = $connexio->lastInsertId();
        $stmt->fetch();
        $connexio->closeStmt();
     }
     else
        throw new Exception('',xxxx);
     $connexio->desconectarBD();
     return $idInserit;
   }

   private function __insertRespGrupBD( $idPag, $dni, $nom, $cognoms,
   $telefon, $email, $adreca, $cp, $poblacio ) {
     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();
     $insertBD = "INSERT INTO respGrups (NOM, COGNOMS, DNI, TELEFON, CORREU,
 						 ADRECA, CODI_POSTAL, POBLACIO, IDPAG)
 						 VALUES (?,?,?,?,?,?,?,?,?)";
     // if ( $stmt=$connexio->prepare( $this->cnsBD["insertRespGrupBD"] ) ) {
     if ( $stmt=$connexio->prepare( $insertBD ) ) {
        $stmt->bind_param("sssdssssd", $nom, $cognoms, $dni, $telefon,
          $email, $adreca, $cp, $poblacio, $idPag);
        $stmt->execute();
        $idInserit = $connexio->lastInsertId();
        $stmt->fetch();
        $connexio->closeStmt();
     }
     else
        throw new Exception('',xxxx);
     $connexio->desconectarBD();
     return $idInserit;
   }

   /*
   * @brief Retorna la claur de prisma
   * @return Retorna la claur de prisma
   */
   private function __getKeyPrisma() {
     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();
     if ( $stmt=$connexio->prepare( $this->cnsBD["cnsParams2"] ) ) {
        $stmt->bind_param("ss", $tipusParam, $orderBy);
        $tipusParam = 'keyEncriptar';
        $orderBy = 'DATAI';
        $stmt->execute();
        $stmt->bind_result($keyEncr);
        $stmt->fetch();
        $connexio->closeStmt();
     }
     else
        throw new Exception('',xxxx);
     $connexio->desconectarBD();
     return $keyEncr;
   }

   /*
   * @brief Retorna el nou IDPAG
   * @return Retorna el nou IDPAG
   */
   private function __getNewIdPag() {
     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();

     if ( $stmt=$connexio->prepare( $this->cnsBD["getLastIdPag"] ) ) {
        $stmt->execute();
        $stmt->bind_result($idPag);
        $stmt->fetch();
        $connexio->closeStmt();
     }
     else
        throw new Exception('',xxxx);

     $connexio->desconectarBD();

     $idPag = $idPag + 1;

     return $idPag;
   }

   /*
   * @brief Retorna el nou id
   * @return Retorna el nou id
   */
   private function __getUrlId( $id ) {
     $keyEncr = $this->__getKeyPrisma();
     $cipher = "AES-128-CBC";
     $ivlen = openssl_cipher_iv_length($cipher);
   	$iv = openssl_random_pseudo_bytes($ivlen);
   	$ciphertext_raw = openssl_encrypt($id, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
   	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
   	$hashIdInserit = base64_encode( $iv.$hmac.$ciphertext_raw );

     return $hashIdInserit;
   }

   /*
   * @brief Retorna el nou IDPAG
   * @return Retorna el nou IDPAG
   */
   private function __getUrlIdPag( $idPag ) {
     $cipher = "AES-128-CBC";
     $keyEncr = $this->__getKeyPrisma();
     $ivlen = openssl_cipher_iv_length($cipher);
   	$iv = openssl_random_pseudo_bytes($ivlen);
   	$ciphertext_raw = openssl_encrypt($idPag, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
   	$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
   	$hashIdPag = base64_encode( $iv.$hmac.$ciphertext_raw );

   	$urlIdPag = "https://www.prisma.cat/pagaments/".$hashIdPag;

     return $urlIdPag;
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
   * @brief Mostra el llistat d'edicions disponibles del curs en el que es vol inscriure
   * @return Retorna el llistat d'edicions disponibles del curs en el que es vol inscriure
   */
   private function __searchEd( $codiCurs ) {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      $select='';

      if ( $stmt=$connexio->prepare( $this->cnsBD["cnsParams2"] ) ) {
         $stmt->bind_param("ss", $tipus, $orderBy);

         $tipus='limit-editions';
         $orderBy='DATAI';
         $stmt->execute();
         $stmt->bind_result($limitEd);
         $stmt->fetch();

         $connexio->closeStmt();
      }
      else
         throw new Exception('',xxxx);

      $diesOberts30 = $diesOberts40 = 7;
      $diesOberts60 = $diesOberts100 = 21;
      if ( $stmt=$connexio->prepare( $this->cnsBD["edicionsDispo"] ) ) {
         $stmt->bind_param("sdddds", $codiCurs, $diesOberts30, $diesOberts40, $diesOberts60, $diesOberts100, $limitEd);
         $stmt->execute();
         $stmt->bind_result($datai, $dataf, $any, $mesDesc, $estatEd);
         require_once 'Edicio.php';
         require_once 'Date.php';
         while ( $stmt->fetch() ) {
            if ( $estatEd!='T' ) {
               // $textDataI = new Text($datai);
               // $textDataF = new Text($dataf);
               //
               // $dataFL=$textDataF->convertirDataLlarga();
               //
               // $partsDataI = explode('-',$datai);
               // $partsDataF = explode('-',$dataf);
               // if ( $partsDataI[0] == $partsDataF[0]) {
               //    if ( $partsDataI[1] == $partsDataF[1])
               //       $dataIL = intval($partsDataI[2]);
               //    else {
               //       $textMesLlarg = new Text($partsDataI[1]);
               //       $dataIL = intval($partsDataI[2])." ".$textMesLlarg->obtenirDeMesLlarg();
               //    }
               // }
               // else
               //    $dataIL=$textDataI->convertirDataLlarga();

               // $select .= "<li class='border-bottom m-0' id='dates-".$any."-".$mesDesc."'>";
               // $select .= "<a href='#'>Del dia ".$dataIL." al ".$dataFL."</a></li>";

               $textDates = $this->__obtenirDatesLlarges($datai, $dataf);
               $textDat = new Text($textDates);

               $select .= "<button id='dates-".$any."-".$mesDesc."' class='datesEdicio mesinfo font-weight-bold position-relative
               border-radius-2 w-100 flex-shrink-1 px-2 py-2 mb-3'>".$textDat->convertirMajPrimLletra()."</button>";
            }
         }
         $connexio->closeStmt();
      }
      else
         throw new Exception('',xxxx);

      $connexio->desconectarBD();

      return $select;
   }

   /*
   * @brief Retorna el nom del curs del curs amb codi $course
   * @return Retorna el nom del curs del curs amb codi $course
   */
   private function __searchNameCourse( $course ) {
     require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      if ( $stmt=$connexio->prepare( $this->cnsBD["nomCurs"] ) ) {
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
   * @brief Retorna el preu del curs $codiCurs, de l'edicio $mes i de l'any $any
   * @return Retorna el preu del curs $codiCurs, de l'edicio $mes i de l'any $any
   */
   private function __getPriceOriginal($any, $mes, $codiCurs) {
     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();

     if ( $stmt=$connexio->prepare( $this->cnsBD["dadesCurs"] ) ) {
        $stmt->bind_param("dss", $any, $mes, $codiCurs);
        $stmt->execute();
        $stmt->bind_result($datai, $dataf, $idPreu);
        $stmt->fetch();

        $connexio->closeStmt();
     }
     else
        throw new Exception('',xxxx);

     if ( $stmt=$connexio->prepare( $this->cnsBD["getPreuCar"] ) ) {
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
   * @brief Retorna el descompte d'amic
   * @return Retorna el descompte d'amic
   */
   private function __getOfferCompy() {
     require_once 'ConnexioBBDD_PreparedStatment.php';
     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();

     if ( $stmt=$connexio->prepare( $this->cnsBD["cnsParams2"] ) ) {
        $stmt->bind_param("ss", $tipus, $orderBy);

        $tipus = 'descompteAmics';
        $orderBy = 'DATAI';

        $stmt->execute();
        $stmt->bind_result($offer);
        $stmt->fetch();

        $connexio->closeStmt();
     }
     else
        throw new Exception('',xxxx);

      $connexio->desconectarBD();

      return $offer;
   }

   /*
   * @brief Retorna el preu amb descompte d'amic amb el preu del curs $codiCurs, de l'edicio $mes i de l'any $any
   * @return Retorna el preu amb descompte d'amic amb el preu del curs $codiCurs, de l'edicio $mes i de l'any $any
   */
   private function __getPriceOffer($any, $mes, $codiCurs) {
     $priceOrig = $this->__getPriceOriginal($any, $mes, $codiCurs);
     $offer = $this->__getOfferCompy();
     $priceOffer = $priceOrig - ( ( $priceOrig * $offer ) / 100 );

     return $priceOffer;
   }

	/* #################################  FUNCIONS MOSTRAR ELEMENTS  ################################# */

   /**
   * @brief Mostra els cursos de $hores horeso tots els cursos si $hores es 'tots'
   * @return Mostra els cursos de $hores hores o tots els cursos si $hores es 'tots'
   */
   public function getCursos( $hores ) {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      if ( $stmt=$connexio->prepare( $this->cnsBD["cnsParams"] ) ) {
         $stmt->bind_param("ss", $tipus, $valorInd);
         $tipus='dies-inscriu-cursos';
         $valorInd='%'.$hores.'%';
         if ( $hores == 'tots')
            $valorInd='%40%';
         $stmt->execute();
         $stmt->bind_result($valor);
         $stmt->fetch();
         $valors = explode('|',$valor);
         $diesOberts = $valors[1];
         $connexio->closeStmt();
      }
      else
         throw new Exception('',xxxx);

      require_once "Curs.php";

      if ( $hores == 'tots' ) {
         if ( $stmt=$connexio->prepare( $this->cnsBD["cursosNo30hores"] ) ) {
            $stmt->bind_param("d", $diesOberts);
            $stmt->execute();
            $stmt->bind_result($codiCurs);
            while ( $stmt->fetch() ) {
              if ( $codiCurs != 'ACOS' && $codiCurs != 'TICS') {
               $curs = new Curs($codiCurs, $this->dispositiu);
               $mostrar .= $curs->mostrarCursDescompteGrup();
             }
            }
            $connexio->closeStmt();
         }
         else
            throw new Exception('',xxxx);
      }
      else {
         if ( $stmt=$connexio->prepare( $this->cnsBD["cursosByHores"] ) ) {
            $stmt->bind_param("dd", $diesOberts, $hores);
            $stmt->execute();
            $stmt->bind_result($codiCurs);
            while ( $stmt->fetch() ) {
              if ( $codiCurs != 'ACOS' && $codiCurs != 'TICS') {
               $curs = new Curs($codiCurs, $this->dispositiu);
               $mostrar .= $curs->mostrarCursDescompteGrup();
             }
            }
            $connexio->closeStmt();
         }
         else
            throw new Exception('',xxxx);
      }

      $connexio->desconectarBD();

      $cnt = "<div id='cnt-cursos' class='d-flex flex-wrap'>
         ".$mostrar."
      </div>";

      return $cnt;
   }

   /**
   * @brief Retorna la informació del titol
   * @return Retorna la informació del titol
   */
	private function __getTitle() {
		// $cnt = "<div class='my-4'>
		// 	<h1>Porta un amic i gaudiu d'un descompte!</h1>
		// 	<div class='subtitol flex-grow-1 py-2'>Gaudiu tú i un amic teu d'un descompte!</div>
		// </div>";
		$cnt = "<div class='my-4'>
			<h1>Inscriu-te amb un amic i gaudiu d’un descompte!</h1>
			<div class='subtitol flex-grow-1 py-2'>25 % de descompte en inscripcions conjuntes</div>
		</div>";
		return $cnt;
	}

	/**
   * @brief Retorna la imatge allargada
   * @return Retorna la imatge allargada
   */
	private function __getBanner() {
      require_once 'Imatge.php';

		$baner = new Imatge(579);
		$versio = $baner->obtenirVersio();
		$altImg = $baner->obtenirAlt();
		$linkImg = $baner->obtenirLink();

      $domini = "https://www.prisma.cat/";
		$linkImgWebP = substr($linkImg, 0, -4).".webp?ver=".$versio;
		$linkImgWebP = $domini.$linkImgWebP;
		$linkImg = $linkImg."?ver=".$versio;
      $linkImg = $domini.$linkImg;

		$cnt = "<div class='info-banner mb-4'>
			<picture>
			<source type='image/webp' class='w-100 border-radius-2 banner-img'
				data-srcset='".$linkImgWebP."' alt='".$altImg."'>
			<source type='image/jpeg' class='w-100 border-radius-2 banner-img'
				data-srcset='".$linkImg."' alt='".$altImg."'>
			<img role='img' class='w-100 border-radius-2 banner-img lazyload'
				data-src='".$linkImg."' alt='".$altImg."'>
			</picture>
		</div>";

		return $cnt;
	}

	/**
   * @brief Retorna el contingut de la pàgina d'inici
   * @return Retorna el contingut de la pàgina d'inici
   */
	private function __getCntInici() {
		$pagina = "<div class='separacio-peu border border-radius-2 bg-white px-4 py-2'>
			<div id='cntPage' class='cntPage'>
				<h2>Informació</h2>
				<p>Aprofitant que el 30 de juliol és el Dia Internacional de l'Amistat, oferim un <strong>descompte del 25 %</strong> a tots aquells que us inscrigueu amb un amic en les <span class='font-weight-bold'>edicions d'estiu (juliol i agost) de qualsevol curs de 40, 60 i 100 hores</span>. Podeu escollir un curs diferent cada un, i teniu <span class='font-weight-bold'>fins al 31 de juliol</span> (inclòs) per fer la inscripció conjunta i gaudir d'aquesta promoció!</p>
				<p>Tots els nostres cursos permeten <span class='font-weight-bold'>flexibilitat horària i de connexió</span> (de manera que cada membre del grup podrà participar-hi al seu propi ritme) i estan reconeguts pel Departament d'Educació com a <span class='font-weight-bold'>formació permanent del professorat</span>.</p>
				<button class='inscripcio text-white position-relative negreta500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2 mr-0 mb-4'>Fer la inscripció conjunta</button>
			</div>
		</div>";

		return $pagina;
	}

   private function __getCntHores() {
		$cnt = "<div id='cnt-hores' class='d-flex flex-column flex-lg-row mb-4 border-bottom'>
		   <div class='d-flex flex-column flex-sm-row w-100'>
		      <button id='hores-tots' onclick=\"mostraCursos('tots')\"
		         class='mesinfo position-relative negreta500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2 mr-0 mr-sm-2 ml-lg-0 mr-lg-2 ml-sm-0 mb-4'>
		         Tots els cursos
		      </button>
		      <button id='hores-40' onclick='mostraCursos(40)'
		      class='mesinfo position-relative negreta500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2 mr-0 mr-sm-2 ml-lg-2 mr-lg-2 ml-sm-0 mb-4'>
		      40 hores
		      </button>
		   </div>
		   <div class='d-flex flex-column flex-sm-row w-100'>
		      <button id='hores-60' onclick='mostraCursos(60)'
		         class='mesinfo position-relative negreta500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2 mr-0 mr-sm-2 ml-lg-2 mr-lg-2 ml-sm-0 mb-4'>
		         60 hores
		      </button>
		      <button id='hores-100' onclick='mostraCursos(100)'
		      class='mesinfo position-relative negreta500 border-0 border-radius-2 w-100 flex-shrink-1 px-2 py-2 mr-0 mr-sm-2 ml-lg-2 mr-lg-0 ml-sm-0 mb-4'>
		      100 hores
		      </button>
		   </div>
		</div>";
		return $cnt;
	}

   /**
   * @brief Retorna el contingut d'un select amb les edicions de juliol i agost del curs $codiCurs
   * @return Retorna el contingut d'un select amb les edicions de juliol i agost del curs $codiCurs
   */
   private function __getCntEd( $codiCurs ) {
      // $cnt = "<div class='form-dades' id='dates_curs'>
		//    <div class='d-flex flex-row align-items-center justify-content-center'>
		//       <div class='col-12 pl-0 pr-0 pr-md-2'>
		//          <div class='form-group field-wrap position-relative'>
		//             <div id='dates' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
		//                <span class='element-selected font-weight-normal w-100'>
		//                Durant quines dates voleu realitzar el curs? Tria l'edició</span>
		//                <ul class='select-list position-absolute' style='display: none;'>
      //                ".$this->__searchEd( $codiCurs )."
		//                </ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i>
		//             </div>
		//             <span id='dates_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
		//          </div>
		//       </div>
		//    </div>
		// </div>";
      $cnt = "<div class='d-flex flex-column align-items-center justify-content-center w-100 botons'>
		   ".$this->__searchEd( $codiCurs )."
		</div>";
      return $cnt;
   }

   private function __cntDivFinal() {
      $cnt = "<div class='d-flex flex-column align-items-center justify-content-center'>
			<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100 botons'>
				<button id='enrereForm' class='enrere boto-disable position-relative border-0
					border-radius-2 w-100 mr-0 mr-md-2 px-4 py-2 my-2'>
					<i class='fas fa-arrow-left mr-2'></i>Torna enrere
				</button>
				<button id='continuaForm' class='continua position-relative text-white
					border-0 border-radius-2 w-100  px-4 py-2 my-2'>
					Continua<i class='fas fa-arrow-right ml-2'></i>
				</button>
			</div>
		</div>";

      return $cnt;
   }

   /**
   * @brief Mostra el formulari de dades de contacte
   * @return Mostra el señect
   * NIF/NIE, eL input del dni, l'input de telefon, l'input d'email, l'input de
   * confirmació d'email, l'input d'adreça, l'input codi postal, l'input de població
   */
   private function __formDadesPers($nom, $cognoms, $dni,
   $telefon, $email, $adreca, $cp, $poblacio) {
     if ($telefon == 0) $telefon = '';

      $classNomActive = '';
      if ( $nom != '' ) $classNomActive = 'active';
      if ( $cognoms != '' ) $classCogActive = 'active';
      if ( $dni != '' ) $classDniActive = 'active';
      if ( $telefon != '' ) $classTelActive = 'active';
      if ( $email != '' ) $classEmailActive = 'active';
      if ( $adreca != '' ) $classAdrecaActive = 'active';
      if ( $cp != '' ) $classCpActive = 'active';
      if ( $poblacio != '' ) $classPobleActive = 'active';

      $formNomCognoms = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
         <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
            <div class='form-group field-wrap position-relative'>
               <label class='position-absolute mb-0 ".$classNomActive."'>
                  <span class='camp'>Nom</span>
                  <span class='req font-weight-bold'>*</span>
               </label>
               <input type='text' class='form-control' id='nom' name='nom' value=\"".$nom."\">
               <span id='nom_cognom_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
            </div>
         </div>
         <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
            <div class='form-group field-wrap position-relative'>
               <label class='position-absolute mb-0 ".$classCogActive."'>
                  <span class='camp'>Cognoms</span>
                  <span class='req font-weight-bold'>*</span>
               </label>
               <input type='text' class='form-control' id='cog' name='cog' value=\"".$cognoms."\">
               <span id='cognom_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
            </div>
         </div>
      </div>";
      $formSelectDniTel = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
         <div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
            <div class='form-group field-wrap position-relative'>
               <div id='doc' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
                  <span class='element-selected font-weight-normal w-100'>NIF/NIE</span>
                  <ul class='select-list position-absolute ' style='display: none;'>
                     <li class='border-bottom m-0' id='doc-dni'><a href='#'>NIF/NIE</a></li>
                     <li class='border-bottom m-0' id='doc-passaport'><a href='#'>Altres</a></li>
                  </ul>
                  <i class='fa triangle-inferior fa-angle-down position-absolute'></i>
               </div>
            </div>
         </div>
         <div class='col-12 col-md-3 pl-0 pr-0 pr-md-2' id='input_doc'>
            <div class='form-group field-wrap position-relative'>
               <label class='position-absolute mb-0 ".$classDniActive."'>
                  <span class='camp'>DNI amb lletra</span>
                  <span class='req font-weight-bold'>*</span>
               </label>
               <input type='text' class='form-control' id='nif' name='nif' value=\"".$dni."\">
               <span id='dni_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
            </div>
         </div>
         <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
            <div class='form-group field-wrap position-relative'>
               <label class='position-absolute mb-0 ".$classTelActive."'>
                  <span class='camp'>Telèfon de contacte</span>
                  <span class='req font-weight-bold'>*</span>
               </label>
               <input type='tel' class='form-control' id='telf' name='telf' pattern='[6-9]{1}[0-9]{8}' maxlength='9'  value=\"".$telefon."\">
               <span id='telf_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
            </div>
         </div>
      </div>";

      $formCorreus = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
         <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
            <div class='form-group field-wrap position-relative'>
               <label class='position-absolute mb-0 ".$classEmailActive."'>
                  <span class='camp'>Correu electrònic</span>
                  <span class='req font-weight-bold'>*</span>
               </label>
               <input type='email' class='form-control' id='email' name='email'  value=\"".$email."\">
               <span id='correu_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
            </div>
         </div>
         <div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
            <div class='form-group field-wrap position-relative'>
               <label class='position-absolute mb-0 ".$classEmailActive."'>
                  <span class='camp'>Confirmaci&oacute del correu electrònic</span>
                  <span class='req font-weight-bold'>*</span>
               </label>
               <input type='email' class='form-control' id='email_conf' name='email_conf'  value=\"".$email."\">
               <span id='correu_conf_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
            </div>
         </div>
      </div>".$this->__modalCorreuValid();

      $formAdreca = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
         <div class='col-12 pl-0 pr-0 pr-md-2'>
            <div class='form-group field-wrap position-relative'>
               <label class='position-absolute mb-0 ".$classAdrecaActive."'>
                  <span class='camp'>Adreça (carrer, número...)</span>
                  <span class='req font-weight-bold'>*</span>
               </label>
               <input type='text' class='form-control' id='adreca' name='adreca' maxlength='150'  value=\"".$adreca."\">
               <span id='adreca_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
            </div>
         </div>
      </div>";

      $formCpPoble = "<div class='d-flex flex-column flex-md-row align-items-center justify-content-center w-100'>
         <div class='col-12 col-md-3 pl-0 pr-0 pr-md-2'>
            <div class='cnt-cp form-group field-wrap position-relative' id='cp_box'>
               <label class='position-absolute mb-0 ".$classCpActive."'>
                  <span class='camp'>Codi postal</span>
                  <span class='req font-weight-bold'>*</span>
               </label>
               <input type='text' class='form-control' id='cp' name='cp' maxlength='5'  value=\"".$cp."\">
               <span id='cp_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
            </div>
         </div>
         <div class='col-12 col-md-9 pl-0 pr-0 pr-md-2'>
            <div class='cnt-poble form-group field-wrap' id='poble_box'>
               <label class='position-absolute mb-0 ".$classPobleActive."'>
                  <span class='camp'>Poblaci&oacute</span>
                  <span class='req font-weight-bold'>*</span>
               </label>
               <input type='text' class='form-control' id='poble' name='poble' maxlength='50' value=\"".$poblacio."\">
               <span id='poble_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
               <ul id='llistat_poblacions' style='display: none'
               class='select-list position-absolute' role='listbox'></ul>
            </div>
         </div>
      </div>";

      $formulari = "<div class='d-flex flex-column align-items-center justify-content-center'>";
      $formulari .= $formNomCognoms.$formSelectDniTel.$formCorreus.$formAdreca.$formCpPoble;
      $formulari .= "</div>".$containerSequencia;

      return $formulari;
   }

   /**
   * @brief Mostra el formulari de dades de curriculars
   * @return Mostra el señect
   */
   private function __formDadesCurr() {
      $dadesCurriculars = "
			<div class='d-flex flex-column align-items-center justify-content-center'>
				<div class='d-flex flex-row align-items-center justify-content-center w-100'>
					<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
						<div class='form-group field-wrap position-relative'>
							<div id='perfil' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
					         <span class='element-selected font-weight-normal w-100'>Estic treballant a</span>
					         <ul class='select-list position-absolute' style='display: none;'>
						         <li class='border-bottom m-0' id='perfil-edInfantil03'><a href='#'>Ed. Infantil (0-3)</a></li>
						         <li class='border-bottom m-0' id='perfil-edInfantil35'><a href='#'>Ed. Infantil (3-5)</a></li>
						         <li class='border-bottom m-0' id='perfil-edPrimaria'><a href='#'>Ed. Primària</a></li>
						         <li class='border-bottom m-0' id='perfil-edEspecial'><a href='#'>Ed. Especial</a></li>
						         <li class='border-bottom m-0' id='perfil-edSecundaria'><a href='#'>Ed. Secundària (Cicles Formatius, ESO, Batxillerat)</a></li>
						         <li class='border-bottom m-0' id='perfil-consultaPrivada'><a href='#'>Consulta privada</a></li>
						         <li class='border-bottom m-0' id='perfil-noEsticTreballant'><a href='#'>No estic treballant</a></li>
						         <li class='border-bottom m-0' id='perfil-altres'><a href='#'>Altres</a></li>
					         </ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i>
				         </div>
							<span id='perfil_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
						</div>
					</div>
					<div class='col-12 col-md-6 pl-0 pr-0 pr-md-2'>
						<div class='form-group field-wrap position-relative'>
							<div id='titulacio' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
					         <span class='element-selected font-weight-normal w-100'>Tinc la titulaci&oacute de</span>
					         <ul class='select-list position-absolute' style='display: none;'>
						         <li class='border-bottom m-0' id='titulacio-TEI'><a href='#'>TEI</a></li>
						         <li class='border-bottom m-0' id='titulacio-edSecundaria'><a href='#'>Prof. Ed. Secundària</a></li>
						         <li class='border-bottom m-0' id='titulacio-audicioLlenguatge'><a href='#'>Audici&oacute i Llenguatge</a></li>
						         <li class='border-bottom m-0' id='titulacio-treballSocial'><a href='#'>Treball Social</a></li>
						         <li class='border-bottom m-0' id='titulacio-psicologia'><a href='#'>Psicologia</a></li>
						         <li class='border-bottom m-0' id='titulacio-psicopedagogia'><a href='#'>Psicopedagogia</a></li>
						         <li class='border-bottom m-0' id='titulacio-pedagogia'><a href='#'>Pedagogia</a></li>
						         <li class='border-bottom m-0' id='titulacio-logopedia'><a href='#'>Logopèdia</a></li>
						         <li class='border-bottom m-0' id='titulacio-edInfantil'><a href='#'>Ed. Infantil</a></li>
						         <li class='border-bottom m-0' id='titulacio-edPrimaria'><a href='#'>Ed. Primària</a></li>
						         <li class='border-bottom m-0' id='titulacio-edEspecial'><a href='#'>Ed. Especial</a></li>
						         <li class='border-bottom m-0' id='titulacio-edMusical'><a href='#'>Ed. Musical</a></li>
						         <li class='border-bottom m-0' id='titulacio-edFisica'><a href='#'>Ed. F&iacutesica</a></li>
						         <li class='border-bottom m-0' id='titulacio-edSocial'><a href='#'>Ed. Social</a></li>
						         <li class='border-bottom m-0' id='titulacio-llEstrangeres'><a href='#'>Ll. Estrangeres</a></li>
						         <li class='border-bottom m-0' id='titulacio-altres'><a href='#'>Altres</a></li>
						         <li class='border-bottom m-0' id='titulacio-estudiant' class='estudiant'><a href='#'>Encara no tinc cap titulaci&oacute, s&oacutec estudiant de</a></li>
					         </ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i>
         				</div>
							<span id='titulacio_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white' ></span>
						</div>
					</div>
				</div>
				<div class='d-flex flex-column align-items-center justify-content-center w-100' id='perfils-altres'></div>
				<div class='d-flex flex-row align-items-center justify-content-center w-100' id='titulacions-altres'></div>
				<div class='d-flex flex-row align-items-center justify-content-center w-100' id='titulacions-secundaria'></div>
				<div class='d-flex flex-row align-items-center justify-content-center w-100' id='titulacions-estudiant'></div>
				<div class='d-flex flex-row align-items-center justify-content-center w-100'>
					<div class='col-12 pl-0 pr-0 pr-md-2'>
						<div class='form-group field-wrap position-relative'>
							<label>
								<span class='camp'>També tinc la titulaci&oacute de</span>
							</label>
							<input type='text' class='form-control' id='titol_de' name='titol_de' maxlength='150' >
						</div>
					</div>
				</div>
			</div>";

      return $dadesCurriculars;
   }

   /**
   * @brief Mostra el formulari de dades de "i per acabar,,,"
   * @return Mostra el señect
   */
   private function __formDadesAcabar() {
      $cnt = "
   		<div class='form-dades' id='txtHint_conegut'>
   		   <div class='d-flex flex-row align-items-center justify-content-center'>
   		      <div class='col-12 pl-0 pr-0 pr-md-2'>
   		         <div class='form-group field-wrap position-relative'>
   		            <div id='comConegut' class='select d-flex flex-column justify-content-center w-100 position-relative m-0'>
   		               <span class='element-selected font-weight-normal w-100'>
   		               Com heu conegut aquest curs? Tria una opció</span>
   		               <ul id='listComConegut' class='select-list position-absolute' style='display: none;'>
   		               </ul><i class='fa triangle-inferior fa-angle-down position-absolute'></i>
   		            </div>
   		            <span id='conegut_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span>
   		         </div>
   		      </div>
   		   </div>
   		</div>
   		<div class='' id='comHasConegut_altres'></div>
         <p class='font-weight-bold'>Tens algun comentari?</p>
         <div class='form-group field-wrap position-relative'>
            <label>
               <span class='camp'>Comentaris</span>
            </label>
            <textarea class='form-control' id='comentaris'></textarea>
         </div>";

      return $cnt;
   }

	/* #################################  FUNCIONS MOSTRAR PÀGINES  ################################# */

	/*
   * @brief Retorna la pàgina inicial del descompte per a amics
   * @return Retorna el contingut inicial del descompte per a amics
   */
	public function page1() {
      $pagina = "<div class='container'><div class='row'><div class='col-12'>";
		$pagina .= $this->__getTitle();
		$pagina .= $this->__getBanner();
		$pagina .= $this->__getCntInici();
		$pagina .= $this->__modalLoading();
		$pagina .= $this->__modalError();
      $pagina .= "</div></div></div>";
      return $pagina;
   }

   /*
   * @brief Retorna la pàgina per tria un curs pel primer amic
   * @return Retorna la pàgina per tria un curs pel primer amic
   */
	public function page2() {
      $pagina = "<h2>Amic 1</h2>";
      $pagina .= "<p><span class='font-weight-bold'>Quin curs vols fer?</span></p>";
		$pagina .= $this->__getCntHores();
		$pagina .= $this->getCursos('tots');
      return $pagina;
   }

   /*
   * @brief Retorna la pàgina per tria l'edició del curs pel primer amic
   * @return Retorna la pàgina per tria l'edició del curs pel primer amic
   */
	public function page3( $course ) {
      $nameCurs = $this->__searchNameCourse( $course );

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      if ( $stmt=$connexio->prepare( $this->cnsBD["dadesCurs2"] ) ) {
         $stmt->bind_param("dss", $any, $mes, $codiCurs);

         $codiCurs = $course;
         $any = 2022;
         $mes = '07';

         $stmt->execute();
         $stmt->bind_result($datai, $dataf, $idPreu, $hores, $dataResol);
         $stmt->fetch();

         $connexio->closeStmt();
      }
      else
         throw new Exception('',xxxx);

      $pagina = "<h2>Amic 1</h2>";
		$pagina .= "<p>Has triat que vols fer el curs <strong style='color: #5a669f'>".$nameCurs."</strong> (".$hores." hores).</p>";
    $pagina .= "<p><span class='font-weight-bold'>Durant quines dates vols fer el curs?</span> Clica sobre l'edició en què et vols inscriure:</p>";
      $pagina .= $this->__getCntEd( $course );
      $pagina .= $this->__cntDivFinal();
      return $pagina;
   }

   /*
   * @brief Retorna la pàgina per tria un curs pel segon amic
   * @return Retorna la pàgina per tria un curs pel segon amic
   */
	public function page4() {
      $pagina = "<h2>Amic 2</h2>";
		$pagina .= "<p><span class='font-weight-bold'>Quin curs vols fer?</span></p>";
		$pagina .= $this->__getCntHores();
		$pagina .= $this->getCursos('tots');
      return $pagina;
   }

   /*
   * @brief Retorna la pàgina per tria l'edició del curs pel primer amic
   * @return Retorna la pàgina per tria l'edició del curs pel primer amic
   */
	public function page5( $course ) {
      $nameCurs = $this->__searchNameCourse( $course );

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      if ( $stmt=$connexio->prepare( $this->cnsBD["dadesCurs2"] ) ) {
         $stmt->bind_param("dss", $any, $mes, $codiCurs);

         $codiCurs = $course;
         $any = 2022;
         $mes = '07';

         $stmt->execute();
         $stmt->bind_result($datai, $dataf, $idPreu, $hores, $dataResol);
         $stmt->fetch();

         $connexio->closeStmt();
      }
      else
         throw new Exception('',xxxx);

      $pagina = "<h2>Amic 2</h2>";
		$pagina .= "<p>Has triat que vols fer el curs <strong style='color: #5a669f'>".$nameCurs."</strong> (".$hores." hores).</p>";
    $pagina .= "<p><span class='font-weight-bold'>Durant quines dates vols fer el curs?</span> Clica sobre l'edició en què et vols inscriure:</p>";
		$pagina .= $this->__getCntEd( $course );
      $pagina .= $this->__cntDivFinal();
      return $pagina;
   }

   /*
   * @brief Retorna la pàgina amb un resum dels cursos i el pagament
   * @return Retorna la pàgina amb un resum dels cursos que has escollit,
             el preu original, el preu amb descompte i el preu total.
             Un botó per escollir qui realitzarà el pagament.
   */
	public function page6( $codiCurs1, $any1, $mes1, $codiCurs2, $any2, $mes2 ) {
      $this->dadesPersona1[0] = $codiCurs1;
      $this->dadesPersona1[1] = $any1;
      $this->dadesPersona1[2] = $mes1;
      $this->dadesPersona2[0] = $codiCurs2;
      $this->dadesPersona2[1] = $any2;
      $this->dadesPersona2[2] = $mes2;

      $nameCourse1 = $this->__searchNameCourse( $codiCurs1 );
      $nameCourse2 = $this->__searchNameCourse( $codiCurs2 );

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      if ( $stmt=$connexio->prepare( $this->cnsBD["dadesCurs2"] ) ) {
         $stmt->bind_param("dss", $any, $mes, $codiCurs);

         $codiCurs = $codiCurs1;
         $any = $any1;
         $mes = $mes1;

         $stmt->execute();
         $stmt->bind_result($datai, $dataf, $idPreu1, $hores1, $dataResol1);
         $stmt->fetch();

         $datesCourse1 = $this->__obtenirDatesLlarges($datai, $dataf);

         $codiCurs = $codiCurs2;
         $any = $any2;
         $mes = $mes2;

         $stmt->execute();
         $stmt->bind_result($datai, $dataf, $idPreu2, $hores2, $dataResol2);
         $stmt->fetch();

         $datesCourse2 = $this->__obtenirDatesLlarges($datai, $dataf);

         $connexio->closeStmt();
      }
      else
         throw new Exception('',xxxx);

      if ( $stmt=$connexio->prepare( $this->cnsBD["getPreuCar"] ) ) {
         $stmt->bind_param("d", $idPreu);

         $idPreu = $idPreu1;
         $stmt->execute();
         $stmt->bind_result($priceCourseOrig1);
         $stmt->fetch();

         $idPreu = $idPreu2;
         $stmt->execute();
         $stmt->bind_result($priceCourseOrig2);
         $stmt->fetch();

         $connexio->closeStmt();
      }
      else
         throw new Exception('',xxxx);

      if ( $stmt=$connexio->prepare( $this->cnsBD["cnsParams2"] ) ) {
         $stmt->bind_param("ss", $tipus, $orderBy);

         $tipus = 'descompteAmics';
         $orderBy = 'DATAI';

         $stmt->execute();
         $stmt->bind_result($descompte);
         $stmt->fetch();

         $connexio->closeStmt();
      }
      else
         throw new Exception('',xxxx);

      $connexio->desconectarBD();

      $priceCourseOffer1 = $priceCourseOrig1 - ( ( $priceCourseOrig1 * $descompte ) / 100 );
      $textPrice1 = new Text($priceCourseOffer1);
      $textPriceCourseOffer1 = $textPrice1->replace(".", ",");
      $priceCourseOffer2 = $priceCourseOrig2 - ( ( $priceCourseOrig2 * $descompte ) / 100 );
      $textPrice2 = new Text($priceCourseOffer2);
      $textPriceCourseOffer2 = $textPrice2->replace(".", ",");

      $this->hores[0] = $hores1;
      $this->hores[1] = $hores2;
      $pagina = "<h2>Cursos escollits</h2>";

		  $pagina .= "<h3>Amic 1</h3>";
      $pagina .= "<div class='dades-amic px-4 pb-2 pt-3'>
         <p>Curs: <span class='font-weight-bold'>".$nameCourse1." (".$hores1." hores)</span></p>
         <p>Dates: <span class='font-weight-bold'>".$datesCourse1."</span></p>
         <p>Preu: <span class='tatxat'>".$priceCourseOrig1." euros</span> <span class='font-weight-bold'>".$textPriceCourseOffer1." euros</span></p>
      </div>";

		  $pagina .= "<h3>Amic 2</h3>";
      $pagina .= "<div class='dades-amic px-4 pb-2 pt-3 mb-3'>
         <p>Curs: <span class='font-weight-bold'>".$nameCourse2." (".$hores2." hores)</span></p>
         <p>Dates: <span class='font-weight-bold'>".$datesCourse2."</span></p>
         <p>Preu: <span class='tatxat'>".$priceCourseOrig2." euros</span> <span class='font-weight-bold'>".$textPriceCourseOffer2." euros</span></p>
      </div>";
      $pagina .= $this->__cntDivFinal();
      return $pagina;
   }

   /*
   * @brief Retorna la pàgina amb les dades personals del primer amic
   * @return Retorna la pàgina on omplir les dades personals del primer amic
   */
   public function page7( $nom, $cognoms, $dni,
   $telefon, $email, $adreca, $cp, $poblacio) {
      $pagina = "<h2>Dades de l'amic 1</h2>";
		  $pagina .= "<p>Emplena el formulari amb les teves dades:</p>";
		  $pagina .= "<h3>Dades personals</h3>";
      $pagina .= $this->__formDadesPers($nom, $cognoms, $dni,
      $telefon, $email, $adreca, $cp, $poblacio);
      $pagina .= "<h3>Dades curriculars</h3>";
      $pagina .= $this->__formDadesCurr();
      $pagina .= "<h3>I per cabar...</h3>";
      $pagina .= $this->__formDadesAcabar();
      $pagina .= $this->__cntDivFinal();
      return $pagina;
   }

   /*
   * @brief Retorna la pàgina amb les dades personals del segon amic
   * @return Retorna la pàgina on omplir les dades personals del segon amic
   */
   public function page8( $nom, $cognoms, $dni,
   $telefon, $email, $adreca, $cp, $poblacio ) {
      $pagina = "<h2>Dades de l'amic 2</h2>";
		$pagina .= "<p>Emplena el formulari amb les teves dades:</p>";
		$pagina .= "<h3>Dades personals</h3>";
      $pagina .= $this->__formDadesPers($nom, $cognoms, $dni,
      $telefon, $email, $adreca, $cp, $poblacio);
      $pagina .= "<h3>Dades curriculars</h3>";
      $pagina .= $this->__formDadesCurr();
      $pagina .= "<h3>I per cabar...</h3>";
      $pagina .= $this->__formDadesAcabar();
      $pagina .= $this->__cntDivFinal();
      return $pagina;
   }

   /*
   * @brief Retorna la pàgina amb les dades personals del segon amic
   * @return Retorna la pàgina on omplir les dades personals del segon amic
   */
   public function page9( $nom1, $cognoms1, $dni1, $telefon1, $email1, $adreca1, $cp1, $poblacio1,
 	$nom2, $cognoms2, $dni2, $telefon2, $email2, $adreca2, $cp2, $poblacio2 ) {
      $textNom = new Text( $nom1 );
      $textCog = new Text( $cognoms1 );
      $textDocumentacio = new Text( $dni1 );
      $numTelf = new Numero( $telefon1 );
      $textEmail = new Text( $email1 );
      $textAdreca = new Text( $adreca1 );
      $textCodiPostal = new Text( $cp1 );
      $textPoblacio = new Text( $poblacio1 );

      $textNom->arreglarParaulaBD('noms');
      $textCog->arreglarParaulaBD('noms');
      $textDocumentacio->arreglarParaulaBD('text_maj');
      $textEmail->arreglarParaulaBD('email');
      $textAdreca->arreglarParaulaBD('text');
      $textCodiPostal->arreglarParaulaBD('text_maj');
      $textPoblacio->arreglarParaulaBD('noms');

      $nom1 = $textNom->obtenirText();
      $cog1 = $textCog->obtenirText();
      $nomCognoms1 = $nom1." ".$cog1;
      $documentacio1 = $textDocumentacio->obtenirText();
      $email1 = $textEmail->obtenirText();
      $telf1 = $numTelf->obtenirNumero();
      $adreca1 = $textAdreca->obtenirText();
      $codiPostal1 = $textCodiPostal->obtenirText();
      $poblacio1 = $textPoblacio->obtenirText();

       $this->dadesPersona1[3] = $nom1;
       $this->dadesPersona1[4] = $cognoms1;
       $this->dadesPersona1[5] = $dni1;
       $this->dadesPersona1[6] = $telefon1;
       $this->dadesPersona1[7] = $email1;
       $this->dadesPersona1[8] = $adreca1;
       $this->dadesPersona1[9] = $cp1;
       $this->dadesPersona1[10] = $poblacio1;

       $textNom = new Text( $nom2 );
       $textCog = new Text( $cognoms2 );
       $textDocumentacio = new Text( $dni2 );
       $numTelf = new Numero( $telefon2 );
       $textEmail = new Text( $email2 );
       $textAdreca = new Text( $adreca2 );
       $textCodiPostal = new Text( $cp2 );
       $textPoblacio = new Text( $poblacio2 );

       $textNom->arreglarParaulaBD('noms');
       $textCog->arreglarParaulaBD('noms');
       $textDocumentacio->arreglarParaulaBD('text_maj');
       $textEmail->arreglarParaulaBD('email');
       $textAdreca->arreglarParaulaBD('text');
       $textCodiPostal->arreglarParaulaBD('text_maj');
       $textPoblacio->arreglarParaulaBD('noms');

       $nom2 = $textNom->obtenirText();
       $cog2 = $textCog->obtenirText();
       $nomCognoms2 = $nom2." ".$cog2;
       $documentacio2 = $textDocumentacio->obtenirText();
       $email2 = $textEmail->obtenirText();
       $telf2 = $numTelf->obtenirNumero();
       $adreca2 = $textAdreca->obtenirText();
       $codiPostal2 = $textCodiPostal->obtenirText();
       $poblacio2 = $textPoblacio->obtenirText();

       $this->dadesPersona2[3] = $nom2;
       $this->dadesPersona2[4] = $cognoms2;
       $this->dadesPersona2[5] = $dni2;
       $this->dadesPersona2[6] = $telefon2;
       $this->dadesPersona2[7] = $email2;
       $this->dadesPersona2[8] = $adreca2;
       $this->dadesPersona2[9] = $cp2;
       $this->dadesPersona2[10] = $poblacio2;

       //Mostrem Amic 1. Dades personals i dades del curs
       $dadesAmic1 = $this->__formDadesPers($nom1, $cognoms1, $dni1,
       $telefon1, $email1, $adreca1, $cp1, $poblacio1);

       //Mostrem Amic 2. Dades personals i dades del curs
       $dadesAmic2 = $this->__formDadesPers($nom2, $cognoms2, $dni2,
       $telefon2, $email2, $adreca2, $cp2, $poblacio2);


       //Mostrem Preu 1, preu 2, pagament total i que es trii qui pagarà.
       $dadesPagament .= "<p>Trieu quin dels dos amics realitzarà el pagament:</p>
       <div class='d-flex flex-column align-items-center justify-content-center w-100 botons'>
         <button id='pagamentDni-".$this->dadesPersona1[5]."' class='pagamentDni mesinfo font-weight-bold position-relative
         border-radius-2 w-100 flex-shrink-1 px-2 py-2 mb-3'>".$this->dadesPersona1[3]." ".$this->dadesPersona1[4]."</button>
         <button id='pagamentDni-".$this->dadesPersona2[5]."' class='pagamentDni mesinfo font-weight-bold position-relative
         border-radius-2 w-100 flex-shrink-1 px-2 py-2 mb-3'>".$this->dadesPersona2[3]." ".$this->dadesPersona2[4]."</button>
       </div>";

       $priceCourseOrig1 = $this->__getPriceOriginal( $this->dadesPersona1[1], $this->dadesPersona1[2], $this->dadesPersona1[0] );
       $priceCourseOrig2 = $this->__getPriceOriginal( $this->dadesPersona2[1], $this->dadesPersona2[2], $this->dadesPersona2[0] );
       $priceCourseOrigAll = $priceCourseOrig1 + $priceCourseOrig2;

       $priceCourseOffer1 = $this->__getPriceOffer( $this->dadesPersona1[1], $this->dadesPersona1[2], $this->dadesPersona1[0] );
       $priceCourseOffer2 = $this->__getPriceOffer( $this->dadesPersona2[1], $this->dadesPersona2[2], $this->dadesPersona2[0] );
       $priceCourseOfferAll = $priceCourseOffer1 + $priceCourseOffer2;

       $titol1 = $this->__searchNameCourse( $this->dadesPersona1[0] );
       $titol2 = $this->__searchNameCourse( $this->dadesPersona2[0] );

       $textPrice1 = new Text($priceCourseOffer1);
       $textPriceCourseOffer1 = $textPrice1->replace(".", ",");
       $textPrice2 = new Text($priceCourseOffer2);
       $textPriceCourseOffer2 = $textPrice2->replace(".", ",");
       $textPriceAll = new Text($priceCourseOfferAll);
       $textPriceCourseOfferAll = $textPriceAll->replace(".", ",");

       $textDe1 = "de";
       if ( $this->dadesPersona1[3][0] == 'A' || $this->dadesPersona1[3][0] == 'E' || $this->dadesPersona1[3][0] == 'I' || $this->dadesPersona1[3][0] == 'O' || $this->dadesPersona1[3][0] == 'U')
        $textDe1 = "d'";
       $textDe2 = "de";
       if ( $this->dadesPersona2[3][0] == 'A' || $this->dadesPersona2[3][0] == 'E' || $this->dadesPersona2[3][0] == 'I' || $this->dadesPersona2[3][0] == 'O' || $this->dadesPersona2[3][0] == 'U')
        $textDe2 = "d'";

       $dadesPagament .= "<div class='dades-amic px-4 pb-2 pt-3'>
          <p>Preu del curs <strong>".$titol1."</strong> (".$this->hores[0]." hores) ".$textDe1." ".$this->dadesPersona1[3]." ".$this->dadesPersona1[4].":
           <span class='tatxat'>".$priceCourseOrig1." euros</span> <span class='font-weight-bold'>".$textPriceCourseOffer1." euros</span></p>
          <p>Preu del curs <strong>".$titol2."</strong> (".$this->hores[1]." hores) ".$textDe2." ".$this->dadesPersona2[3]." ".$this->dadesPersona2[4].":
           <span class='tatxat'>".$priceCourseOrig2." euros</span> <span class='font-weight-bold'>".$textPriceCourseOffer2." euros</span></p>
          <p>Preu total: <span class='tatxat'>".$priceCourseOrigAll." euros</span> <span class='font-weight-bold'>".$textPriceCourseOfferAll." euros</span></p>
       </div>";

       $pagina = "<h2>Resum</h2>";
      $pagina .= "<h3>Dades personals de l'amic 1</h3>";
      $pagina .= $dadesAmic1;
      $pagina .= "<h3>Dades personals de l'amic 2</h3>";
      $pagina .= $dadesAmic2;
      $pagina .= "<h3>Dades del pagament conjunt</h3>";
      $pagina .= $dadesPagament;
      $pagina .= $this->__cntDivFinal();

      return $pagina;
   }

   public function enviaDades($dniPagador, $perfil1, $perfil2,
 	 $titulacio1, $titulacio2, $comentaris1, $comentaris2, $conegut1, $conegut2) {

     $codiCurs1 = $this->dadesPersona1[0];
     $codiCurs2 = $this->dadesPersona2[0];
     $any1 = $this->dadesPersona1[1];
     $any2 = $this->dadesPersona2[1];
     $mes1 = $this->dadesPersona1[2];
     $mes2 = $this->dadesPersona2[2];
     $dni1 = $this->dadesPersona1[5];
     $dni2 = $this->dadesPersona2[5];

     $titolCurs1 = $this->__searchNameCourse( $codiCurs1 );
     $titolCurs2 = $this->__searchNameCourse( $codiCurs2 );

     $textEdicio = new Text( $mes1 );
     $textCodiCurs = new Text( $codiCurs1 );
     $textTitolCurs = new Text($titolCurs1);
     $textCodiCurs->arreglarParaulaBD('text_maj');
     $textTitolCurs->arreglarParaulaBD('text_no_mod');
     $textEdicio->arreglarParaulaBD('text_no_mod');

     $codiCurs1 = $textCodiCurs->obtenirText();
     $titolCurs1 = $textTitolCurs->obtenirText();
     $mes1 = $textEdicio->obtenirText();

     $textEdicio = new Text( $mes2 );
     $textCodiCurs = new Text( $codiCurs2 );
     $textTitolCurs = new Text($titolCurs2);
     $textCodiCurs->arreglarParaulaBD('text_maj');
     $textTitolCurs->arreglarParaulaBD('text_no_mod');
     $textEdicio->arreglarParaulaBD('text_no_mod');

     $codiCurs2 = $textCodiCurs->obtenirText();
     $titolCurs2 = $textTitolCurs->obtenirText();
     $mes2 = $textEdicio->obtenirText();

     $subjectMailInsc1 = "Inscripció ".$codiCurs1." ".$mes1." - ".$dni1;
     $subjectMailInsc2 = "Inscripció ".$codiCurs2." ".$mes2." - ".$dni2;
   	if ($comentaris1 != '' )
   		$subjectMailInsc1 .= " + O";
   	if ($comentaris2 != '' )
   		$subjectMailInsc2 .= " + O";

    $dataInsc = date('d')."-".date('m')."-".date('Y')." ".date('H').":".date('i');

    $subject1 = "Inscripció al curs ".$titolCurs1;
    $subjectDate1 = $subject1." ".$dataInsc;
    $subject2 = "Inscripció al curs ".$titolCurs2;
    $subjectDate2 = $subject2." ".$dataInsc;

    $idPag = $this->__getNewIdPag();
    $urlIdPag = $this->__getUrlIdPag( $idPag );

    $msgCurt1 = $this->__msgCurt( $codiCurs1, $any1, $mes1, $idPag, $urlIdPag, $dni1,
                $this->dadesPersona1[3], $this->dadesPersona1[4], $this->dadesPersona1[6],
                $this->dadesPersona1[7], $this->dadesPersona1[8], $this->dadesPersona1[9],
                $this->dadesPersona1[10], $perfil1, $titulacio1, $comentaris1, $conegut1 );
    $msgCurt2 = $this->__msgCurt( $codiCurs2, $any2, $mes2, $idPag, $urlIdPag, $dni2,
                $this->dadesPersona2[3], $this->dadesPersona2[4], $this->dadesPersona2[6],
                $this->dadesPersona2[7], $this->dadesPersona2[8], $this->dadesPersona2[9],
                $this->dadesPersona2[10], $perfil2, $titulacio2, $comentaris2, $conegut2 );
    $msgLlarg1 = $this->__msgLlarg( $dniPagador, $codiCurs1, $any1, $mes1, $idPag, $urlIdPag, $dni1,
                $this->dadesPersona1[3], $this->dadesPersona1[4], $this->dadesPersona1[6],
                $this->dadesPersona1[7], $this->dadesPersona1[8], $this->dadesPersona1[9],
                $this->dadesPersona1[10], $perfil1, $titulacio1, $comentaris1, $conegut1 );
    $msgLlarg2 = $this->__msgLlarg( $dniPagador, $codiCurs2, $any2, $mes2, $idPag, $urlIdPag, $dni2,
                $this->dadesPersona2[3], $this->dadesPersona2[4], $this->dadesPersona2[6],
                $this->dadesPersona2[7], $this->dadesPersona2[8], $this->dadesPersona2[9],
                $this->dadesPersona2[10], $perfil2, $titulacio2, $comentaris2, $conegut2 );


    require_once 'ConnexioBBDD_PreparedStatment.php';
    $connexio = new ConnexioBBDDSTMT();
    $connexio->connectarBD();

    if ( $stmt=$connexio->prepare( $this->cnsBD["cnsParams2"] ) ) {
       $stmt->bind_param("ss", $tipus, $orderBy);
       $tipus = 'autentificacioInscripcioCopiaInscripcions';
       $orderBy = "DATAI";
       $stmt->execute();
       $stmt->bind_result($valor);
       $stmt->fetch();

     	$autentificacioInscripcio = explode('|',$valor);
     	$usernameInsc = $autentificacioInscripcio[0];
     	$passwordInsc = $autentificacioInscripcio[1];
     	$nameUserInsc = $autentificacioInscripcio[2];

    	$tipusParam = 'autentificacioInscripcio';
    	$stmt->execute();
    	$stmt->bind_result($valor);
    	$stmt->fetch();
    	$autentificacioInscripcio = explode('|',$valor);
    	$username = $autentificacioInscripcio[0];
    	$password = $autentificacioInscripcio[1];
    	$nameUser = $autentificacioInscripcio[2];

       $connexio->closeStmt();
    }
    else
       throw new Exception('',xxxx);

       $connexio->desconectarBD();
   //Envia correu curt 1 a inscripcions
    $nomFromHead = 'Secretaria PrisMa';
  	$correuFromHead = 'inscripcions@prisma.cat';
  	$nomReplyHead = $this->dadesPersona1[3]." ".$this->dadesPersona1[4];
  	$correuReplyHead = $this->dadesPersona1[6];

  	$nomTo = 'Secretaria PrisMa';
  	$correuTo = 'inscripcions@prisma.cat';
  	// $correuTo = 'meriem.prisma.cat@gmail.com';

    require_once 'MailSMTPComvive.php';

    $mailCopiaInsc = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
  										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
  										$subjectMailInsc1, $msgCurt1);

    //Envia correu llarg 1 a resguard.secretaria
    //Envia correu llarg 1 a inscripcions
    $correuFromHead = 'secretaria@prisma.cat';

  	$nomTo = "PrisMa Secretaria";
  	$correuTo = "resguard.secretaria@prisma.cat";
    // $correuTo = 'meriem.prisma.cat@gmail.com';

  	$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
  										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
  										$subjectDate1, $msgLlarg1);

    $nomTo = 'Secretaria PrisMa';
    $correuTo = 'inscripcions@prisma.cat';
    // $correuTo = 'meriem.prisma.cat@gmail.com';

  	$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
  										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
  										$subject1, $msgLlarg1);

    //Envia correu curt 2 a inscripcions
    $correuFromHead = 'inscripcions@prisma.cat';
  	$nomReplyHead = $this->dadesPersona2[3]." ".$this->dadesPersona2[4];
  	$correuReplyHead = $this->dadesPersona2[6];

  	$nomTo = 'Secretaria PrisMa';
  	$correuTo = 'inscripcions@prisma.cat';
    // $correuTo = 'meriem.prisma.cat@gmail.com';

    $mailCopiaInsc = new MailSMTPComvive($usernameInsc, $passwordInsc, $nomFromHead, $correuFromHead,
  										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
  										$subjectMailInsc2, $msgCurt2);

    //Envia correu llarg 2 a resguard.secretaria
    //Envia correu llarg 2 a inscripcions
    $correuFromHead = 'secretaria@prisma.cat';

  	$nomTo = "PrisMa Secretaria";
  	$correuTo = "resguard.secretaria@prisma.cat";
    // $correuTo = 'meriem.prisma.cat@gmail.com';

  	$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
  										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
  										$subjectDate2, $msgLlarg2);

    $nomTo = 'Secretaria PrisMa';
    $correuTo = 'inscripcions@prisma.cat';
    // $correuTo = 'meriem.prisma.cat@gmail.com';

  	$mailCopiaSecre = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
  										$nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
  										$subject2, $msgLlarg2);

    //Insert les dues Inscripcions
    $idInsert1 = $this->__insertBD($codiCurs1, $any1, $mes1, $idPag, $urlIdPag, $dni1,
                $this->dadesPersona1[3], $this->dadesPersona1[4], $this->dadesPersona1[6],
                $this->dadesPersona1[7], $this->dadesPersona1[8], $this->dadesPersona1[9],
                $this->dadesPersona1[10], $perfil1, $titulacio1, $comentaris1, $conegut1);
    $idInsert2 = $this->__insertBD( $codiCurs2, $any2, $mes2, $idPag, $urlIdPag, $dni2,
                $this->dadesPersona2[3], $this->dadesPersona2[4], $this->dadesPersona2[6],
                $this->dadesPersona2[7], $this->dadesPersona2[8], $this->dadesPersona2[9],
                $this->dadesPersona2[10], $perfil2, $titulacio2, $comentaris2, $conegut2 );

     //Envia correu llarg 1 a resguard.secretaria
     if ( $dni1 == $dniPagador ) {
         $idInsert = $idInsert1;
         $idInsertResp = $this->__insertRespGrupBD($idPag, $dni1,
                     $this->dadesPersona1[3], $this->dadesPersona1[4], $this->dadesPersona1[6],
                     $this->dadesPersona1[7], $this->dadesPersona1[8], $this->dadesPersona1[9],
                     $this->dadesPersona1[10]);
      }
      else {
        $idInsert = $idInsert2;
        $idInsertResp = $this->__insertRespGrupBD($idPag, $dni2,
                    $this->dadesPersona2[3], $this->dadesPersona2[4], $this->dadesPersona2[6],
                    $this->dadesPersona2[7], $this->dadesPersona2[8], $this->dadesPersona2[9],
                    $this->dadesPersona2[10]);
        //
      }


     //Envia correu llarg 1 a l'alumne
     //Envia correu llarg 2 a resguard.secretaria
     //Envia correu llarg 2 a l'alumne

     $nomFromHead = $nameUser;
     $correuFromHead = $username;
     $nomReplyHead = $this->dadesPersona1[3]." ".$this->dadesPersona1[4];
     $correuReplyHead = $this->dadesPersona1[7];

     $nomTo = "PrisMa Secretaria";
     $correuTo = "inscripcions.prisma@gmail.com";
     // $correuTo = 'meriem.prisma.cat@gmail.com';

     $mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
                      $nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
                      $subjectMailInsc1, $msgCurt1);

     $nomTo = "PrisMa Secretaria";
     $correuTo = "resguard.secretaria@prisma.cat";
     // $correuTo = 'meriem.prisma.cat@gmail.com';

     $mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
                      $nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
                      $subjectDate1, $msgLlarg1);

     $nomTo = $this->dadesPersona1[3]." ".$this->dadesPersona1[4];
     $correuTo = $this->dadesPersona1[7];
     // $correuTo = 'meriem.prisma.cat@gmail.com';

     $mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
                      $nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
                      $subject1, $msgLlarg1);

     $nomFromHead = $nameUser;
     $correuFromHead = $username;
     $nomReplyHead = $this->dadesPersona2[3]." ".$this->dadesPersona2[4];
     $correuReplyHead = $this->dadesPersona2[7];

     $nomTo = "PrisMa Secretaria";
     $correuTo = "inscripcions.prisma@gmail.com";

     $mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
                      $nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
                      $subjectMailInsc2, $msgCurt2);

     $nomTo = "PrisMa Secretaria";
     $correuTo = "resguard.secretaria@prisma.cat";
     // $correuTo = 'meriem.prisma.cat@gmail.com';

     $mailCopia = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
                      $nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
                      $subjectDate2, $msgLlarg2);

     $nomTo = $this->dadesPersona2[3]." ".$this->dadesPersona2[4];
     $correuTo = $this->dadesPersona2[7];
     // $correuTo = 'meriem.prisma.cat@gmail.com';

     $mailAlumne = new MailSMTPComvive($username, $password, $nomFromHead, $correuFromHead,
                      $nomReplyHead, $correuReplyHead, $nomTo, $correuTo,
                      $subject2, $msgLlarg2);

      return $this->__getUrlId( $idInsert );
   }

   private function __msgCurt( $codiCurs, $any, $mes, $idPag, $urlIdPag, $dni, $nom, $cognoms,
   $telefon, $email, $adreca, $cp, $poblacio, $perfil, $titulacio, $comentaris, $conegut) {
      $nomCognoms = $nom." ".$cognoms;
      $textPerfil = new Text( $perfil );
      $textTitulacio = new Text( $titulacio );

      $textPerfil->arreglarParaulaBD('text_no_mod');
      $textTitulacio->arreglarParaulaBD('text_no_mod');

      $perfils = $textPerfil->obtenirText();
      $titulacions = $textTitulacio->obtenirText();

      $priceOrig =  $this->__getPriceOriginal( $any, $mes, $codiCurs );
      $priceOffer =  $this->__getPriceOffer( $any, $mes, $codiCurs );

     $titol = $this->__searchNameCourse( $codiCurs );
     $textTitol = new Text($titol);
     $textTitol->arreglarParaulaBD('text_no_mod');
     $titolCurs = $textTitol->obtenirText();
     require_once 'ConnexioBBDD_PreparedStatment.php';

     $connexio = new ConnexioBBDDSTMT();
     $connexio->connectarBD();

     if ( $stmt=$connexio->prepare( $this->cnsBD["dadesCurs2"] ) ) {
        $stmt->bind_param("dss", $any, $mes, $codiCurs);
        $stmt->execute();
        $stmt->bind_result($datai, $dataf, $idPreu, $hores, $dataResol);
        $stmt->fetch();

        $connexio->closeStmt();
     }
     else
        throw new Exception('',xxxx);

      $connexio->desconectarBD();
     $datesRealitzacio = $this->__obtenirDatesLlarges($datai, $dataf);

     $msgInsc = "<p><strong>Nom:</strong> ".$nomCognoms."</p>
     <p><strong>Document:</strong> ".$dni."</p>
     <p><strong>Email:</strong> ".$email."</p>
     <p><strong>Telèfon:</strong> ".$telefon."</p>
     <p><strong>Adreça:</strong> ".$adreca."</p>
     <p><strong>CP:</strong> ".$cp."</p>
    <p><strong>Població:</strong> ".$poblacio."</p>
    <p><strong>Estic treballant a:</strong> ".$perfils."</p>
    <p><strong>Titulació / especialitat:</strong> ".$titulacions."</p>
    <p><strong>Curs:</strong> ".$titolCurs."</p>
    <p><strong>Data:</strong> ".$datesRealitzacio."</p>
    <p><strong>Com has conegut aquest curs?:</strong> ".$conegut."</p>
    <p><strong>Preu:</strong> ".$priceOffer." euros</p>
    <p><strong>IDPAG:</strong> ".$idPag."</p>
    <p><strong>URL pagament:</strong> ".$urlIdPag."</p>
    <p><strong>Comentaris:</strong> ".$comentaris."</p>
    <p>Descompte per amic</p>";

    return $msgInsc;
   }

   private function __msgLlarg( $dniPagador, $codiCurs, $any, $mes, $idPag, $urlIdPag, $dni, $nom, $cognoms,
   $telefon, $email, $adreca, $cp, $poblacio, $perfil, $titulacio, $comentaris, $conegut ) {
     $nomCognoms = $nom." ".$cognoms;
     $textPerfil = new Text( $perfil );
     $textTitulacio = new Text( $titulacio );

   	$textPerfil->arreglarParaulaBD('text_no_mod');
   	$textTitulacio->arreglarParaulaBD('text_no_mod');

     $perfils = $textPerfil->obtenirText();

     $priceOrig =  $this->__getPriceOriginal( $any, $mes, $codiCurs );
     $priceOffer =  $this->__getPriceOffer( $any, $mes, $codiCurs );

    $titol = $this->__searchNameCourse( $codiCurs );
    $textTitol = new Text($titol);
    $textTitol->arreglarParaulaBD('text_no_mod');
    $titolCurs = $textTitol->obtenirText();
    require_once 'ConnexioBBDD_PreparedStatment.php';

    $connexio = new ConnexioBBDDSTMT();
    $connexio->connectarBD();
    if ( $stmt=$connexio->prepare( $this->cnsBD["dadesCurs2"] ) ) {
       $stmt->bind_param("dss", $any, $mes, $codiCurs);
       $stmt->execute();
       $stmt->bind_result($datai, $dataf, $idPreu, $hores, $dataResol);
       $stmt->fetch();

       $connexio->closeStmt();
    }
    else
       throw new Exception('',xxxx);
     $connexio->desconectarBD();

    $datesRealitzacio = $this->__obtenirDatesLlarges($datai, $dataf);

    /* ######################################################################### */

    $textCursReconegut = "<p>Aquest curs està reconegut pel Departament d'Educació
  	de la Generalitat de Catalunya i té una durada lectiva de <strong>".$hores." hores</strong>.</p>";

  	if ($dataResol==null or $dataResol=='') {
  		$textCursReconegut = "<p>Aquest curs té una durada lectiva de
      <strong>".$hores." hores</strong>.<p>
  		<p>PrisMa, com a entitat organitzadora, ha sol·licitat el reconeixement de
  		les edicions del curs escolar 2020-2021 al Departament d'Educació. Tan bon
  		punt surti la resolució t'avisarem a través del correu electrònic.";
  	}

  	if ( strpos($perfils, 'Consulta privada') !== false ||
      strpos($perfils, 'No estic treballant') !== false ||
      strpos($perfils, 'Altres') !== false ) {
  		$textCursReconegut .= "Els nostres cursos compten com Formació Permanent
  		del Professorat sempre que es realitzin posteriorment a la data d’expedició
  		del títol d’accés a la docència (Magisteri o CAP / Màster en Educació Secundària).";
  	}
    /* ######################################################################### */
    $textEstudiant = '';
    if ( strpos($perfils, 'sóc estudiant de') !== false ) {
      $textEstudiant = "<p>En el teu cas, ​atès que encara ​no disposes d'aquesta titulació
      finalitzada, rebràs un certificat de PrisMa que p​odràs fer constar​ com ​a ​
      currículum personal però ​que ​no ​te donarà punts en convocatòries oficials
      del Departament d'Educació. En el cas que tinguis uns estudis universitaris
      finalitzats, si us plau, contacta amb nosaltres perquè rectifiquem la sol·licitud.</p>";
    }
    /* ######################################################################### */

    if ( $dni == $dniPagador ) {
      $priceOffer1 =  $this->__getPriceOffer( $this->dadesPersona1[1], $this->dadesPersona1[2], $this->dadesPersona1[0] );
      $priceOffer2 =  $this->__getPriceOffer( $this->dadesPersona2[1], $this->dadesPersona2[2], $this->dadesPersona2[0] );
      $preuTotal = $priceOffer1 + $priceOffer2;

      $textPagament = "
  			<p>Per tal de pagar els <strong>".$preuTotal." euros</strong> de la matrícula,
  			pots escollir una de les opcions següents:</p>

    		<ul style='list-style: square; margin-bottom: 20px; margin-left: 30px; padding: 0;'>
    			<li style='margin-top: 8px; line-height: 24px;'>
    				TARGETA: clica a l'enllaç següent (cal que tinguis activat el codi
    				de compra segura facilitat per la teva entitat bancària):
    				<a href='$urlIdPag' title='Pagament del curs ".$titolCurs."'>".$urlIdPag."</a>.
    			</li>
    			<li style='margin-top: 8px; line-height: 24px;'>
    				TRANSFERÈNCIA o INGRÉS BANCARI: indica clarament el concepte
    				<strong>«".$codiCurs."-".$mes."+ el número
    				del teu NIF/NIE/passaport»</strong>, en qualsevol dels comptes següents:
    				<ul style='list-style: circle; margin-left: 30px; padding: 0;'>
    					<li style='margin-top: 8px; line-height: 24px;'>La Caixa: ES30 2100 4279 21 2200080678 </li>
    					<li style='margin-top: 8px; line-height: 24px;'>BBVA: ES04 0182 5117 00 0201534816</li>
    				</ul>
    			</li>
    		</ul>
    		<p>Un cop hagis realitzat el pagament és important que conservis el
    		justificant bancari fins que t'arribi un correu electrònic que confirmi
    		que l’hem rebut correctament.</p>";
    }

    /* ######################################################################### */

    if ( $datai <= date('Y-m-d') ) { /*ha començat el curs*/
  		$textIniciCurs = "<p>En un període de 24 hores laborals podràs accedir al
  		curs amb les teves claus.</p>";
  		if ( $tipusDescompte == 0 )
  			$textIniciCurs = "<p>En un període de 24 hores laborals rebràs un correu
  			electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
  	}
  	else {
  		$textIniciCurs = "<p>Uns dies abans de l'inici del curs podràs accedir a
  		l'apartat general de l'aula amb les teves claus.</p>";
  		if ( $tipusDescompte == 0 )
  			$textIniciCurs = "<p>Uns dies abans de l'inici del curs rebràs un correu
  			electrònic amb les teves dades d'accés al Campus Virtual de PrisMa.</p>";
  	}

    /* ######################################################################### */

    $missatge = "<p>Benvolgut/da ".$nom.",</p>
    <p>Hem rebut la teva sol·licitud i ens plau comunicar-te que
    ja t'hem inscrit en el curs en línia <strong style='color: #496baa'>".$titolCurs."</strong>
    que es realitza <strong>".$datesRealitzacio."</strong> amb les dades personals següents:</p>
    <div style='background-color:#e8ecf5;border:1px solid #d7deee;border-radius:2px;padding:5px 25px;margin-bottom:20px'>
  		<p><strong>Nom:</strong> ".$nomCognoms."</p>
  		<p><strong>NIF/NIE/passaport:</strong> ".$dni."</p>
  		<p><strong>Correu electrònic:</strong> ".$email."</p>
  		<p><strong>Telèfon de contacte:</strong> ".$telefon."</p>
  		<p><strong>Adreça:</strong> ".$adreca." - ".$cp." ".$poblacio."</p>
  	</div>";
  	$missatge .= $textCursReconegut;
  	$missatge .= $textEstudiant;
  	$missatge .= $textPagament;
  	$missatge .= $textIniciCurs;
  	$missatge .= "<p>Per a qualsevol consulta, no dubtis a posar-te en contacte amb nosaltres.</p>";

    return $missatge;
   }

   /* #################################  FUNCIONS MODALS ################################# */

	/*
   * @brief Retorna un modal de càrrega "Espera un moment"
   * @return Retorna un modal de càrrega "Espera un moment"
   */
   private function __modalLoading() {
      $modal = "<div class='modal carrega' id='modalLoading' tabindex='-1' role='dialog'
      aria-labelledby='modalLoading' style='display: none' aria-modal='true'>
         <div class='modal-dialog modal-dialog-centered' role='document'>
            <div class='modal-content w-100 border-0'>
               <div class='modal-body'>
                  <div class='loading-wrapper'>
                     <div class='loading-content'></div>
                  </div>
               </div>
            </div>
         </div>
    	</div>";

   	return $modal;
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
   					<button role='button' data-dismiss='modal' class='btn btn-warning border-0 border-radius-2 text-center negreta500 m-0 mr-3'>D'acord</button>
   				</div>
   			</div>
   		</div>
   	</div>";

   	return $mostrar;
   }

	/*
   * @brief Retorna un modal d'error
   * @return Retorna un modal d'error
   */
   private function __modalError() {
      $modal = "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' aria-hidden='true'>
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

   	return $modal;
   }


	/*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
}

?>
