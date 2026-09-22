<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/js/comunicats.js  */

$mes = $_GET['mes'];
$any = $_GET['any'];
$hores = $_REQUEST['hores'];

include('../inc/funcions_strings.php');
include('../inc/dades.php');
include('../inc/dades_moodle_nou.php');

$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($connexio, "utf8");

$connexio_m = mysqli_connect('localhost',$usuari_m,$pw_m,$bbdd_m);
if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($connexio_m, "utf8");

function mes_lletres($valor)
{
	include('../inc/dades.php');
	$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
	$valor = intval($valor);
	$consulta_mesos = "SELECT nom, minusc FROM mesos WHERE (mesos.id=".$valor.")";
	$result_mesos = mysqli_query($connexio, $consulta_mesos);
	$row_mesos = mysqli_fetch_array($result_mesos);
	$nom_mes_curs = $row_mesos['minusc'];

	if (($nom_mes_curs=='abril') || ($nom_mes_curs=='agost') || ($nom_mes_curs=='octubre'))
		$nom_mes_curs = "d'".$nom_mes_curs;
	else
		$nom_mes_curs = "de ".$nom_mes_curs;

	return $nom_mes_curs;
}

if ($hores=="Tots")
	$result_curs = mysqli_query ($connexio, "SELECT id_Curs, ANY, MES, CURS, AULA, `NOM CURS` as NOM, codi_udg, `DATA FIN` as datafi, Data_llarga_Inici FROM cursos WHERE ANY=".$any." AND MES='".$mes."' AND DNI_TUTOR <> '0' AND CURS NOT LIKE '%0%' ORDER BY CURS, AULA");
else if ($hores=="40")
	$result_curs = mysqli_query ($connexio, "SELECT id_Curs, ANY, MES, CURS, AULA, `NOM CURS` as NOM, codi_udg, `DATA FIN` as datafi, Data_llarga_Inici FROM cursos WHERE ANY=".$any." AND MES='".$mes."' AND DNI_TUTOR <> '0' AND (HORES = 40) AND CURS NOT LIKE '%0%' ORDER BY CURS, AULA");
else
	$result_curs = mysqli_query ($connexio, "SELECT id_Curs, ANY, MES, CURS, AULA, `NOM CURS` as NOM, codi_udg, `DATA FIN` as datafi, Data_llarga_Inici FROM cursos WHERE ANY=".$any." AND MES='".$mes."' AND DNI_TUTOR <> '0' AND (HORES = ".$hores.") AND CURS NOT LIKE '%0%' ORDER BY CURS, AULA");

$html_cursos = "<table class=\"table table-hover\" id=\"taula_cursos\" style=\"max-width: 1200px;\">
<thead class=\"thead-light\">
	<tr>
	  <th scope=\"col\">ANY</th>
	  <th scope=\"col\">CURS</th>
	  <th scope=\"col\">MES</th>
	  <th scope=\"col\">AULA</th>
	  <th scope=\"col\">NOM CURS</th>
	  <th scope=\"col\">SELECCIONAR</th>
	  <th scope=\"col\">ENLLAÇ</th>
	</tr>
</thead>
<tbody>";

for ($n=0; $n<mysqli_num_rows($result_curs); $n++)
{
	$row = mysqli_fetch_array($result_curs);

	$shortname = $row['ANY'].$row['CURS'].$row['MES'].$row['AULA'];

	//BUSCAR LA ID DEL CURS CORRESPONENT AL SHORTNAME
	$consulta_curs_moodle = "SELECT id, fullname FROM mdl_course WHERE shortname = '".$shortname."'";
	$result_curs_moodle = mysqli_query($connexio_m, $consulta_curs_moodle);
	$row_curs_moodle = mysqli_fetch_array($result_curs_moodle);
	$id_curs_moodle = $row_curs_moodle['id'];

	//BUSCAR EL NOM I EL INICI DEL CURS CORRESPONENT AL SHORTNAME
	//$nom_llarg_curs = str_replace("'","\'",$row['nom_curs']);
	$nom_llarg_curs = $row['NOM'];
	$inici_curs = $row['Data_llarga_Inici'];


	//BUSCAR LA ID DE L'AULA OBERTA CORRESPONENT
	$consulta_aula_oberta_moodle = "SELECT id FROM mdl_course WHERE shortname = '".$row['CURS']."'";
	$result_aula_oberta_moodle = mysqli_query($connexio_m, $consulta_aula_oberta_moodle);
	$row_aula_oberta_moodle = mysqli_fetch_array($result_aula_oberta_moodle);
	$id_aula_oberta_moodle = $row_aula_oberta_moodle['id'];


	//SI ESTÀ PENDENT DE COMENÇAR ES MARCA EN VERMELL
	if ($row['codi_udg'] != '#') {
		$html_cursos .= "<tr>";
	}
	else {
		$html_cursos .= "<tr class=\"text-danger\">";
	}

	//ES MOSTREN LES DADES DEL CURS
	$html_cursos .= "<td id=\"any".$n."\">".$row['ANY']."</td>";
	$html_cursos .= "<td id=\"mes".$n."\">".$row['MES']."</td>";
	$html_cursos .= "<td id=\"curs".$n."\">".$row['CURS']."</td>";
	$html_cursos .= "<td id=\"aula".$n."\">".$row['AULA']."</td>";
	$html_cursos .= "<td id=\"nom".$n."\">".$row['NOM']."</td>";

	$html_cursos .= "<td>";

	//SI EL CURS ESTÀ PENDENT DE COMENÇAR, ES DESMARCARÀ. SI EL MISSATGE JA S'HA ENVIAT, ES MARCARÀ COM A ENVIAT. ALTRAMENT ES MARCARÀ PER ENVIAR EL MISSATGE.
	if ($row['codi_udg'] != '#') {
		//buscar si el missatge està escrit
		$consulta_id_forum_moodle = "SELECT * FROM mdl_forum_discussions WHERE course=".$id_curs_moodle." and name LIKE \"ACC%S A L’AULA OBERTA\"";
		$result_forum = mysqli_query($connexio_m, $consulta_id_forum_moodle);

		$row_d = mysqli_fetch_array($result_forum);

		$consulta_id_forum_moodle_p = "SELECT id FROM mdl_forum_posts WHERE discussion=".$row_d['id']."";
		$result_forum_p = mysqli_query($connexio_m, $consulta_id_forum_moodle_p);

		if(mysqli_num_rows($result_forum)>0 && (mysqli_num_rows($result_forum_p)>0))
			$html_cursos .= "enviat";
		else
		{
			if (mysqli_num_rows($result_forum)>0 && (mysqli_num_rows($result_forum_p)==0))
			{
				//eliminar missatge comunicat
				$delete_id_forum_discussion = "DELETE FROM mdl_forum_discussions WHERE id =".$row_d['id']."";
				$result_forum = mysqli_query($connexio_m, $delete_id_forum_discussion);
			}
			$html_cursos .= "<input type=\"checkbox\" id=\"missatge_enviat".$n."\" name=\"missatge_enviat".$n."\" checked=\"checked\" />";
		}
	}
	else {
		$html_cursos .= "<input type=\"checkbox\" id=\"missatge_enviat".$n."\" name=\"missatge_enviat".$n."\" />";
	}

	$html_cursos .= "<td id=\"enllaç_curs".$n."\" style=\"width: 10%; text-align: center\"><a target=\"_blank\" href=\"https://www.prisma.cat/campus/course/view.php?id=".$id_curs_moodle."\"><img src=\"https://www.prisma.cat/intranet/img/informacio_comunicats.png\" /></a></td>";

	$html_cursos .= "<input type=\"hidden\" id=\"id_curs_moodle".$n."\" name=\"id_curs_moodle".$n."\" value=\"".$id_curs_moodle."\" />";

	$html_cursos .= "<input type=\"hidden\" id=\"any".$n."\" name=\"any".$n."\" value=\"".$row['ANY']."\" />";
	$html_cursos .= "<input type=\"hidden\" id=\"mes".$n."\" name=\"mes".$n."\" value=\"".$row['MES']."\" />";
	$html_cursos .= "<input type=\"hidden\" id=\"curs".$n."\" name=\"curs".$n."\" value=\"".$row['CURS']."\" />";
	$html_cursos .= "<input type=\"hidden\" id=\"aula".$n."\" name=\"aula".$n."\" value=\"".$row['AULA']."\" />";
	$html_cursos .= "<input type=\"hidden\" id=\"nom".$n."\" name=\"nom".$n."\" value=\"".$row['NOM']."\" />";

	$html_cursos .= "</td>";
	$html_cursos .= "</tr>";

	$nom_mes_curs = mes_lletres($mes);

	$data_fi_curs =  $row['datafi'];
	$increment_mes = 3;
	$data_tancament = date ("d-m-Y", strtotime("$data_fi_curs + $increment_mes months"));
	$parts_data_tancament = explode("-", $data_tancament);
	$dia_data_tancament = $parts_data_tancament[0]; //calcular el dia de la data de tancament
	$mes_data_tancament = $parts_data_tancament[1]; //calcular el dia de la data de tancament

	$nom_mes_tancament = mes_lletres($mes_data_tancament);

$message = "<p>Benvolgudes i benvolguts,</p>
<p>Us comuniquem que a partir d’avui teniu l’opció d’incriure-us a l’<strong>Aula Oberta ".$nom_llarg_curs."</strong>. </p>
<p>Les Aules Obertes són espais lliures, autoregulats i alternatius per a tots els que en algun moment heu estat alumnes d’un curs de PrisMa. Us poden servir per continuar aprenent i reflexionant amb l'acompanyament i suport de la resta de participants que han realitzat el mateix curs. </p>

<p>Què hi trobareu?</p>
<ul>
    <li>Tots els exalumnes del curs  <strong>".$nom_llarg_curs."</strong> que hagin sol·licitat tenir accés a l'Aula Oberta.</li>
    <li>Les lectures actualitzades i la bibliografia.</li>
    <li>Els audiovisuals.</li>
    <li>La mediateca actualitzada.</li>
    <li>Fòrums per intercanviar experiències i recursos.</li>
</ul>

<p>Si desitgeu accedir-hi i rebre informació relativa a la temàtica del curs, només heu d’entrar a la vostra Intranet i donar-hi el vostre consentiment: </p>
<p><a href=\"https://campus.prisma.cat/alumnes/meus-cursos/\" target=\"_blank\"><img title=\"La meva Intranet\" alt=\"La meva Intranet\" src=\"https://www.prisma.cat/documents/imatges/comunicats/intranet-alumnes.jpg\" /></a></p>
<p>Tingueu en compte que en qualsevol moment podreu donar-vos de baixa d'aquest espai.</p>
<p>Disposeu de tres mesos, fins al dia <strong>".intval($dia_data_tancament)." ".$nom_mes_tancament."</strong>, per seguir entrant al curs que heu realitzat (i consultar els fòrums, qüestionaris, devolucions, etc.), i a partir de llavors únicament tindreu accés, en cas que us hi hàgiu inscrit, a l’<strong>Aula Oberta</strong>.</p>
<p>Si voleu estar al cas de les novetats i promocions de PrisMa, us convidem a subscriure-us al nostre <a href=\"https://www.prisma.cat/mailing/nou.php\" target=\"_blank\">butlletí</a> i a seguir-nos a <a href=\"https://www.instagram.com/prisma.educacio/\" target=\"_blank\"><em>Instagram</em></a>, <a href=\"https://www.facebook.com/PrisMaFormacio\" target=\"_blank\"><em>Facebook</em></a> i <a href=\"https://twitter.com/PrisMaFormacio\" target=\"_blank\"><em>Twitter</em></a>... I si teniu un minut..., podeu deixar-nos <a href=\"https://g.page/r/CczqwM5-nqOCEB0/review\" target=\"_blnk\">una ressenya a Google</a>; la vostra opinió és molt important per a nosaltres!.</p>
<p>Salutacions ben cordials,</p>
<p>Pablo Martori.</p>";

	//$message = str_replace("'","\'",$message);

	if ($row['codi_udg'] != '#' && (mysqli_num_rows($result_forum)==0 || mysqli_num_rows($result_forum_p)==0))
		$html_cursos .= "<tr id=\"previsualitzacio\"><td colspan=\"7\"><div style=\"text-align: left; padding: 20px; margin: 35px; box-shadow: 0px 0px 30px -10px #424141; \">".$message."</div></td></tr>";
}

$html_cursos .= "</tbody>";
$html_cursos .= "</table>";

$html_cursos .= "<input type=\"hidden\" id=\"num_total_reg\" name=\"num_total_reg\" value=\"".$n."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"any\" name=\"any\" value=\"".$row['ANY']."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"mes\" name=\"mes\" value=\"".$row['MES']."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"comunicat\" name=\"comunicat\" value=\"aules_obertes\" >";

$html_cursos .= "<input type=\"submit\" name=\"enviar_correus\" class=\"botones\" value=\"Enviar correus\"/>";

echo $html_cursos;

mysqli_close($conexion);
?>
