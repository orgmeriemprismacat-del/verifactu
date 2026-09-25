<?php //require_once("./inc/config.inc.php"); 
require('../../config.php'); ?><!DOCTYPE html>
<!--[if lt IE 7 ]><html class="ie ie6" lang="es"> <![endif]-->
<!--[if IE 7 ]><html class="ie ie7" lang="es"> <![endif]-->
<!--[if IE 8 ]><html class="ie ie8" lang="es"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--><html lang="es"> <!--<![endif]-->
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

	<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

	<title>Calendari de tasques</title>

	<meta http-equiv="PRAGMA" content="NO-CACHE">
	<meta http-equiv="EXPIRES" content="-1">

	<link type="text/css" rel="stylesheet" media="all" href="./css/estilos2.css">

	</head>
<body>
	<div class="calendario_ajax">
		<div class="cal"></div><div id="mask"></div>
	</div>

<script src="https://code.jquery.com/jquery-1.11.1.min.js"></script>
	<script src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.12.0/jquery.validate.min.js"></script>
	<script src="https://ajax.aspnetcdn.com/ajax/jquery.validate/1.12.0/localization/messages_es.js"></script>

	<?php
		$codi_curs = $_GET['shortname'];
	?>

	<script>
	function generar_calendario(mes,anio,codi_curs)
	{
		var agenda=$(".cal");
		agenda.html("<img src='./images/loading.gif'>");
		$.ajax({
			type: "GET",
			url: "calendari2.php",
			cache: false,
			data: { mes:mes,anio:anio,accion:"generar_calendario",codi_curs:codi_curs}
		}).done(function( respuesta )
		{
			agenda.html(respuesta);
		});
	}

	function formatDate (input) {
		var datePart = input.match(/\d+/g),
		year = datePart[0].substring(2),
		month = datePart[1], day = datePart[2];
		return day+'/'+month+'/'+year;
	}

		$(document).ready(function()
		{
			/* GENERAMOS CALENDARIO CON FECHA DE HOY */
			generar_calendario("<?php if (isset($_GET["mes"])) echo $_GET["mes"]; ?>","<?php if (isset($_GET["anio"])) echo $_GET["anio"]; ?>","<?php echo $codi_curs ?>");


			/* AGREGAR UN EVENTO */
			$(document).on("click",'a.add',function(e)
			{
				e.preventDefault();
				var id = $(this).data('evento');
				var fecha = $(this).attr('rel');

				$('#mask').fadeIn(1000).html("<div id='nuevo_evento' class='window' rel='"+fecha+"'>Afegir una tasca el "+formatDate(fecha)+"</h2><a href='#' class='close' rel='"+fecha+"'>&nbsp;</a><div id='respuesta_form'></div><form class='formeventos'><input type='text' name='evento_titulo' id='evento_titulo' class='required'><input type='button' name='Enviar' value='Guardar' class='enviar'><input type='hidden' name='evento_fecha' id='evento_fecha' value='"+fecha+"'></form></div>");
			});

			/* LISTAR EVENTOS DEL DIA */
			$(document).on("click",'a.modal',function(e)
			{
				e.preventDefault();
				var fecha = $(this).attr('rel');

				$('#mask').fadeIn(1000).html("<div id='nuevo_evento' class='window' rel='"+fecha+"'>Tasques del "+formatDate(fecha)+"</h2><a href='#' class='close' rel='"+fecha+"'>&nbsp;</a><div id='respuesta'></div><div id='respuesta_form'></div></div>");
				alert(fecha+accion+codi_curs);
				$.ajax({
					type: "GET",
					url: "calendari2.php",
					cache: false,
					data: { fecha:fecha,accion:"listar_evento",codi_curs:"<?php echo $codi_curs ?>"}
				}).done(function( respuesta )
				{
					$("#respuesta_form").html(respuesta);
				});

			});

			$(document).on("click",'.close',function (e)
			{
				e.preventDefault();
				$('#mask').fadeOut();
				setTimeout(function()
				{
					var fecha=$(".window").attr("rel");
					var fechacal=fecha.split("-");
					generar_calendario(fechacal[1],fechacal[0],"<?php echo $codi_curs ?>");
				}, 500);
			});

			//guardar evento
			$(document).on("click",'.enviar',function (e)
			{
				e.preventDefault();
				if ($("#evento_titulo").valid()==true)
				{
					$("#respuesta_form").html("<img src='./images/loading.gif'>");
					var evento=$("#evento_titulo").val();
					var fecha=$("#evento_fecha").val();
					$.ajax({
						type: "GET",
						url: "calendari2.php",
						cache: false,
						data: { evento:evento,fecha:fecha,accion:"guardar_evento",codi_curs:"<?php echo $codi_curs ?>"}
					}).done(function( respuesta2 )
					{
						$("#respuesta_form").html(respuesta2);
						$(".formeventos,.close").hide();
						setTimeout(function()
						{
							$('#mask').fadeOut('fast');
							var fechacal=fecha.split("-");
							generar_calendario(fechacal[1],fechacal[0],"<?php echo $codi_curs ?>");
						}, 3000);
					});
				}
			});

			//eliminar evento
			$(document).on("click",'.eliminar_evento',function (e)
			{
				e.preventDefault();
				var current_p=$(this);
				$("#respuesta").html("<img src='./images/loading.gif'>");
				var id=$(this).attr("rel");
				$.ajax({
					type: "GET",
					url: "calendari2.php",
					cache: false,
					data: { id:id,accion:"borrar_evento",codi_curs:"<?php echo $codi_curs ?>"}
				}).done(function( respuesta2 )
				{
					$("#respuesta").html(respuesta2);
					current_p.parent("p").fadeOut();
				});
			});

			$(document).on("click",".anterior,.siguiente",function(e)
			{
				e.preventDefault();
				var datos=$(this).attr("rel");
				var nueva_fecha=datos.split("-");
				generar_calendario(nueva_fecha[1],nueva_fecha[0],"<?php echo $codi_curs ?>");
			});

		});
		</script>

</body>
</html>
