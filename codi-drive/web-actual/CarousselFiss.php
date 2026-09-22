<?php
require_once 'Caroussel.php';

/**
   * @class CarousselTastets
   * @brief Conté  els slides dels perfils professionals de la home
*/
class CarousselFiss extends Caroussel {
   private $cursEsc1; /**< String El primer curs escolar contemplat a la llista de perfils */
   private $cursEsc2; /**< String El segon curs escolar contemplat a la llista de perfils */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /**
   * @brief Constructor de la classe.
   * @return S'ha creat el slideshow
   */
   public function __construct($dispositiu) {
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

   	setlocale(LC_ALL,"es_ES");
   	$numMesAct = date("m");
   	$numAnyAct = date("Y");

   	$any_anterior = date("Y") - 1;
   	$any_seguent = date("Y") + 1;

   	$anyEsc1 = date("Y");
   	$anyEsc2 = date("Y");

   	if ($numMesAct>=1 && $numMesAct<9)
   		$anyEsc1 = $numAnyAct;
   	else {
   		/* Si exiteix algun curs amb fiss del any_seguent*/
   		$cnsPerfil = "SELECT i.ID FROM curs INNER JOIN informacio as i WHERE
                       ANY LIKE ? AND ((FISS IS NOT NULL) AND (FISS <> '')) AND i.ESTAT = 1 AND PUBLIC = 1";
   		$stmtPerfil=$connexio->prepare($cnsPerfil);
         $stmtPerfil->bind_param("d", $any_seguent);
         $stmtPerfil->execute();
         $stmtPerfil->store_result();
   		if ( $stmtPerfil->num_rows() > 0 ) {
   			if ($numMesAct>=9 && $numMesAct<=12) {
   				$anyEsc1 = $numAnyAct;
   				$anyEsc2 = $any_seguent;
   			}
   			else
   				$anyEsc1 = $any_seguent;
   		}
   		else {
   			$anyEsc1 = $numAnyAct;
   		}
   		$connexio->closeStmt();
   	}

   	if ($anyEsc2 == '')
   		$anyEsc2=$anyEsc1;

   	$cnsPerfils="SELECT i.CODI_CURS FROM curs INNER JOIN informacio as i ON i.CODI_CURS = curs.CURS WHERE
                  (ANY = ? OR ANY = ?) AND i.ESTAT=1 AND PUBLIC = 1 AND ((FISS IS NOT NULL) AND (FISS <> '')) GROUP BY i.CODI_CURS ORDER BY i.TITOL";
   	$stmPerfils = $connexio->prepare($cnsPerfils);
   	$stmPerfils->bind_param("dd", $anyEsc1, $anyEsc2);
   	$stmPerfils->execute();
   	$stmPerfils->bind_result($codiCurs);
      $this->nElements=0;
      require_once 'Curs.php';
   	while($stmPerfils->fetch()) {
         $curs = new Curs($codiCurs, $dispositiu);
         if ( $curs->obtenirEstat() == 1 ) {
           $this->llista[$this->nElements]= $curs;
           $this->nElements++;
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
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   protected function __vistaElBody( $curs ) {
      return $curs->mostrarCursRelacionat();
   }
}
?>
