<?php

require('../../config.php');

$pagina = "estat_laboral";

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Intranet | Estat laboral</title>
<link rel="stylesheet" href="../css/estilo_back_tutors.css"/>    
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
						
						$row = mysqli_fetch_array($result_menu);
										
					?>					                         
                </div>            
           	</td>
        </tr>                
        <tr>
            <td align="center">
                <div id="login">     
                    <div id="llegenda_curs" class="ge">
                    	<div style="text-align:left; border-top:solid 1px #CCCCCC"></div><br /><br />
                            
                            <?php

							if ($_POST['enviar']=="ENVIAR")
							{								
								// dades connexió
								include('../../../intranet/inc/dades.php');
							
								$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
								
								mysqli_set_charset ($connexio, "utf8");
								
								if (mysqli_connect_errno()) 
								{
									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
								}
								
								else 
								{		
									$result1 = mysqli_query ($connexio,"SELECT NOM, COGNOMS, MAIL_PRISMA, DNI FROM personal WHERE DNI LIKE '%".$USER->username."%'");
																		
									$row1 = mysqli_fetch_array($result1);	
									
									$observacions = str_replace("'","''",$_POST[observacions]);								
																	
									$result2 = mysqli_query ($connexio,"INSERT INTO estat (DNI,SITUACIO,DATA, OBSERVACIONS) VALUES ('".$row1[DNI]."','".$_POST[estat]."',CURRENT_DATE,'".$observacions."')");
									
									$nom = str_replace("\'","'",$row1['NOM']);
									$cognoms = str_replace("\'","'",$row1['COGNOMS']);
									
									
									// li enviem un correu de confirmació al tutor/a
									$to0 = $row1[MAIL_PRISMA];
									
									$headers0  = "MIME-Version: 1.0\r\n";
									$headers0 .= "Content-type: text/html; charset=UTF-8\r\n"; 
									$headers0 .= "From: ".$nom." <".$row1[MAIL_PRISMA].">\nReply-To: gestio@prisma.cat";
									
									$subject0 = "Informació estat laboral - ".$nom." ".$cognoms;
										
									$message0 = "<p>Bon dia, ".$nom.",</p>
									<p>T'informem que hem rebut correctament el <em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em>.</p>
									<p>Recorda que l'espai «NOTIFICACIÓ ESTAT LABORAL» només t'apareixerà a la teva Intranet cada sis mesos, a principis de gener i a principis de juliol. Un cop hagis enviat el document acreditatiu que verifiqui el teu estat laboral, aquesta pestanya es tornarà a ocultar fins al proper període descrit.</p>
									<p>T'agrairem que ens comuniquis directament a facturacio@prisma.cat les modificacions que es produeixin fora d'aquestes dates. </p>
									<p>Gràcies per la teva col·laboració, i quedem a la teva disposició per a qualsevol dubte o consulta.</p>
									<p>Equip PrisMa</p>";
									// fi correu confirmació
																	
									// ens enviem el fitxer
									$bHayFicheros = 0; 
									$sCabeceraTexto = ""; 
									$sAdjuntos = ""; 
																			
									$to = "facturacio@prisma.cat, gestio@prisma.cat, suport@prisma.cat, coordinacio@prisma.cat";
																																							
									$subject = "Estat laboral - ".$nom." ".$cognoms;		
										
									$headers = "From: ".$nom." <".$row1[MAIL_PRISMA].">\nReply-To: ".$row1[MAIL_PRISMA]."\n";
									$headers .= "MIME-version: 1.0\n"; 
																
									$sTexto = "<p><em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em> enviat!</p> ";
													
									$comentaris = str_replace("\'","'",$_POST[observacions]);		
									
									$sTexto .= "<p><br />".$comentaris."</p>";														
																			
									foreach ($_FILES as $vAdjunto)
									{ 
										if ($bHayFicheros == 0)
										{ 
											$bHayFicheros = 1; 
											$headers .= "Content-type: multipart/mixed;"; 
											$headers .= "boundary=\"--_Separador-de-mensajes_--\"\n"; 
											
											$sCabeceraTexto = "----_Separador-de-mensajes_--\n"; 
											$sCabeceraTexto .= "Content-type: text/html;charset=iso-8859-1\n"; 
											$sCabeceraTexto .= "Content-transfer-encoding: 7BIT\n"; 
											
											$sTexto = $sCabeceraTexto.$sTexto; 
										} 
										if ($vAdjunto["size"] > 0)
										{ 
											$sAdjuntos .= "\n\n----_Separador-de-mensajes_--\n"; 
											$sAdjuntos .= "Content-type: ".$vAdjunto["type"].";name=\"".$vAdjunto["name"]."\"\n";; 
											$sAdjuntos .= "Content-Transfer-Encoding: BASE64\n"; 
											$sAdjuntos .= "Content-disposition: attachment;filename=\"".$vAdjunto["name"]."\"\n\n"; 
											
											$oFichero = fopen($vAdjunto["tmp_name"], 'r'); 
											$sContenido = fread($oFichero, filesize($vAdjunto["tmp_name"])); 
											$sAdjuntos .= chunk_split(base64_encode($sContenido)); 
											fclose($oFichero); 
										} 											
									} 
									
									if ($bHayFicheros) 
									{
										$sTexto .= $sAdjuntos."\n\n----_Separador-de-mensajes_----\n"; 
										if (mail($to0, $subject0, $message0, $headers0) && mail($to, $subject, $sTexto, $headers))
											echo "Has enviat el <em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em> correctament.";
									}
									// fi missatge	
								}							
							}
							else   
                            { 									
								?>
												
                                                
                                <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js'></script>     
                                <script>
									function posar() {
										$('#archivo1').prop("required", true);
										document.getElementById('adj').style.display=''
									}
									function treure() {
										$('#archivo1').removeAttr("required");
										document.getElementById('adj').style.display='none'; 
									}
								</script>           
                                                		
                            	<form name="gestions" method="post" action="<?php echo $PHP_SELF ?>" enctype="multipart/form-data">
                            	<p align="justify">L'Associació PrisMa estableix que la col·laboració docent que fan els tutors/autors és una activitat complementària a la seva activitat laboral o professional principal. Per tal de verificar el compliment adequat d'aquest criteri, et sol·licitem que semestralment ens confirmis la teva situació laboral escollint una de les opcions següents i adjuntant-nos una còpia actual del teu <em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em>. </p>
                                <ul style="text-align:left; font-size:12px; color: #1A1A1A">
                                    <li style="list-style:none"><input type="radio" name="estat" value="Sóc autònom/a." required onclick="posar()" /> Sóc autònom/a.</li>
                                    <li style="list-style:none"><input type="radio" name="estat" value="Treballo per compte aliè." onclick="posar()" /> Treballo per compte aliè.</li>
                                    <li style="list-style:none"><input type="radio" name="estat" value="Estic jubilat/da." onclick="treure()" /> Estic jubilat/da.</li>
                                    <li style="list-style:none"><input type="radio" name="estat" value="No estic treballant." onclick="posar()" /> No estic treballant.</li>
                                    <li style="list-style:none"><input type="radio" name="estat" value="Altres..." onclick="posar()" /> Altres... </li>                                
                                </ul>
                                
                               
                                
                                
                                 <textarea name="observacions" style="width:911px; height:65px" placeholder="Observacions"></textarea>
                                
                                
                                <p align="left" id="adj"><br /><strong>Adjuntar el <em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em></strong> (pots consultar les instruccions per obtenir aquest «Certificat» clicant <a href="https://sede.seg-social.gob.es/wps/portal/sede/sede/EmpresasyProfesionales/EmpresasyProfDetalle/!ut/p/z1/rVVNU9swEP0r4cBRo9WHbeloGGoICWkIhsQXRpbloBY7wXah9NdXTmmZKbXNNPgm7frt6u3bXZzgJU5K9WjXqrGbUt278yrxbxnxOZFAJhGcnUAYT89jyeYsmjN8s3OAji8EnPT_f40TnOiy2TZ3eFWbzNzqTdmY0mab-hDai0MwxbYytXJnW-abqjD16HmkTdXY3Gq1c6TgSU5arK22GV4J4isFgUGaegRxRgCJgGkEOcmFnxOmGX_JvSM5kIO537TxBhB2Dn309Aah_oBD6OGVe0Xw6gAQOeD4CBbXQAlELs1Ha55wXDrmXEUXryQBp5yAL1EW8BRxnmqUZlogqpXUmdBpDhKfwt8RIjL32ggzcXY5JyCCPSP8gvdmAsgY6AT8qYBQzhfx5IIwCPie8OMhhp3G7ZeHhyR0Qmy1973Byw4lavstU5kq25vKrG1hypGpt0ZbdT_KzP2oUNUhkE5bG4tW0-Pp2j1BNXeoFTRedrv3QL2pyhGHkMyufHp6QmDWR5vxtCSBRDkBz9HmZ0gaKVCWBllgFOFM6f6qRED2hO-vyhn536qo2tbO7Jga1aq0jaqsascDBc7fUHbu2iSMP80kjwMKvW8CUCkFjWgGrFVajqRymnO6I8BSHXgeG4JnHwLf2SfBnvDjoUHzb-12jGS8BNJpen9p3zH6u-MMdIjfTdi71sdAh9A94cdD6-djaXzZoNsiLgR7Rl8vT38cXaDoOBVPV3lxI-p8esK8dXE7bXY3vw2fJ38MdXhw8BPRUxKm/dz/d5/L2dBISEvZ0FBIS9nQSEh/" title="Clica aquí" target="_blank" style="text-decoration:none; color:#8FA97A; font-weight:bold">AQUÍ</a>): <br /><br /><input type='file' name='archivo1' id='archivo1' required> </p>
<p align="left"></p>                                
                                
                            <p align="justify"><br /><br />T'agraïm la teva col·laboració i quedem a la teva disposició per a qualsevol dubte o consulta.</p>
							
                                <div align="center"><br />
                                  <input type="submit" name="enviar" class="botones" value="ENVIAR"/>
                                </div>							
                            
                           
                            </form>
							<?php
							}
							?>  
                            </div>
                       </div>
                <?php
				}
				else
					echo "<p align=center style=font-size:12px><br /><br />Per accedir a la Intranet has d'estar identificat/da en el Campus Virtual.<br /><br /><a href=http://www.prisma.cat/ target=_blank style=text-decoration:none>www.prisma.cat</a></p>";		
				?>            
            </td>
        </tr>             
	</table>
</body>
</html>
