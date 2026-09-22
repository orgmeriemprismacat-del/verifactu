<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/js/revisions.js  */

$servername = "localhost";
$username = "suport";
$password = "1324GiRoNa";
$dbname = "gestio";

$any = $_REQUEST['any'];
$mes = $_REQUEST['mes'];

$conn = mysqli_connect($servername, $username, $password, $dbname);
if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($conn, "utf8");

$html_cursos = "<table class=\"table table-hover\" id=\"taula_cursos\">
<thead class=\"thead-light\">
	<tr>
	  <th scope=\"col\">ANY</th>
	  <th scope=\"col\">CURS</th>
	  <th scope=\"col\">MES</th>
	  <th scope=\"col\">AULA</th>
	  <th scope=\"col\" style=\"text-align: left\">NOM CURS</th>
	  <th scope=\"col\" style=\"text-align: left\">TUTOR</th>
	  <th scope=\"col\">DATA</th>
	  <th scope=\"col\">INFORME</th>
	</tr>
</thead>
<tbody>";

$sql_curs = "SELECT DISTINCT ANY, MES, CURS, AULA, `NOM CURS`AS NOM_CURS, DNI_TUTOR, HORES, NOM, COGNOMS FROM cursos, personal WHERE cursos.dni_tutor=personal.dni and ANY=".$any." and MES=".$mes." and DNI_TUTOR<>'0' ORDER BY id_Curs";

$result_cursos = mysqli_query ($conn, $sql_curs);

//Per cada curs mostrem les dades
for ($n=0; $n<mysqli_num_rows($result_cursos); $n++) {
	$row = mysqli_fetch_array($result_cursos);

	$dni_tutor = $row['DNI_TUTOR'];
	$shortname = $row['ANY'].$row['CURS'].$row['MES'].$row['AULA'];

	$nom_cognoms_tutor = $row['NOM']." ".$row['COGNOMS'];

	$sql_informe = "SELECT finalitzat FROM informe_tutor WHERE codic='$shortname'";
	$result_informe = mysqli_query ($conn, $sql_informe);
	$row_informe = mysqli_fetch_array($result_informe);

	if ($row_informe['finalitzat']!="") //s'ha realitzat l'informe
	{
		$estat_informe = "<a href=\"https://old.prisma.cat/informes/".strtolower($row['CURS']).".php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
		$html_cursos .= "<tr>";
	}
	else {
		$estat_informe = "-";
		$html_cursos .= "<tr class=\"linia_desactivada\">";
	}

	$data = date_create($row_informe['finalitzat']);
	$data = date_format($data,"d-m-Y");

	$html_cursos .= "<td id=\"any".$n."\" \">".$row['ANY']."</td>";
	$html_cursos .= "<td id=\"mes".$n."\" \">".$row['MES']."</td>";
	$html_cursos .= "<td id=\"curs".$n."\" \">".$row['CURS']."</td>";
	$html_cursos .= "<td id=\"aula".$n."\" \">".$row['AULA']."</td>";
	$html_cursos .= "<td id=\"nom".$n."\"  style=\"text-align: left\" \">".$row['NOM_CURS']."</td>";
	$html_cursos .= "<td id=\"tutor".$n."\"  style=\"text-align: left\" \">".$nom_cognoms_tutor."</td>";
	$html_cursos .= "<td id=\"data".$n."\"  \">".$data."</td>";
	$html_cursos .= "<td id=\"informe".$n."\" \">".$estat_informe."</td>";
	$html_cursos .= "</tr>";
}

$html_cursos .= "</tbody></table>";

echo $html_cursos;
mysqli_close($conn);
?>
