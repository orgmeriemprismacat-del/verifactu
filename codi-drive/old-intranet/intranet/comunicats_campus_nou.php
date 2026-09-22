<?php
session_name("sessio_admin");
session_start();

$pagina = "comunicats";
$grup = "secretaria";

include('./inc/funcions_strings.php');

if(isset($_SESSION['usuari']) && isset($_SESSION['contrasenya_encriptada']) && ($_SESSION['rol']=="admin"))
{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<!-- Required meta tags -->
    <meta charset="utf-8">
	<!-- Responsive meta tag -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<title>Intranet | Comunicats d'aula</title>

	<!-- CSS Menu-->
	<link rel="stylesheet" href="./css/estilo_back.css"/>
	<!-- CSS General Intranet-->
	<link rel="stylesheet" href="./css/estil_general.css"/>
	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

	<!-- Funcions relacionades amb els anys i els mesos generals -->
	<!-- <script language="Javascript" src="./js/buscar_anys_mesos.js"></script> -->
	<!-- Funcions js utilitzades -->
	<script language="Javascript" src="./js/comunicats_campus_nou.js"></script>

	<!-- jQuery-->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<!-- Bootstrap JS -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<!-- AJAX-->
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>

	<script>
		$(document).ready(function(){
			anys_disponibles();
		});
	</script>
</head>

<body topmargin="0">

    <table align="center" style="min-width: 1100px;" id="main">
        <tr>
            <?php
				// menú principal
				include('./inc/menu_intranet.php');
			?>
        </tr>
        <tr>
            <td align="center">
                <div id="login">
                    <div id="llegenda_curs">
                        <form name="revisions" method="post" action="<?php echo $PHP_SELF ?>">
                        	<div style="text-align:left; border-top:solid 1px #CCCCCC">
                            	<p style='text-align: center; font-weight: bold;'>
											CAMPUS NOU
										</p>
                            	<p>
											Selecciona el comunicat que vols enviar, l'any i el mes a cercar:
										</p>
								&nbsp;
								<select name="comunicat" id="comunicat" size="1" onChange="mostrar_cursos()">
									<option value="Triar" selected>-- Tria un comunicat --</option>
									<option value="obertura_aules" >-- Obertura d'aules --</option>
									<option value="a_punt_de_comencar" >-- A punt de començar --</option>
									<option value="atencio_telefonica_i_suport_tecnic" >-- Atenció telefònica i suport tècnic --</option>
									<option value="certificat" >-- Certificat --</option>
									<option value="aules_obertes" >-- Aules Obertes --</option>
									<option value="tancament_curs" >-- Tancament de curs --</option>
								</select>
								&nbsp;
								<select name="any" id="any" size="1" onChange="mesos_disponibles()">
									<option value="Triar" selected>-- Tria un any --</option>
								</select>
								&nbsp;
								<select name="mesos" id="mesos" size="1" onChange="mostrar_cursos()">
									<option value="Cap" selected>-- Tria un mes --</option>
								</select>
								<select name="hores" id="hores" size="1" onChange="mostrar_cursos()">
									<option value="Triar" selected>-- Triar --</option>
									<option value="Tots">-- Tots --</option>
									<option value="15">-- 15 Hores --</option>
                                    <option value="30">-- 30 Hores --</option>
									<option value="40">-- 40 Hores --</option>
									<option value="50">-- 50 Hores --</option>
									<option value="60">-- 60 Hores --</option>
									<option value="70">-- 70 Hores --</option>
									<option value="100">-- 100 Hores --</option>
								</select>
								<br /><br /><br />
                           	</div>
							<div id="formulari"></div>
							<div id="loading" style="width: fit-content; padding-bottom: 10px;"></div>
							<div id="cursos">

							</div>

							<?php

							if ($_POST['enviar_correus']=="Enviar correus")
							{
								//echo "num total de registres".$_POST['num_total_reg']."<br>";

								for ($i=0;$i<$_POST[num_total_reg];$i++) {
									$mes = $_POST[mes."0"];

									$userid = 1006; //USUARI DE SECRETARIA

									include('./inc/dades.php');
									include('./inc/dades_moodle_nou.php');

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

									if (isset($_POST[missatge_enviat."$i"])) {
										$time = time();

										$shortname = $_POST[any."$i"].$_POST[curs."$i"].$_POST[mes."$i"].$_POST[aula."$i"];

										//busquem el nom i la id del curs del moodle
										$consulta_curs_moodle = "SELECT id FROM mdl_course WHERE shortname = '".$shortname."'";
										$result_curs_moodle = mysqli_query($connexio_m, $consulta_curs_moodle);
										$row_curs_moodle = mysqli_fetch_array($result_curs_moodle);
										$id_curs_moodle = $row_curs_moodle['id'];

										//busquem la data d'inici curs de prisma
										$consulta_curs_prisma = "SELECT id_Curs, `DATA INICI` AS datai, `NOM CURS` AS nom_curs, Data_llarga_Inici, `DATA FIN` AS datafi FROM cursos WHERE id_Curs = '".$shortname."'";
										$result_curs_prisma = mysqli_query($connexio, $consulta_curs_prisma);
										$row_curs_prisma = mysqli_fetch_array($result_curs_prisma);

										$nom_llarg_curs = $row_curs_prisma['nom_curs'];
										$inici_curs = $row_curs_prisma['datai'];
										$parts_data_inici = explode("-", $inici_curs);
										$dia_inici_curs = $parts_data_inici[2]; //calcular el mes de la d'inici
										$inici_curs = $parts_data_inici[1]; //calcular el mes de la d'inici
										$data_llarga_inici = $row_curs_prisma['Data_llarga_Inici'];

										//busquem el forum de comunicats d'aula del curs del moodle
										$consulta_id_forum_moodle = "SELECT * FROM mdl_forum WHERE course=".$id_curs_moodle." and name = \"COMUNICATS D'AULA\"";
										$result_forum = mysqli_query($connexio_m, $consulta_id_forum_moodle);
										$row_forum = mysqli_fetch_array($result_forum);
										$id_forum = $row_forum['id'];

										//busqueu els nom dels mes en què es fa un curs
										$consulta_mesos = "SELECT nom, minusc FROM mesos WHERE (mesos.id='".$inici_curs."')";
										$result_mesos = mysqli_query($connexio, $consulta_mesos);
										$row_mesos = mysqli_fetch_array($result_mesos);

										$nom_mes_curs = $row_mesos['minusc'];
										if (($nom_mes_curs=='abril') || ($nom_mes_curs=='agost') || ($nom_mes_curs=='octubre'))
											$nom_mes_curs = "d'<strong>".$nom_mes_curs."</strong>";
										else
											$nom_mes_curs = "de <strong>".$nom_mes_curs."</strong>";

										if ($_POST[comunicat]=="obertura_aules")
										{
											$nom_post = "OBERTURA D\'AULES";
											$message = "<p>Benvolgudes i benvolguts,</p>
											<p>Us informem que hem obert les \"portes\" de les aules del curs <strong>".$nom_llarg_curs."</strong> del mes ".$nom_mes_curs.", encara que les classes començaran oficialment el dia <strong>".$data_llarga_inici."</strong>.</p>
                                 <p>Sóc en Pablo Martori, i juntament amb els meus companys de secretaria realitzo tasques d’administració dels cursos, com ara revisar activitats, assegurar el funcionament dels vídeos i enllaços, comprovar si les dades són correctes, controlar les inscripcions, respondre correus, fer els certificats, etc.</p>
                              	<p>Ja podeu entrar al vostre curs per llegir les <strong>Lectures</strong>, consultar la <strong>Bibliografia</strong>, veure els <strong>Audiovisuals</strong> i visitar els enllaços de la <strong>Mediateca</strong>.</p>
                              	<p>Heu d’anar a <a target=\"_blank\" href=\"https://www.prisma.cat\"><strong>www.prisma.cat</strong></a> i entrar-hi utilitzant l’usuari i contrasenya que us hem donat per correu electrònic (o els que fèieu servir en cursos anteriors).</p>
                              	<p>Si teniu problemes per ingressar al campus, podeu demanar una contrasenya nova clicant a «<strong><a href=\"https://campus.prisma.cat/login/forgot_password.php\" target=\"_blank\">Heu oblidat la contrasenya?</a></strong>»; escriviu el vostre <strong>DNI sense la lletra</strong> i us enviarem una nova clau provisional per correu electrònic.</p>
                              	<p>Un cop enregistrats al campus, trobareu els cursos en què us heu matriculat.</p>
                                 <p>Heu de clicar a damunt del curs que trobareu a l'apartat «<a href=\"https://campus.prisma.cat/my\" target=\"_blank\"><strong>Els meus cursos</strong></a>» per accedir-hi.</p>";
											// $message .= "<p>Així doncs, si teniu qualsevol dubte o problema sobre el funcionament del curs, podeu enviar-nos un correu electrònic a <a target=\"_blank\" href=\"mailto:secretaria@prisma.cat\">secretaria@prisma.cat</a> o bé trucar-nos al 972 21 75 65 o al 678 123 687 (de dilluns a dijous de 8 h a 18 h i divendres de 8 h a 15 h).</p><p>Rebeu una salutació ben cordial,</p>";
											$message .= "<p>Així doncs, si teniu qualsevol dubte o problema sobre el funcionament del curs, podeu enviar-nos un correu electrònic a <a target=\"_blank\" href=\"mailto:secretaria@prisma.cat\">secretaria@prisma.cat</a> o bé trucar-nos al 972 21 75 65 o al 678 123 687 (de dilluns a divendres de 8 a 15 h).</p><p>Rebeu una salutació ben cordial,</p>";
											$message .= "<p>Pablo Martori <br />Secretari Pedagògic</p>";

											$message = str_replace("'","\'",$message);
										}
										else if ($_POST[comunicat]=="a_punt_de_comencar")
										{
											$setmana_data_inici = date ("l", strtotime($row_curs_prisma['datai']));
											$nom_setmana_catala = setmana_english_catala($setmana_data_inici);

											if ($_POST[curs."$i"]=='HTP')
												$preguntes_frequents = "faqs_htp";
											else if ($_POST[curs."$i"]=='TEL')
												$preguntes_frequents = "faqs_tel";
											else if ($_POST[curs."$i"]=='INTEL')
												$preguntes_frequents = "faqs_intel";
											else
												$preguntes_frequents = "preg_frequents";

											$nom_post = "A PUNT DE COMENÇAR...";
											$message = "<p>Bon dia,</p>
											<p>Us recordem que, tot i que el curs <strong>".$nom_llarg_curs."</strong> comenci el dia <strong>".intval($dia_inici_curs)." ".$nom_mes_curs."</strong>, ja podeu accedir a algunes seccions del curs.</p>
											<p>Què podeu fer per <strong>tenir-ho tot a punt</strong>?</p>
											<p>Podeu començar <strong>actualitzant les dades del vostre perfil de la Plataforma (Moodle)</strong>
											(nom, imatge, adreça electrònica, població, etc.). Per fer-ho, heu d'entrar a «<strong><a href='https://campus.prisma.cat/alumnes/' target=\"_blank\">la meva intranet</a></strong>» al final del bloc «<strong>L'espai de l'usuari</strong>» que
											 trobareu dintre de l'aula:</p>
											<p><img title=\"ESPAI DE USUARI\" alt=\"ESPAI DE USUARI\" src=\"https://www.prisma.cat/documents/imatges/comunicats/espai_usuari.jpg\" border=\"0\" hspace=\"0\" style=\"border: 0; margin-left: 0px; margin-right: 0px; width: 100%; max-width: 232px;\" /></p>
											<p>Clicant a l'espai de la imatge podeu modificar les vostres dades i <strong>personalitzar el vostre usuari amb una imatge personalitzada</strong>.</p>
											<p>Us recordem que les dades a les quals poden accedir els participants del curs són el nom i la població.</p>
											<p>A la resta de dades del vostre perfil del campus (el llistat de cursos realitzats a PrisMa i l'adreça de correu electrònic) només hi podreu accedir vosaltres i els tutors del curs.</p>
											<p>També us podeu presentar al fòrum <strong>Presentació i expectatives</strong>... Volem saber qui sou i què espereu del curs al qual us heu inscrit. Trobareu el fòrum a l'<strong>Apartat General</strong> (l'únic mòdul que de moment tenim obert a l'<strong>Aula Virtual</strong>).</p>
											<p>Finalment, per conèixer el <strong>funcionament del curs</strong>, us recomano que consulteu els <strong>bàners</strong> que trobareu a l'esquerra de l'<strong>Aula Virtual</strong>.</p>
											<p><a href=\"https://www.prisma.cat/menu/funcionament/funcionament-".strtolower($_POST[curs."$i"]).".html\" target=\"_blank\"><img title=\"FUNCIONAMENT DEL CURS\" alt=\"FUNCIONAMENT DEL CURS\" src=\"https://www.prisma.cat/documents/imatges/comunicats/funcionament-curs.jpg\" border=\"0\" hspace=\"0\" style='width: 100%; max-width: 202px;'/></a></p>
											<p><a href=\"https://www.prisma.cat/menu/preguntes/".$preguntes_frequents.".php\" target=\"_blank\"><img title=\"PREGUNTES FREQÜENTS\" alt=\"PREGUNTES FREQÜENTS\" src=\"https://www.prisma.cat/documents/imatges/comunicats/preguntes-frequents.jpg\" border=\"0\" hspace=\"0\"style='width: 100%; max-width: 202px;' /></a></p>
											<p></p>
											<p>Us recordo que el proper ".$nom_setmana_catala." entre les 9 i les 10 h activarem totes les seccions del curs.</p>
											<p>Podeu entrar al Campus Virtual des de:</p>
											<p align=\"left\"><a href=\"https://www.prisma.cat\" target=\"_blank\"><img title=\"CAMPUS VIRTUAL PRISMA\" alt=\"CAMPUS VIRTUAL PRISMA\" src=\"https://www.prisma.cat/documents/imatges/comunicats/nou_acces_campus.jpg\" style='width: 100%; max-width: 900px;'/></a></p>
											<p>Per accedir al Campus heu de fer servir les dades següents: </p>
											<ul>
											<li>El nom d'usuari és el <strong>DNI/NIE sense cap lletra</strong> ni cap zero inicial.</li>
											<li>La contrasenya és la que heu rebut per correu electrònic (si sou usuaris nous) o la que teníeu al Campus. Podeu demanar-ne una de nova clicant damunt «<strong><a href=\"https://campus.prisma.cat/login/forgot_password.php\" target=\"_blank\">Heu oblidat la contrasenya?</a></strong>» (escrivint només el DNI sense la lletra) o contactant amb <a href=\"mailto:secretaria@prisma.cat\">secretaria@prisma.cat</a>.</li>
											</ul>
											<p>Fins ben aviat,</p>
											<p>Pablo Martori <br />Secretari Pedagògic</p>";

											$message = str_replace("'","\'",$message);
										}
										else if ($_POST[comunicat]=="tancament_curs")
										{
											//busquem el nom del curs del moodle
											$consulta_aula_oberta_moodle = "SELECT id, fullname FROM mdl_course WHERE shortname = '".$_POST[curs."$i"]."'";
											$result_aula_oberta_moodle = mysqli_query($connexio_m, $consulta_aula_oberta_moodle);
											$row_aula_oberta_moodle = mysqli_fetch_array($result_aula_oberta_moodle);

											$id_aula_oberta_moodle = $row_aula_oberta_moodle['id'];
											//$nom_llarg_aula_oberta_moodle = str_replace("'","\'",$row_aula_oberta_moodle['fullname']);
											$nom_llarg_aula_oberta_moodle = $row_aula_oberta_moodle['fullname'];

											$data_fi_curs =  $row_curs_prisma['datafi'];
											$increment_mes = 3;
											$data_tancament = date ("d-m-Y", strtotime("$data_fi_curs + $increment_mes months"));
											$parts_data_tancament = explode("-", $data_tancament);
											$dia_data_tancament = $parts_data_tancament[0]; //calcular el dia de la data de tancament
											$mes_data_tancament = $parts_data_tancament[1]; //calcular el mes de la data de tancament

											$consulta_mesos_t = "SELECT nom, minusc FROM mesos WHERE (mesos.id='".$mes_data_tancament."')";
											$result_mesos_t = mysqli_query($connexio, $consulta_mesos_t);
											$row_mesos_t = mysqli_fetch_array($result_mesos_t);
											$nom_mes_tancament = $row_mesos_t['minusc'];

											if (($nom_mes_tancament=='abril') || ($nom_mes_tancament=='agost') || ($nom_mes_tancament=='octubre'))
												$nom_mes_tancament = "d'".$nom_mes_tancament;
											else
												$nom_mes_tancament = "de ".$nom_mes_tancament;

											$nom_post = "TANCAMENT DEL CURS";
											$message = "<p>Benvolgudes i benvolguts,</p>
											<p>Us informem que a partir del dia <strong>".intval($dia_data_tancament)." ".$nom_mes_tancament."</strong> es tancarà el curs <b>$nom_llarg_curs</b> del mes <strong>".$nom_mes_curs."</strong>.</p>
											<p>A partir d'aquest dia només podreu accedir, en cas que us hi hàgiu inscrit, a <b>Aula Oberta ".$nom_llarg_curs."</b>, on trobareu:</p>
											<ul>
											<li>Tots els exalumnes que han fet el curs <b>$nom_llarg_curs</b> des de la primera edició.</li>
											<li>Les lectures actualitzades</li>
											<li>La mediateca actualitzada</li>
											<li>Fòrums per intercanviar experiències i recursos</li>
											</ul>
											<p>Podeu accedir a l'aula oberta des d'aquesta imatge:</p>
											<p align=\"left\"><a title=\"cliqueu aquí\" href=\"https://campus.prisma.cat/course/view.php?id=".$id_aula_oberta_moodle."\" target=\"_blank\"><img title=\"CLIQUEU AQUI\" alt=\"CLIQUEU AQUI\" hspace=\"0\" src=\"https://www.prisma.cat/documents/imatges/comunicats/cursos/".strtolower($_POST[curs."$i"]).".jpg\" style='max-width: 800px; height: 138px; object-fit: cover; width:100%' border=\"0\" /></a></p>
											<p>En cas que no hi estigueu inscrits, visiteu la vostra Intranet per demanar-ne l’accés: </p>
											<p><a href=\"https://campus.prisma.cat/alumnes/meus-cursos/\" title=\"Cliqueu aquí\" target=\"_blank\"><img title=\"CLICA AQUÍ\" alt=\"CLICA AQUÍ\" src=\"https://www.prisma.cat/documents/imatges/comunicats/la-meva-intranet.jpg\" hspace=\"0\" border=\"0\" /></a></p>
											<p>Si no esteu subscrits al butlletí electrònic de PrisMa i us interessa rebre informació sobre les novetats dels nostres cursos, us hi podeu donar d'alta aquí: </p>
											<p><a title=\"Subscripció Butlletí electrònic\" name=\"Subscripció Butlletí electrònic\" href=\"https://www.prisma.cat/mailing/nou.php\" target=\"_blank\"><img src=\"https://www.prisma.cat/documents/imatges/comunicats/butlleti_electronic.jpg\" border=\"0\"></a></p>
											<p>Ben cordialment,</p>
											<p>Pablo Martori <br />Secretari Pedagògic</p>";

											$message = str_replace("'","\'",$message);
										}
										else if ($_POST[comunicat]=="atencio_telefonica_i_suport_tecnic")
										{
											$nom_post = "ATENCIÓ TELEFÒNICA I SUPORT TÈCNIC";
											$message = "<p>Bon dia,</p>
                              	<p>Per si us sorgeix algun dubte o incidència, recordeu que compteu amb un servei d'atenció telefònica per a qualsevol <strong>consulta administrativa, de secretaria o tècnica</strong>.</p>
                              	<p><a href=\"https://campus.prisma.cat/alumnes/contacte/\" title=\"Cliqueu aquí\" target=\"_blank\"><img title=\"CLICA AQUÍ\" alt=\"CLICA AQUÍ\" src=\"https://www.prisma.cat/documents/imatges/comunicats/atencio-usuari.jpg\" hspace=\"0\" border=\"0\" /></a></p>
                              	<p>Els telèfons de secretaria són el <strong>972 21 75 65</strong> i el <strong>678 123 687</strong>. L'horari d'atenció és <strong>de dilluns a divendres de 8 a 15 h</strong></p>
                              	<p>També us podeu posar en contacte amb <a href=\"https://campus.prisma.cat/alumnes/contacte/incidencies\" title=\"Cliqueu aquí\" target=\"_blank\"><strong>Suport informàtic</strong></a> a través de l'enllaç que trobareu al Campus:</p>
                              	<p><a href=\"https://campus.prisma.cat/alumnes/contacte/incidencies\" title=\"Cliqueu aquí\" target=\"_blank\"><img title=\"CLICA AQUÍ\" alt=\"CLICA AQUÍ\" src=\"https://www.prisma.cat/documents/imatges/comunicats/suport-informatic.jpg\" hspace=\"0\" border=\"0\" /></a></p>
                              	<p>També us podeu posar en contacte amb PrisMa des de «<a href=\"https://campus.prisma.cat/alumnes/\" title=\"Cliqueu aquí\" target=\"_blank\"><strong>la meva intranet</strong></a>»</p>
                              	<p><a href=\"https://campus.prisma.cat/alumnes/\" title=\"Cliqueu aquí\" target=\"_blank\"><img title=\"CLICA AQUÍ\" alt=\"CLICA AQUÍ\" src=\"https://www.prisma.cat/documents/imatges/comunicats/la-meva-intranet.jpg\" hspace=\"0\" border=\"0\" /></a></p>
                              	<p>Nota: us recomanem que utilitzeu els navegadors <strong><a href=\"https://www.mozilla.org/es-ES/firefox/new/\" title=\"cliqueu aquí\" name=\"Mozilla Firefox\" target=\"_blank\">Mozilla Firefox</a></strong> i <strong><a href=\"https://www.google.com/intl/es/chrome/browser/?hl=es\" title=\"cliqueu aquí\" name=\"Google Chrome\" target=\"_blank\">Google Chrome</a></strong> per accedir al Campus i per a una millor realització del curs.</p>
                              	<p>No dubteu a contactar amb nosaltres!</p>
                              	<p>Ben cordialment,</p>
                              	<p>Pablo Martori <br />Secretari Pedagògic</p>";
											$message = str_replace("'","\'",$message);
										}
										else if ($_POST[comunicat]=="certificat")
										{
											//busquem el nom del curs del moodle
											$consulta_aula_oberta_moodle = "SELECT id, fullname FROM mdl_course WHERE shortname = '".$_POST[curs."$i"]."'";
											$result_aula_oberta_moodle = mysqli_query($connexio_m, $consulta_aula_oberta_moodle);
											$row_aula_oberta_moodle = mysqli_fetch_array($result_aula_oberta_moodle);

											$id_aula_oberta_moodle = $row_aula_oberta_moodle['id'];
											$nom_llarg_aula_oberta_moodle = str_replace("'","\'",$row_aula_oberta_moodle['fullname']);
											// $nom_llarg_aula_oberta_moodle = $row_aula_oberta_moodle['fullname'];

											$data_fi_curs =  $row_curs_prisma['datafi'];
											$increment_mes = 3;
											$data_tancament = date ("d-m-Y", strtotime("$data_fi_curs + $increment_mes months"));
											$parts_data_tancament = explode("-", $data_tancament);
											$dia_data_tancament = $parts_data_tancament[0]; //calcular el dia de la data de tancament
											$mes_data_tancament = $parts_data_tancament[1]; //calcular el mes de la data de tancament

											$consulta_mesos_t = "SELECT nom, minusc FROM mesos WHERE (mesos.id='".$mes_data_tancament."')";
											$result_mesos_t = mysqli_query($connexio, $consulta_mesos_t);
											$row_mesos_t = mysqli_fetch_array($result_mesos_t);
											$nom_mes_tancament = $row_mesos_t['minusc'];

											if (($nom_mes_tancament=='abril') || ($nom_mes_tancament=='agost') || ($nom_mes_tancament=='octubre'))
												$nom_mes_tancament = "d'".$nom_mes_tancament;
											else
												$nom_mes_tancament = "de ".$nom_mes_tancament;

											$nom_post = "CERTIFICAT";

                      $nom_llarg_curs = str_replace("'","\'",$nom_llarg_curs);
                      $nom_mes_curs = str_replace("'","\'",$nom_mes_curs);
                      $nom_mes_tancament = str_replace("'","\'",$nom_mes_tancament);

											$message = "<p>Bon dia,</p>
											<p>Us comuniquem que us hem enviat la informació sobre el certificat del curs <strong>".$nom_llarg_curs."</strong> del mes ".$nom_mes_curs." al correu electrònic de la inscripció.</p>
											<p>Podeu consultar si el certificat consta a la XTEC, si es va enviar per correu electrònic o hi ha algun problema i encara s’està tramitant des de l’apartat «<a href=\"https://campus.prisma.cat/alumnes/meus-cursos/\" title=\"els meus cursos\" target=\"_blank\"><strong>Els meus cursos</strong></a>» que trobareu a «<a href=\"https://campus.prisma.cat/alumnes/\" title=\"la meva intranet\" target=\"_blank\"><strong>la meva intranet</strong></a>».</p>
											<p><a href=\"https://campus.prisma.cat/alumnes/\" title=\"Cliqueu aquí\" target=\"_blank\"><img title=\"CLICA AQUÍ\" alt=\"CLICA AQUÍ\" src=\"https://www.prisma.cat/documents/imatges/comunicats/la-meva-intranet.jpg\" hspace=\"0\" border=\"0\" /></a></p>
											<p>A més, us recordem que el curs seguirà obert fins al dia <strong>".intval($dia_data_tancament)." ".$nom_mes_tancament."</strong> per si voleu consultar els fòrums, els qüestionaris, les devolucions, etc. A partir d’aquest dia només tindreu accés a l’<strong>Aula Oberta ".$nom_llarg_curs."</strong>, que ja teniu activa per consultar la Mediateca, el Material de lectura, etc.</p>
											<p>Podeu entrar a l’aula oberta des d’<b>Els meus cursos</b> o clicant damunt la imatge següent:</p>
											<p><a target=\"_blank\" href=\"https://campus.prisma.cat/course/view.php?id=".$id_aula_oberta_moodle."\"><img hspace=\"0\" border=\"0\" src=\"https://www.prisma.cat/documents/imatges/comunicats/cursos/".strtolower($_POST[curs."$i"]).".jpg\" alt=\"CLIQUEU AQUI\" title=\"CLIQUEU AQUI\" style=\"max-width: 800px; height: 138px; object-fit: cover; width:100%\" /></a></p>
											<p>Si encara no heu demanat accés a l’aula oberta, podeu fer-ho des de l’apartat «<a href=\"https://campus.prisma.cat/alumnes/meus-cursos/\" title=\"els meus cursos\" target=\"_blank\"><strong>Els meus cursos</strong></a>» que trobareu a «<a href=\"https://campus.prisma.cat/alumnes/\" title=\"la meva intranet\" target=\"_blank\"><strong>la meva intranet</strong></a>».</p>
											<p>Salutacions ben cordials,</p>
											<p>Pablo Martori<br />Secretari Pedagògic</p>";

										}
				                  else if ($_POST[comunicat]=="aules_obertes")
				                  {

				                      $data_fi_curs =  $row_curs_prisma['datafi'];
											$increment_mes = 3;
											$data_tancament = date ("d-m-Y", strtotime("$data_fi_curs + $increment_mes months"));
											$parts_data_tancament = explode("-", $data_tancament);
											$dia_data_tancament = $parts_data_tancament[0]; //calcular el dia de la data de tancament
											$mes_data_tancament = $parts_data_tancament[1]; //calcular el mes de la data de tancament

											$consulta_mesos_t = "SELECT nom, minusc FROM mesos WHERE (mesos.id='".$mes_data_tancament."')";
											$result_mesos_t = mysqli_query($connexio, $consulta_mesos_t);
											$row_mesos_t = mysqli_fetch_array($result_mesos_t);
											$nom_mes_tancament = $row_mesos_t['minusc'];

											if (($nom_mes_tancament=='abril') || ($nom_mes_tancament=='agost') || ($nom_mes_tancament=='octubre'))
												$nom_mes_tancament = "d'".$nom_mes_tancament;
											else
												$nom_mes_tancament = "de ".$nom_mes_tancament;

			                      //missatge obertur aules
			                      $nom_post = "ACCÉS A L’AULA OBERTA";
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
										 <p>Si voleu estar al cas de les novetats i promocions de PrisMa, us convidem a subscriure-us al nostre <a href=\"https://www.prisma.cat/mailing/nou.php\" target=\"_blank\">butlletí</a> i a seguir-nos a <a href=\"https://www.instagram.com/prisma.educacio/\" target=\"_blank\"><em>Instagram</em></a>, <a href=\"https://www.facebook.com/PrisMaFormacio\" target=\"_blank\"><em>Facebook</em></a> i <a href=\"https://twitter.com/PrisMaFormacio\" target=\"_blank\"><em>Twitter</em>... I si teniu un minut..., podeu deixar-nos <a href=\"https://g.page/r/CczqwM5-nqOCEB0/review\" target=\"_blnk\">una ressenya a Google</a>; la vostra opinió és molt important per a nosaltres!</a>.</p>

										 <p>Salutacions ben cordials,</p>
										 <p>Pablo Martori.</p>";
			                      $message = str_replace("'","\'",$message);
			                    }

										//busquem la id de l'ultim forum_discussion
										$consulta_id_discussion_moodle = "SELECT * FROM mdl_forum_discussions ORDER BY id DESC LIMIT 1";
										$result_discussion = mysqli_query($connexio_m, $consulta_id_discussion_moodle);
										$row_discussion = mysqli_fetch_array($result_discussion);
										$id_discussions = intval($row_discussion['id'])+1;

										//busquem la id de l'ultim post
										$consulta_id_posts_moodle = "SELECT * FROM mdl_forum_posts ORDER BY id DESC LIMIT 1";
										$result_posts = mysqli_query($connexio_m, $consulta_id_posts_moodle);
										$row_posts = mysqli_fetch_array($result_posts);
										$id_posts = intval($row_posts['id'])+1;

										print 'Curs <strong>'.$shortname."</strong> enviat<br />";

										mysqli_query($connexio_m,"INSERT INTO mdl_forum_posts (id, discussion, parent, userid, created, modified, mailed, subject, message, messageformat, messagetrust, totalscore, mailnow) VALUES
										(".$id_posts.", ".$id_discussions.", 0, ".$userid.", ".$time.", ".$time.", 0, '".$nom_post."', '".$message."', 1, 1, 0, 0)");

                    echo "INSERT INTO mdl_forum_posts (id, discussion, parent, userid, created, modified, mailed, subject, message, messageformat, messagetrust, totalscore, mailnow) VALUES
										(".$id_posts.", ".$id_discussions.", 0, ".$userid.", ".$time.", ".$time.", 0, '".$nom_post."', '".$message."', 1, 1, 0, 0)";


										mysqli_query($connexio_m,"INSERT INTO mdl_forum_discussions (id, course, forum, name, firstpost, userid, groupid, assessed, timemodified, usermodified,  timestart, timeend) VALUES
										(".$id_discussions.", ".$id_curs_moodle.", ".$id_forum.", '".$nom_post."', ".$id_posts.", ".$userid.", 1, 1, ".$time.", ".$userid.", 0, 0)");

                    echo "INSERT INTO mdl_forum_discussions (id, course, forum, name, firstpost, userid, groupid, assessed, timemodified, usermodified,  timestart, timeend) VALUES
										(".$id_discussions.", ".$id_curs_moodle.", ".$id_forum.", '".$nom_post."', ".$id_posts.", ".$userid.", 1, 1, ".$time.", ".$userid.", 0, 0)";

										// PER COMPROVAR COM S'ENVIA, FEM PROVA AMB EL CORREU

									/*	$subject = $_POST[comunicat].$shortname;

										$headers = "MIME-Version: 1.0\r\n";
										$headers .= "Content-type: text/html; charset=UTF-8\r\n";
										$headers .= "From: SUPORT INFORMATIC <suport@prisma.cat>\nReply-To: suport.informatic@prisma.cat";

										mail("suport@prisma.cat", $subject, $message, $headers);*/
									}

								}

							}

							?>
                       	</form>
                    </div>
                </div>
                <br /><br />
            </td>
        </tr>
	</table>

</body>

</html>

<?php
}
else
{
	header("Location: acces.php");
	exit;
}
?>
