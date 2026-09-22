<?php
session_name("sessio_admin");
session_start();

$pagina = "revisio";
$grup = "tut";

if(isset($_SESSION['usuari']) && isset($_SESSION['contrasenya_encriptada']))
{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<!--<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>Intranet | Revisió dels cursos</title>
	<link rel="stylesheet" href="./css/estilo_back.css"/>    -->

	<!-- Required meta tags -->
    <meta charset="utf-8">
	<!-- Responsive meta tag -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<title>Intranet | Revisió dels cursos</title>

	<!-- CSS Menu-->
	<link rel="stylesheet" href="./css/estilo_back.css"/>
	<!-- CSS General Intranet-->
	<link rel="stylesheet" href="./css/estil_general.css"/>
	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

	<!-- jQuery-->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<!-- Bootstrap JS -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<!-- AJAX-->
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>

	<script>
		//busquem tots els mesos que hi han cursos d'un any
		function cursos_disponibles() {
			var any = $("#any").val();
			var mes = $("#mesos").val();
			$.ajax({
				url: "https://old.prisma.cat/ajax/buscar_cursos_disponibles_revisions_coord.php?&any="+any+"&mes="+mes+"&order=id_Curs",
				cache: false,
				type: "GET",
				success: function(cursos) {
					$("#cursos").html(cursos);
				}
			});

		}

		function mesos_disponibles() {
			var any = $("#any").val();
			$.ajax({
				url: "https://old.prisma.cat/ajax/buscar_mesos_disponibles_any_actual.php?&any="+any,
				cache: false,
				type: "GET",
				success: function(mesos) {
					$("#mesos").html(mesos);
					cursos_disponibles();
				}
			});

		}

		function anys_disponibles() {
			$.ajax({
				url: "https://old.prisma.cat/ajax/buscar_anys_disponibles_any_actual.php?",
				cache: false,
				type: "GET",
				success: function(anys) {
					$("#any").html(anys);
					mesos_disponibles();
				}
			});
		}

		$(document).ready(function(){
			anys_disponibles();
		});
	</script>

</head>

<body topmargin="0">
	<table width="1100" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main">
        <tr>
            <?php
				// menú principal
				include('./inc/menu_intranet.php');
			?>
        </tr>
        <!--<tr>
            <td>
                <img src="./img/formacio_rectangular.jpg" style="float:left" />
                <div style="position:relative; width:1100px; padding-right:25px; padding-top:10px; text-align:right"><input type="button" class="myButton" name="myButton" value="Tancar sessió" onclick="window.location.href='tancarsessio.php';" /></div>
                <div id="menu-ppal" style="clear:both;">
                    <?php
                    if ($_SESSION['rol']=="admin")
                    {
					?>
						<div class="nav ppal" style="margin-left:19px; visibility:"><a href="pagaments.php" style="margin-left:1px;">GESTIÓ</a></div>
                        <div class="nav ppal"><a href="alumnes_secre.php" style="margin-left:1px;">SECRETARIA</a></div>
                        <div class="nav_on ppal_on">COORDINACIÓ</div>
                        <div class="nav ppal"><a href="resum_anual_mesos.php" style="margin-left:1px;">ADMINISTRACIÓ</a></div>
                        <div class="nav ppal"><a href="moodle.php" style="margin-left:1px;">SUPORT</a></div>

					<?php
					}
					else if ($_SESSION['rol']=="coord")
					{
					?>

                        <div class="nav ppal" style="margin-left:19px; visibility:"><a style="margin-left:1px;">GESTIÓ</a></div>
                        <div class="nav ppal"><a class="res" style="margin-left:1px;">SECRETARIA</a></div>
                        <div class="nav_on ppal_on">COORDINACIÓ</div>
                        <div class="nav ppal"><a class="res" href="resum_anual_mesos.php" style="margin-left:1px;">ADMINISTRACIÓ</a></div>
                        <div class="nav ppal"><a class="res" style="margin-left:1px;">SUPORT</a></div>
					<?php
					}
					?>
                </div>

                <div id="menu" style="clear:both; padding-top:10px; padding-left:50px">
                <div class="nav2"><a href="proposta_tutoritzacio.php" style="margin-left:1px;"><span style="font-size:10px">PROPOSTA</span><br />TUTORITZACIÓ</a></div>                <div class="nav2"><a href="confirmacio_tutoritzacio.php" style="margin-left:1px;"><span style="font-size:10px">CONFIRMACIÓ</span><br />TUTORITZACIÓ</a></div>
                <div class="nav_on2" style="margin-left:1px; padding-top: 12px; height: 25px;">REVISIÓ</div>
                </div>
            </td>
        </tr>      -->
        <tr>
            <td align="center">
                <div id="login">
                    <div id="llegenda_curs">
                    	<form name="correus" method="post" action="<?php echo $PHP_SELF ?>">
                            <div style="text-align:left; border-top:solid 1px #CCCCCC">
                            <br />Selecciona el mes d'inici del curs:
                            &nbsp;
                            <select name="any" id="any" size="1" onChange="mesos_disponibles()">
                                <!--<option value="2017" selected="selected">2017</option>
                                <option value="2018" selected="selected">2018</option>-->
                            </select>
                            &nbsp;
                            <select name="mesos" id="mesos" size="1" onChange="cursos_disponibles()">
                                <!--<option value="Cap" selected>-- Tria el mes --</option>
                                <option value="01">Gener</option>
                                <option value="02">Febrer</option>
                                <option value="03">Març</option>
                                <option value="04">Abril</option>
                                <option value="05">Maig</option>
                                <option value="06">Juny</option>
                                <option value="07">Juliol</option>
                                <option value="08">Agost</option>
                                <option value="09">Setembre</option>
                                <option value="10">Octubre</option>
                                <option value="11">Novembre</option>
                                <option value="12">Desembre</option>-->
                            </select>
                            <!--&nbsp;&nbsp;<input type="submit" name="buscar" class="botones" value="buscar"/>--><br /><br /><br />
                        	</div>

							<div id="cursos">

							</div>

							<?php

							if ($_POST['confirmar']=="RECORDAR REVISIONS")
							{
								$conexion = mysqli_connect('localhost','suport','1324GiRoNa','gestio');

								mysqli_set_charset ($conexion, "utf8");

								if (mysqli_connect_errno())
								{
									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
								}
								else
								{
									for ($i=1;$i<$_POST[registros];$i++)
									{
										if (isset($_POST[confirmar."$i"]))
										{
											$headers  = "MIME-Version: 1.0\r\n";
											$headers .= "Content-type: text/html; charset=UTF-8\r\n"; 
											$headers .= "From: Isabel López <suport@prisma.cat>\nReply-To: suport@prisma.cat";

											// mail a tutor con correos distintos
											$to = $_POST[correu."$i"].", ".$_POST[correue."$i"].", suport@prisma.cat";
											//$to = "suport.informatic@prisma.cat";

											$subject = "Revisió edició curs ".$_POST[id."$i"]."";

											$message = "<p>Benvolgut/da ".$_POST[nom."$i"].",</p>
											<p align=justify>T'informem que encara està pendent el teu enviament conforme has pogut realitzar la revisió dels diferents recursos de l'aula.</p>

											<p align=justify>Et recordem que és necessari enviar la revisió <strong>abans que comenci el curs</strong> perquè el servei de suport pugui solucionar les incidències abans de l'inici, així els participants comencen el curs amb l'aula actualitzada.</p>

											<p align=justify>Restem a l'espera de la teva tramesa.</p>
											<p>Salutacions cordials,</p>
<p>Isabel López<br>
Departament de Formaci&oacute;<br>
Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa<br>
972 21 75 65 - 678 12 36 87 - www.prisma.cat<br>
<div style='padding-top: 6px; border-bottom: 1px solid #7a7a7b; padding-bottom: 6px; max-width: 352px'>
   		<a title='Instagram' name='Instagram' href='https://www.instagram.com/prisma.educacio/' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/instagram.png'>
   		</a><a title='YouTube' name='YouTube' href='https://www.youtube.com/channel/UCy5M8DYXgHm5IjI4MIUCXqg' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/youtube.png'>
   		</a><a title='Facebook' name='Facebook' href='https://www.facebook.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/facebook.png'>
   		</a><a title='X' name='X' href='https://x.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/x.png'>
   		</a><a title='Tiktok' name='Tiktok' href='https://www.tiktok.com/@prisma.educacio' target='_blank' style='text-decoration: none!important;'>
   			<img border='0' style='height: 30px;' src='https://www.prisma.cat/img/social/firma/tiktok.png'>
   		</a>
   	</div>
<p style='font-size:10px' align=justify><br />Aquest missatge es dirigeix exclusivament al seu destinatari; si no &eacute;s aix&iacute;, et preguem que ens ho comuniquis i l’esborris. La informaci&oacute; tractada pot ser confidencial i no est&agrave; permesa la seva comunicaci&oacute;, reproducci&oacute; o distribuci&oacute;. De conformitat amb el que disposa la normativa vigent en protecció de dades (<em>RGPD</em> i <em>LOPD</em>), les teves dades personals estan incorporades als nostres fitxers amb la finalitat de dur a terme correctament les gestions acad&eacute;miques i administratives i mantenir el contacte amb tu per via correu electr&ograve;nic. En qualsevol moment pots exercir els teus drets d'acc&eacute;s, rectificaci&oacute;, cancel·laci&oacute; i oposici&oacute; escrivint a l'Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa a atencio.usuari@prisma.cat. Consulta l’<a title=\"Avís legal\" name=\"Avís legal\" href=\"https://www.prisma.cat/avis-legal\" target=\"_blank\">Av&iacute;s legal</a> per a més informaci&oacute;.</p>";

											if (mail($to, $subject, $message, $headers))
												echo("S'ha enviat un missatge a ".$_POST[nom."$i"]." ".$_POST[cognoms."$i"]." per al curs ".$_POST[id."$i"].".<br><br>");
											else
												echo("No s'ha pogut enviat un missatge a ".$_POST[nom."$i"]." ".$_POST[cognoms."$i"]."<br><br>");

										}
									}
								}
							}

							?>

                        </form>
                    </div>
                </div>
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
