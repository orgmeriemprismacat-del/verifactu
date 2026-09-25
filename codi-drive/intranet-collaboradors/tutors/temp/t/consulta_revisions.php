<?php 

require('../../config.php');

$pagina = "consulta_revisions";
	
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Intranet | Consulta revisions</title>
<link rel="stylesheet" href="../css/estilo_back_tutors.css"/>   
<link rel="stylesheet" href="../css/estil_informe_tutors.css"/>       

<script language="javascript">
  	function buscar_cursos(any)
	{
		document.informe.any_seleccionat.value=any;
  		document.informe.submit();
	}
  </script>

</head>

<body topmargin="0">
<table width="1100" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main">  	
        <tr>
            <td>
                <img src="../img/formacio_rectangular_2.jpg" style="float:left" />
                <?php  // comprovem si està identificat en el campus
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
                        <form name="informe" method="post" action="<?php echo $PHP_SELF ?>">
                        	<div style="text-align:left; border-top:solid 1px #CCCCCC">
                            	<br />Selecciona l'any a consultar: 
                            	&nbsp; 
                                <?php
	                            
								$conexion = mysqli_connect('localhost','suport','1324GiRoNa','gestio');

								if (mysqli_connect_errno()) 
								{
									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
								}

								mysqli_set_charset ($conexion, "utf8");
								
								if (($USER->username == '33881437') || ($USER->username == '78100121'))
									$text = "((DNI_TUTOR LIKE '33881437%') OR (DNI_TUTOR LIKE '78100121%'))";
								else
									$text = "DNI_TUTOR LIKE '%".$USER->username."%'";
						
								$result_anys = mysqli_query ($conexion,"SELECT ANY FROM cursos WHERE data_revisio IS NOT NULL AND ".$text." GROUP BY ANY ORDER BY ANY DESC");
								
																
								if(isset($_POST['any']))
								{
									$result_cursos = mysqli_query ($conexion, "SELECT id_Curs, `NOM CURS` AS nomc, AULA, nom FROM cursos, mesos WHERE ANY = ".$_POST['any']." AND data_revisio IS NOT NULL AND ".$text." AND MES=num ORDER BY CURS, MES, AULA");
									?>
									
                                    <select name="any" id="any" size="1" onChange='buscar_cursos(this.value)'>
                                    	<?php
                                        for ($n=0; $n<mysqli_num_rows($result_anys); $n++)
                                        {
                                            $a = mysqli_fetch_array($result_anys);
                                            if ($a['ANY']==$_POST['any'])
												echo "<option value=".$a['ANY']." selected=selected>".$a['ANY']."</option>";
											else
												echo "<option value=".$a['ANY'].">".$a['ANY']."</option>";
                                        }
                                    ?>
                                    </select> 
                                    
                                    &nbsp;
                                    <select name="curs" id="curs" size="1" onchange="this.form.submit()">
                                      <?php
											if (mysqli_num_rows($result_cursos)==0)
											{
										?>
                                      <option value="Sense" selected>-- No hi ha cap curs --</option>
                                      <?php
											}
											else 
											{
										?>
                                      <option value="Cap" selected>-- Tria el curs --</option>
                                      <?php
										
												for ($n=0; $n<mysqli_num_rows($result_cursos); $n++)
												{
													$c = mysqli_fetch_array($result_cursos);
													echo "<option value=".$c['id_Curs'].">".$c['nomc']." - ".$c['nom']." - Aula ".$c['AULA']."</option>";
												}
											}
										?>
                                    </select>
                                    <?php
								}
								else
								{
								
								?>   
								
							  		<select name="any" id="any" size="1" onChange='buscar_cursos(this.value)'>   
                                    	<option value="Cap" selected>-- Tria un any --</option>                               	
										<?php
											for ($n=0; $n<mysqli_num_rows($result_anys); $n++)
											{
												$a = mysqli_fetch_array($result_anys);
												echo "<option value=".$a['ANY'].">".$a['ANY']."</option>";
											}
										?>
									</select> 
								
									<?php
									}
									
									?>
								
									<input type="hidden" name="any_seleccionat" />  
                           
                          	</div>            
                       	</form>	 
                        
                        <?php
					
						if(isset($_POST['curs']))
						{

                            // comprovem si hi ha dades guardades
							$conexion = mysqli_connect("localhost","suport","1324GiRoNa","gestio");
							if (mysqli_connect_errno())  
							{
								echo "No es pot connectar: " . mysqli_connect_error();
							}
							mysqli_set_charset($conexion, "utf8");							
							
							$result = mysqli_query ($conexion, "SELECT * FROM revisio_tutor WHERE codic='".$_POST[curs]."' AND finalitzat IS NOT NULL");
							
							if (mysqli_num_rows($result)>0)
							{
								$row_g = mysqli_fetch_array($result);
									  
								// mostrem el que hi ha guardat			
								$conexion_m = mysqli_connect("localhost","darrer","6rid.rpeH-m2pgeYBF-P","noumoodle");
								if (mysqli_connect_errno())  
								{
									echo "No es pot connectar: " . mysqli_connect_error();
								}
								mysqli_set_charset($conexion_m, "utf8");								 
								
								$result_dades = mysqli_query ($conexion,"SELECT id_Curs, c.MES, HORES, `NOM CURS` AS ncurs, AULA, `DATA FIN` AS dataf, p.NOM AS tnom, p.COGNOMS AS tcogs, MAIL_PRISMA, m.nom as nmes FROM cursos AS c, personal AS p, inscripcions AS i, mesos AS m WHERE id_Curs='".$_POST[curs]."' AND DNI_TUTOR=p.DNI AND c.ANY=i.ANY AND c.MES =i.MES AND c.CURS =i.CURS AND c.AULA=i.Grup AND m.num=i.MES");
								
								if(mysqli_num_rows($result_dades)>0)						
								{
									$row = mysqli_fetch_array($result_dades);
																									
							 ?>                	
										<br /><br />							
										<table border="0" align="center" width="100%" vspace="0" cellpadding="0" cellspacing="0" class="taula">   
											<tr class="apartat">
												<td>
												DADES DEL CURS                            </td>
												<td>
												(tutor / secretaria)                            </td>
											</tr>
											<tr>
												<td class="celda" width="700">                                
													<p align="justify"><strong>Tutor/a: </strong><?php echo $row['tnom']." ".$row['tcogs']; ?></p>                            </td>
											</tr> 
											<tr>
												<td class="celda">                                
													<p align="justify"><strong>Curs:</strong> <?php echo $row['ncurs']; ?></p>                            </td>
										  </tr>
											<tr>
												<td class="celda" width="700">                                 
													<p align="justify"><strong>Aula:</strong> <?php echo $row['AULA']; ?>                            </td>
											</tr> 
                                            <tr>
												<td class="celda" width="700">                                 
													<p align="justify"><strong>Convocatòria:</strong> <?php echo $row['nmes']; ?></p></p>                            </td>
											</tr> 
                                            <tr>
												<td colspan="2" align="center">
												<?php
												
													$codicurs = substr ($_POST[curs], 0, strlen($_POST[curs]) - 1);
													$codicurs = preg_replace('/[0-9]+/', '', $codicurs);
													
													function strtolower_utf8($inputString) 
													{
														$outputString = utf8_decode($inputString);
														$outputString = strtolower($outputString);
														$outputString = utf8_encode($outputString);
														return $outputString;
													}
																				
													$codicurs = strtolower_utf8($codicurs);														
													
													$hores = $row['HORES'];
														
													if ($codicurs=="dfd") 
														$nom_fitxer = "dfd.php";
													else if ($codicurs=="htp") 
														$nom_fitxer = "htp.php";
													else if ($codicurs=="clee" or $codicurs=="logo") 
														$nom_fitxer = "100_hores_2.php";
													else
														$nom_fitxer = $row['HORES']."_hores.php";
													
												?>
												
														<form name="imprimir" method="post" action="./revisions/<?php echo $nom_fitxer ?>?shortname=<?php echo $row['id_Curs'] ?>" target="_blank">
																<input type="hidden" name="curs" value="<?php echo $row['id_Curs'] ?>" />
																<br /><input type="submit" name="imprimir" class="botones" value="OBRIR INFORME"/><br /><br />
														</form>     
												
												</td>
											</tr>
										</table>
								<?php
								mysqli_close($conexion_m);
								}
								else
								{
									echo "<p>La revisió no ha estat enviat encara.</p>";
								}	
							}
							else
							{
								echo "<p>La revisió no ha estat enviat encara.</p>";
							}							
						}
                        
						?>
                        
                     </div>
                </div>          
                <br /><br />          
                <?php
				}
				else
					echo "<p align=center style=font-size:12px><br /><br />Per accedir a la Intranet has d'estar identificat/da en el Campus Virtual.<br /><br /><a href=http://www.prisma.cat/ target=_blank style=text-decoration:none>www.prisma.cat</a></p>";		
				?>            </td>
        </tr>             
	</table>		
           
</body>
</html>