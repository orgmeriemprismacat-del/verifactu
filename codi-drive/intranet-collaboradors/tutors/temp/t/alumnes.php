<?php 

require('../../../../config.php');
require('../../../../my/lib.php');


$pagina = "alumnes_curs";
	
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Intranet | Alumnes cursos</title>
<link rel="stylesheet" href="../c/estilo_back_tutors.css"/>    

<script language="javascript">
    function validar()
	{
		valid=true;
		
		if (document.informe.mesos.value=="Cap")
		{
			alert("Cal triar un mes.");
			valid=false;
		}		
		return(valid);
	}
	
</script>	
</head>

<body topmargin="0">
	<table width="1100" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main">  	
        <tr>
            <td>
                <img src="../i/formacio_rectangular_2.jpg" style="float:left" />
                
                <?php  // comprovem si està identificat en el campus
				require_login();

//if (isguestuser())
				
			   	if (isloggedin()) 
			   {
			   ?>
              	 <div style="position:relative; width:920px; padding-right:25px; padding-top:10px; text-align:right">Hola <?php echo $USER->firstname; ?></div>
                              
                <div id="menu" style="clear:both">                    
                    <?php
					
						// menú principal
						include('../inc/menu1.php');
										
					?>   
                </div>  
                           
            </td>
        </tr>                 
        <tr>
            <td align="center">
                <div id="login">     
                    <div id="llegenda_curs">
                    	<form name="informe" method="post" action="<?php echo $PHP_SELF ?>" onsubmit="return validar()">
                        	<div style="text-align:left; border-top:solid 1px #CCCCCC">
                            <?php
							
								$conexion_any = mysql_connect("localhost","suport","1324GiRoNa");
								mysql_select_db ("gestio", $conexion_any) OR die ("No es pot connectar.");
								mysql_query ("SET NAMES 'utf8'");
						
								$result_anys = mysql_query ("SELECT ANY FROM cursos GROUP BY ANY ORDER BY ANY DESC", $conexion_any);
								
                        	?>
                            
                            	<br />Selecciona l'any i el mes del curs que es vol consultar: 
                            &nbsp; 
                            <select name="any" id="any" size="1">
                                <?php
                                        for ($n=0; $n<mysql_num_rows($result_anys); $n++)
                                        {
                                            $a = mysql_fetch_array($result_anys);
                                            echo "<option value=".$a['ANY'].">".$a['ANY']."</option>";
                                        }
                                ?>
                            </select>
                            &nbsp; 
                            <select name="mesos" id="mesos" size="1">
                                <option value="Cap" selected>-- Tria un mes --</option>   
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
							
                            $conexion = mysql_connect("localhost","suport","1324GiRoNa");
                            mysql_select_db ("gestio", $conexion) OR die ("No es pot connectar.");
                        	mysql_query ("SET NAMES 'utf8'");
                        
							if ($_POST['buscar']=="buscar")
							{			
						
								// probamos conexión
								if(mysql_error($conexion)!="") 
									echo "<p class=text>Hi ha hagut un error: (".mysql_error($conexion)."). <br><br>Si continua, pots posar-te en contacte amb suport@prisma.cat. <br>Disculpa les molèsties.</p>"; 
								else
								{		
									// si és la Carmen Boix, podrà accedir als de la Laura S. i l'Ester
									if ($USER->username == '77897279')
									{
										// busquem els cursos relacionats amb les tutores de SIST
									$result_c = mysql_query ("SELECT CURS, `NOM CURS` AS nomc, AULA, `DATA INICI` AS inici, `DATA FIN` AS fi, nom FROM cursos, mesos WHERE (DNI_TUTOR LIKE '43674436%' OR DNI_TUTOR LIKE '79302336%') AND ANY = ".$_POST[any]." AND MES = '".$_POST[mesos]."' AND MES=num ORDER BY CURS, AULA", $conexion);
									}
									else if (($USER->username == '33881437') || ($USER->username == '78100121'))
									{
										// busquem els cursos relacionats amb les tutores de NEURO
									$result_c = mysql_query ("SELECT CURS, `NOM CURS` AS nomc, AULA, `DATA INICI` AS inici, `DATA FIN` AS fi, nom FROM cursos, mesos WHERE (DNI_TUTOR LIKE '33881437%' OR DNI_TUTOR LIKE '78100121%') AND ANY = ".$_POST[any]." AND MES = '".$_POST[mesos]."' AND MES=num ORDER BY CURS, AULA", $conexion);
									}
									else
									{
										// busquem els cursos relacionats amb el tutor/a
									$result_c = mysql_query ("SELECT CURS, `NOM CURS` AS nomc, AULA, `DATA INICI` AS inici, `DATA FIN` AS fi, nom FROM cursos, mesos WHERE DNI_TUTOR LIKE '%".$USER->username."%' AND ANY = ".$_POST[any]." AND MES = '".$_POST[mesos]."' AND MES=num ORDER BY CURS, AULA", $conexion);
									}
									
									echo "<br><br><strong style=font-size:14px>".$_POST['mesos']."/".$_POST['any']."</strong>";	
									
									if(mysql_num_rows($result_c)>0)
									{
										while ($row_c = mysql_fetch_array($result_c))
										{
											?>
                                    		<br />
                                            <table border="0" cellspacing="15" align="left">
                                            	<tr>
                                            		<td>
                                                    	Curs: <strong><?php echo ($row_c['nomc']." - Aula ".$row_c['AULA']); ?></strong>
                                                    </td>
                                                    <td>
                                                    	Mes: <strong><?php echo ($row_c['nom']); ?></strong>
                                                    </td>
                                                    <td>
                                                    	<?php 
														
														$fecha=$row_c['inici'];
														$year=substr($fecha,0,4);
														$month=substr($fecha,5,2);
														$day=substr($fecha,8,2);
														$datai=$day."-".$month."-".$year;
														?>
														
                                                    	Inici: <strong><?php echo ($datai); ?></strong>
                                                    </td>
                                                    <td>
                                                    	<?php 
														
														$fecha=$row_c['fi'];
														$year=substr($fecha,0,4);
														$month=substr($fecha,5,2);
														$day=substr($fecha,8,2);
														$dataf=$day."-".$month."-".$year;
														?>
                                                        
                                                    	Fi: <strong><?php echo ($dataf); ?></strong>
                                                    </td>
                                            	</tr>
                                    		</table>
                                    <br />
                                    
                                            <table style="width:1000px" class="titols_llegenda" cellspacing="0">                          					
                                            <tr>                    	    	
                                                    <td style="width:75px">NOM</td>
                                                    <td style="width:125px">COGNOMS</td>
                                                    <td style="width:180px">E-MAIL</td>
                                                    <td style="width:70px">TREBALLA A</td>
                                                    <td style="width:150px">TITULACIÓ</td> 
                                                    <td style="width:80px">DATA INSC.</td>                                
                                                    <td style="width:65px">ESTAT</td>
                                                    <td style="width:75px">DATA BAIXA</td>
                                                    <td style="width:180px">MOTIU BAIXA</td> 
                                                </tr>            
                                            </table>
                                            <table style="width:1000px" class="info_pay" cellspacing="0">

										<?php 	
											
											// busquem els alumnes relacionats amb el curs relacionat 
											$result = mysql_query ("SELECT NOM, COGNOMS, CORREU, PERFIL, Titulacio, DATA_INSC, `INSC CURS` AS estat, DATA_BAIXA, MOTIU_BAIXA, `DATA INICI` AS dinici FROM inscripcions, cursos WHERE inscripcions.CURS = '".$row_c['CURS']."' AND inscripcions.MES = '".$_POST[mesos]."' AND inscripcions.ANY = '".$_POST[any]."' AND Grup = '".$row_c['AULA']."' AND `INSC CURS` <> 'd' AND `INSC CURS` <> 'D' AND inscripcions.CURS=cursos.CURS AND inscripcions.MES=cursos.MES AND inscripcions.ANY=cursos.ANY AND inscripcions.Grup=cursos.AULA ORDER BY COGNOMS, NOM", $conexion);
																																
											if(mysql_num_rows($result)>0)
											{
												$pendents=$inscrits=$baixes=$canvis=0;
												
												while ($row = mysql_fetch_array($result))
												{
													if ((strtolower($row['estat']) == "x") || (strtoupper($row['estat']) == "C"))
													{
												?>
														<tr style="color:#FF0000">
	                                                    <td style="width:75px"><?php echo($row['NOM']); ?></td>
                                                        <td style="width:125px"><?php echo($row['COGNOMS']); ?></td>
                                                        <td style="width:180px"><?php echo($row['CORREU']); ?></td>
                                                        <td style="width:70px"><?php echo($row['PERFIL']); ?></td>
                                                        <td style="width:150px"><?php echo($row['Titulacio']); ?></td>
                                                        
                                                        	<?php 
														
															$fecha=$row['DATA_INSC'];
															$year=substr($fecha,0,4);
															$month=substr($fecha,5,2);
															$day=substr($fecha,8,2);
															$data_insc=$day."-".$month."-".$year;
															?>
                                                                                                                
                                                        <td style="width:80px"><?php echo($data_insc); ?></td>
                                                        
                                                        	<?php 
                                                        	if ($row['estat'] == "0")
															{
																$estat = "Pendent";
																$pendents++;
															}
															else if ($row['estat'] == "1")
															{
																$estat = "Inscrit";
																$inscrits++;
															}
															else if (strtolower($row['estat']) == "x")
															{
																$estat = "Baixa";
																$baixes++;
															}   
															else if (strtoupper($row['estat']) == "C")
															{
																$estat = "Canvi";
																$canvis++;
															}                                                       
															?>
                                                        
                                                        <td style="width:65px;"><?php echo($estat); ?></td>
                                                        
															<?php 
                                                            
                                                            $fecha=$row['DATA_BAIXA'];
                                                            $year=substr($fecha,0,4);
                                                            $month=substr($fecha,5,2);
                                                            $day=substr($fecha,8,2);
                                                            $data_b=$day."-".$month."-".$year;
                                                            ?>
                                                        
                                                        <td style="width:75px"><?php echo($data_b); ?></td>
                                                        
                                                        <?php



														if (strtotime($row['DATA_INSC']) > strtotime($row['dinici']))																												
														{
															$dies_inscrits = (strtotime($row['DATA_BAIXA'])-strtotime($row['DATA_INSC']))/86400;													
														}
														else if (strtotime($row['DATA_INSC']) == strtotime($row['dinici']))																												
														{
															$dies_inscrits = 0;														
														}
														else
														{		
															$dies_inscrits = (strtotime($row['DATA_BAIXA'])-strtotime($row['dinici']))/86400;					
														}

														$dies_inscrits = floor($dies_inscrits);
																							
														if (($dies_inscrits == 0) || ($dies_inscrits < 0))
															$dies_inscrits = "Cap dia inscrit";
														else if ($dies_inscrits == 1)
															$dies_inscrits .= "dia inscrit";
														else
															$dies_inscrits .= " dies inscrit";												
														
                                                        ?>
                                                        
                                                        <td style="width:180px"><?php echo($row['MOTIU_BAIXA']." (".$dies_inscrits.")"); ?></td>
                                                    </tr> 
											<?
													}
													else
													{
												
											?>		
													<tr>
	                                                    <td style="width:75px"><?php echo($row['NOM']); ?></td>
                                                        <td style="width:125px"><?php echo($row['COGNOMS']); ?></td>
                                                        <td style="width:180px"><?php echo($row['CORREU']); ?></td>
                                                        <td style="width:70px"><?php echo($row['PERFIL']); ?></td>
                                                        <td style="width:150px"><?php echo($row['Titulacio']); ?></td>
                                                        
                                                        	<?php 
														
															$fecha=$row['DATA_INSC'];
															$year=substr($fecha,0,4);
															$month=substr($fecha,5,2);
															$day=substr($fecha,8,2);
															$data_insc=$day."-".$month."-".$year;
															?>
                                                                                                                
                                                        <td style="width:80px"><?php echo($data_insc); ?></td>
                                                        
                                                        	<?php 
                                                        	if ($row['estat'] == "0")
															{
																$estat = "Pendent";
																$pendents++;
															}
															else if ($row['estat'] == "1")
															{
																$estat = "Inscrit";
																$inscrits++;
															}
															else if (strtolower($row['estat']) == "x")
															{
																$estat = "Baixa";
																$baixes++;
															}  
															else if (strtoupper($row['estat']) == "C")
															{
																$estat = "Canvi";
																$canvis++;
															}                                                        
															?>
                                                        
                                                        <td style="width:65px;"><?php echo($estat); ?></td>
                                                        
															<?php 
                                                            
                                                            $fecha=$row['DATA_BAIXA'];
                                                            $year=substr($fecha,0,4);
                                                            $month=substr($fecha,5,2);
                                                            $day=substr($fecha,8,2);
                                                            $data_b=$day."-".$month."-".$year;
                                                            ?>
                                                        
                                                        <td style="width:75px"><?php echo($data_b); ?></td>
                                                        <td style="width:180px"><?php echo($row['MOTIU_BAIXA']); ?></td>
                                                    </tr>  	
										
												<?php	
													}
												}
												
												echo("<tr><td colspan=9 align=left style=\"border-bottom:none\"><strong>Inscrits: ".$inscrits." | "."Pendents: ".$pendents." | "."Baixes: ".$baixes." | "."Canvis: ".$canvis."</strong></td></tr>");								
											}
											?>
											</table>
                                            
                                            <?php	
										}
									}
									else
										echo "<br><br>No s'ha trobat cap resultat.";			
                                }                                
							}
						
							?>                                                         
                              
                        </form>
                    </div>
                </div>                    
                <?php
				}
				else
					echo "<p align=center style=font-size:14px><br /><br />Per accedir a la Intranet has d'estar identificat/da en el <strong>Campus Virtual anterior</strong>.<br /><br /><a href=http://www.prisma.cat/campus/login/ target=_blank style=text-decoration:none>www.prisma.cat/campus/login/</a></p>";		
				?>   
            </td>
        </tr>           
	</table>
</body>
</html>
