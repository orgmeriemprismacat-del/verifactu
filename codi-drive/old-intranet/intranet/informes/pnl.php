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
<?php 
// aquí s'obté el codi del curs
$url= $_SERVER["REQUEST_URI"];
$pos = strpos($url, 'shortname');
$codi_curs = substr($url, $pos+10);

$conexion = mysqli_connect('localhost','suport','1324GiRoNa','gestio');
								
mysqli_set_charset ($conexion, "utf8");

if (mysqli_connect_errno()) 
{
	echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
}
else
{		
	$result = mysqli_query ($conexion,"SELECT `NOM CURS` AS ncurs, AULA FROM cursos WHERE id_Curs='".$codi_curs."'");	
	$row = mysqli_fetch_array($result);
}

?>
<title>Informe del curs <?php echo $codi_curs; ?></title>
<link rel="stylesheet" href="../css/estilo_back.css"/> 
<link rel="stylesheet" href="../css/estil_informe.css"/>  

<script>
	function imprimeix()
	{
		document.getElementById("imprimir").style.visibility = 'hidden';
		window.print();
		document.getElementById("imprimir").style.visibility = 'visible';
	}
</script>
   
</head>

<?php

// Valoració segons els continguts de cada mòdul:

// Mòdul 1 
$enunciat1="Comprendre la relació que existeix entre la personalitat de cadascú i el tipus de conductes i pensaments.";
$enunciat2="Entendre la PNL com a eina de creixement personal que permet la millora qualitativa de la pràctica docent.";

// Mòdul 2 
$enunciat3="Analitzar i reflexionar sobre la influència de les creences en els mapes mentals que ens fem i com determinen la nostra pròpia identitat.";
$enunciat4="Analitzar quin tipus de creences afavoreixen la vida professional i emocional.";
$enunciat5="Constatar la importància que tenen les creences en l’acció docent.";

// Mòdul 3 
$enunciat6="Identificar i reflexionar sobre els diversos sistemes representatius de recollida d’informació i la seva aplicació a la docència.";
$enunciat7="Conèixer recursos per adequar estratègies d’aprenentatge a un grup d’alumnat o a un alumne segons les seves característiques i processos de pensament i aprenentatge.";

// Mòdul 4 
$enunciat8="Conèixer, analitzar i valorar les principals tècniques de PNL aplicades dins l’entorn escolar.";

// Valoració segons el grau de participació i interacció dels participants a les diferents activitats per mòdul: 

// Mòdul 1 
$enunciat9="Grau de participació.";
$enunciat10="Grau d’interacció entre els participants.";

// Mòdul 2 
$enunciat11="Grau de participació.";
$enunciat12="Grau d’interacció entre els participants.";

// Mòdul 3
$enunciat13="Grau de participació.";
$enunciat14="Grau d’interacció entre els participants.";

// Mòdul 4 
$enunciat15="Grau de participació.";
$enunciat16="Grau d’interacció entre els participants.";

// ANÀLISI DE LES ACTIVITATS COMPLEMENTÀRIES (DEBATS, RACÓ D’INTERCANVI I P@SSADÍS)
$enunciat17="Resposta dels participants al DEBAT 1.";
$enunciat18="Resposta dels participants al DEBAT 2.";
$enunciat19="Resposta dels participants al DEBAT 3.";
$enunciat20="Resposta dels participants al DEBAT 4.";
$enunciat21="Hi ha debats iniciats pels participants?";
$enunciat22="Hi ha recursos proposats pels participants en el RACÓ D'INTERCANVI?";
  
 /***************************************************************/
    
?>


<body topmargin="0" style="background-color:#FFFFFF !important;">
	<table width="100%" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0">  	               
        <tr>
            <td align="center">
                <div id="login">     
                    <div id="llegenda_curs">                  	             
                          	 
							<?php
					
                            // comprovem si hi ha dades guardades
							$conexion = mysqli_connect("localhost","suport","1324GiRoNa","gestio");
							if (mysqli_connect_errno())  
							{
								echo "No es pot connectar: " . mysqli_connect_error();
							}
							mysqli_set_charset($conexion, "utf8");
							
							$result = mysqli_query ($conexion, "SELECT * FROM informe_tutor WHERE codic='".$codi_curs."' AND finalitzat IS NOT NULL");
							$result_t = mysqli_query ($conexion, "SELECT NOM, COGNOMS FROM personal, cursos WHERE id_Curs='".$codi_curs."' AND DNI=DNI_TUTOR");
								
							if (mysqli_num_rows($result)>0)
							{
								// mostrem el que hi ha guardat			
								
								$row_r = mysqli_fetch_array($result);
								$row_t = mysqli_fetch_array($result_t);
								
								$cadena=explode("#", $row_r['radios']);
																
							 ?>                	
								
								
										<table border="0" align="center" width="100%" vspace="0" cellpadding="0" cellspacing="0" class="taula">   
										 	<tr>
												<td colspan="2" class="titol secretaria">
													INFORME D’AVALUACIÓ I VALORACIÓ DE L’ACTIVITAT REALITZAT PEL FORMADOR/TUTOR<br /><br />
                                                    <?php echo $row_t['NOM']." ".$row_t['COGNOMS']; ?>                         
												</td>
											</tr>
                                            <tr class="apartat">
												<td colspan="2">
													ANÀLISI DE LES EXPECTATIVES (fòrum PRESENTACIÓ I EXPECTATIVES i ESPAI DE COMIAT)                            
												</td>
											</tr>
											<tr>
												<td class="celda" colspan="2">	
													<p><?php echo $row_r['expectatives']; ?></p>
												</td>
											</tr>                        
										</table>
										<br>
										<table border="0" align="center" width="100%" vspace="0" style="margin-top:0px; padding-bottom:5px;" cellpadding="0" cellspacing="0" class="celda">       
									<tr class="apartat">
										<td colspan="8">
											GRAU D'ASSOLIMENT DELS OBJECTIUS DE CADA MÒDUL
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">
											
										</td>
									</tr>	
									<tr>
										<td class="celda2" colspan="8">
											<p><strong class="fort2">VALORACIÓ SEGONS ELS CONTINGUTS DE CADA MÒDUL:</strong></p>
										</td>
									</tr>	
                                    <tr>
                                    	<td colspan="8" class="modul">
                                        
                                        </td>
                                    </tr>
									<tr>
										<td width="780" class="apartat2" colspan="2">
											<strong class="fort">MÒDUL 1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">0</strong>
										</td>                     
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">5</strong>
										</td>  
									</tr>                        
									<tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat1; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg1" value="0" <?php if ($cadena[1]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg1" value="1" <?php if ($cadena[1]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg1" value="2" <?php if ($cadena[1]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg1" value="3" <?php if ($cadena[1]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg1" value="4" <?php if ($cadena[1]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg1" value="5" <?php if ($cadena[1]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat2; ?></p>      
										<td align="center">                    		
											<input type="radio" name="preg2" value="0" <?php if ($cadena[2]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg2" value="1" <?php if ($cadena[2]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg2" value="2" <?php if ($cadena[2]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg2" value="3" <?php if ($cadena[2]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg2" value="4" <?php if ($cadena[2]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg2" value="5" <?php if ($cadena[2]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                   
									</tr>
									<tr>
                                    	<td colspan="8" class="modul">
                                        
                                        </td>
                                    </tr>
									<tr>
										<td width="780" class="apartat2" colspan="2">
											<strong class="fort">MÒDUL 2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">0</strong>
										</td>                     
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">5</strong>
										</td>  
									</tr>
                                    <tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat3; ?></p>       
										</td>
                                        <td align="center">                    		
											<input type="radio" name="preg3" value="0" <?php if ($cadena[3]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg3" value="1" <?php if ($cadena[3]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg3" value="2" <?php if ($cadena[3]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg3" value="3" <?php if ($cadena[3]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg3" value="4" <?php if ($cadena[3]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg3" value="5" <?php if ($cadena[3]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                   
									</tr>									
                                    <tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat4; ?></p>       
										</td>
                                        <td align="center">                    		
											<input type="radio" name="preg4" value="0" <?php if ($cadena[4]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg4" value="1" <?php if ($cadena[4]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg4" value="2" <?php if ($cadena[4]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg4" value="3" <?php if ($cadena[4]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg4" value="4" <?php if ($cadena[4]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg4" value="5" <?php if ($cadena[4]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                   
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat5; ?></p>       
										</td>
                                        <td align="center">                    		
											<input type="radio" name="preg5" value="0" <?php if ($cadena[5]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg5" value="1" <?php if ($cadena[5]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg5" value="2" <?php if ($cadena[5]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg5" value="3" <?php if ($cadena[5]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg5" value="4" <?php if ($cadena[5]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg5" value="5" <?php if ($cadena[5]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
                                    	<td colspan="8" class="modul">
                                        
                                        </td>
                                    </tr>
									<tr>
										<td width="780" class="apartat2" colspan="2">
											<strong class="fort">MÒDUL 3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">0</strong>
										</td>                     
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">5</strong>
										</td>  
									</tr>
                                    <tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat6; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg6" value="0" <?php if ($cadena[6]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg6" value="1" <?php if ($cadena[6]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg6" value="2" <?php if ($cadena[6]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg6" value="3" <?php if ($cadena[6]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg6" value="4" <?php if ($cadena[6]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg6" value="5" <?php if ($cadena[6]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>									
                                    <tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat7; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg7" value="0" <?php if ($cadena[7]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg7" value="1" <?php if ($cadena[7]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg7" value="2" <?php if ($cadena[7]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg7" value="3" <?php if ($cadena[7]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg7" value="4" <?php if ($cadena[7]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg7" value="5" <?php if ($cadena[7]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
                                    	<td colspan="8" class="modul">
                                        
                                        </td>
                                    </tr>
									<tr>
										<td width="780" class="apartat2" colspan="2">
											<strong class="fort">MÒDUL 4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">0</strong>
										</td>                     
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">5</strong>
										</td>  
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat8; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg8" value="0" <?php if ($cadena[8]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg8" value="1" <?php if ($cadena[8]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg8" value="2" <?php if ($cadena[8]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg8" value="3" <?php if ($cadena[8]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg8" value="4" <?php if ($cadena[8]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg8" value="5" <?php if ($cadena[8]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>									
                                    <tr>
										<td class="celda" colspan="8">&nbsp;
											
										</td>
									</tr>
									<tr>
										<td class="celda2" colspan="8">
											<p><strong class="fort2">VALORACIÓ SEGONS EL GRAU DE PARTICIPACIÓ I INTERACCIÓ DELS PARTICIPANTS A LES DIFERENTS ACTIVITATS PER MÒDUL: </strong></p>
										</td>
									</tr>
                                     <tr>
                                    	<td colspan="8" class="modul">
                                        
                                        </td>
                                    </tr>
									<tr>
										<td width="780" class="apartat2" colspan="2">
											<strong class="fort">MÒDUL 1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">0</strong>
										</td>                     
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">5</strong>
										</td>  
									</tr>                        
									<tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat9; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg9" value="0" <?php if ($cadena[9]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg9" value="1" <?php if ($cadena[9]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg9" value="2" <?php if ($cadena[9]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg9" value="3" <?php if ($cadena[9]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg9" value="4" <?php if ($cadena[9]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg9" value="5" <?php if ($cadena[9]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat11; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg10" value="0" <?php if ($cadena[10]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg10" value="1" <?php if ($cadena[10]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg10" value="2" <?php if ($cadena[10]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg10" value="3" <?php if ($cadena[10]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg10" value="4" <?php if ($cadena[10]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg10" value="5" <?php if ($cadena[10]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
                                    	<td colspan="8" class="modul">
                                        
                                        </td>
                                    </tr>
									<tr>
										<td width="780" class="apartat2" colspan="2">
											<strong class="fort">MÒDUL 2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">0</strong>
										</td>                     
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">5</strong>
										</td>  
									</tr>                        
									<tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat11; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg11" value="0" <?php if ($cadena[11]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg11" value="1" <?php if ($cadena[11]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg11" value="2" <?php if ($cadena[11]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg11" value="3" <?php if ($cadena[11]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg11" value="4" <?php if ($cadena[11]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg11" value="5" <?php if ($cadena[11]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat12; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg12" value="0" <?php if ($cadena[12]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg12" value="1" <?php if ($cadena[12]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg12" value="2" <?php if ($cadena[12]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg12" value="3" <?php if ($cadena[12]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg12" value="4" <?php if ($cadena[12]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg12" value="5" <?php if ($cadena[12]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
                                    <tr>
                                    	<td colspan="8" class="modul">
                                        
                                        </td>
                                    </tr>
									<tr>
										<td width="780" class="apartat2" colspan="2">
											<strong class="fort">MÒDUL 3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">0</strong>
										</td>                     
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">5</strong>
										</td>  
									</tr>                        
									<tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat13; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg13" value="0" <?php if ($cadena[13]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg13" value="1" <?php if ($cadena[13]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg13" value="2" <?php if ($cadena[13]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg13" value="3" <?php if ($cadena[13]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg13" value="4" <?php if ($cadena[13]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg13" value="5" <?php if ($cadena[13]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat14; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg14" value="0" <?php if ($cadena[14]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg14" value="1" <?php if ($cadena[14]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg14" value="2" <?php if ($cadena[14]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg14" value="3" <?php if ($cadena[14]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg14" value="4" <?php if ($cadena[14]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg14" value="5" <?php if ($cadena[14]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
                                    <tr>
                                    	<td colspan="8" class="modul">
                                        
                                        </td>
                                    </tr>
									<tr>
										<td width="780" class="apartat2" colspan="2">
											<strong class="fort">MÒDUL 4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">0</strong>
										</td>                     
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">1</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">2</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">3</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">4</strong>
										</td>
										<td width="25" align="center" class="apartat2">                    		
											<strong class="fort">5</strong>
										</td>  
									</tr>                        
									<tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat15; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg15" value="0" <?php if ($cadena[15]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg15" value="1" <?php if ($cadena[15]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg15" value="2" <?php if ($cadena[15]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg15" value="3" <?php if ($cadena[15]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg15" value="4" <?php if ($cadena[15]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg15" value="5" <?php if ($cadena[15]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat16; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg16" value="0" <?php if ($cadena[16]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg16" value="1" <?php if ($cadena[16]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg16" value="2" <?php if ($cadena[16]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg16" value="3" <?php if ($cadena[16]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg16" value="4" <?php if ($cadena[16]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg16" value="5" <?php if ($cadena[16]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
                                    <tr>
										<td colspan="8">&nbsp;                          	
										</td>
									</tr>
									<tr class="apartat">
										<td colspan="8">
											ACTIVITATS QUE MÉS DEBAT HAN GENERAT
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">	
											<p><?php echo $row_r['mes_debat']; ?></p>											
										</td>
									</tr> 
									<tr>
										<td colspan="8">&nbsp;                          	
										</td>
									</tr>
									<tr class="apartat">
										<td colspan="8">
											ACTIVITATS QUE MENYS DEBAT HAN GENERAT
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">	
											<p><?php echo $row_r['menys_debat']; ?></p>
										</td>
									</tr> 
									<tr>
										<td colspan="8">&nbsp;                          	
										</td>
									</tr>
									<tr class="apartat">
										<td>
											ANÀLISI DE LES ACTIVITATS COMPLEMENTÀRIES (DEBATS, RACÓ D’INTERCANVI i P@SSADÍS)
										</td>
                                        <td width="25" align="center">                    		
											NO PROPOSAT
										</td>
										<td width="25" align="center">                    		
											0
										</td>                     
										<td width="25" align="center">                    		
											1
										</td>
										<td width="25" align="center">                    		
											2
										</td>
										<td width="25" align="center">                    		
											3
										</td>
										<td width="25" align="center">                    		
											4
										</td>
										<td width="25" align="center">                    		
											5
										</td>  
									</tr>
									<tr class="borde_inferior">
										<td>                        		
											<p><?php echo $enunciat17; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg17" value="C" <?php if ($cadena[17]=='C') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg17" value="0" <?php if ($cadena[17]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg17" value="1" <?php if ($cadena[17]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg17" value="2" <?php if ($cadena[17]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg17" value="3" <?php if ($cadena[17]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg17" value="4" <?php if ($cadena[17]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg17" value="5" <?php if ($cadena[17]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr class="borde_inferior">
										<td>                        		
											<p><?php echo $enunciat18; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg18" value="0" <?php if ($cadena[18]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg18" value="C" <?php if ($cadena[18]=='C') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg18" value="1" <?php if ($cadena[18]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg18" value="2" <?php if ($cadena[18]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg18" value="3" <?php if ($cadena[18]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg18" value="4" <?php if ($cadena[18]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg18" value="5" <?php if ($cadena[18]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr class="borde_inferior">
										<td>                        		
											<p><?php echo $enunciat19; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg19" value="C" <?php if ($cadena[19]=='C') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg19" value="0" <?php if ($cadena[19]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg19" value="1" <?php if ($cadena[19]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg19" value="2" <?php if ($cadena[19]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg19" value="3" <?php if ($cadena[19]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg19" value="4" <?php if ($cadena[19]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg19" value="5" <?php if ($cadena[19]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr class="borde_inferior">
										<td>                        		
											<p><?php echo $enunciat20; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg20" value="C" <?php if ($cadena[20]=='C') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg20" value="0" <?php if ($cadena[20]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg20" value="1" <?php if ($cadena[20]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg20" value="2" <?php if ($cadena[20]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg20" value="3" <?php if ($cadena[20]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg20" value="4" <?php if ($cadena[20]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg20" value="5" <?php if ($cadena[20]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr class="borde_inferior">
										<td colspan="4">                        		
											<p><?php echo $enunciat21; ?></p>
										</td>               
										<td align="center">                    		
											SÍ
										</td>
										<td align="center">                    		
											<input type="radio" name="preg21" value="si" <?php if ($cadena[21]=='si') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											NO
										</td>
										<td align="center">                    		
											<input type="radio" name="preg21" value="no" <?php if ($cadena[21]=='no') {echo ('checked');} ?> disabled="disabled">
										</td>                                                                                
									</tr>
									<tr>
										<td colspan="4">              		
											<p><?php echo $enunciat22; ?></p>
										</td>       
										<td align="center">                    		
											SÍ
										</td>
										<td align="center">                    		
											<input type="radio" name="preg22" value="si" <?php if ($cadena[22]=='si') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											NO
										</td>
										<td align="center">                    		
											<input type="radio" name="preg22" value="no" <?php if ($cadena[22]=='no') {echo ('checked');} ?> disabled="disabled">
										</td>                                                 
									</tr>
                                    <tr>
										<td colspan="8">&nbsp;                          	
										</td>
									</tr>
									<?php
										if ($row_r['observacions']!="")
										{									
									?>
									
									<tr>
										<td colspan="8" class="apartat2">
											<strong class="fort">Altres observacions</strong>
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">	
											<p><?php echo $row_r['observacions']; ?></p>
										</td>
									</tr>
									<?php
										}									
									?>
									 
									<tr>
										<td colspan="8">&nbsp;                          	
										</td>
									</tr>
									<tr class="apartat">
										<td colspan="8">
											DUBTES I CONSULTES (Incidències i dubtes de contingut, temporalització, tècniques...)
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">	
											<p><?php echo $row_r['dubtes']; ?></p>
										</td>
									</tr> 
									<tr>
										<td colspan="8">&nbsp;                          	
										</td>
									</tr>
									<tr class="apartat">
										<td colspan="8">
											INCIDÈNCIES REBUDES AL CORREU
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">	
											<p><?php echo $row_r['incidencies']; ?></p>                                            
										</td>
									</tr>
									<tr>
										<td colspan="8">&nbsp;                          	
										</td>
									</tr>
									<tr class="apartat">
										<td colspan="8">
											ANÀLISI DE L’ENQUESTA DE SATISFACCIÓ
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">	
											<p><?php echo $row_r['enquesta']; ?></p>
										</td>
									</tr>
									<tr>
										<td colspan="8">&nbsp;                          	
										</td>
									</tr>
									<tr class="apartat">
										<td colspan="8">
											ANÀLISI DE LES VALORACIONS FINALS
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">	
											<p><?php echo $row_r['valoracions']; ?></p>
										</td>
									</tr>
									<tr class="apartat">
										<td colspan="8">
											PROPOSTA DE MILLORES DEL TUTOR/A
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">	
											<p><?php echo $row_r['millores']; ?></p>
										</td>
									</tr> 
									<tr>
										<td colspan="8">&nbsp;                          	
										</td>
									</tr>
									<tr class="apartat">
										<td colspan="8">
											ALTRES COMENTARIS DEL TUTOR/A
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">	
											<p><?php echo $row_r['comentaris']; ?></p>
										</td>
									</tr> 
									<tr>
										<td class="celda" colspan="8" align="center">	
											<input id="imprimir" type="button" name="imprimir" value="IMPRIMIR" onclick="imprimeix()">
										</td>
									</tr>
								</table>
										<?php
								
							}	
                                    
                                        ?>
    
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
	header("Location: ../acces.php");
	exit;
}
?>