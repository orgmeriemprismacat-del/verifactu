<?php
/**
   * @class LlistatCursos
   * @brief Guarda un llistat de cursos
*/
class LlistatCursos {

   private $llista; /**< Array Guarda el llistat de Cursos */
   private $nElements; /**< int conté el número d'elements de l'array $llista */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
   * @brief Constructor de la classe.
   * @param $llista Llistat de cursos per crear un LlistatCursos
   * @return El LlistatCursos amb el llistat de cursos $llista
   */
   public function __construct($llista) {
      $this->llista = $llista;
      $this->nElements = count($this->llista);

   }

   public function getList() {
     return $this->llista;
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /*
   * @brief Obtens el curs
   * @param $posicio La posicio de la llista del curs corresponent al curs.
   * @return Si el curs té un curs i la posicio existeix a la llista de cursos, retorna el curs del llistat. Altrament, null.
   */
   public function obtenirCurs($posicio) {
      if ($this->llista == null and count($this->llista)==0)
         return null;
      else if ($posicio>=0 && $posicio < count($this->llista))
         return $this->llista[$posicio];
      else
         return null;
   }

   /*
   * @brief Obtens el número d'elements
   * @param Obtens el nombre d'elements de la llista
   */
   public function obtenirNumeroElements() {
      return $this->nElements;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/

   /*
   * @brief Descripció curta
   * @return Mostra el llistat de cursos amb el format d'un curs quadrat.
   */
   public function mostrarLlistatCursos() {
      $cnt_llista = 0;
      $mostrar = "";
      require_once 'Curs.php';

      while ($cnt_llista < $this->nElements) {
         $mostrar .= $this->llista[$cnt_llista]->mostrarCurs();
         $mostrar .= $this->llista[$cnt_llista]->crearTastet();
         $cnt_llista++;
      }
      return $mostrar;
   }

   /**
   * @brief Mostra els cursos que tutoritza el tutor
   * @return Mostra la informació del curs o cursos que tutoritza un tutor
   */
   public function mostrarCursTutoritzat($dni_tutor, $midaPantalla) {
      $cnt_llista = 0;
      $mostrar = "";
      require_once 'Curs.php';

      while ($cnt_llista < $this->nElements) {
        if ( $this->llista[$cnt_llista]->obtenirEstat() == 1 ) {
           $mostrar .= $this->llista[$cnt_llista]->mostrarCursTutor($dni_tutor, $midaPantalla);
           $mostrar .= $this->llista[$cnt_llista]->crearTastet();
         }
         $cnt_llista++;
      }
      return $mostrar;
   }

   /*
   * @brief Descripció curta
   * @return Mostra el llistat de cursos amb el format d'un curs quadrat.
   */
   public function mostrarLlistatCursosFiltres($inici, $numElements, $head) {
      $cnt_llista = $inici;
      $cntMostrats = 0;
      $mostrar = "";
      require_once 'Curs.php';

      while ($cnt_llista < $this->nElements && $cntMostrats < $numElements) {
         $mostrar .= $this->llista[$cnt_llista]->mostrarCursosFiltres($head);
         // $mostrar .= $this->llista[$cnt_llista]->crearTastet();
         $cnt_llista++;
         $cntMostrats++;
      }
      return $mostrar;
   }

   /*
   * @brief Ordena els cursos aleatoriament
   * @return Ordena els cursos aleatoriament
   */
   public function ordenaAleatoriament($dispositiu) {
      /* Creo una llista a partir de la llista actual */
      for ( $i = 0; $i < $this->nElements; $i++ ) {
         $copiaLlista[$i] = $this->llista[$i];
      }

      $this->llista = [];

      /* Mentre existeixin elements a la llista copiada, generem un numero aletori $rand
      entre 0 i el numero d'elements que conté $copiaLlista, seleccionem l'element $rand
      de la llista  $copiaLlista i l'afegim al final de la nostra llista*/
      while ( count($copiaLlista) > 0 ) {
         /* Genero un numero aletori $rand entre 0 i el numero d'elements que conté $copiaLlista */
         $rand = rand( 0, (count($copiaLlista)-1) );
         /* Afegim l'element $rand de $copiaLlista al final de la nostra llista */
         $this->llista[] = $copiaLlista[$rand];
         /* Destruim l'element de l'array */
         unset( $copiaLlista[$rand] );
         /* Actualitzem les claus de l'array */
         $copiaLlista = array_values( $copiaLlista );
      }
   }

   /*
   * @brief Buscar els cursos per ordre de data de creació
   * @return Buscar els cursos per ordre de data de creació
   */
   public function ordenaMesVisitats($dispositiu) {
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();
      $cnsDI = "SELECT DATAI FROM curs WHERE CURS=? AND PUBLIC=1 AND GTAF IS NOT NULL AND GTAF!='' ORDER BY DATAI LIMIT 1";
      $stmtDI = $connexio->prepare($cnsDI);
      $stmtDI->bind_param("s", $codiCurs);
      require_once 'Curs.php';
      require_once 'Pack.php';
      for ($i=0; $i<$this->nElements; $i++) {
         if ( get_class($this->llista[$i]) == "Curs" ) {
            $codiCurs = $this->llista[$i]->obtenirCodi()->obtenirText();

            $stmtDI->execute();
            $stmtDI->bind_result($dataI);
            $stmtDI->fetch();
            $llistatOrdre[$dataI][]=[$codiCurs, "C"];
         }
         else if ( get_class($this->llista[$i]) == "Pack" ) {
            $dataI = $this->llista[$i]->obtenirEdicions()[0]->obtenirDataInici()->obtenirText();
            $idPack = $this->llista[$i]->obtenirIdPack()->obtenirText();

            $llistatOrdre[$dataI][]=[$idPack, "P"];
         }
      }
      $connexio->closeStmt();
      krsort($llistatOrdre);
      $connexio->desconectarBD();
      $i = 0;
      foreach ($llistatOrdre as $dataInici => $val) {
         foreach ($val as $key2 => $idCursos) {
            foreach ($idCursos as $ident => $valors) {
               if ($ident == 0 ) $codiCurs = $valors;
               if ($ident == 1 ) $tipus = $valors;
            }

            if ( $tipus == "C" )
               $this->llista[$i]=new Curs($codiCurs, $dispositiu);
            else if ( $tipus == "P" )
               $this->llista[$i]=new Pack($codiCurs, $dispositiu);
            $i++;
         }
      }
      return $llistatOrdre;
   }

   /*
   * @brief Buscar els packs per ordre de data de creació
   * @return Buscar els packs per ordre de data de creació
   */
   // public function ordenaDataCreacio($dispositiu) {
   //    $connexio = new ConnexioBBDDSTMT();
   //    $connexio->connectarBD();
   //    $cnsDI = "SELECT DATA_CREATE FROM info_pack WHERE ID_PACK=? and ESTAT = 1";
   //    $stmtDI = $connexio->prepare($cnsDI);
   //    $stmtDI->bind_param("s", $idPack);
   //    require_once 'Pack.php';
   //    for ($i=0; $i<$this->nElements; $i++) {
   //       if ( get_class($this->llista[$i]) == "Pack" ) {
   //          $idPack = $this->llista[$i]->obtenirIdPack()->obtenirText();
   //          $stmtDI->execute();
   //          $stmtDI->bind_result($dataI);
   //          $stmtDI->fetch();
   //
   //          $llistatOrdre[$dataI][]=[$this->llista[$i], "P"];
   //       }
   //    }
   //    $connexio->closeStmt();
   //    krsort($llistatOrdre);
   //    $connexio->desconectarBD();
   //    $i = 0;
   //    foreach ($llistatOrdre as $dataInici => $val) {
   //       foreach ($val as $key2 => $idCursos) {
   //          foreach ($idCursos as $ident => $valors) {
   //             if ($ident == 0 ) $curs = $valors;
   //             if ($ident == 1 ) $tipus = $valors;
   //          }
   //
   //          if ( $tipus == "C" )
   //             $this->llista[$i]= $curs;
   //          else if ( $tipus == "P" )
   //             $this->llista[$i]= $curs;
   //          $i++;
   //       }
   //    }
   //    return $llistatOrdre;
   // }
   public function ordenaDataCreacio($dispositiu) {
      usort(
        $this->llista,
        function ($a, $b) {
            $dataA = $a->obtenirDataCreacio();
            $dataB = $b->obtenirDataCreacio();

            return strtotime($dataB) <=> strtotime($dataA);
        }
    );

    return $this->llista;
   }

   /*
   * @brief Buscar els cursos per ordre de data de creació
   * @return Buscar els cursos per ordre de data de creació
   */
   // public function ordenaAlfabeticamentASC($dispositiu) {
   //
   //    for ($i=0; $i<$this->nElements; $i++) {
   //       if ( get_class($this->llista[$i]) == "Curs" ) {
   //          $codiCurs = $this->llista[$i]->obtenirCodi()->obtenirText();
   //          $titol = $this->llista[$i]->obtenirTitol()->convertirMin();
   //          $llistatOrdre[$titol]=$this->llista[$i];
   //       }
   //       else if ( get_class($this->llista[$i]) == "Pack" ) {
   //          $idPack = $this->llista[$i]->obtenirIdPack()->obtenirText();
   //          $titol = $this->llista[$i]->obtenirTitol()->convertirMin();
   //          $llistatOrdre[$titol] = $this->llista[$i];
   //
   //       }
   //    }
   //    ksort($llistatOrdre);
   //    $i = 0;
   //    foreach ($llistatOrdre as $clau => $val) {
   //       if ( get_class($val) == "Curs" ) {
   //          $this->llista[$i]=$val;
   //       }
   //       else if ( get_class($val) == "Pack" ) {
   //          $this->llista[$i]= $val;
   //       }
   //       $i++;
   //    }
   // }
   public function ordenaAlfabeticamentASC($dispositiu) {
      usort(
        $this->llista,
        function ($a, $b) {
            $titolA = $a
                ->obtenirTitol()
                ->convertirMin();

            $titolB = $b
                ->obtenirTitol()
                ->convertirMin();

            return strcmp($titolA, $titolB);
        }
    );
   }

   /*
   * @brief Buscar els cursos per ordre de data de creació
   * @return Buscar els cursos per ordre de data de creació
   */
   // public function ordenaAlfabeticamentDESC($dispositiu) {
   //    for ($i=0; $i<$this->nElements; $i++) {
   //       if ( get_class($this->llista[$i]) == "Curs" ) {
   //          $codiCurs = $this->llista[$i]->obtenirCodi()->obtenirText();
   //          $titol = $this->llista[$i]->obtenirTitol()->convertirMin();
   //          $llistatOrdre[$titol]=$this->llista[$i];
   //       }
   //       else if ( get_class($this->llista[$i]) == "Pack" ) {
   //          $idPack = $this->llista[$i]->obtenirIdPack()->obtenirText();
   //          $titol = $this->llista[$i]->obtenirTitol()->convertirMin();
   //          $llistatOrdre[$titol] = $this->llista[$i];
   //
   //       }
   //    }
   //    krsort($llistatOrdre);
   //    $i = 0;
   //    foreach ($llistatOrdre as $clau => $val) {
   //       if ( get_class($val) == "Curs" ) {
   //          $this->llista[$i]=$val;
   //       }
   //       else if ( get_class($val) == "Pack" ) {
   //          $this->llista[$i]= $val;
   //       }
   //       $i++;
   //    }
   // }
   public function ordenaAlfabeticamentDESC($dispositiu) {
      usort(
        $this->llista,
        function ($a, $b) {
            $titolA = $a
                ->obtenirTitol()
                ->convertirMin();

            $titolB = $b
                ->obtenirTitol()
                ->convertirMin();

            return strcmp($titolB, $titolA);
        }
    );
   }
}
?>
