<?php

function caracters_especials($s) {
	$s = ereg_replace("à","&agrave;",$s);
	$s = ereg_replace("À","&Agrave;",$s);
	$s = ereg_replace("è","&egrave;",$s);
	$s = ereg_replace("È","&Egrave;",$s);
	$s = ereg_replace("ì","&igrave;",$s);
	$s = ereg_replace("Ì","&Igrave;",$s);
	$s = ereg_replace("ò","&ograve;",$s);
	$s = ereg_replace("Ò","&Ograve;",$s);
	$s = ereg_replace("ù","&ugrave;",$s);
	$s = ereg_replace("Ù","&Ugrave;",$s);

	$s = ereg_replace("á","&aacute;",$s);
	$s = ereg_replace("Á","&Aacute;",$s);
	$s = ereg_replace("é","&eacute;",$s);
	$s = ereg_replace("É","&Eacute;",$s);
	$s = ereg_replace("í","&iacute;",$s);
	$s = ereg_replace("Í","&Iacute;",$s);
	$s = ereg_replace("ó","&oacute;",$s);
	$s = ereg_replace("Ó","&Oacute;",$s);
	$s = ereg_replace("ú","&uacute;",$s);
	$s = ereg_replace("Ú","&Uacute;",$s);

	$s = str_replace("ñ","&ntilde;",$s);
	$s = str_replace("Ñ","&Ntilde;",$s);
	
	$s = str_replace("ç","&#231;",$s);
	$s = str_replace("Ç","&#199;",$s);

	$s = str_replace("’","'",$s);
	return $s;
}

function setmana_english_catala($valor){
	if ($valor=="Monday")
		$nom_setmana="dilluns";
	else if ($valor=="Tuesday")
		$nom_setmana="dimarts";
	else if ($valor=="Wednesday")
		$nom_setmana="dimecres";
	else if ($valor=="Thursday")
		$nom_setmana="dijous";
	else if ($valor=="Friday")
		$nom_setmana="divendres";
	else if ($valor=="Saturday")
		$nom_setmana="dissabte";
	else if ($valor=="Sunday")
		$nom_setmana="diumenge";

	return $nom_setmana;
}

//arreglar els apostrofes d'un string
function convert_ap($paraula){
	$paraula = str_replace("'","''",$paraula);
	return $paraula;
}

function intranet_mes_lletres_amb_de($valor)
{
	//echo "Valor".$valor;
	include('./inc/dades.php');
	$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
	if (mysqli_connect_errno()) 
	{
		echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
	}
	$valor = intval($valor);
	//echo "Valor".$valor;
	$consulta_mesos = "SELECT nom, minusc FROM mesos WHERE (mesos.id=".$valor.")";
	$result_mesos = mysqli_query($connexio, $consulta_mesos);
	$row_mesos = mysqli_fetch_array($result_mesos);
	$nom_mes_curs = $row_mesos['minusc'];
	//echo "nom_mes_curs".$nom_mes_curs;
	if (($nom_mes_curs=='abril') || ($nom_mes_curs=='agost') || ($nom_mes_curs=='octubre'))
		$nom_mes_curs = "d'".$nom_mes_curs;
	else
		$nom_mes_curs = "de ".$nom_mes_curs;
	//echo "nom_mes_curs".$nom_mes_curs;
	return $nom_mes_curs;
}



?>
