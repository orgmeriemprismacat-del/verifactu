<?php
   require('../../config.php');
   include ('../ConnexioWeb.php'); //BD cursos
   include ('../ConnexioMoodle.php'); //BD cursos
   include ('../ConnexioMoodleAntic.php'); //BD cursos
   include ('../ConnexioIntranet.php'); //BD cursos
   include ('../Text.php');
   include ('inc/missatgesError.php');

   $conWeb = new ConnexioWeb();
   $conWeb->connectarBD();

   if ( $stmt = $conWeb->prepare( "SELECT ADRECA, CP, POBLE, FIX, MBL, EMAIL
   FROM contacte WHERE ESTAT=1" ) ) {
      $stmt->execute();
      $stmt->store_result();
      if ( $stmt->num_rows() > 0 ) {
         $stmt->bind_result($adreca, $cp, $poblacio, $numFix, $numMbl, $email);
         $stmt->fetch();
      }
   }
   else {
      throw new Exception('', 11301);
   }

   $conWeb->desconectarBD();

?>

<?php
 /***************************************************************/

 // Aquí es canvia el codi del curs que s'ha de passar l'informe. També canviar el títol del document a "informe_model.php"

 $curs = $_GET['shortname']; // canviar també el número de radios si canviem les preguntes!!!!
 $course = substr( $curs, 4, -3);
 $codi_curs = substr($_GET['shortname'], 0, -1)."a";

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
	<head>
      <!-- Required meta tags -->
      <meta charset='utf-8'>
      <meta http-equiv='X-UA-Compatible' content='IE=edge'>
      <!-- Responsive meta tag -->
      <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>

      <title>Calendari de tasques</title>

      <link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css'>
      <!-- Font Awesome -->
      <script src="https://kit.fontawesome.com/649877b29a.js" crossorigin="anonymous"></script>
      <!-- Colors CSS -->
      <link rel='stylesheet' href='css/variables.css?ver=2.0'>
      <!-- Font Awesome -->
      <script src="https://kit.fontawesome.com/649877b29a.js" crossorigin="anonymous"></script>

      <!-- jQuery JS-->
      <script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js'></script>
      <script>
         function generar_calendario(mes,anio,codi_curs)
         {
            var agenda=$("#agenda");
            agenda.html("<img src='./images/loading.gif'>");
            $.ajax({
               type: "GET",
               url: "calendari.php",
               cache: false,
               data: { mes:mes,anio:anio,accion:"generar_calendario",codi_curs:codi_curs }
            }).done(function( respuesta )
            {
               agenda.html(respuesta);
               $('a.modal').bind("click",function(e)
               {
                  e.preventDefault();
                  var id = $(this).data('evento');
                  var fecha = $(this).attr('rel');
                  if (fecha!="")
                  {
                     $("#evento_fecha").val(fecha);
                     $("#que_dia").html(fecha);
                  }
                  var maskHeight = $(document).height();
                  var maskWidth = $(window).width();

                  $('#mask').css({'width':maskWidth,'height':maskHeight});

                  $('#mask').fadeIn(1000);
                  $('#mask').fadeTo("slow",0.8);

                  var winH = $(window).height();
                  var winW = $(window).width();

                  $(id).css('top',  winH/2-$(id).height()/2);
                  $(id).css('left', winW/2-$(id).width()/2);

                  $(id).fadeIn(200);

               });

               $('.close').bind("click",function (e)
               {
                  var fecha=$(this).attr("rel");
                  var nueva_fecha=fecha.split("-");
                  e.preventDefault();
                  $('#mask').hide();
                  $('.window').hide();
                  generar_calendario(nueva_fecha[1],nueva_fecha[0],"<?php echo substr($_GET['shortname'], 0, -1)."a" ?>");
               });

               //guardar evento
               $('.enviar').bind("click",function (e)
               {
                  e.preventDefault();
                  $("#respuesta_form").html("<img src='./images/loading.gif'>");
                  var evento=$("#evento_titulo").val();
                  var fecha=$("#evento_fecha").val();
                  $.ajax({
                     type: "GET",
                     url: "calendari.php",
                     cache: false,
                     data: {evento:evento,fecha:fecha,accion:"guardar_evento",codi_curs:"<?php echo substr($_GET['shortname'], 0, -1)."a" ?>"}
                  }).done(function( respuesta2 )
                  {
                     $("#respuesta_form").html(respuesta2);
                     var evento=$("#evento_titulo").val("");
                  });
               });

               //eliminar evento
               $('.eliminar_evento').bind("click",function (e)
               {
                  e.preventDefault();
                  var current_p=$(this);
                  $(".respuesta").html("<img src='./images/loading.gif'>");
                  var id=$(this).attr("rel");
                  $.ajax({
                     type: "GET",
                     url: "calendari.php",
                     cache: false,
                     data: { id:id,accion:"borrar_evento",codi_curs:"<?php echo substr($_GET['shortname'], 0, -1)."a" ?>" }
                  }).done(function( respuesta2 )
                  {
                     $(".respuesta").html(respuesta2);
                     current_p.parent("p").fadeOut();
                  });
               });

               //pasar datos al form de editar
               $('.editar_evento').bind("click",function (e)
               {
                  e.preventDefault();
                  var evento=$(this).data("evento_titulo");
                  var id=$(this).data("id");
                  $("#evento_titulo").val(evento);
                  $("#id_evento").val(id);
                  $(".form_editar").fadeIn();
               });

               //editar evento
               $('.guardar_editar_evento').bind("click",function (e)
               {
                  e.preventDefault();
                  $(".form_editar").fadeOut();
                  var current_p=$(this);
                  $(".respuesta").html("<img src='./images/loading.gif'>");
                  var evento=$("#evento_titulo").val();
                  var id=$("#id_evento").val();
                  $.ajax({
                     type: "GET",
                     url: "calendari.php",
                     cache: false,
                     data: { evento:evento,id:id,accion:"editar_evento",codi_curs:"<?php echo substr($_GET['shortname'], 0, -1)."a" ?>"}
                  }).done(function( respuesta2 )
                  {
                     $(".respuesta").html(respuesta2);
                     $(".pevento").hide();
                  });
               });

               $(".anterior,.siguiente").bind("click",function(e)
               {
                  e.preventDefault();
                  var datos=$(this).attr("rel");
                  var nueva_fecha=datos.split("-");
                  generar_calendario(nueva_fecha[1],nueva_fecha[0],"<?php echo substr($_GET['shortname'], 0, -1)."a" ?>");
               })

               $(window).resize(function ()
               {
                  var box = $('#boxes .window');
                  var maskHeight = $(document).height();
                  var maskWidth = $(window).width();
                  $('#mask').css({'width':maskWidth,'height':maskHeight});
                  var winH = $(window).height();
                  var winW = $(window).width();
                  box.css('top',  winH/2 - box.height()/2);
                  box.css('left', winW/2 - box.width()/2);
               });
            });
         }
         $(document).ready(function()
         {
            /* GENERAMOS CALENDARIO CON FECHA DE HOY */
            generar_calendario("<?php if (isset($_GET["mes"])) echo $_GET["mes"]; ?>","<?php if (isset($_GET["anio"])) echo $_GET["anio"]; ?>","<?php echo $codi_curs ?>");
            setTimeout(function() {$('#mensaje').fadeOut('fast');}, 3000);
         });
      </script>
   </head>
   <body>
      <?php
      if (isloggedin()) {
         include('./inc/comprovarUser.php');
         if($user_course) {
             ?>
             <div id="agenda"></div>
             <div id="mask"></div>
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
      <!-- CSS -->
      <link rel='stylesheet' href='css/estilos.css?ver=1.1'>

      <script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
   </body>
</html>
