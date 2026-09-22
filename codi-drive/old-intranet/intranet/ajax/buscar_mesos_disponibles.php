<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/js/revisions.js 
/intranet/js/informes.js  */

$servername = "localhost";
$username = "suport";
$password = "1324GiRoNa";
$dbname = "gestio";

$any = $_REQUEST['any'];

$conn = mysqli_connect($servername, $username, $password, $dbname);
if (mysqli_connect_errno())  
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($conn, "utf8");

$sql = "SELECT DISTINCT MES FROM cursos WHERE ANY=".$any." ORDER BY MES";
$result_mesos = mysqli_query ($conn, $sql);
$html_mes = "<option value=\"Triar\" selected>-- Tria un mes --</option>";

for ($n=0; $n<mysqli_num_rows($result_mesos); $n++)
{
	$mes = mysqli_fetch_array($result_mesos);
	$html_mes .= "<option value=".$mes['MES'].">".$mes['MES']."</option>";
}

echo $html_mes;
mysqli_close($conn);
?>