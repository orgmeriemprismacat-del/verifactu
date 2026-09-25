<?php

require('../../config.php');
include ('../ConnexioMoodle.php'); //BD darrer
include ('../ConnexioWeb.php'); //BD cursos
include ('../ConnexioIntranet.php'); //BD cursos
include ('inc/missatgesError.php');

//Canviar 22 per el nombre d'apartats que hi ha a la revisió
$num_inc = 29;
//Afegir tants #Cap com nombre d'apartats hi ha a la revisió
$cap_incidencia="";
for ( $i = 0; $i < $num_inc; $i++ ) {
	$cap_incidencia .= "#Cap";
}

$hores = "tic";
$user = $USER->username;
$shortname = $_REQUEST['shortname'];
$course = substr( $shortname, 4, -3);

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
	<head>
          <!-- Required meta tags -->
          <meta charset='utf-8'>
          <meta http-equiv='X-UA-Compatible' content='IE=edge'>
          <!-- Responsive meta tag -->
          <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>

          <title>Revisió</title>

          <link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css'>
          <!-- Font Awesome -->
          <script src="https://kit.fontawesome.com/649877b29a.js" crossorigin="anonymous"></script>
          <!-- Colors CSS -->
          <link rel='stylesheet' href='css/variables.css?ver=2.0'>
          <!-- Font Awesome -->
          <script src="https://kit.fontawesome.com/649877b29a.js" crossorigin="anonymous"></script>

          <!-- <link rel="stylesheet" href="../css/estil_revisions.css"/> -->
          <!-- jQuery JS-->
          <script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js'></script>
          <!-- Activitat JS -->
          <script language='Javascript' src='js/revisions.js?ver=1.0'></script>
	</head>
	<body>
<div id='row_revisio' class='col-md-12 marc_activitat d-flex justify-content-start'>
            <input type="hidden" id="num_apartats" value="<?php echo $num_inc;?>">
            <input type="hidden" id="hores" value="<?php echo $hores;?>">
            <input type="hidden" id="shortname" value="<?php echo $shortname;?>">
            <input type="hidden" id="course" value="<?php echo $course;?>">
            <input type="hidden" id="user" value="<?php echo $user;?>">
            <div class="prisma-header w-100">
               <nav id="nav-header" class="navbar prisma-nav w-100 px-0">
                   <div class="container">
                        <a role="link" class="navbar-brand prisma-brand mr-0 ml-0"
                        href="https://www.prisma.cat/" target="_self"
                        title="Veure la pàgina principal de PrisMa">
                            <img width="139.39" height="46" role="img" src="https://www.prisma.cat/img/logo-prisma-light.png" alt="Logo PrisMa">
                        </a>
                   </div>
               </nav>
            </div>
            <!-- <div class='container d-flex flex-column justify-content-center align-items-center text-center w-100 py-0 my-0'> -->
          <?php
           if (isloggedin()) {
              include('./inc/comprovarUser.php');
              if($user_course) {
              ?>
              <script>
                 var es_chrome = navigator.userAgent.toLowerCase().indexOf('chrome') > -1;
                 var es_firefox = navigator.userAgent.toLowerCase().indexOf('firefox') > -1;

                 if (!es_chrome && !es_firefox) {
                    $('#row_activitat').html("<div class='container no-inscrit d-flex flex-column justify-content-center align-items-center text-center w-100 py-0 my-0'>Per evitar incompatibilitats és necessari utilitzar el navegador <a href='https://www.google.com/intl/es/chrome/browser/?hl=es' target='_blank' class='navegador'>Chrome</a> o <a href='https://www.mozilla.org/es-ES/firefox/new/' target='_blank' class='navegador'>Firefox</a>.</p><p>Per a qualsevol dubte podeu enviar-nos un correu a través del formulari d'<a href='https://www.prisma.cat/menu/usuari/suport.php' target='_blank' class='navegador'>Incidències tècniques</a>.</p></div><div id='peu' align='center'><br>Pl. Poeta Marquina 5, 1r-1a · 17002 Girona · 972 21 75 65 · 678 12 36 87 · <a href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a> · secretaria@prisma.cat</div>");
                 }
                 else {
                    $('#row_revisio').append("<div id='revisio' class='container cnt_revisio d-flex flex-column justify-content-center align-items-center text-center w-100 py-0 my-0'></div>");
                    $('#row_revisio').append("<div id='peu' class='text-center my-4'>Pl. Poeta Marquina 5, 1r-1a · 17002 Girona · 972 21 75 65 · 678 12 36 87 · <a href='https://www.prisma.cat' target='_blank' style='color:#526b3e; text-decoration: none'>www.prisma.cat</a> · secretaria@prisma.cat</div>");
                    mostrarRevisio();
                 }
              </script>
              <?php
              }
              else {
                 include('./inc/errorNoInscritCurs.php');
              }
           }
           else {
              include('./inc/errorNoIniciatSessio.php');
           }
           ?>
           <!-- </div> -->
       </div>

    <!-- Marc de l'activitat i inicar sessió en el curs corresponent CSS -->
    <link rel='stylesheet' href='css/login.css'>
     <link rel='stylesheet' href='css/general.css'>
     <link rel='stylesheet' href='css/variables.css'>
     <!-- Font CSS -->
    <link rel='stylesheet' href='https://fonts.googleapis.com/css?family=Roboto:400,400i,500,500i,700,700i&display=swap' />

    <!-- Activitat CSS -->
    <link rel='stylesheet' href='css/forms.css?ver=1.0'>
    <link rel='stylesheet' href='css/revisions.css?ver=1.0'>

    <!-- Bootstrap JS -->
   <script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>
