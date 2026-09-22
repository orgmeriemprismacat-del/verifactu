<?php 
session_name("sessio_admin");
session_start();

$pagina = "estadistiques";
$grup = "coordinacio";

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
	
	<title>Intranet | Estadístiques</title>
	
	<!-- CSS Menu-->
	<link rel="stylesheet" href="./css/estilo_back.css"/>
	<!-- CSS General Intranet-->
	<link rel="stylesheet" href="./css/estil_general.css"/>
	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	<!-- CSS Estadístiques-->
	<link rel="stylesheet" href="./css/estil_estadistiques.css"/>
    
	<!-- Funcions relacionades amb els anys i els mesos generals -->
	<script language="Javascript" src="./js/stats_cursos.js"></script>
	<!-- Funcions js utilitzades a estadistiques -->
	<script language="Javascript" src="./js/estadistiques.js"></script>
	
	<!-- jQuery-->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<!-- Bootstrap JS -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<!-- AJAX-->
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>

	<script>
		$(document).ready(function(){
			cursos_disponibles();
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
                        <form name="stats" method="post" action="<?php echo $PHP_SELF ?>">
                        	<div style="text-align:left; border-top:solid 1px #CCCCCC">
                            	<br />Selecciona curs a consultar:								
                                &nbsp; 
								<select name="cursos" id="cursos" size="1" onChange="mostrar_estadistiques()">
									<option value="Cap" selected>-- Tria un curs --</option> 
								</select>
								<br /><br /><br />
                           	</div>
							<div id="estadistiques">
								
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