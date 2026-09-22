<?php
session_name("sessio_admin");
session_start();
if(isset($_SESSION['usuari']) && isset($_SESSION['contrasenya_encriptada']) && ($_SESSION['rol']=="admin"))
{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Intranet | Suma de pagaments</title>
<link rel="stylesheet" href="./css/estilo_back.css"/>

</head>

<body topmargin="0">
	<table width="1100" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main">
        <tr>
            <td>
                <img src="./img/formacio_rectangular.jpg" style="float:left" />
                <div style="position:relative; width:1100px; padding-right:25px; padding-top:10px; text-align:right"><input type="button" class="myButton" name="myButton" value="Tancar sessió" onclick="window.location.href='tancarsessio.php';" /></div>
                <div id="menu-ppal" style="clear:both;">
                    <div class="nav ppal" style="margin-left:19px; visibility:"><a href="primera_reclamacio.php" style="margin-left:1px;">GESTIÓ</a></div>
                    <div class="nav ppal"><a href="informes.php" style="margin-left:1px;">SECRETARIA</a></div>
                    <div class="nav ppal"><a href="estadistiques.php" style="margin-left:1px;">COORDINACIÓ</a></div>
                    <div class="nav_on ppal_on">ADMINISTRACIÓ</div>
                    <div class="nav ppal"><a href="moodle.php" style="margin-left:1px;">SUPORT</a></div>
                </div>

                <div id="menu" style="clear:both; padding-top:10px; padding-left:50px">
                    <!--<div class="nav2"><a href="resum_anual_mesos.php" style="margin-left:1px;"><span style="font-size:10px">RESUM ANUAL</span><br />PER MESOS</a></div>
                <div class="nav2"><a href="resum_anual_cursos.php" style="margin-left:1px;"><span style="font-size:10px">RESUM ANUAL</span><br />PER CURSOS</a></div>
                --><div class="nav2"><a href="resum_mes.php" style="margin-left:1px;"><span style="font-size:10px">RESUM</span><br />d'UN MES</a></div>
              <!-- <div class="nav2"><a href="resum_curs.php" style="margin-left:1px;"><span style="font-size:10px">RESUM</span><br />d'UN CURS</a></div>
                <!--<div class="nav2"><a href="resum_altres_formacions.php" style="margin-left:1px;"><span style="font-size:10px">RESUM ALTRES</span><br />FORMACIONS</a></div>
                <!--<div class="nav2"><a href="resum_anual_total.php" style="margin-left:1px;"><span style="font-size:10px">RESUM ANUAL</span><br />TOTAL</a></div>
                <div class="nav"><a href="previsions.php" style="margin-left:1px;">PREVISIONS</a></div>  -->
                <!--<div class="nav"><a href="enquestes.php" style="margin-left:1px;">ENQUESTES</a></div>-->
                <div class="nav_on2" style="margin-left:1px;"><span style="font-size:10px">SUMA</span><br />DE PAGAMENTS</div>

                </div>
            </td>
        </tr>
        <tr>
            <td align="center">
                <div id="login">
                    <div id="llegenda_curs">
                    	<div style="text-align:left; border-top:solid 1px #CCCCCC"></div>

                    <?php

						$conexion_suma = mysqli_connect('localhost','suport','1324GiRoNa','gestio');

						mysqli_set_charset ($conexion_suma, "utf8");

						if (mysqli_connect_errno())
						{
							echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
						}
						else
						{
							$result_suma_asso = mysqli_query ($conexion_suma,"SELECT SUM(CASE WHEN ((entitat = 'Asso') AND (forma_pagament = 'Caixa') AND (f.data_pagament = subdate(current_date, 1))) THEN f.import ELSE 0 END) asso_caixa, SUM(CASE WHEN ((entitat = 'Asso') AND (forma_pagament = 'BBVA') AND (f.data_pagament = subdate(current_date, 1))) THEN f.import ELSE 0 END) asso_bbva, SUM(CASE WHEN ((entitat = 'Asso') AND (forma_pagament = 'tpv') AND (DATE(f.data_pagament) = subdate(current_date, 1))) THEN f.import ELSE 0 END) asso_tpv from factures AS f");

							$result_suma_sl = mysqli_query ($conexion_suma,"SELECT SUM(CASE WHEN (t.data_pagament = subdate(current_date, 1)) THEN t.import ELSE 0 END) SL from factures_sl AS t");

							$row_asso = mysqli_fetch_array($result_suma_asso);
							$row_sl = mysqli_fetch_array($result_suma_sl);


						?>
                        	 <br />

                             <?php $ahir = date("d/m/Y", strtotime( '-1 days' ) ); ?>

                             <p align="center">SUMA DE PAGAMENTS AMB DATA: <?php echo ($ahir); ?></p>

                             <table style="width:600px" class="titols_llegenda" cellspacing="0">
                                <tr>
                                    <td style="width:100px; text-align:center">ASSO CAIXA</td>
                                    <td style="width:100px; text-align:center">ASSO BBVA</td>
                                    <td style="width:100px; text-align:center">ASSO TPV</td>
                                    <td style="width:100px; text-align:center">SL TRIPARTITA</td>
                              	</tr>
                            </table>
                            <table style="width:600px" class="info_pay" cellspacing="0">
                                <tr>
                                    <td style="width:100px; text-align:center"><?php echo(number_format($row_asso['asso_caixa'], 2, ",", ".")); ?> €</td>
                                    <td style="width:100px; text-align:center"><?php echo(number_format($row_asso['asso_bbva'], 2, ",", ".")); ?> €</td>
                                    <td style="width:100px; text-align:center"><?php echo(number_format($row_asso['asso_tpv'], 2, ",", ".")); ?> €</td>
                                    <td style="width:100px; text-align:center"><?php echo(number_format($row_sl['SL'], 2, ",", ".")); ?> €</td>
                              	</tr>
                            </table>
							<br /><br />
						<?php
                    	}
                    ?>

                    	<form name="canvi" method="post" action="<?php echo $PHP_SELF ?>" onsubmit="return validar()">

                            	<p align="center">Introdueix la data a consultar (dd/mm/aaaa)
                                &nbsp;
                                <input type="text" name="valor" value="" maxlength="100" style="width:100px" />&nbsp;&nbsp;<input type="submit" name="calcular" class="botones" value="calcular"/><br /><br /></p>

							<?php

							if ($_POST['calcular']=="calcular")
							{

								$fecha_caste=$_POST[valor];
								$fecha_separada=explode("/",$fecha_caste);
								$fecha_ingles=$fecha_separada[1]."/".$fecha_separada[0]."/".$fecha_separada[2];

								$data_pag = date('Y-m-d',strtotime($fecha_ingles));


								$result_suma_asso_data = mysqli_query ($conexion_suma,"SELECT SUM(CASE WHEN ((entitat = 'Asso') AND (forma_pagament = 'Caixa') AND (f.data_pagament = '".$data_pag."')) THEN f.import ELSE 0 END) asso_caixa, SUM(CASE WHEN ((entitat = 'Asso') AND (forma_pagament = 'BBVA') AND (f.data_pagament = '".$data_pag."')) THEN f.import ELSE 0 END) asso_bbva, SUM(CASE WHEN ((entitat = 'Asso') AND (forma_pagament = 'tpv') AND (DATE(f.data_pagament) = '".$data_pag."')) THEN f.import ELSE 0 END) asso_tpv from factures AS f");

								$result_suma_sl_data = mysqli_query ($conexion_suma,"SELECT SUM(CASE WHEN (t.data_pagament = '".$data_pag."') THEN t.import ELSE 0 END) SL from factures_sl AS t");

								$row_asso_data = mysqli_fetch_array($result_suma_asso_data);
								$row_sl_data = mysqli_fetch_array($result_suma_sl_data);

								?>
								<p align="center"><br /><br />SUMA DE PAGAMENTS AMB DATA: <?php echo ($_POST[valor]); ?></p>
								<table style="width:600px" class="titols_llegenda" cellspacing="0">
									<tr>
										<td style="width:100px; text-align:center">ASSO CAIXA</td>
										<td style="width:100px; text-align:center">ASSO BBVA</td>
										<td style="width:100px; text-align:center">ASSO TPV</td>
										<td style="width:100px; text-align:center">SL TRIPARTITA</td>
									</tr>
								</table>
								<table style="width:600px" class="info_pay" cellspacing="0">
									<tr>
										<td style="width:100px; text-align:center"><?php echo(number_format($row_asso_data['asso_caixa'], 2, ",", ".")); ?> €</td>
										<td style="width:100px; text-align:center"><?php echo(number_format($row_asso_data['asso_bbva'], 2, ",", ".")); ?> €</td>
										<td style="width:100px; text-align:center"><?php echo(number_format($row_asso_data['asso_tpv'], 2, ",", ".")); ?> €</td>
										<td style="width:100px; text-align:center"><?php echo(number_format($row_sl_data['SL'], 2, ",", ".")); ?> €</td>
									</tr>
								</table>
									<?php

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
