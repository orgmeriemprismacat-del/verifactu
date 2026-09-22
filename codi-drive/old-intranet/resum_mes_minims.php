<?php
session_name("sessio_admin");
session_start();
if(isset($_SESSION['usuari']) && isset($_SESSION['contrasenya_encriptada']))
{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Intranet | Resum per mesos</title>
<link rel="stylesheet" href="./css/estilo_back.css"/>
<script language="Javascript" src="./js/validacions_resums.js"></script>

</head>

<body topmargin="0">
	<table width="1100" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main">
        <tr>
            <td>
                <img src="./img/formacio_rectangular.jpg" style="float:left" />
                <div style="position:relative; width:1100px; padding-right:25px; padding-top:10px; text-align:right"><input type="button" class="myButton" name="myButton" value="Tancar sessió" onclick="window.location.href='tancarsessio.php';" /></div>
                <div id="menu-ppal" style="clear:both;">
                    <?php
                    if ($_SESSION['rol']=="admin")
                    {
					?>
                        <div class="nav ppal"><a href="informes.php" style="margin-left:1px;">SECRETARIA</a></div>
                        <div class="nav ppal"><a href="estadistiques.php" style="margin-left:1px;">COORDINACIÓ</a></div>
                        <div class="nav_on ppal_on">ADMINISTRACIÓ</div>

					<?php
					}
          else if ($_SESSION['rol'] == 'tut')
          {
          ?>
                        <div class="nav ppal"><a href="informes.php" style="margin-left:1px;">SECRETARIA</a></div>
                        <div class="nav ppal_on"><a href="estadistiques.php" style="margin-left:1px;">COORDINACIÓ</a></div>

          <?php
          }
					else if ($_SESSION['rol']=="resum")
					{
					?>

                        <div class="nav ppal"><a style="margin-left:1px;">SECRETARIA</a></div>
                        <div class="nav ppal"><a style="margin-left:1px;">COORDINACIÓ</a></div>
                        <div class="nav_on ppal_on">ADMINISTRACIÓ</div>
					<?php
					}
					else if ($_SESSION['rol']=="coord")
					{
					?>

                        <div class="nav ppal"><a style="margin-left:1px;">SECRETARIA</a></div>
                        <div class="nav ppal"><a href="estadistiques.php" style="margin-left:1px;">COORDINACIÓ</a></div>
                        <div class="nav_on ppal_on">ADMINISTRACIÓ</div>
					<?php
					}
					?>
                </div>
                
                <?if ($_SESSION['rol'] <> 'tut') { ?>

                <div id="menu" style="clear:both; padding-top:10px; padding-left:50px">                
                
                <div class="nav_on2" style="margin-left:1px;"><span style="font-size:10px">RESUM</span><br />D'UN MES<br /></div>    
                    
                <div class="nav2"><a href="resum_mes.php" style="margin-left:1px;"><span style="font-size:10px">RESUM</span><br />D'UN MES (antic)</a></div>                
		
								<?php
                  //if ($_SESSION['rol']=="admin") {
                ?>
								<div class="nav2"><a href="resum_altres_formacions.php" style="margin-left:1px;"><span style="font-size:10px">RESUM ALTRES</span><br />FORMACIONS</a></div>
								<?php
                //}
                ?>
                
								<?php
                    }
                    else {
                        echo '<script>window.location="./revisions.php"</script>';    
                    
                    }
                  if ($_SESSION['rol']=="admin") {
                ?>
                      <div class="nav2"><a href="suma_pagaments.php" style="margin-left:1px;"><span style="font-size:10px">SUMA</span><br />DE PAGAMENTS</a></div>
								<?php
                }
                ?>
                </div>
            </td>
        </tr>
        <tr>
            <td align="center">
                <div id="login">
                    <div id="llegenda_curs">
                        <?php if ($_SESSION['rol'] <> 'tut') { ?>
                    	<form name="resum" method="post" action="<?php echo $PHP_SELF ?>" onsubmit="return validar_mes();">
                        <div style="text-align:left; border-top:solid 1px #CCCCCC">
                        	<?php
								$conexion_any = mysql_connect("localhost","suport","1324GiRoNa");
								mysql_select_db ("gestio", $conexion_any) OR die ("No es pot connectar.");
								mysql_query ("SET NAMES 'utf8'");

								$result_anys = mysql_query ("SELECT ANY FROM cursos GROUP BY ANY ORDER BY ANY DESC", $conexion_any);
                        	?>
							<!--<p style="text-align:center"><span style="font-size:12px; color:#009933">(Pàgina amb els càlculs actualitzats)</span><p>-->
                            <br />Selecciona l'any a consultar:
                            &nbsp;
                            <select name="any" id="any" size="1">
                                <?php
										$any_actual = date("Y");

										for ($n=0; $n<mysql_num_rows($result_anys); $n++)
                                        {
											$sel = "";
                                            $a = mysql_fetch_array($result_anys);
											if ($any_actual == $a['ANY'])
												$sel = "selected";
                                            echo "<option value=".$a['ANY']." ".$sel.">".$a['ANY']."</option>";
                                        }
                                ?>
                            </select>
                            &nbsp;
                            <select name="mesos" id="mesos" size="1">
                                <option value="Cap" selected>-- Tria el mes --</option>
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
                                <option value="12">Desembre</option>
                            </select>
                            &nbsp;&nbsp;<input type="submit" name="buscar" class="botones" value="buscar"/><br />
                        	</div>

							<?php

                            if ($_POST['buscar']=="buscar")
							{
								$conexion = mysql_connect("localhost","suport","1324GiRoNa");
								mysql_select_db ("gestio", $conexion) OR die ("No es pot connectar.");
								mysql_query ("SET NAMES 'utf8'");

								$result = mysql_query ("SELECT i.CURS, i.MES, `NOM CURS` AS ncurs, SUM(CASE WHEN (`INSC CURS` = '0' OR `INSC CURS` = '1' OR UPPER(`INSC CURS`) = 'X' OR `INSC CURS` = 'C') THEN 1 ELSE 0 END) sollicituds, SUM(CASE WHEN (UPPER(`INSC CURS`) = 'X' OR `INSC CURS` = 'C') THEN 1 ELSE 0 END) baixes, SUM(CASE WHEN (`INSC CURS` = '0' OR `INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits, SUM(CASE WHEN (PAGAMENT > 0) or (A_PAGAR=0) THEN 1 ELSE 0 END) pagats, SUM(CASE WHEN (`INSC CURS` = '0' OR `INSC CURS` = '1' OR UPPER(`INSC CURS`) = 'F') THEN A_PAGAR ELSE 0 END) facturat, SUM(PAGAMENT) cobrat, id_cuho, m.nom, codi_udg FROM inscripcions AS i, cursos AS c, mesos as m WHERE i.ANY = ".$_POST['any']." AND c.MES = '".$_POST['mesos']."' AND c.CURS = i.CURS AND c.ANY = i.ANY AND c.MES = i.MES AND c.AULA = i.Grup AND m.num=c.MES AND i.CURS NOT LIKE '%0%' AND c.CURS NOT LIKE '%0%' AND UPPER(`INSC CURS`) <> 'D' AND UPPER(`INSC CURS`) <> 'C' GROUP BY c.CURS ORDER BY i.CURS, i.MES", $conexion);

								$result_regals = mysql_query ("SELECT i.CURS, SUM(CASE WHEN (i.OBSERVACIONS LIKE '%CURS REGAL%' AND (`INSC CURS` = '0' OR `INSC CURS` = '1')) THEN 1 ELSE 0 END) regals, SUM(CASE WHEN (i.OBSERVACIONS LIKE '%CURS REGAL%' AND (`INSC CURS` = '0' OR `INSC CURS` = '1') AND i.ID=r.USAT) THEN r.IMPORT ELSE 0 END) comprats FROM inscripcions AS i, cursos AS c, mesos AS m, regal AS r WHERE i.ANY = ".$_POST['any']." AND c.MES = '".$_POST['mesos']."' AND c.CURS = i.CURS AND c.ANY = i.ANY AND c.MES = i.MES AND c.AULA = i.Grup AND m.num=c.MES AND i.CURS NOT LIKE '%0%' AND c.CURS NOT LIKE '%0%' AND UPPER(`INSC CURS`) <> 'D' AND i.ID=r.USAT GROUP BY c.CURS ORDER BY i.CURS, i.MES", $conexion);


								// suma total del mes
								$suma_cursos=0;
								$suma_sollicituds=0;
								$suma_baixes=0;
								$suma_inscrits=0;
								$suma_pagats=0;
								$suma_pc_inscrits=0;
								$suma_pc_pagats=0;
								$suma_regals=0;
								$suma_comprats=0;
								$suma_facturat=0;
								$suma_cobrat=0;
								$suma_pendent=0;
								$suma_pendent_total=0;
								$suma_retornat=0;
								$suma_despeses=0;
								$suma_net=0;

								if(mysql_num_rows($result)>0)
								{
									$row = mysql_fetch_array($result);

									$result_cursos = mysql_query ("SELECT DISTINCT CURS, id_cuho FROM cursos WHERE ANY = ".$_POST['any']." AND MES = '".$_POST['mesos']."' AND (CURS NOT LIKE '%0%' AND CURS<>'JOR' AND CURS<>'PROVA') GROUP BY CURS ORDER BY CURS", $conexion);

									echo "<strong style=font-size:14px>".$_POST['any']." - ".$row['nom']."</strong><br><br>";
                                    
                                    if (($_POST['any']<2026) || ($_POST['any']=2026) && ($_POST['mesos'] == '01' || $_POST['mesos'] == '02'))
                                        echo "<strong style=font-size:14px>En aquest mes i any NO és correcte. Consultar a <a href='https://old.prisma.cat/resum_mes.php' target='_blank'>RESUM D'UN MES (antic)</a></strong><br><br>";
                                    

									?>

                                        <table style="width:1050px" class="titols_llegenda" cellspacing="0">
                                            <tr>
                                                <td style="width:45px; text-align:center">CURS</td>
                                                <td style="width:65px; text-align:center">SOL·LIC.</td>
                                                <td style="width:65px; text-align:center">BAIXES</td>
                                                <td style="width:65px; text-align:center">INSCRITS</td>
                                                <td style="width:65px; text-align:center">PAGATS</td>
                                                <td style="width:80px; text-align:center">REGALS</td>
                                                <td style="width:97px; text-align:center">% INSCRITS</td>
                                                <td style="width:97px; text-align:center">% PAGATS</td>
                                                <td style="width:97px; text-align:center">€ FACTURATS</td>
                                                <td style="width:97px; text-align:center">€ COBRATS</td>
                                                <td style="width:97px; text-align:center">€ PENDENTS</td>
                                                <td style="width:100px; text-align:center">DESPESES</td>
                                                <td style="width:100px; text-align:center">GUANYS</td>
                                            </tr>
                                        </table>

                                     <?php

									$row_regals = mysql_fetch_array($result_regals);

									while ($c = mysql_fetch_array($result_cursos))
									{
										if ($row['CURS']==$c['CURS'])
										{
											if ($row_regals['CURS']==$c['CURS'])
											{
												$regals=$row_regals['regals'];
												$comprats=$row_regals['comprats'];
												$row_regals = mysql_fetch_array($result_regals);
											}
											else
											{
												$regals=0;
												$comprats=0;
											}

											$suma_sollicituds+=$row['sollicituds'];
											$suma_baixes+=$row['baixes'];

											if ($row['id_cuho']<>13)
											{
												$suma_cursos++;
												$suma_inscrits+=$row['inscrits'];
											}

											$suma_pagats+=$row['pagats'];
											$suma_pc_inscrits+=number_format(($row['inscrits']*100)/$row['sollicituds'], 2, ",", ".");

											if ($row['id_cuho']<>13 && $row['facturat'])
												$suma_pc_pagats+=number_format(($row['cobrat']*100)/$row['facturat'], 2, ",", ".");

											$suma_regals+=$regals;
											$suma_comprats+=$comprats;
											$suma_facturat+=$row['facturat'];
											$suma_cobrat+=$row['cobrat'];

											if (($row['facturat']-$row['cobrat'])>=0)
												$suma_pendent+=($row['facturat']-$row['cobrat']);
											else
												$suma_retornat+=($row['facturat']-$row['cobrat']);

											// calculem l'import que hem de cobrar restant les despeses de tutors, autors i duos

											if ($c['id_cuho']<>17)
											{ 
												$result_despeses = mysql_query ("SELECT PERFIL, PREU_ALUMNE, MINIM_COBRAR, CURS, id_cuho FROM honoraris AS h, rel_cuho AS r WHERE r.id_cuho=".$c['id_cuho']." AND r.id_hono=h.ID", $conexion);

												$despeses = 0;
												$net = 0;                                                

												while ($row_desp = mysql_fetch_array($result_despeses))
												{							
													if ($row_desp['PERFIL']=='tutor')
													{
                                                        $minim = $row_desp['MINIM_COBRAR'];
                                                        if ($minim == 0)
                                                            $minim ="";
                                                        else
                                                            $minim = " <span style='color:#999999'>(".($minim).")</span>";
														$neuro = 0;
														if ($row_desp['id_cuho'] == 123 || $row_desp['id_cuho'] == 124)
															$neuro = 10;
                                                        
                                                        if ($row_desp['CURS'] == 'GED') { // canvi de preu mixt
															if (($_POST['any']<2025) || (($_POST['any']==2025) && ($_POST['mesos']=='02')))
																$despeses = $despeses + (2*$row_desp['PREU_ALUMNE'])*$row['inscrits'];
															else
																$despeses = $despeses + $row_desp['PREU_ALUMNE']*$row['inscrits'];
														}
														else if ($row['inscrits'] >= $row_desp['MINIM_COBRAR']) // l'ideal
															$despeses = $despeses +  ($row_desp['PREU_ALUMNE']+$neuro)*$row['inscrits'];														
														/*else if ($row_desp['MINIM_ALUMNES'] == 0)
															$despeses = $despeses + $row_desp['PREU_ALUMNE']*$row['inscrits'];*/
														else
															$despeses = $despeses + ($row_desp['PREU_ALUMNE']+$neuro)*$row_desp['MINIM_COBRAR'];
													}													
													else
														$despeses = $despeses + $row_desp['PREU_ALUMNE']*$row['inscrits'];



													if ( $c['CURS'] == 'SUI' && $c['id_cuho']<>13 ) {
														$result_preu_fix_sui = mysql_query ("SELECT VALOR AS preu FROM params WHERE TIPUS = 'preu-fix-edicio-sui' AND (DATAI <= CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME <= DATAF))", $conexion);
					  							   		$row_preu_fix_sui = mysql_fetch_array($result_preu_fix_sui);
	                                                   	$despeses = $row_preu_fix_sui['preu'];
													}
													if ( $c['CURS'] == 'GED' && $c['id_cuho']<>13 ) {
														$result_preu_fix_ged = mysql_query ("SELECT VALOR AS preu FROM params WHERE TIPUS = 'preu-fix-edicio-ged' AND (DATAI <= CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME <= DATAF))", $conexion);
					  							   		$row_preu_fix_ged = mysql_fetch_array($result_preu_fix_ged);
														//if (($_POST['any']<2025) || (($_POST['any']==2025) && ($_POST['mesos']=='02')))
														//	$despeses = $despeses + 2*$row_preu_fix_ged['preu'];
														//else
															$despeses = $despeses + $row_preu_fix_ged['preu'];
													}
													if ( $c['CURS'] == 'SDA' && $c['id_cuho']<>13 ) {
														$result_preu_fix_sda = mysql_query ("SELECT VALOR AS preu FROM params WHERE TIPUS = 'preu-fix-edicio-sda' AND (DATAI <= CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME <= DATAF))", $conexion);
					  							   		$row_preu_fix_sda = mysql_fetch_array($result_preu_fix_sda);
	                                                   	$despeses = $row_preu_fix_sda['preu'];
													}
												}

												/*if ( $c['CURS'] == 'SUI' ) {
													$result_preu_fix_sui = mysql_query ("SELECT VALOR AS preu FROM params WHERE TIPUS = 'preu-fix-edicio-sui' AND (DATAI <= CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME <= DATAF))", $conexion);
					  							   $row_preu_fix_sui = mysql_fetch_array($result_preu_fix_sui);
	                                                                                              $despeses = $row_preu_fix_sui['preu'];
												}
												if ( $c['CURS'] == 'GED' ) {
													$result_preu_fix_ged = mysql_query ("SELECT VALOR AS preu FROM params WHERE TIPUS = 'preu-fix-edicio-ged' AND (DATAI <= CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME <= DATAF))", $conexion);
					  							   $row_preu_fix_ged = mysql_fetch_array($result_preu_fix_ged);
	                                                                                              $despeses = $despeses + $row_preu_fix_ged['preu'];
												}*/

												$net = $row['facturat']+$comprats-$despeses;
												$despeses_text = number_format($despeses, 2, ",", ".")." €";

												$suma_despeses+=$despeses;
												$suma_net+=$net;

												$suma_despeses_text = number_format($suma_despeses, 2, ",", ".")." €";

												if ($net > 0)
													$net_text = number_format($net, 2, ",", ".")." €";
												else
													$net_text = "<span style=color:red>".number_format($net, 2, ",", ".")." €</span>";

												if ($suma_net > 0)
													$suma_net_text = number_format($suma_net, 2, ",", ".")." €";
												else
													$suma_net_text = "<span style=color:red>".number_format($suma_net, 2, ",", ".")." €</span>";
											}
											else
											{
												$despeses_text = "<span style=color:#fc9003>No assignat</span>";
												$net_text = "<span style=color:#fc9003>No assignat</span>";
												$suma_despeses_text = "<span style=color:#fc9003>Pendent</span>";
												$suma_net_text = "<span style=color:#fc9003>Pendent</span>";
											}

											?>
											<table style="width:1050px" class="info_pay" cellspacing="0">
											<?php if ($row['codi_udg'] == "#")
													echo "<tr style=background-color:#fdddbc;>";
												else if ($row['id_cuho']==13)
													echo "<tr style=background-color:#ffe0e0;>";
												else
													echo "<tr>";

												?>
													<td style="width:45px;"><?php echo ($row['CURS']); ?></td>
                                                    <td style="width:65px; text-align:center;"><?php echo ($row['sollicituds']); ?></td>
                                                    <td style="width:65px; text-align:center;"><?php echo ($row['baixes']); ?></td>
                                                    <td style="width:65px; text-align:center;"><?php if ($row['id_cuho']==13) {echo "<span style=color:red>ANUL·LAT</span>";} else if ($row['inscrits']<5) echo "<span style=color:red>".$row['inscrits']." *</span>"; else if (($row['inscrits']>=5) && ($row['inscrits']<10)) echo "<span style=color:#fc9003>".$row['inscrits']."</span>"; else if (($row['inscrits']>=10) && ($row['inscrits']<15)) echo "<span style=color:#3AB73A>".$row['inscrits']."</span>"; else {echo ($row['inscrits']);} echo ($minim) ?></td>
                                                    <td style="width:65px; text-align:center;"><?php echo ($row['pagats']); ?></td>
                                                    <td style="width:80px; text-align:center;"><?php if ($regals>0) { echo ($regals." / ".number_format($comprats, 2, ",", "."))." €";} ?></td>
                                                    <td style="width:97px; text-align:center;"><?php if ($row['id_cuho']<>13) echo number_format(($row['inscrits']*100)/$row['sollicituds'], 2, ",", ".")." %"; ?></td>
                                                    <td style="width:97px; text-align:center;"><?php if ($row['id_cuho']<>13 && $row['facturat']) echo number_format((($row['cobrat']+$comprats)*100)/($row['facturat']+$comprats), 2, ",", ".")." %"; else if ($row['id_cuho']<>13 && $row['facturat']==0) echo "---" ?></td>
                                                    <td style="width:97px; text-align:center;"><?php echo number_format($row['facturat']+$comprats, 2, ",", "."); ?> €</td>
                                                    <td style="width:97px; text-align:center;"><?php echo number_format($row['cobrat']+$comprats, 2, ",", "."); ?> €</td>
                                                    <td style="width:97px; text-align:center;"><?php if (($row['facturat']-$row['cobrat'])<0) {echo "<span style=color:#af8f11>".number_format($row['facturat']-$row['cobrat'], 2, ",", ".")." €</span>";} else if (($row['facturat']-$row['cobrat'])>0) {echo "<span style=color:red>".number_format($row['facturat']-$row['cobrat'], 2, ",", ".")." €</span>";} else {echo number_format($row['facturat']-$row['cobrat'], 2, ",", ".")." €";} ?></td>
                                                    <td style="width:100px; text-align:center;"><?php echo $despeses_text; ?></td>
                                                    <td style="width:100px; text-align:center;"><?php echo $net_text; ?></td>
												</tr>
											</table>
											<?php

											$row = mysql_fetch_array($result);

										}
										else
										{
											?>
											<table style="width:1050px" class="info_pay" cellspacing="0">
												<tr style="background-color:#ffe0e0">
                                                    <?php
                                                    if ($c['id_cuho']<>13)
													{
														echo ("<td style=\"width:70px;\">".$c['CURS']."</td>");
														echo ("<td style=\"width:120px; background: #fde5c5;\">No hi ha inscripcions</td>");
														echo ("<td style=\"width:833px; padding-left: 27px;\"></td>");
													}
                                                    else
													{
                                                    	echo ("<td style=\"width:70px;\">".$c['CURS']."</td>");
														echo ("<td style=\"width:953px; padding-left: 27px; background: #ffe0e0;\"><span style=\"color:red\">ANUL·LAT</span></td>");
														echo ("<td style=\"width:0; \"></td>");
													}

													?>

												</tr>
											</table>

											<?php

										}
									}
									$suma_pendent_total=$suma_pendent+$suma_retornat;
									?>
										<table style="width:1050px;" class="info_pay" cellspacing="0">
                                            <tr>
                                                <td style="width:45px; font-weight:bold;"><?php echo ($suma_cursos); ?></td>
                                                <td style="width:65px; text-align:center; font-weight:bold;"><?php echo ($suma_sollicituds); ?></td>
                                                <td style="width:65px; text-align:center; font-weight:bold;"><?php echo ($suma_baixes); ?></td>
                                                <td style="width:65px; text-align:center; font-weight:bold;"><?php echo ($suma_inscrits); ?></td>
                                                <td style="width:65px; text-align:center; font-weight:bold;"><?php echo ($suma_pagats); ?></td>
                                                <td style="width:80px; text-align:center; font-weight:bold;"><?php echo ($suma_regals." / ".number_format($suma_comprats, 2, ",", ".")); ?> €</td>
                                                <td style="width:97px; text-align:center; font-weight:bold;"><?php echo number_format(($suma_inscrits*100)/$suma_sollicituds, 2, ",", "."); ?> %</td>
                                                <td style="width:97px; text-align:center; font-weight:bold;"><?php echo number_format((($suma_cobrat+$suma_comprats)*100)/($suma_facturat+$suma_comprats), 2, ",", "."); ?> %</td>
                                                <td style="width:97px; text-align:center; font-weight:bold;"><?php echo number_format($suma_facturat+$suma_comprats, 2, ",", "."); ?> €</td>
                                                <td style="width:97px; text-align:center; font-weight:bold;"><?php echo number_format($suma_cobrat+$suma_comprats, 2, ",", "."); ?> €</td>
                                                <td style="width:97px; text-align:center;"><?php echo number_format($suma_pendent, 2, ",", "."); ?> €<br /><span style="color: #af8f11;"><?php echo number_format($suma_retornat, 2, ",", "."); ?></span> €<br /><strong><?php echo number_format($suma_pendent_total, 2, ",", "."); ?> €</strong></td>
                                                <td style="width:100px; text-align:center; font-weight:bold;"><?php echo $suma_despeses_text; ?></td>
                                                <td style="width:100px; text-align:center; font-weight:bold;"><?php echo $suma_net_text; ?></td>
                                            </tr>
                                        </table>
                                   	<?php

								}
							}

							?>

                        </form>
                    </div>
                <?php } ?>
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
