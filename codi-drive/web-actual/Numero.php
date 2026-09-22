<?php
/**
   * @class Numero
   * @brief Conté el numero amb el que es vol treballar
*/
class Numero {
   private $num; /**< int Correspon al numero amb el que es vol treballar */

   /*********************************** FUNCIONS CONSTRUCTORS ***********************************/
   /*
      * @brief Constructor de la classe
      * @param $numero El numero amb el qual es vol crear l'objecte
      * @return El Numero amb el num $numero assignat
   */
   public function __construct($numero) {
      if ($numero=='') {
         throw new Exception('',1010);
      }

      $this->num = $numero;
   }

   /*********************************** FUNCIONS CONSULTAR ATRIBUTS ***********************************/
   /*
      * @brief Obtenir el numero
      * @return El numero
   */
   public function obtenirNumero() {
      return $this->num;
   }

   /*********************************** FUNCIONS MODIFICAR ATRIBUTS ***********************************/
   /*
      * @brief Mostra el número en el format de la durada d'un video
      * @return Si el numero és >= 60, retorna un Text amb el numero en format MM.SS (min).
      * Si el numero és < 60, retorna un Text amb el numero en format SS (sec).
   */
   public function mostrarDuradaVideo() {
      $segons = $this->num;
      $minuts = 0;

      while ($segons >= 60){
         $minuts++;
         $segons = $segons - 60;
      }

      require_once 'Text.php';
      if ($this->num >= 60) {
         $text_numero = new Text("".$minuts."");
         if ($segons < 10)
            $text_segons = "0".$segons;
         else
            $text_segons = $segons;

         $text_numero->afegirFinal(".".$text_segons." min");
      }
      else
         $text_numero = new Text("".$segons." sec");

      return $text_numero->obtenirText();
   }

   /*
      * @brief Mostra el número amb decimals excepte si acaba amb 00, en aquest cas es mostrarà sense decimals
      * @return Si el numero és decimal i el decimal no és 00, retorna el numero amb decimals.
      * Si el numero és decimal i el decimal és 00, retorna el numero sense decimals.
   */
   public function mostrarNumeroDecimalsSense0() {
      $number = floatval( $this->num );
      $partEntera = floor( $number );
      $partDecimal = $number - $partEntera;

      if ( $partDecimal > 0 )
         $numberFinal = number_format($number, 2, ",", ".");
      else
         $numberFinal = intval( $number );

      return $numberFinal;
   }
}
?>
