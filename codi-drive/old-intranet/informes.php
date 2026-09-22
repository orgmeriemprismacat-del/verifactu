<?php
session_name("sessio_admin");
session_start();
$pagina = "informes";
$grup = "secretaria";

if(isset($_SESSION['usuari']) && isset($_SESSION['contrasenya_encriptada']) && ($_SESSION['rol']=="admin" or $_SESSION['rol']='tut'))
{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<!-- Required meta tags -->
    <meta charset="utf-8">
	<!-- Responsive meta tag -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<title>Intranet | Informes</title>

	<!-- CSS Menu-->
	<link rel="stylesheet" href="./css/estilo_back.css"/>
	<!-- CSS General Intranet-->
	<link rel="stylesheet" href="./css/estil_general.css"/>
	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

	<!-- Funcions relacionades amb els anys i els mesos generals -->
	<script language="Javascript" src="./js/buscar_anys_mesos.js"></script>
	<!-- Funcions js utilitzades a informes -->
	<script language="Javascript" src="./js/informes.js"></script>

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
                            	<br />Selecciona l'any i el mes a cercar:
								&nbsp;
								<select name="any" id="any" size="1" onChange="mesos_disponibles()">
									<option value="Triar" selected>-- Tria un any --</option>
								</select>
								&nbsp;
								<select name="mesos" id="mesos" size="1" onChange="cursos_disponibles()">
									<option value="Cap" selected>-- Tria un mes --</option>
								</select>
								<br /><br /><br />
                           	</div>
							<div id="cursos">

							</div>
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
