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
	$result_curs = mysqli_query ($connexio, "SELECT id_Curs, ANY, MES, CURS, AULA, `NOM CURS` AS nom_curs, codi_udg, `DATA INICI` AS datai, `DATA FIN` as datafi, Data_llarga_Inici FROM cursos WHERE ANY=".$any." AND MES='".$mes."' AND DNI_TUTOR <> '0' AND CURS NOT LIKE '%0%' ORDER BY CURS, AULA");
else if ($hores=="40")
	$result_curs = mysqli_query ($connexio, "SELECT id_Curs, ANY, MES, CURS, AULA, `NOM CURS` AS nom_curs, codi_udg, `DATA INICI` AS datai, `DATA FIN` as datafi, Data_llarga_Inici FROM cursos WHERE ANY=".$any." AND MES='".$mes."' AND DNI_TUTOR <> '0' AND (HORES = 40) AND CURS NOT LIKE '%0%' ORDER BY CURS, AULA");
else
	$result_curs = mysqli_query ($connexio, "SELECT id_Curs, ANY, MES, CURS, AULA, `NOM CURS` AS nom_curs, codi_udg, `DATA INICI` AS datai, `DATA FIN` as datafi, Data_llarga_Inici FROM cursos WHERE ANY=".$any." AND MES='".$mes."' AND DNI_TUTOR <> '0' AND (HORES = ".$hores.") AND CURS NOT LIKE '%0%' ORDER BY CURS, AULA");


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
		$consulta_id_forum_moodle = "SELECT id FROM mdl_forum_discussions WHERE course=".$id_curs_moodle." and name LIKE \"A PUNT DE COMEN%AR...\"";
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

	$data_inici_curs =  $row['datai'];
	$data_inici = date ("d-m-Y", strtotime("$data_inici_curs"));
	$parts_data_inici = explode("-", $data_inici);
	$dia_data_inici = $parts_data_inici[0]; //calcular el dia de la data d'inici
	$mes_data_inici = $parts_data_inici[1]; //calcular el mes de la data d'inici
	$setmana_data_inici = date ("l", strtotime($row['datai']));

	$nom_mes_inici = mes_lletres($mes_data_inici);
	$nom_setmana_catala = setmana_english_catala($setmana_data_inici);

	if ($row['CURS']=='HTP')
		$preguntes_frequents = "faqs_htp";
	else if ($row['CURS']=='TEL')
		$preguntes_frequents = "faqs_tel";
	else if ($row['CURS']=='INTEL')
		$preguntes_frequents = "faqs_intel";
	else
		$preguntes_frequents = "preg_frequents";

	$message = "<p>Bon dia,</p>
	<p>Us recordem que, tot i que el curs <strong>".$nom_llarg_curs."</strong> comenci el dia <strong>".intval($dia_data_inici)." ".$nom_mes_inici."</strong>, ja podeu accedir a algunes seccions del curs.</p>
	<p>Què podeu fer per <strong>tenir-ho tot a punt</strong>?</p>
	<p>Podeu començar <strong>actualitzant les dades del vostre perfil de la Plataforma (Moodle)</strong> (nom, imatge, adreça electrònica, població, etc.). Per fer-ho, heu d'entrar a «<strong><a href='https://campus.prisma.cat/alumnes/' target=\"_blank\">la meva intranet</a></strong>» al final del bloc «<strong>L'espai de l'usuari</strong>» que trobareu dintre de l'aula:</p>
	<p><img title=\"ESPAI DE USUARI\" alt=\"ESPAI DE USUARI\" src=\"https://www.prisma.cat/documents/imatges/comunicats/espai_usuari.jpg\" border=\"0\" hspace=\"0\" style=\"border: 0; margin-left: 0px; margin-right: 0px; width: 100%; max-width: 232px;\" /></p>
	<p>Clicant a l'espai de la imatge podeu modificar les vostres dades i <strong>personalitzar el vostre usuari amb una imatge personalitzada</strong>.</p>
	<p>Us recordem que les dades a les quals poden accedir els participants del curs són el nom i la població.</p>
	<p>A la resta de dades del vostre perfil del campus (el llistat de cursos realitzats a PrisMa i l'adreça de correu electrònic) només hi podreu accedir vosaltres i els tutors del curs.</p>
	<p>També us podeu presentar al fòrum <strong>Presentació i expectatives</strong>... Volem saber qui sou i què espereu del curs al qual us heu inscrit. Trobareu el fòrum a l'<strong>Apartat General</strong> (l'únic mòdul que de moment tenim obert a l'<strong>Aula Virtual</strong>).</p>
	<p>Finalment, per conèixer el <strong>funcionament del curs</strong>, us recomano que consulteu els <strong>bàners</strong> que trobareu a l'esquerra de l'<strong>Aula Virtual</strong>.</p>
	<p><a href=\"https://www.prisma.cat/menu/funcionament/funcionament-".strtolower($row['CURS']).".html\" target=\"_blank\"><img title=\"FUNCIONAMENT DEL CURS\" alt=\"FUNCIONAMENT DEL CURS\" src=\"https://www.prisma.cat/documents/imatges/comunicats/funcionament-curs.jpg\" border=\"0\" hspace=\"0\" style='width: 100%; max-width: 202px;' /></a></p>
	<p><a href=\"https://www.prisma.cat/menu/preguntes/".$preguntes_frequents.".php\" target=\"_blank\"><img title=\"PREGUNTES FREQÜENTS\" alt=\"PREGUNTES FREQÜENTS\" src=\"https://www.prisma.cat/documents/imatges/comunicats/preguntes-frequents.jpg\" border=\"0\" hspace=\"0\" style='width: 100%; max-width: 202px;' /></a></p>
	<p></p>
	<p>Us recordo que el proper ".$nom_setmana_catala." entre les 9 i les 10 h activarem totes les seccions del curs.</p>
	<p>Podeu entrar al Campus Virtual des de:</p>
	<p align=\"left\"><a href=\"https://www.prisma.cat\" target=\"_blank\"><img title=\"CAMPUS VIRTUAL PRISMA\" alt=\"CAMPUS VIRTUAL PRISMA\" src=\"https://www.prisma.cat/documents/imatges/comunicats/nou_acces_campus.jpg\" style='width: 100%; max-width:900px;' /></a></p>
	<p>Per accedir al Campus heu de fer servir les dades següents: </p>
	<ul>
	<li>El nom d'usuari és el <strong>DNI/NIE sense cap lletra</strong> ni cap zero inicial.</li>
	<li>La contrasenya és la que heu rebut per correu electrònic (si sou usuaris nous) o la que teníeu al Campus. Podeu demanar-ne una de nova clicant damunt «<strong><a href=\"https://campus.prisma.cat/login/forgot_password.php\" target=\"_blank\">Heu oblidat la contrasenya?</a></strong>» (escrivint només el DNI sense la lletra) o contactant amb <a href=\"mailto:secretaria@prisma.cat\">secretaria@prisma.cat</a>.</li>
	</ul>
	<p>Fins ben aviat,</p>
	<p>Pablo Martori <br />Secretari Pedagògic</p>";

	if ($row['codi_udg'] != '#' && (mysqli_num_rows($result_forum)==0 || mysqli_num_rows($result_forum_p)==0))
		$html_cursos .= "<tr id=\"previsualitzacio\"><td colspan=\"7\"><div style=\"text-align: left; padding: 20px; margin: 35px; box-shadow: 0px 0px 30px -10px #424141; \">".$message."</div></td></tr>";
}

$html_cursos .= "</tbody>";
$html_cursos .= "</table>";

$html_cursos .= "<input type=\"hidden\" id=\"num_total_reg\" name=\"num_total_reg\" value=\"".$n."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"any\" name=\"any\" value=\"".$row['ANY']."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"mes\" name=\"mes\" value=\"".$row['MES']."\" >";
$html_cursos .= "<input type=\"hidden\" id=\"comunicat\" name=\"comunicat\" value=\"a_punt_de_comencar\" >";

$html_cursos .= "<input type=\"submit\" name=\"enviar_correus\" class=\"botones\" value=\"Enviar correus\"/>";

echo $html_cursos;

mysqli_close($conexion);
?>
