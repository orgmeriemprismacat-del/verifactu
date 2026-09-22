<?php
session_name("sessio_tutor_extern");
session_start();
if(isset($_SESSION['usuari']) && isset($_SESSION['contrasenya_encriptada']))
{
	if ($_SESSION['usuari']=="G17843830" or $_SESSION['usuari']=="67253443") //Poso com a dni el de l'empresa d'ADS, el dni de la Laura, de l'Ester i de la Carme
		$dni = 	$_SESSION['usuari']."' OR DNI_TUTOR LIKE '43674436N' OR DNI_TUTOR LIKE '79302336S' OR DNI_TUTOR LIKE '52194953H' OR DNI_TUTOR LIKE '40873976D' OR DNI_TUTOR LIKE 'G67253443";
	else if ($_SESSION['usuari']=="43400030L") //Si és el Daniel, poso com a dni el de'n Daniel i el de l'empresa Boira
		$dni = 	$_SESSION['usuari']."' OR DNI_TUTOR LIKE 'B25750407";
	else if ($_SESSION['usuari']=="39352558H" or $_SESSION['usuari']=="B87456992" or $_SESSION['usuari']=="77897279M") //Si és la Neus o el FB, poso com a dni el seu.
		$dni = 	$_SESSION['usuari'];
    else if ($_SESSION['usuari']=="43674436")
        $dni = "43674436N";
	else {
		$dni = 	$_SESSION['usuari'];		
	}
	/* ----------------------------------------------------------------------------------------------------------------------------------*/
	$idioma = "ca";

	if ($_SESSION['usuari']=="B87456992") //Si és la Neus o el FB, poso com a dni el seu.
		$idioma = "es";
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Intranet | Consulta cobraments</title>
<link rel="stylesheet" href="../css/estilo_back_tutors.css"/>
</head>

<body topmargin="0">
	<table width="1100" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main">
        <tr>
            <td>
                <img src="../img/formacio_rectangular_2.jpg" style="float:left" />

               <div style="position:relative; width:920px; padding-right:25px; padding-top:10px; text-align:right">Hola <?php echo $_SESSION['name'] ?></div>


                <div id="menu" style="clear:both">
                    <div class="nav2" style="margin-left:20px;"><a href="gestio_cobraments_extern.php" style="margin-left:1px;"><span style="font-size:10px">GESTIÓ</span><br />COBRAMENTS</a></div>                    <div class="nav_on2" style="margin-left:1px;"><span style="font-size:10px">CONSULTA</span><br />COBRAMENTS</div>

                </div>
            </td>
        </tr>
        <tr>
            <td align="center">
                <div id="login">
                    <div id="llegenda_curs" class="ge">
                    	<form name="gestions" method="post" action="<?php echo $PHP_SELF ?>">
                        	<div style="text-align:left; border-top:solid 1px #CCCCCC"></div><br /><br />

                            <?php

							  	$conexion = mysqli_connect('localhost','suport','1324GiRoNa','gestio');

								mysqli_set_charset ($conexion, "utf8");

								if (mysqli_connect_errno())
								{
									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
								}
								else
								{
									if (($_POST[any] == "Cap") || !(isset($_POST[any])))
									{
										// busquem els últims cursos relacionats amb el tutor/a, liminant-los a 12 registres
										//echo "SELECT * FROM cobraments WHERE (DNI_TUTOR LIKE '".$dni."') AND GESTIONAT IS NOT NULL AND ALUMNES>0 ORDER BY ANY DESC, MES DESC, CURS";
										$result = mysqli_query ($conexion, "SELECT * FROM cobraments WHERE (DNI_TUTOR LIKE '".$dni."') AND GESTIONAT IS NOT NULL AND ALUMNES>0 ORDER BY ANY DESC, MES DESC, CURS");
									}
									else if ($_POST[any] == "Tots")
									{
										// busquem tots els cursos relacionats amb el tutor/a
										$result = mysqli_query ($conexion, "SELECT * FROM cobraments WHERE (DNI_TUTOR LIKE '".$dni."') AND GESTIONAT IS NOT NULL AND ALUMNES>0 ORDER BY ANY DESC, MES DESC, CURS");
									}
									else
									{
										// busquem tots els cursos relacionats amb el tutor/a, en un any determinat
										$result = mysqli_query ($conexion, "SELECT * FROM cobraments WHERE (DNI_TUTOR LIKE '".$dni."') AND GESTIONAT IS NOT NULL AND ALUMNES>0 AND ANY = ".$_POST[any]." ORDER BY ANY DESC, MES DESC, CURS");
									}


									if(mysqli_num_rows($result)>0)
									{
										?>

                                        <table style="width:1050px" class="titols_llegenda" cellspacing="0" align="left">
                                            <tr>
                                                <td style="width:80px; text-align:center">ANY</td>
                                                <td style="width:80px; text-align:center">CURS</td>
                                                <td style="width:70px; text-align:center">MES</td>
                                                <td style="width:80px; text-align:center">ALUMNES</td>
                                                <td style="width:100px; text-align:center">I. BRUT</td>
                                                <td style="width:80px; text-align:center">IRPF</td>
                                                <td style="width:100px; text-align:center">A COBRAR</td>
                                                <td style="width:100px; text-align:center">GESTIONAT</td>
                                                <td style="width:100px; text-align:center">PAGAT</td>
                                                <td style="width:260px; text-align:center">OBSERVACIONS</td>
                                            </tr>
                                        </table>
                                        <br />

                                    	<?php

										$i=0;
										$alumnes=0;
										$diners=0;

										while ($row = mysqli_fetch_array($result))
										{
											// canvi format dates

											$data_gestionat = $data_confirmat = $data_pagat = "";

											if ($row['GESTIONAT'] != "")
											{
												$fecha_separada=explode("-",$row['GESTIONAT']);
												$data_gestionat=$fecha_separada[2]."/".$fecha_separada[1]."/".$fecha_separada[0];
											}

											if ($row['PAGAT'] != "")
											{
												$fecha_separada=explode("-",$row['PAGAT']);
												$data_pagat=$fecha_separada[2]."/".$fecha_separada[1]."/".$fecha_separada[0];
											}

										?>
                                            <table style="width:1050px; clear: both;" class="info_pay" cellspacing="0" align="left">
                                                <tr>
                                                    <td style="width:80px; text-align:center"><?php echo($row['ANY']); ?></td>
                                                    <td style="width:50px; padding-left:30px;"><?php echo($row['CURS']); ?></td>
                                                    <td style="width:35px; text-align: right; padding-right: 35px;"><?php echo($row['MES']); ?></td>
                                                    <td style="width:55px; text-align: right; padding-right: 35px;"><?php echo($row['ALUMNES']); ?></td>
                                                    <td style="width:65px; text-align: right; padding-right: 25px;"><?php echo($row['IMPORT']); ?> €</td>
                                                    <td style="width:80px; text-align:center"><?php echo($row['IRPF']); ?> €</td>
                                                	<td style="width:100px; text-align:center"><strong><?php echo($row['APAGAR']); ?> €</strong></td>
                                                    <td style="width:100px; text-align: center;"><?php echo($data_gestionat); ?></td>
                                                    <td style="width:100px; text-align: center;"><?php echo($data_pagat); ?></td>
                                                    <td style="width:260px;"><?php echo($row['OBSERVACIONS']); ?></td>
                                                </tr>
                                            </table>

                                        <?
											$alumnes=$alumnes+$row['ALUMNES'];
											$diners=$diners+$row['IMPORT'];

											if (is_null($row['PAGAT']))
												$i++;
										}

										if (!(($_POST[any] == "Cap") || !(isset($_POST[any])) || ($_POST[any] == "Tots")))
										{
										?>

                                            <table style="width:1050px; clear: both;" class="info_pay2" cellspacing="0" align="left">
                                                <tr>
                                                    <td style="width:196px; text-align: right; padding-right: 35px;"><strong>TOTAL</strong></td>
                                                    <td style="width:55px; text-align: right; padding-right: 35px;"><strong><?php echo($alumnes); ?></strong></td>
                                                    <td style="width:65px; text-align: right; padding-right: 25px;"><strong><?php echo($diners); ?> €</strong></td>
                                                    <td style="width:640px;"></td>
                                                </tr>
                                            </table>

										<?php
										}

										if ($i == 0)
											echo "<p style=text-align:left;clear:both;><br>No tienes ningún curso pendiente de cobro.</p>";

										?>

                                        <div align="left" style="padding-top:20px; clear:both">

                                        <?php

										$conexion_any = mysql_connect("localhost","suport","1324GiRoNa");
										mysql_select_db ("gestio", $conexion_any) OR die ("No es pot connectar.");
										mysql_query ("SET NAMES 'utf8'");

										$result_anys = mysql_query ("SELECT ANY FROM cobraments WHERE DNI_TUTOR LIKE '".$dni."' GROUP BY ANY ORDER BY ANY DESC", $conexion_any);
										?>

                                        <br />Selecciona el any a consultar:
										&nbsp;

                                        <select name="any" id="any" size="1" onchange="this.form.submit()">
                                        	<option value="Cap" selected="selected">--Escollir--</option>
											<option value="Tots">Tots</option>

                                            <?php
												for ($n=0; $n<mysql_num_rows($result_anys); $n++)
												{
													$a = mysql_fetch_array($result_anys);
													echo "<option value=".$a['ANY'].">".$a['ANY']."</option>";
												}
											?>
                                        </select>
                                        </div>

                                        <?php
									}
									else
										echo "No s'ha trobat cap resultat.";

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
	header("Location: ../acces_extern.php");
	exit;
}
?>
