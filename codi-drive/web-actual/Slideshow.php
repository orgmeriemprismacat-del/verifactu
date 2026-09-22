<?php
/**
   * @class Slideshow
   * @brief Conté tota la informació relacionada amb un Slideshow.
*/
class Slideshow {

   private $nElements; /**< Int El numero d'elements del llistat de sliders */
   private $llista; /**< Text El llistat de sliders del slideshow */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /**
   * @brief Constructor de la classe.
   * @return S'ha creat el slideshow
   */
   public function __construct() {
      require_once 'ConnexioBBDD_PreparedStatment.php';
      $connexio = new ConnexioBBDDSTMT();
      $connexio->connectarBD();
      /* Es busca els ids del sliders que estan actius ordenats per ORDRE */
      $cns="SELECT ID, ORDRE FROM slider WHERE DATAI<=CURRENT_TIMESTAMP AND (DATAF IS NULL OR CURRENT_TIMESTAMP<=DATAF) ORDER BY ORDRE";
      $stmt=$connexio->prepare($cns);
      $stmt->execute();
      $stmt->bind_result($id_slider, $ordre);
      $this->nElements=0;
      require_once 'Slider.php';
      $ordreAnt = -1;
      $llistaSlidersAmbIgualOrdre = [];
      while ( $stmt->fetch() ) {
      	if ($orderAnt != -1 && $ordreAnt!=$ordre) {
      		//ordena aleatoriament llistaSlidersAmbIgualOrdre
      		/* Creo una llista a partir de la llista actual */
            for ( $i = 0; $i < count($llistaSlidersAmbIgualOrdre); $i++ ) {
               $copiaLlista[$i] = $llistaSlidersAmbIgualOrdre[$i];
            }
            $llistaSlidersAmbIgualOrdre = [];
            /* Mentre existeixin elements a la llista copiada, generem un numero aletori $rand
            entre 0 i el numero d'elements que conté $copiaLlista, seleccionem l'element $rand
            de la llista  $copiaLlista i l'afegim al final de la nostra llista*/
            while ( count($copiaLlista) > 0 ) {
               /* Genero un numero aletori $rand entre 0 i el numero d'elements que conté $copiaLlista */
               $rand = rand( 0, (count($copiaLlista)-1) );
               /* Afegim l'element $rand de $copiaLlista al final de la nostra llista */
               $llistaSlidersAmbIgualOrdre[] = $copiaLlista[$rand];
               /* Destruim l'element de l'array */
               unset( $copiaLlista[$rand] );
               /* Actualitzem les claus de l'array */
               $copiaLlista = array_values( $copiaLlista );
            }

      		//afegeix els elements de la llistaSlidersAmbIgualOrdre a la llista de l'objecte actual
      		for ($i = 0; $i<count($llistaSlidersAmbIgualOrdre); $i++) {
      			$this->llista[$this->nElements]= new Slider($llistaSlidersAmbIgualOrdre[$i]);
      			$this->nElements++;
      		}
      		$llistaSlidersAmbIgualOrdre = [];
      	}

         $llistaSlidersAmbIgualOrdre[] = $id_slider;
      	$ordreAnt = $ordre;
      }

      for ($i = 0; $i<count($llistaSlidersAmbIgualOrdre); $i++) {
      	$this->llista[$this->nElements]= new Slider($llistaSlidersAmbIgualOrdre[$i]);
      	$this->nElements++;
      }
      $connexio->closeStmt();
      $connexio->desconectarBD();
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   /**
   * @brief Mostres el slideshow
   */
   public function mostrarSlideShow() {
      // $mostrar ="<div id='carouselHome' class='carousel slide'  data-interval='false'>";
      $mostrar ="<div id='carouselHome' class='carousel slide' data-ride='carousel'>";
      /*$mostrar .= "<div class='band20years type2 position-absolute'>
         <span>20 ANYS!</span>
      </div>";*/
      $mostrar.="<ol class='carousel-indicators'>";
      for ($i=0; $i<$this->nElements; $i++) {
         $mostrar .= "<li data-target='#carouselHome' data-slide-to='".$i."' class='ml-1 mr-1'";
         if ($i==0) $mostrar.= " active";
         $mostrar.="'></li>";
      }
      $mostrar.="</ol><div class='carousel-inner' role='listbox' aria-label=\"Informació d'interès\">";
      for ($i=0; $i<$this->nElements; $i++) {
         $slider = $this->llista[$i];
         $mostrar .= $slider->obtenirSlider($i);
      }
      $mostrar.="</div>";
      $mostrar.="<a class='carousel-control-prev' href='#carouselHome' role='button' data-slide='prev'>";
      $mostrar.="<i class='carousel-control-prev-icon fa fa-angle-left' aria-hidden='true'></i>";
      $mostrar.="<span class='sr-only'>Previous</span></a>";
      $mostrar.="<a class='carousel-control-next' href='#carouselHome' role='button' data-slide='next'>";
      $mostrar.="<i class='carousel-control-next-icon fa fa-angle-right' aria-hidden='true'></i>";
      $mostrar.="<span class='sr-only'>Next</span></a>";
      $mostrar.="</div>";
      return $mostrar;
   }
}
?>
