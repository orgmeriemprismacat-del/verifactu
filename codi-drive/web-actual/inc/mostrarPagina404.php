<?php

function buscarPagina404($url_actual) {
	$connexio = new ConnexioBBDD();
	$connexio->connectarBD();

	$connexio->consultarBD("SELECT ID FROM amigable WHERE URL LIKE '/404'");
	$row_url = $connexio->obtenirResultat();
	$id_amigable = $row_url['ID'];

	$connexio->consultarBD("SELECT ID_CONTINGUT FROM pagina WHERE ID_URL = ".$id_amigable." and ESTAT = 1");
	$row_pagina = $connexio->obtenirResultat();

	$id_contingut = $row_pagina['ID_CONTINGUT'];

	$connexio->consultarBD("SELECT CONTINGUT FROM contingut WHERE ID = ".$id_contingut."");
	$row_contingut = $connexio->obtenirResultat();

	$contingut = $row_contingut['CONTINGUT'];

	$connexio->lliurarConsulta();
	$connexio->desconectarBD();

	return $contingut;
}
?>
