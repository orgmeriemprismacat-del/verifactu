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
		$nom_mes_curs = "d'<strong>".$nom_mes_curs."</strong>";
	else
		$nom_mes_curs = "de <strong>".$nom_mes_curs."</strong>";

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
		$consulta_id_forum_moodle = "SELECT id FROM mdl_forum_discussions WHERE course=".$id_curs_moodle." and name = \"OBERTURA D'AULES\"";
		$result_forum = mysqli_query($connexio_m, $consulta_id_forum_moodle);
		$row_d = mysqli_fetch_array($result_forum);

		$consulta_id_forum_moodle_p = "SELECT id FROM mdl_forum_posts WHERE discussion=".$row_d['id']."";
		$result_forum_p = mysqli_query($connexio_m, $consulta_id_forum_moodle_p);

		/*if(mysqli_num_rows($result_forum)>0)
			$html_cursos .= "enviat";
		else
			$html_cursos .= "<input type=\"checkbox\" id=\"missatge_enviat".$n."\" name=\"missatge_enviat".$n."\" checked=\"checked\" />";*/

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
		$html_cursos .= "pendent";
	}

	$html_cursos .= "<td id=\"enllaç_curs".$n."\" style=\"width: 10%; text-align: center\"><a target=\"_blank\" href=\"https://campus.prisma.cat/course/view.php?id=".$id_curs_moodle."\"><img src=\"https://www.prisma.cat/intranet/img/informacio_comunicats.png\" /></a></td>";

	$html_cursos .= "<input type=\"hidden\" id=\"id_curs_moodle".$n."\" name=\"id_curs_moodle".$n."\" value=\"".$id_curs_moodle."\" />";

	$html_cursos .= "<input type=\"hidden\" id=\"any".$n."\" name=\"any".$n."\" value=\"".$row['ANY']."\" />";
	$html_cursos .= "<input type=\"hidden\" id=\"mes".$n."\" name=\"mes".$n."\" value=\"".$row['MES']."\" />";
	$html_cursos .= "<input type=\"hidden\" id=\"curs".$n."\" name=\"curs".$n."\" value=\"".$row['CURS']."\" />";
	$html_cursos .= "<input type=\"hidden\" id=\"aula".$n."\" name=\"aula".$n."\" value=\"".$row['AULA']."\" />";
	$html_cursos .= "<input type=\"hidden\" id=\"nom".$n."\" name=\"nom".$n."\" value=\"".$row['NOM']."\" />";

	$html_cursos .= "</td>";
	$html_cursos .= "</tr>";

	$nom_mes_curs = mes_lletres($mes);

	$message = "<p>Benvolgudes i benvolguts,</p>
	<p>Us informem que hem obert les \"portes\" de les aules del curs <strong>".$nom_llarg_curs."</strong> del mes ".$nom_mes_curs.", encara que les classes començaran oficialment el dia <strong>".$inici_curs."</strong>.</p>
	<p>Sóc en Pablo Martori, i juntament amb els meus companys de secretaria realitzo tasques d’administració dels cursos, com ara revisar activitats, assegurar el funcionament dels vídeos i enllaços, comprovar si les dades són correctes, controlar les inscripcions, respondre correus, fer els certificats, etc.</p>
	<p>Ja podeu entrar al vostre curs per llegir les <strong>Lectures</strong>, consultar la <strong>Bibliografia</strong>, veure els <strong>Audiovisuals</strong> i visitar els enllaços de la <strong>Mediateca</strong>.</p>
	<p>Heu d’anar a <a target=\"_blank\" href=\"https://www.prisma.cat\"><strong>www.prisma.cat</strong></a> i entrar-hi utilitzant l’usuari i contrasenya que us hem donat per correu electrònic (o els que fèieu servir en cursos anteriors).</p>
	<p>Si teniu problemes per ingressar al campus, podeu demanar una contrasenya nova clicant a «<strong><a href=\"https://campus.prisma.cat/login/forgot_password.php\" target=\"_blank\">Heu oblidat la contrasenya?</a></strong>»; escriviu el vostre <strong>DNI sense la lletra</strong> i us enviarem una nova clau provisional per correu electrònic.</p>
	<p>Un cop enregistrats al campus, trobareu els cursos en què us heu matriculat.</p>
	<p>Heu de clicar a damunt del curs que trobareu a l'apartat «<a href=\"https://campus.prisma.cat/my\" target=\"_blank\"><strong>Els meus cursos</strong></a>» per accedir-hi.</p>";
	// $message .= "<p>Així doncs, si teniu qualsevol dubte o problema sobre el funcionament del curs, podeu enviar-nos un correu electrònic a <a target=\"_blank\" href=\"mailto:secretaria@prisma.cat\">secretaria@prisma.cat</a> o bé trucar-nos al 972 21 75 65 o al 678 123 687 (de dilluns a dijous de 8 h a 18 h i divendres de 8 h a 15 h).</p><p>Rebeu una salutació ben cordial,</p>";
	$message .= "<p>Així doncs, si teniu qualsevol dubte o problema sobre el funcionament del curs, podeu enviar-nos un correu electrònic a <a target=\"_blank\" href=\"mailto:secretaria@prisma.cat\">secretaria@prisma.cat</a> o bé trucar-nos al 972 21 75 65 o al 678 123 687 (de dilluns a divendres de 8 h a 15 h).</p><p>Rebeu una salutació ben cordial,</p>";
	$message .= "<p>Pablo Martori <br />Secretari Pedagògic</p>";

	//$message = str_replace("'","\'",$message);

	if ($row['codi_udg'] != '#' && (mysqli_num_rows($result_forum)==0 || mysqli_num_rows($result_forum_p)==0) )
		$html_cursos .= "<tr id=\"previsualitzacio\"><td colspan=\"7\"><div style=\"text-align: left; padding: 20px; margin: 35px; box-shadow: 0px 0px 30px -10px #424141; \">".$message."</div></td></tr>";
}

$html_cursos .= "</tbody>";
$html_cursos .= "</table>";

$html_cursos .= "<input type=\"hidden\" id=\"num_total_reg\" name=\"num_total_reg\" value=\"".$n."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"any\" name=\"any\" value=\"".$row['ANY']."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"mes\" name=\"mes\" value=\"".$row['MES']."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"comunicat\" name=\"comunicat\" value=\"obertura_aules\" >";

$html_cursos .= "<input type=\"submit\" name=\"enviar_correus\" class=\"botones\" value=\"Enviar correus\"/>";

echo $html_cursos;

mysqli_close($conexion);
?>
