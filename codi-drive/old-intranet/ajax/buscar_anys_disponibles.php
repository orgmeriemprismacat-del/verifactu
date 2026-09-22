<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/js/revisions.js  */

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

$sql = "SELECT DISTINCT ANY FROM cursos WHERE ANY>=2019 ORDER BY ANY DESC";
$result_anys = mysqli_query ($conn, $sql);
$html_any = "<option value=\"Triar\" selected>-- Tria un any --</option>";

for ($n=0; $n<mysqli_num_rows($result_anys); $n++)
{
	$any = mysqli_fetch_array($result_anys);
	$html_any .= "<option value=".$any['ANY'].">".$any['ANY']."</option>";
}

echo $html_any;
mysqli_close($conn);

?>
