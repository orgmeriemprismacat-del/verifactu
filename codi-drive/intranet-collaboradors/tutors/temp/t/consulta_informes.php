<?php 

require('../../config.php');

$pagina = "consulta_informes";
	
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Intranet | Consulta d'informes</title>
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
						
								$result_anys = mysqli_query ($conexion,"SELECT ANY FROM cursos WHERE data_informe IS NOT NULL AND ".$text." GROUP BY ANY ORDER BY ANY DESC");
								
																
								if(isset($_POST['any']))
								{
									$result_cursos = mysqli_query ($conexion, "SELECT id_Curs, `NOM CURS` AS nomc, AULA, nom FROM cursos, mesos WHERE ANY = ".$_POST['any']." AND data_informe IS NOT NULL AND ".$text." AND MES=num ORDER BY CURS, MES, AULA");
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
							
							$result = mysqli_query ($conexion, "SELECT * FROM informe_tutor WHERE codic='".$_POST[curs]."' AND finalitzat IS NOT NULL");
							
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
								
								$result_dades = mysqli_query ($conexion,"SELECT id_Curs, c.MES, c.AULA, `NOM CURS` AS ncurs, `DATA FIN` AS dataf, DNI_TUTOR, p.NOM AS tnom, p.COGNOMS AS tcogs, m.nom as nmes, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits, SUM(CASE WHEN ((`CERTIFICAT` NOT LIKE '%no aprovat%' OR `CERTIFICAT` IS NULL) AND `INSC CURS`=1) THEN 1 ELSE 0 END) aprovats, SUM(CASE WHEN ((`CERTIFICAT` LIKE '%no aprovat%') AND `INSC CURS`=1) THEN 1 ELSE 0 END) pendents, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats FROM cursos AS c, personal AS p, inscripcions AS i, mesos AS m WHERE id_Curs='".$_POST[curs]."' AND DNI_TUTOR=p.DNI AND c.ANY=i.ANY AND c.MES =i.MES AND c.CURS =i.CURS AND c.AULA=i.Grup AND m.num=i.MES");
								
								// $result_m = mysqli_query ($conexion_m,"SELECT SUM(CASE WHEN (consulta.grade = '6') THEN 1 ELSE 0 END) superats, SUM(CASE WHEN ((consulta.grade <> '-1') AND (consulta.grade <> '6')) THEN 1 ELSE 0 END) no_superats FROM (SELECT c.id, s.userid, s.grade FROM mdl_user AS u, mdl_assign_grades AS s, mdl_course AS c, mdl_assign AS a WHERE (((s.userid) In (SELECT userid FROM mdl_role_assignments WHERE roleid = 5 and contextid in (SELECT id FROM mdl_context WHERE contextlevel = 50 and instanceid in (SELECT c.id FROM mdl_course AS c, mdl_assign AS a, mdl_assign_grades AS s WHERE shortname='".$_POST[curs]."' AND c.id=a.course AND a.id=s.assignment)))) AND ((u.id) In (SELECT userid FROM mdl_role_assignments WHERE roleid = 5 and contextid in (SELECT id FROM mdl_context WHERE contextlevel = 50 and instanceid in (SELECT c.id FROM mdl_course AS c, mdl_assign AS a, mdl_assign_grades AS s WHERE shortname='".$_POST[curs]."' AND c.id=a.course AND a.id=s.assignment))) And (u.id)=s.userid) AND ((c.shortname)='".$_POST[curs]."') AND ((s.assignment)=a.id) AND ((a.course)=c.id))) AS consulta");
								
								if ($_POST[curs] != 'HTP')
									$result_m = mysqli_query ($conexion_m,"SELECT SUM(CASE WHEN (finalgrade>=66) THEN 1 ELSE 0 END) superats, SUM(CASE WHEN (finalgrade<66) THEN 1 ELSE 0 END) AS no_superats FROM mdl_grade_grades AS gg INNER JOIN mdl_grade_items AS gi ON gi.id=gg.itemid INNER JOIN (SELECT userid FROM mdl_role_assignments AS ass WHERE ass.roleid=5 AND ass.contextid = (SELECT cx.id FROM mdl_context AS cx WHERE contextlevel=50 AND instanceid=(SELECT id from mdl_course WHERE shortname='".$_POST[curs]."'))) AS resultat ON resultat.userid = gg.userid WHERE gi.itemtype='course' AND gi.courseid=(SELECT id from mdl_course WHERE shortname='".$_POST[curs]."')");	
								else
									$result_m = mysqli_query ($conexion_m,"SELECT SUM(CASE WHEN (finalgrade>=62.50) THEN 1 ELSE 0 END) superats, SUM(CASE WHEN (finalgrade<62.50) THEN 1 ELSE 0 END) AS no_superats FROM mdl_grade_grades AS gg INNER JOIN mdl_grade_items AS gi ON gi.id=gg.itemid INNER JOIN (SELECT userid FROM mdl_role_assignments AS ass WHERE ass.roleid=5 AND ass.contextid = (SELECT cx.id FROM mdl_context AS cx WHERE contextlevel=50 AND instanceid=(SELECT id from mdl_course WHERE shortname='".$_POST[curs]."'))) AS resultat ON resultat.userid = gg.userid WHERE gi.itemtype='course' AND gi.courseid=(SELECT id from mdl_course WHERE shortname='".$_POST[curs]."')");	
									//$result_m = mysqli_query ($conexion_m,"SELECT SUM(CASE WHEN (finalgrade>=62.50) THEN 1 ELSE 0 END) superats, SUM(CASE WHEN (finalgrade<62.50) THEN 1 ELSE 0 END) no_superats FROM mdl_grade_grades AS gg JOIN mdl_grade_items AS gi ON gi.id=gg.itemid JOIN mdl_user AS u ON u.id=gg.userid JOIN mdl_course AS c WHERE shortname='".$curs."' AND gi.itemtype='course' AND gi.courseid=c.id");
								
								if(mysqli_num_rows($result_dades)>0)						
								{
									$row = mysqli_fetch_array($result_dades);
									$row_m = mysqli_fetch_array($result_m);
																									
							 ?>                	
										<br /><br />							
										<table border="0" align="center" width="100%" vspace="0" cellpadding="0" cellspacing="0" class="taula">   
											<tr class="apartat">
												<td align="center">
													<?php echo $row['ncurs']; ?> <?php echo $row['AULA']; ?> | <?php echo $row['nmes']; ?>
                                                </td>
											</tr>	
                                            <tr>
												<td align="center">
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
																										
												?>
												
														<form name="imprimir" method="post" action="./informes/<?php echo $codicurs ?>.php" target="_blank">
																<input type="hidden" name="curs" value="<?php echo $_POST[curs] ?>" />
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
									echo "<p>L'informe no ha estat enviat encara.</p>";
								}	
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