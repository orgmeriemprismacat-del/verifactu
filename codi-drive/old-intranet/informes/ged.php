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

// Valoració segons els continguts del curs:

// Videotrobades
$enunciat1="Conèixer els elements necessaris per a una gestió emocional saludable.";
$enunciat2="Reconèixer recursos personals per a l’acompanyament en moments de pèrdua i dol.";
$enunciat3="Identificar elements que faciliten i elements que dificulten l’acompanyament en moments de pèrdua i dol.";
$enunciat4="Identificar els criteris pedagògics que justifiquen les pràctiques educatives amb relació a la mort, la vida, les pèrdues i el dol.";
$enunciat5="Conèixer recursos de la pedagogia de la vida i la mort i la pedagogia del dol per comunicar-se amb les famílies i acompanyar-les.";

// Valoració segons el grau de participació i interacció dels participants a les diferents videotrucades:

// Sessió 1
$enunciat6="Grau de participació.";
$enunciat7="Grau d’interacció entre els participants.";

// Sessió 2
$enunciat8="Grau de participació.";
$enunciat9="Grau d’interacció entre els participants.";

// Sessió 3
$enunciat10="Grau de participació.";
$enunciat11="Grau d’interacció entre els participants.";

// Sessió 4
$enunciat12="Grau de participació.";
$enunciat13="Grau d’interacció entre els participants.";

// ANÀLISI DEL RACÓ D’INTERCANVI
$enunciat14="Hi ha recursos proposats pels participants?";

  
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
											GRAU D'ASSOLIMENT DELS OBJECTIUS DEL CURS
										</td>
									</tr>
									<tr>
										<td class="celda" colspan="8">
											
										</td>
									</tr>	
									<tr>
										<td class="celda2" colspan="8">
											<p><strong class="fort2">VALORACIÓ SEGONS ELS CONTINGUTS DE LES SESSIONS:</strong></p>
										</td>
									</tr>	
                                    <tr>
                                    	<td colspan="8" class="modul">
                                        
                                        </td>
                                    </tr>
									<tr>
										<td width="780" class="apartat2" colspan="2">
											<strong class="fort">VIDEOTROBADES</strong>
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
										<tr class="borde_inferior">
											<td colspan="2">
												<p><?php echo $enunciat2; ?></p>
											</td>
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
											<td class="celda" colspan="8">&nbsp;

											</td>
										</tr>
										<tr>
											<td class="celda2" colspan="8">
												<p><strong class="fort2">VALORACIÓ SEGONS EL GRAU DE PARTICIPACIÓ I INTERACCIÓ DELS PARTICIPANTS A LES DIFERENTS VIDEOTROBADES: </strong></p>
											</td>
										</tr>
										 <tr>
											<td colspan="8" class="modul">

											</td>
										</tr>
										<tr>
											<td width="780" class="apartat2" colspan="2">
												<strong class="fort">SESSIÓ 1</strong>
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
												<strong class="fort">SESSIÓ 2</strong>
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
											<td colspan="8" class="modul">

											</td>
										</tr>
										<tr>
											<td width="780" class="apartat2" colspan="2">
												<strong class="fort">SESSIÓ 3</strong>
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
												<p><?php echo $enunciat10; ?></p>
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
											<td colspan="8" class="modul">

											</td>
										</tr>
										<tr>
											<td width="780" class="apartat2" colspan="2">
												<strong class="fort">SESSIÓ 4</strong>
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
											<td colspan="8">&nbsp;
											</td>
										</tr>
										<tr class="apartat">
											<td>
												ANÀLISI DEL RACÓ D’INTERCANVI
											</td>
											<td width="25" align="center">
												
											</td>
                                            <td width="25" align="center">
												
											</td>
											<td width="25" align="center">
												
											</td>
											<td width="25" align="center">
												
											</td>
											<td width="25" align="center">
												
											</td>
											<td width="25" align="center">
												
											</td>
											<td width="25" align="center">
												
											</td>
										</tr>										
										<tr>
											<td colspan="4">
												<p><?php echo $enunciat14; ?></p>
											</td>
											<td align="center">
												SÍ
											</td>
											<td align="center">
												<input type="radio" name="preg14" value="si" <?php if ($cadena[14]=='si') {echo ('checked');} ?> disabled="disabled">
											</td>
											<td align="center">
												NO
											</td>
											<td align="center">
												<input type="radio" name="preg14" value="no" <?php if ($cadena[14]=='no') {echo ('checked');} ?> disabled="disabled">
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