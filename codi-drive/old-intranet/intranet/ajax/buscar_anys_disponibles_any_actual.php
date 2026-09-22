<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/js/revisions.js  */

include("../inc/dades.php");

$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($connexio, "utf8");

$any_actual = date("Y");
$any_anterior = $any_actual + 1;

// descartem cursos pendents i anul·lats
$result_data = mysqli_query ($connexio,"SELECT DISTINCT `DATA INICI` AS di FROM cursos WHERE CURS NOT LIKE '%0%' AND DNI_TUTOR!='0' AND DNI_TUTOR!='1' AND ANY>=2018 ORDER BY `DATA INICI` DESC limit 1");
$row_data = mysqli_fetch_array($result_data);

$data_separada = explode("-",$row_data['di']);

$proper_any = $data_separada[0];

echo "proper".$proper_any;

for ($any = $proper_any; $any >= 2019; $any--)
{
	$html_any .= "<option value=".$any;
	if ($proper_any == $any)
	{
			$html_any .= " selected";
	}
	$html_any .= ">".$any."</option>";
}

echo $html_any;
mysqli_close($connexio);

?>
