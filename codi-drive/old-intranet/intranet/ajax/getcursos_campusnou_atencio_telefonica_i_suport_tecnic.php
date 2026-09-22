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
	$result_curs = mysqli_query ($connexio, "SELECT id_Curs, ANY, MES, CURS, AULA, `NOM CURS` as NOM, codi_udg, `DATA FIN` as datafi, Data_llarga_Inici FROM cursos WHERE ANY=".$any." AND MES='".$mes."' AND DNI_TUTOR <> 0 AND CURS NOT LIKE '%0%' ORDER BY CURS, AULA");
else if ($hores=="30_40")
	$result_curs = mysqli_query ($connexio, "SELECT id_Curs, ANY, MES, CURS, AULA, `NOM CURS` as NOM, codi_udg, `DATA FIN` as datafi, Data_llarga_Inici FROM cursos WHERE ANY=".$any." AND MES='".$mes."' AND DNI_TUTOR <> 0 AND (HORES = 30 OR HORES = 40) AND CURS NOT LIKE '%0%' ORDER BY CURS, AULA");
else
	$result_curs = mysqli_query ($connexio, "SELECT id_Curs, ANY, MES, CURS, AULA, `NOM CURS` as NOM, codi_udg, `DATA FIN` as datafi, Data_llarga_Inici FROM cursos WHERE ANY=".$any." AND MES='".$mes."' AND DNI_TUTOR <> 0 AND HORES = ".$hores." AND CURS NOT LIKE '%0%' ORDER BY CURS, AULA");


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
	$nom_llarg_curs = $row['nom_curs'];
	$inici_curs = $row['Data_llarga_Inici'];

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
		$consulta_id_forum_moodle = "SELECT * FROM mdl_forum_discussions WHERE course=".$id_curs_moodle." and name = \"ATENCIÓ TELEFÒNICA I SUPORT TÈCNIC\"";
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

	$message = "<p>Bon dia,</p>
	<p>Per si us sorgeix algun dubte o incidència, recordeu que compteu amb un servei d'atenció telefònica per a qualsevol <strong>consulta administrativa, de secretaria o tècnica</strong>.</p>
	<p><a href=\"https://campus.prisma.cat/alumnes/contacte/\" title=\"Cliqueu aquí\" target=\"_blank\"><img title=\"CLICA AQUÍ\" alt=\"CLICA AQUÍ\" src=\"https://www.prisma.cat/documents/imatges/comunicats/atencio-usuari.jpg\" hspace=\"0\" border=\"0\" /></a></p>
	<p>Els telèfons de secretaria són el <strong>972 21 75 65</strong> i el <strong>678 123 687</strong>. Hi podeu fer les vostres consultes <strong>de dilluns a divendres de 8 a 15 h</strong>.</p>
	<p>També us podeu posar en contacte amb <a href=\"https://campus.prisma.cat/alumnes/contacte/incidencies\" title=\"Cliqueu aquí\" target=\"_blank\"><strong>Suport informàtic</strong></a> a través de l'enllaç que trobareu al Campus:</p>
	<p><a href=\"https://campus.prisma.cat/alumnes/contacte/incidencies\" title=\"Cliqueu aquí\" target=\"_blank\"><img title=\"CLICA AQUÍ\" alt=\"CLICA AQUÍ\" src=\"https://www.prisma.cat/documents/imatges/comunicats/suport-informatic.jpg\" hspace=\"0\" border=\"0\" /></a></p>
	<p>També us podeu posar en contacte amb PrisMa des de «<a href=\"https://campus.prisma.cat/alumnes/\" title=\"Cliqueu aquí\" target=\"_blank\"><strong>la meva intranet</strong></a>»</p>
	<p><a href=\"https://campus.prisma.cat/alumnes/\" title=\"Cliqueu aquí\" target=\"_blank\"><img title=\"CLICA AQUÍ\" alt=\"CLICA AQUÍ\" src=\"https://www.prisma.cat/documents/imatges/comunicats/la-meva-intranet.jpg\" hspace=\"0\" border=\"0\" /></a></p>
	<p>Nota: us recomanem que utilitzeu els navegadors <strong><a href=\"https://www.mozilla.org/es-ES/firefox/new/\" title=\"cliqueu aquí\" name=\"Mozilla Firefox\" target=\"_blank\">Mozilla Firefox</a></strong> i <strong><a href=\"https://www.google.com/intl/es/chrome/browser/?hl=es\" title=\"cliqueu aquí\" name=\"Google Chrome\" target=\"_blank\">Google Chrome</a></strong> per accedir al Campus i per a una millor realització del curs.</p>
	<p>No dubteu a contactar amb nosaltres!</p>
	<p>Ben cordialment,</p>
	<p>Pablo Martori <br />Secretari Pedagògic</p>";

	if ($row['codi_udg'] != '#' && (mysqli_num_rows($result_forum)==0 || mysqli_num_rows($result_forum_p)==0))
		$html_cursos .= "<tr id=\"previsualitzacio\"><td colspan=\"7\"><div style=\"text-align: left; padding: 20px; margin: 35px; box-shadow: 0px 0px 30px -10px #424141; \">".$message."</div></td></tr>";
}

$html_cursos .= "</tbody>";
$html_cursos .= "</table>";

$html_cursos .= "<input type=\"hidden\" id=\"num_total_reg\" name=\"num_total_reg\" value=\"".$n."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"any\" name=\"any\" value=\"".$row['ANY']."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"mes\" name=\"mes\" value=\"".$row['MES']."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"comunicat\" name=\"comunicat\" value=\"atencio_telefonica_i_suport_tecnic\" >";

$html_cursos .= "<input type=\"submit\" name=\"enviar_correus\" class=\"botones\" value=\"Enviar correus\"/>";

echo $html_cursos;

mysqli_close($conexion);
?>
