<?php 

function buscarPagina($url_actual)
{
	$connexio = new ConnexioBBDD();
	$connexio->connectarBD();
	$connexio->consultarBD("SELECT ID FROM amigable WHERE URL = '".$url_actual."'");
	$row = $connexio->obtenirResultat();
	$id_url = $row['ID'];
	$connexio->lliurarConsulta();
	$connexio->desconectarBD();
	
	return $id_url;
}
?>