<?php
require_once 'Caroussel.php';

/**
   * @class CarousselTastets
   * @brief Conté  els slides dels perfils professionals de la home
*/
class CarousselPerfils extends Caroussel {
   private $cursEsc1; /**< String El primer curs escolar contemplat a la llista de perfils */
   private $cursEsc2; /**< String El segon curs escolar contemplat a la llista de perfils */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /**
   * @brief Constructor de la classe.
   * @return S'ha creat el slideshow
   */
   public function __construct($dispositiu) {
      // parent::__construct();

      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();
   	$connexio2 = new ConnexioBBDDSTMT();
   	$connexio2->connectarBD();

   	setlocale(LC_ALL,"es_ES");
   	$numMesAct = date("n");
   	$numAnyAct = date("Y");
   	$numAnyAnt = date("Y") - 1;
   	$numAnySeg = date("Y") + 1;
      $noPerfil = true;

   	$cursEsc1 = '';
   	$cursEsc2 = '';

      $cnsPerfil = "SELECT ID FROM perfils WHERE CURS_ESCOLAR LIKE ?";
      $stmtPerfil=$connexio->prepare($cnsPerfil);
      $stmtPerfil->bind_param("s", $cursEscSearch1);
      while ( $noPerfil ) {
         if ( $numMesAct >= 1 && $numMesAct <= 8 ) {
            $cursEscSearch1 = $numAnyAct."/".$numAnySeg;
            $cursEscSearch2 = $cursEscSearch1;
         }
         else {
            $cnsParams = "SELECT VALOR FROM params WHERE TIPUS LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR DATAF >= CURRENT_TIME)";
   			$stmtParam=$connexio2->prepare($cnsParams);
   	      $stmtParam->bind_param("s", $parametre);
   			$parametre = 'DATAF_MAX_PERFILS_ANY_ANTERIOR';
   	      $stmtParam->execute();
   	      $stmtParam->store_result();
   			if ( $stmtParam->num_rows() > 0 ) {
   				$stmtParam->bind_result($valor);
   				$stmtParam->fetch();
   				$date2 = new DateTime($valor);
   			}
   			else {
   				$date2 = $date1;
   			}
   			$connexio2->closeStmt();
   			$date1 = new DateTime("now");
   			$diff = $date1->diff($date2);

            if ( $numMesAct >= 6 && ($numMesAct <= 8  || $diff->invert == 0) ) {
               $cursEscSearch1 = $numAnyAct."/".$numAnySeg;
               $cursEscSearch2  = $numAnyAct."/".$numAnySeg;
            }
            else {
               $cursEscSearch1 = $numAnyAct."/".$numAnySeg;
               $cursEscSearch2 = $cursEscSearch1;
            }

         }

         /* Si exiteix algun curs amb perfil del cursEscSearch1*/
         $stmtPerfil->execute();
         $stmtPerfil->store_result();
         if ( $stmtPerfil->num_rows() > 0 ) {
            //Existeixen perfils del curs escolar cursEscSearch1
            $noPerfil = false;
         }
         else {
            $numAnySeg = $numAnyAct;
            $numAnyAct = $numAnyAnt;
            $numAnyAnt--;
         }
      }
      $connexio->closeStmt();

      $cnsParams = "SELECT VALOR FROM params WHERE TIPUS LIKE ? AND DATAI<=CURRENT_TIME AND (DATAF IS NULL OR DATAF >= CURRENT_TIME)";
      $stmtParam=$connexio2->prepare($cnsParams);
      $stmtParam->bind_param("s", $parametre);
      $parametre = 'DATAF_MAX_PERFILS_ANY_ANTERIOR';
      $stmtParam->execute();
      $stmtParam->store_result();
      if ( $stmtParam->num_rows() > 0 ) {
         $stmtParam->bind_result($valor);
         $stmtParam->fetch();
         $date2 = new DateTime($valor);
      }
      else {
         $date2 = $date1;
      }
      $connexio2->closeStmt();
      $date1 = new DateTime("now");
      $diff = $date1->diff($date2);
      $connexio2->desconectarBD();

      $cursEsc1 = $cursEscSearch1;
      if ( $numMesAct >= 6 && ($numMesAct <= 8  || $diff->invert == 0) ) {
         /* Busquem si hi ha perfils durant l'any escolar cursEscSearch2. */
         $stmtPerfil=$connexio->prepare($cnsPerfil);
         $stmtPerfil->bind_param("s", $cursEscSearch2);
         $stmtPerfil->execute();
         $stmtPerfil->store_result();
         if ( $stmtPerfil->num_rows() > 0 ) {
            $cursEsc2 = $cursEscSearch2;
         }
         else {
            $cursEsc2 = $cursEsc1;
         }
         $connexio->closeStmt();
      }
      else {
         $cursEsc2 = $cursEsc1;
      }

      $this->cursEsc1 = $cursEsc1;
      $this->cursEsc2 = $cursEsc2;

   	$cnsPerfils="SELECT i.CODI_CURS
   		FROM (perfils AS p INNER JOIN informacio AS i ON p.CODI = i.CODI_CURS)
   		WHERE (CURS_ESCOLAR=? OR CURS_ESCOLAR=?) AND i.ESTAT=1
         GROUP BY p.CODI ORDER BY i.TITOL";
   	$stmPerfils = $connexio->prepare($cnsPerfils);
   	$stmPerfils->bind_param("ss", $cursEsc1, $cursEsc2);
   	$stmPerfils->execute();
   	$stmPerfils->bind_result($codiCurs);
      $this->nElements=0;
      require_once 'Curs.php';
   	while($stmPerfils->fetch()) {
       if ( $codiCurs != 'TRIACAT' ) {
         $curs = new Curs($codiCurs, $dispositiu);
         if ( $curs->obtenirEstat()==1 ) {
           $this->llista[$this->nElements]= $curs;
           $this->nElements++;
         }
       }
   	}
   	$connexio->closeStmt();
   	$connexio->desconectarBD();
   }

   /*********************************** FUNCIONS ASSIGNAR ATRIBUTS ***********************************/

   public function setinfo( $id, $titol, $ariaLabelGeneral, $ariaLabelUnic ) {
      $this->id = $id;
      $this->titol = $titol;
      $this->ariaLabel = $ariaLabelGeneral;
      $this->ariaLabelUnic = $ariaLabelUnic;

      if ( $this->cursEsc1 != $this->cursEsc2 )
         $titol .= " 2024/2025";
      else
         $titol .= " 2024/2025";
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   protected function __vistaElBody( $curs ) {
      return $curs->mostrarCursBlocPerfils();
   }
}
?>
