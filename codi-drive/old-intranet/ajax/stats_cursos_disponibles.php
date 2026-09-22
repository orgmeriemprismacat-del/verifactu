<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/js/estadistiques.js  */

$servername = "localhost";
$username = "suport";
$password = "1324GiRoNa";
$dbname = "gestio";

$conn = mysqli_connect($servername, $username, $password, $dbname);
if (mysqli_connect_errno())  
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($conn, "utf8");

$sql = "SELECT DISTINCT CURS FROM cursos WHERE DNI_TUTOR<>0 AND CURS NOT LIKE '%0%' AND CURS <> 'JOR' AND CURS NOT LIKE 'PROVA%' ORDER BY CURS";
$result_cursos = mysqli_query ($conn, $sql);
$html_curs = "<option value=\"Triar\" selected>-- Tria un curs --</option>";

for ($n=0; $n<mysqli_num_rows($result_cursos); $n++)
{
	$row = mysqli_fetch_array($result_cursos);
	$html_curs .= "<option value=".$row['CURS'].">".$row['CURS']."</option>";
}

echo $html_curs;
mysqli_close($conn);
?>