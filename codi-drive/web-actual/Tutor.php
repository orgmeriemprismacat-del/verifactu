<?php

require_once("Docent.php");

/**
   * @class Tutor
   * @brief Conté tota la informació extesa sobre un Docent
*/
class Tutor extends Docent {
   private $titulacio; /**< Text Petit text amb la titulacio del tutor. Ex: Psicòleg clínic */
   private $curriculum; /**< Text El breu currículum del tutor */
   private $img_large; /**< Imatge La imatge gran del tutor. Si no n'hi ha, valdrà null */
   private $list_cursos; /**< Array Llistat de CODI_CURS */
   private $dispositiu; /**< string Mobil si el dispositiu és mobil i ordindador si el dispositiu és mobil */
   private $esTutor; /**< string Retorna si és un tutor */
   private $esAutor; /**< string Retorna si és un autor */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @param $url La url corresponent al tutor
   * @return El tutor està creat
    */
   public function __construct($url, $dispositiu) {
      if ($url==null or $url=='') {
         throw new Exception('',201);
      }

      $this->url = $url;
      $this->dispositiu = $dispositiu;

      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();

      /* Es busca nom, els cognoms del registre que la ID_URL de la taula PERSONAL
      correspon a la ID amigable obtinguda anteriorment */
      $cnsTutor = "SELECT NOM, COGNOMS, DNI, TITULACIO, CURRICULUM,
      ID_IMG_SMALL, ID_IMG_LARGE FROM personal WHERE ESTAT=1 AND ID_URL=?";
      $stmtTut=$connexio->prepare($cnsTutor);
      $stmtTut->bind_param("d", $idAmig);
      $idAmig=$url->obtenirID();
      $stmtTut->execute();
      $stmtTut->store_result();
      if ( $stmtTut->num_rows() <= 0 ) {   //no existeix la id_amigable
         $connexio->closeStmt();
         $this->estat=0;
         // $consultaExisteixTut = "SELECT ID FROM personal WHERE ESTAT=0 AND ID_URL=?";
         // $stmtTut=$connexio->prepare($consultaExisteixTut);
         // $stmtTut->bind_param("d", $idAmig);
         // $stmtTut->execute();
         // $stmtTut->store_result();
         // if ( $stmtTut->num_rows() > 0 )
            throw new Exception('',206);
         // else
         //    throw new Exception('',207);
      }
      else {
         $stmtTut->bind_result($nom, $cognoms, $dni, $titol, $cv, $idImgSmall, $idImgLarge);
         $stmtTut->fetch();
         $connexio->closeStmt();

         // $consultaExisteixTut = "SELECT ID FROM honoraris WHERE ESTAT=1 AND PERFIL='tutor' AND DNI_TUTOR=?";
         // $stmtTut=$connexio->prepare($consultaExisteixTut);
         // $stmtTut->bind_param("s", $dni);
         // $stmtTut->execute();
         // $stmtTut->store_result();
         // if ( $stmtTut->num_rows() <= 0 )
         //    throw new Exception('',206);
         //
         // $connexio->closeStmt();
      }

      $this->esTutor = 1;
	  
      $cnsEstat = "SELECT ID FROM honoraris WHERE DNI_TUTOR=? AND PERFIL='tutor' AND ESTAT=1";
      $stmtTut=$connexio->prepare($cnsEstat);
      $stmtTut->bind_param("s", $dni);
      $stmtTut->execute();
      $stmtTut->store_result();
      if ( $stmtTut->num_rows() <= 0 ) {
         $this->esTutor = 0;
		 $this->esAutor = 1;
		 
		  $cnsEstatA = "SELECT ID FROM honoraris WHERE DNI_TUTOR=? AND (PERFIL='autor' OR PERFIL='duo') AND ESTAT=1";
		  $stmtAut=$connexio->prepare($cnsEstatA);
		  $stmtAut->bind_param("s", $dni);
		  $stmtAut->execute();
		  $stmtAut->store_result();
		  if ( $stmtAut->num_rows() <= 0 ) {
			 $this->esAutor = 0;
		  }
      }
      $connexio->closeStmt();

      require_once 'Text.php';
      if ($nom!=null and $nom!='')
          $this->nom = new Text($nom);
      else
          $this->nom = null;
      if ($cognoms!=null and $cognoms!='')
          $this->cognoms = new Text($cognoms);
      else
          $this->cognoms = null;
      if ($dni!=null and $dni!='')
          $this->dni = new Text($dni);
      else
          $this->dni = null;
      if ($titol!=null and $titol!='')
          $this->titulacio = new Text($titol);
      else
          $this->titulacio = null;
      if ($cv!=null and $cv!='')
          $this->curriculum = new Text($cv);
      else
          $this->curriculum = null;
      require_once 'Imatge.php';
      if ($idImgSmall!=null and $idImgSmall!='')
          $this->img_small = new Imatge($idImgSmall);
      else
          $this->img_small = null;
      if ($idImgLarge!=null and $idImgLarge!='')
          $this->img_large = new Imatge($idImgLarge);
      else
          $this->img_large = null;

       //echo "tutor: ".$this->esTutor;
	  //echo "autor: ".$this->esAutor."<br>";
      if ( $this->esTutor == 1 ) {
         /*
         * Es busca els cursos que tutoritza el tutor DNI_TUTOR on ESTAT=1 i PERFIL=tutor de la
         * taula HONORARIS on el DNI_TUTOR correspon al DNI obtingut anteriorment  i on a
         * rel_cuho estigui ACTIU=1, agrupat per curs per si hi ha dos tutors en el mateix curs,
         * ordenats per ORDRE_TUTOR
         */
         $cnsCursosRel = "SELECT CURS FROM honoraris INNER JOIN rel_cuho ON
         honoraris.ID=rel_cuho.ID_HONO WHERE DNI_TUTOR=? AND PERFIL=?
         AND ESTAT='1' AND ACTIU=1 GROUP BY CURS ORDER BY ORDRE_CURS";
         $sentencia = $connexio->prepare($cnsCursosRel);
         $sentencia->bind_param("ss", $dni, $perfil);
         $perfil='tutor';
         $sentencia->execute();
         $sentencia->bind_result($curs);

         $cntCursos = 0;
         require_once 'Curs.php';
         while ($sentencia->fetch()) {
            $curs = new Curs($curs, $dispositiu);
            if ($curs->obtenirEstat()==1) {
               $listCursos[$cntCursos] = $curs;
               $cntCursos++;
            }
         }
         $connexio->closeStmt();
         $connexio->desconectarBD();

         require_once 'LlistatCursos.php';
         $this->list_cursos = new LlistatCursos($listCursos);
      }
	  else if ( $this->esAutor == 1 ) {		  
		  $listCursos = [];
		  require_once 'LlistatCursos.php';
          $this->list_cursos = new LlistatCursos($listCursos);
	  }
      else {
         $this->list_cursos = null;
      }

   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

	/**
   * @brief Obtenir si és només autor
   * @return 1 si només és autor i 0 si no
    */
   public function obtenirNomesAutor() {      
      return $this->esAutor;
   }

   /**
   * @brief Obtenir titulació del tutor
   * @return La titulació del docent
   * @throws No existeixen la titulació del docent
    */
   private function __obtenirTitulacio() {
      if ($this->titulacio==null) {
         throw new Exception('',202);
      }
      return $this->titulacio;
   }

   /**
   * @brief Obtenir el cv del tutor
   * @return El cv del docent
   * @throws No existeixen el cv del docent
    */
   private function __obtenirCurriculum() {
      if ($this->curriculum==null) {
         throw new Exception('',203);
      }
      return $this->curriculum;
   }

   /**
   * @brief Obtens la imatge gran del docent
   * @return La imatge gran del docent
   * @throws No existeix la imatge gran del docent
    */
   private function __obtenirImatgeGran() {
      if ($this->img_large==null) {
         throw new Exception('',204);
      }
      return $this->img_large;
   }

   /*
   * @brief Obtens el codi del curs
   * @param $posicio La posicio de la llista de cursos que pot tutoritzar un tutor
   * @return Si el tutor té un curs relacionat i la posicio existeix a la llista de cursos,
             retorna el codi del curs. Altrament, null.
   */
   protected function obtenirCodiCurs($posicio) {
         if ($this->list_cursos[$posicio] == null && count($this->list_cursos)==0)
            return null;
         else if ($posicio>=0 && $posicio < count($this->list_cursos))
            return $this->list_cursos[$posicio];
         else
            return null;
   }

   /**
   * @brief Obtens la llista de cursos
   * @return La llista de cursos
   * @throws No existeix la llista de cursos
   */
   public function obtenirLlistat() {
      if ($this->list_cursos==null) {
         throw new Exception('',205);
      }
      return $this->list_cursos;
   }

   /*
   * @brief Consulta si es visualitza des d'un mòbil
   * @return Obtens TRUE si es visualitza des d'un mòbil, altramanet retorna FALSE
   */
   private function __isMobile() {
      if ($this->dispositiu == "mobil")
         return true;
      else
         return false;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /**
   * @brief Mostra la informació d'un tutor
   * @return Mostra la informació detallada d'un tutor dins un curs
   */
   public function mostrarTutorCurs() {
      $domain = "https://www.prisma.cat";
      $objUrl = $this->obtenirUrl();
      $objImgSmall = $this->obtenirImatgePetita();
      $url = $domain.$objUrl->obtenirLink();
      $urlTitle = $objUrl->obtenirTitle();
      $urlImg = $domain.$objImgSmall->obtenirLink();
      $urlTitleImg = $objImgSmall->obtenirAlt();
      $nom = $this->obtenirNomComplet()->obtenirText();
      $titol = $this->__obtenirTitulacio()->obtenirText();
      $cv = $this->__obtenirCurriculum()->obtenirText();

      $linkImgOrig = substr($urlImg, 0, -4);
     	$linkImgWebP = $linkImgOrig.".webp"."?ver=".$objImgSmall->obtenirVersio();
     	$linkImgJPG = $urlImg."?ver=".$objImgSmall->obtenirVersio();

      $mostrar = "<div class='instructors-tab'>
      <div class='media flex-column flex-md-row align-items-center mb-3'>
      <div class='media-left my-0 mr-md-4'>
      <a role='link' href='".$url."' title='".$urlTitle."'>
      <picture>
         <source type='image/webp' data-srcset='".$linkImgWebP."'>
         <source type='image/jpeg' data-srcset='".$linkImgJPG."'>
         <img data-src='".$linkImg."' alt=\"".$this->$urlTitleImg."\" src='".$linkImgJPG."'
            class='border-radius-2 lazyload'/>
      </picture>
      </a></div>
      <div class='media-body'><a role='link' class='color-text' href='".$url."' title='".$urlTitle."'>
      <h3>".$nom."</h3></a><span>".$titol."</span></div></div>".$cv."</div>";

      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un tutor
   * @return Mostra la informació detallada d'un tutor dins d'una trobada
   */
   public function mostrarTutorTrobada() {

      $objUrl = $this->obtenirUrl();
      $objImgSmall = $this->obtenirImatgePetita();
      $url = $objUrl->obtenirLink();
      $urlTitle = $objUrl->obtenirTitle();
      $urlImg = $objImgSmall->obtenirLink();
      $urlTitleImg = $objImgSmall->obtenirAlt();
      $nom = $this->obtenirNomComplet()->obtenirText();
      $titol = $this->__obtenirTitulacio()->obtenirText();

      if (!$this->__isMobile()) {
         if ( $this->esTutor )
            $mostrar = "<div class='ponent enllac pb-2 pt-4'>";
         else
            $mostrar = "<div class='ponent pb-2 pt-4'>";

         $mostrar .= "<div class='d-flex flex-column flex-sm-row flex-align-start justify-content-center justify-content-sm-start align-items-center'>
         <div class='my-2 mr-4'>";

         if ( $this->esTutor )
            $mostrar .= "<a role='link' href='https://www.prisma.cat".$url."' title='".$urlTitle."'>";

         $mostrar .= "<img role='img' class='border-radius- mb-2' alt='".$this->$urlTitleImg."' ";
         $mostrar .= "src='https://www.prisma.cat".$urlImg."?ver=".$objImgSmall->obtenirVersio()."'>";

         if ( $this->esTutor )
            $mostrar .= "</a>";

         $mostrar .= "</div>";
         $mostrar .= "<div class='d-flex flex-column p-0'>";
         if ( $this->esTutor ) {
            $mostrar .= "<a role='link' class='color-text' ";
            $mostrar .= "href='https://www.prisma.cat".$url."' title='".$urlTitle."'>";
         }
         $mostrar .= "<h3 class='negreta500 m-0'>".$nom."</h3>";
         if ( $this->esTutor )
            $mostrar .= "</a>";
         $mostrar .= "<span class='titulacio pt-1'>".$titol."</span></div></div></div>";
      }
      else {
         if ( $this->esTutor ) {
            $mostrar .= "<div class='ponent enllac text-center pb-2 pt-4'><a role='link' href='https://www.prisma.cat".$url."' title='".$urlTitle."'>";
         }
         else {
            $mostrar .= "<div class='ponent text-center pb-2 pt-4'>";
         }

         $mostrar .= "<img role='img' class='border-radius-2' alt='".$urlTitleImg."' ";
         $mostrar .= "src='https://www.prisma.cat".$urlImg."?ver=".$objImgSmall->obtenirVersio()."'>";

         if ( $this->esTutor ) {
            $mostrar .= "</a>";
            $mostrar .= "<a role='link' class='color-text' title='".$urlTitle."' ";
            $mostrar .= "href='https://www.prisma.cat".$url."'>";
         }
         $mostrar .= "<h3 class='negreta500 m-0'>".$nom."</h3>";
         if ( $this->esTutor )
            $mostrar .= "</a>";
         $mostrar .= "<span class='titulacio pt-1'>".$titol."</span>".$cv."</div>";
      }
      return $mostrar;
   }

   /**
   * @brief Mostra la informació d'un tutor
   * @return Mostra la informació detallada d'un tutor en una pàgina sola
   */
   public function mostrarTutor() {
      $imatgeGran = $this->__obtenirImatgeGran();

      $mostrar="<div class='d-flex flex-column align-items-center'>
      <img role='img' class='w-100 mb-3 mt-4' src='https://www.prisma.cat".$imatgeGran->obtenirLink()."?ver=".$imatgeGran->obtenirVersio()."'
       alt='".$this->__obtenirImatgeGran()->obtenirAlt()."'>
      <h1 class='negreta500 mb-0 mt-1 text-center'>".$this->obtenirNomComplet()->obtenirText()."</h1>
      <h2 class='prisma-titulacio pb-3 text-center font-weight-normal mt-2 prisma-titulacio'>
      ".$this->__obtenirTitulacio()->obtenirText()."</h2>
      </div>".$this->__obtenirCurriculum()->obtenirText();
	  /*if ($this->obtenirNomesAutor()) {
		$mostrar.="<p><a role='link' class='mostrar-tots' href='https://www.prisma.cat/autors/'
		target='_self' title='Visualitza autors'>
		<i class='fas fa-long-arrow-alt-left'></i> Mostra els autors</a></p>"; 
	  }
	  else {
		$mostrar.="<p><a role='link' class='mostrar-tots' href='https://www.prisma.cat/docents/'
		target='_self' title='Visualitza l&#39;equip docent'>
		<i class='fas fa-long-arrow-alt-left'></i> Mostra l'equip docent</a></p>";
	  }*/
	  
	  $mostrar.="<p><a role='link' class='mostrar-tots' href='https://www.prisma.cat/docents/'
		target='_self' title='Visualitza l&#39;equip docent'>
		<i class='fas fa-long-arrow-alt-left'></i> Mostra l'equip docent</a></p>";
	  
      return $mostrar;
   }
}
?>
