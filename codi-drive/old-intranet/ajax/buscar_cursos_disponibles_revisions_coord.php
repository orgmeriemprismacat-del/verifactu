<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/js/revisions.js  */

$any = $_REQUEST['any'];
$mes = $_REQUEST['mes'];

include("../inc/dades.php");

$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($connexio, "utf8");

//$sql_curs = "SELECT DISTINCT ANY, MES, CURS, AULA, `NOM CURS`AS NOM, DNI_TUTOR, HORES FROM cursos WHERE ANY=".$any." and MES=".$mes." and DNI_TUTOR<>0 ORDER BY ".$order."";
$sql_curs = "SELECT id_Curs, CURS, AULA, NOM, COGNOMS, data_revisio, MAIL_PRISMA, MAIL_PERSONAL FROM cursos, personal WHERE ANY=".$any." AND MES='".$mes."' AND CURS NOT LIKE '%0%' AND DNI_TUTOR=DNI AND DNI_TUTOR!='0' AND DNI_TUTOR!='1' ORDER BY CURS, AULA";

$result_cursos = mysqli_query ($connexio, $sql_curs);

if(mysqli_num_rows($result_cursos)>0) 
{
	$html_cursos = "<table class=\"table table-hover\" id=\"taula_cursos\" style=\"max-width: 600px;\">
	<thead class=\"thead-light\">
		<tr>
		  <th scope=\"col\">CURS</th>
		  <th scope=\"col\">AULA</th>
		  <th scope=\"col\" style=\"text-align: left\">TUTOR</th>
		  <th scope=\"col\">SELECCIONAR</th>
		</tr>
	</thead>
	<tbody>";

	$j=0;
	//Per cada curs mostrem les dades
	for ($n=1; $n<=mysqli_num_rows($result_cursos); $n++) 
	{
		$row = mysqli_fetch_array($result_cursos);	
													
		/*$html_cursos .= "<input type=\"hidden\" name=\"id".$n."\" value=\"".$row['id_Curs']."\"  />";
		$html_cursos .= "<input type=\"hidden\" name=\"nom".$n."\"  value=\"".$row['NOM'])."\"  />";
		$html_cursos .= "<input type=\"hidden\" name=\"cognoms".$n."\" value=\"".$row['COGNOMS']."\"  />";
		$html_cursos .= "<input type=\"hidden\" name=\"correu".$n."\"  value=\"".$row['MAIL_PRISMA']."\"  />";
		$html_cursos .= "<input type=\"hidden\" name=\"correue".$n."\" value=\"."row['MAIL_PERSONAL']."\"  />";*/
		
		$html_cursos .= "<input type=\"hidden\" id=\"id".$n."\" name=\"id".$n."\" value=\"".$row['id_Curs']."\" />";
		$html_cursos .= "<input type=\"hidden\" id=\"nom".$n."\" name=\"nom".$n."\" value=\"".$row['NOM']."\" />";
		$html_cursos .= "<input type=\"hidden\" id=\"cognoms".$n."\" name=\"cognoms".$n."\" value=\"".$row['COGNOMS']."\" />";
		$html_cursos .= "<input type=\"hidden\" id=\"correu".$n."\" name=\"correu".$n."\" value=\"".$row['MAIL_PRISMA']."\" />";
		$html_cursos .= "<input type=\"hidden\" id=\"correue".$n."\" name=\"correue".$n."\" value=\"".$row['MAIL_PERSONAL']."\" />";
		
		$html_cursos .= "<tr>";
		
		$html_cursos .= "<td id=\"curs".$n."\" \">".$row['CURS']."</td>";
		$html_cursos .= "<td id=\"aula".$n."\" \">".$row['AULA']."</td>";
		$html_cursos .= "<td id=\"tutor".$n."\"  style=\"text-align: left\" \">".$row['NOM']." ".$row['COGNOMS']."</td>";
		$html_cursos .= "<td id=\"confirmar".$n."\" \">";
		
		if ($row['data_revisio'] == '') 
		{
			$j++;
			$html_cursos .= "<input type=\"checkbox\" id=\"confirmar".$n."\" name=\"confirmar".$n."\" />";
		}
		else 
		{
			$html_cursos .= "Enviat";
		}
		
		$html_cursos .= "</td>";
		
		$html_cursos .= "</tr>";
		$html_cursos .= "<input type=\"hidden\" id=\"registros\" name=\"registros\" value=\"".$n."\" />";
	}

	$html_cursos .= "</tbody></table>";
	$n--;
	$html_cursos .= "<div style=\"width:480px\">";
		$html_cursos .= "<div align=\"left\"; style=\"margin:20px 0px; width:50%; float:left\">Nombre de cursos trobats: ".$n."</div>";
		$html_cursos .= "<div align=\"right\"; style=\"margin:20px 0px; width:50%; float:left\">";
			if ($j > 0) 
			{
				$html_cursos .= "<input type=\"submit\" name=\"confirmar\" class=\"botones\" value=\"RECORDAR REVISIONS\"/>";
			}
			else 
				$html_cursos .= "TOTS CONFIRMATS";
		$html_cursos .= "</div>";
	$html_cursos .= "</div><div style=\"clear: both;\"></div>";
}
else 
{
	$html_cursos .= "No s'han trobat resultats.<br><br>";	
}

echo $html_cursos;
mysqli_close($connexio);
?>
