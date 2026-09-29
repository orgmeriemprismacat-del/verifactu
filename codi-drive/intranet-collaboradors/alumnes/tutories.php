<?php
require('../../config.php');
include ('../ConnexioMoodle.php'); //BD darrer
include ('../ConnexioWeb.php'); //BD cursos
include ('../ConnexioIntranet.php'); //BD intranet
include ('../Text.php');
include ('inc/missatgesError.php');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ca">
<head>
  <!-- Required meta tags -->
  <meta charset='utf-8'>
  <meta http-equiv='X-UA-Compatible' content='IE=edge'>
  <!-- Responsive meta tag -->
  <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>

  <title>Tutoria</title>

  <link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css'>
  <!-- Font Awesome -->
  <script src="https://kit.fontawesome.com/649877b29a.js" crossorigin="anonymous"></script>
  <!-- jQuery JS-->
  <script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js'></script>
  <!-- Activitat JS -->
  <script language='Javascript' src='js/tutoria.js?ver=3.2'></script>
</head>
<body>
	<div id='tot'>
		<?php
			if ( isloggedin() ) {
				include('./inc/comprovarUser.php');
				echo "<div id='shortname' style='display: none;'>".$_REQUEST['shortname']."</div>";
				echo "<div id='username' style='display: none;'>".$USER->username."</div>";
				echo "<div id='firstname' style='display: none;'>".$USER->firstname."</div>";
				echo "<div id='lastname' style='display: none;'>".$USER->lastname."</div>";
				echo "<div id='emailAlumne' style='display: none;'>".$USER->email."</div>";

				if ($user_course) {
					?>
		         <script>
		            var es_chrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;
		            var es_firefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;

		            if (!es_chrome && !es_firefox) {
		               $('#tot').html("<div id='activitat' class='col-md-12 marc_activitat_error'><div class='error'><img class='capcalera' src='https://campus.prisma.cat/intranet-collaboradors/alumnes/img/banner_prisma_rectangle.png'></div><div id='cos_error'><p style='margin-right: 40px; margin-left: 40px; text-align: center; line-height: 25px;'>Per evitar incompatibilitats és necessari utilitzar el navegador <a href='https://www.google.com/intl/es/chrome/browser/?hl=es' target='_blank' class='navegador'>Chrome</a> o <a href='https://www.mozilla.org/es-ES/firefox/new/' target='_blank' class='navegador'>Firefox</a>.</p><p style='margin-right: 40px; margin-left: 40px; text-align: center; line-height: 25px; text-align: center'>Per a qualsevol dubte podeu enviar-nos un correu a través del formulari d'<a href='https://www.prisma.cat/menu/usuari/suport.php' target='_blank' class='navegador'>Incidències tècniques</a>.</p></div><div id='peu' align='center'><br>972 21 75 65 · 678 12 36 87 · <a href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a> · secretaria@prisma.cat</div></div>");
		            }
		            else {
		               mostrarTutories();
		            }
		         </script>
					<?php
				}
				else
					include('./inc/errorNoInscritCurs.php');
			}
			else {
				include('./inc/errorNoIniciatSessio.php');
			}
		?>
	</div>

	<!-- Marc de l'activitat i inicar sessió en el curs corresponent CSS -->
	<link rel='stylesheet' href='css/login.css'>
	<link rel='stylesheet' href='css/general.css'>
	<!-- Font CSS -->
	<link rel='stylesheet' href='https://fonts.googleapis.com/css?family=Roboto:400,400i,500,500i,700,700i&display=swap' />

	<!-- Activitat CSS -->
	<link rel='stylesheet' href='css/tutoria.css?ver=1.0'>

	<!-- Bootstrap JS -->
  <script language="Javascript" src="./js/validacions.js?ver=1.0"></script>
  <script src='https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js'></script>
  <script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>
