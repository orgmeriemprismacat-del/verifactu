<?php
session_name("sessio_admin");
session_start();

$pagina = "certificats";
$grup = "gestio";

include('../inc/funcions_strings.php');

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

	<title>Intranet | Generar certificats</title>

	<!-- CSS Menu-->
	<link rel="stylesheet" href="./css/estilo_back.css"/>
	<!-- CSS General Intranet-->
	<link rel="stylesheet" href="./css/estil_general.css"/>
	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

	<!-- jQuery-->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<!-- Funcions js utilitzades -->
	<script language="Javascript" src="./js/certificats.js"></script>
	<!-- Bootstrap JS -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<!-- AJAX-->
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>


</head>

<body topmargin="0">

    <table align="center" style="min-width: 1100px;" id="main">
        <tr>
            <?php
				// menú principal
				include('./inc/menu_intranet.php');
        echo "<div id='session' style='display:none'>".$_SESSION['usuari']."</div>";
			?>
        </tr>
        <tr>
            <td align="center">
                <div id="login">
                    <div id="llegenda_curs">
                        <form name="revisions" method="post" action="<?php echo $PHP_SELF ?>">
                        	<div style="text-align:left; border-top:solid 1px #CCCCCC">
              								<br />Selecciona el que vols fer:
              								&nbsp;
              								<select name="opcio" id="opcio" size="1" onChange="mostrar_cursos()">
              									<option value="Triar" selected>-- Tria una opció --</option>
              									<option value="generarCertificat" >-- Generar certificats --</option>
              									<!-- <option value="enviarCertificat" >-- Enviar certificats --</option> -->
              								</select>
              								&nbsp;
              								<br /><br /><br />
                           	</div>
            							<div id="formulari"></div>
            							<div id="loading" style="width: fit-content; padding-bottom: 10px;"></div>
            							<div id="cursos"></div>
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
