<?php

/**
* @class Jornades
* @brief Conté tota la informació relacionada amb les jornades en línia
*/

class Jornades {
	private $anyActiu; /* Any actiu de les trobades en línia */
	private $imgPortada; /* Imatge imatge de la portada de la pàgina de les trobades en línia */
	private $dispositiu; /** string Dispositiu amb el que s'accedeix a la web */

	/* #################################    FUNCIONS CONSTRUCTORS    ################################# */

	public function __construct( $dispositiu ) {
		$this->dispositiu = $dispositiu;
		$this->anyActiu = date("Y");

		throw new Exception('',302);
	}

	/*********************************** FUNCIONS MODIFICAR ATRIBUTS ################################# */
}

?>
