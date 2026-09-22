<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/js/revisions.js
/intranet/js/informes.js  */

include("../inc/dades.php");

$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($connexio, "utf8");

$any = $_REQUEST['any'];

// descartem cursos pendents i anul·lats
$result_data = mysqli_query ($connexio,"SELECT DISTINCT `DATA INICI` AS di FROM cursos WHERE CURS NOT LIKE '%0%' AND DNI_TUTOR!='0' AND DNI_TUTOR!='1' AND ANY>=2018 ORDER BY `DATA INICI` DESC limit 1");
$row_data = mysqli_fetch_array($result_data);
$data_separada = explode("-",$row_data['di']);

$proper_mes = $data_separada[1];

$result_mesos = mysqli_query ($connexio,"SELECT nom, num FROM mesos ORDER BY id");

//i=1 si és gener, 12 si és desembre
for ($i=1; $i<=mysqli_num_rows($result_mesos); $i++)
{
	$row_mesos = mysqli_fetch_array($result_mesos);
	$comprovar_si_existeix_curs = mysqli_query ($connexio,"SELECT id_Curs FROM cursos WHERE MES=".$row_mesos['num']." AND ANY=".$any."");
	if (mysqli_num_rows($comprovar_si_existeix_curs)>0)
	{
		$html_mes .= "<option value=".$row_mesos['num'];
		if ($proper_mes == $row_mesos['num'])
		{
				$html_mes .= " selected";
		}
		$html_mes .= ">".$row_mesos['nom']."</option>";
	}
}

echo $html_mes;
mysqli_close($connexio);
?>
