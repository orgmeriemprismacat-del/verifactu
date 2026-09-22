<?php
/**
   * @class Caroussel
   * @brief Conté cursos d'un tipus per mostrar-los en format carroussel
*/
class Caroussel {
   protected $nElements; /**< Int El numero d'elements del llistat de sliders */
   protected $llista; /**< Text El llistat de sliders del slideshow */
   protected $cntVistaElemCarr; /**< Int El numero d'elements visibles en el carroussel */
   protected $titol; /**< String El títol que es mostra en el carroussel */
   protected $id; /**< String El id carroussel */
   protected $ariaLabel; /**< String El aria label general del carroussel */
   protected $ariaLabelUnic; /**< String El aria label de cada element del carroussel */
   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/

   public function __construct($dispositiu) {
      $this->nElements=0;
      $this->llista = "";
      $this->cntVistaElemCarr=0;
      $this->titol = "";
      $this->id = "";
      $this->ariaLabel = "";
      $this->ariaLabelUnic = "";
   }

   public function getnElements() {
      return $this->nElements;
   }

   /**
   * @brief Mostres el slideshow amb 4 elements si la mida de la pantalla $midaPantalla
   * és més gran que $midaMin4, 2 elements si és més gran que $midaMin2
   * i 1 element si és més petit que $midaMin2
   * @return Mostres el slideshow amb 4 elements si la mida de la pantalla $midaPantalla
   * és més gran que $midaMin4. Mostra el slideshow amb 2 elements si la mida de la
   * pantalla $midaPantalla és més gran que $midaMin2 i més petit que $midaMin4.
   * Mostra el slideshow amb 1 element si la mida de la midaPantalla és més petit que $midaMin2
   */
   public function vista($midaPantalla, $midaMin4, $midaMin2) {
      $dataInterval=2000;
      if ($midaPantalla >= $midaMin4)
         $this->cntVistaElemCarr = 4;
      else if ($midaPantalla < $midaMin4 && $midaPantalla >= $midaMin2)
         $this->cntVistaElemCarr = 2;
      else
         $this->cntVistaElemCarr = 1;

      $vista = "<div class='row'>
      <div class='col-12 mb-3 d-flex justify-content-between align-items-center'>";
      $vista .= $this->getHead();
      $vista .= $this->getBody();
      $vista .="</div></div>";
      return $vista;
   }

   public function getHead( ) {
      $vista.="<h2 class='h1'>".$this->titol."</h2>";
      $vista.="<div class='d-flex flex-row caroussel-head'><a class='carousel-control-prev text-center border border-secondary ";
      $vista.="d-flex justify-content-center position-relative' href='#".$this->id."' ";
      $vista.="role='button' data-slide='prev'><i class='fa fa-angle-left'></i>";
      $vista.="<span class='sr-only'>Previous</span></a>";
      $vista.="<a class='carousel-control-next text-center border border-secondary ";
      $vista.="d-flex justify-content-center position-relative' href='#".$this->id."' ";
      $vista.="role='button' data-slide='next'><i class='fa fa-angle-right'></i>";
      $vista.="<span class='sr-only'>Next</span></a>";
      $vista.="</div></div></div>";

      return $vista;
   }

   public function getBody() {
      $nPagines = $this->nElements;
      $nElemVis = $this->cntVistaElemCarr;
      $ariaLabel = $this->ariaLabel;
      $ariaLabelUnic = $this->ariaLabelUnic;

      $vista.="<div id='".$this->id."' class='carousel carousel-cursos pointer-event'
      data-interval='false'>";
      $vista.="<div class='carousel-inner' role='listbox'  aria-label=".$ariaLabel.">";
      for ($i=0; $i<$nPagines; $i++) { //Slide
         $vista .= "<div class='carousel-item d-flex flex-row item-".$i;
         if ($i==0) $vista .= " active";
         $vista.= "' role='option' aria-label='".$ariaLabelUnic."'>
         <div class='row'>";
         $c=0;
         while ($c<$nElemVis && $c < $this->nElements) {
            $nActual = $i+$c;
            if ($i+$c>=$this->nElements) $nActual = $i+$c-$this->nElements;
            $vista .= $this->__vistaElBody( $this->llista[$nActual] );
            $c++;
         }
         $vista .= "</div></div>";
      }

      return $vista;
   }
}
?>
