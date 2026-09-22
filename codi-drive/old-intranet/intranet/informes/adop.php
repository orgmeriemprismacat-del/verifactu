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
// Aquí es canvia el codi del curs que s'ha de passar a revisió.

// Aquí van les preguntes dels radiobuttons
 
// Valoració segons els continguts de cada mòdul:

// Mòdul 1  
$enunciat1="Constatar científicament el pes de l’aferrament i l’estrès en la primera infància i la influència en el desenvolupament del nen.";
$enunciat2="Conèixer les etapes i els diferents tipus d'aferrament i reflexionar sobre el tipus de vincle que es crea amb el docent.";
$enunciat3="Prendre consciència de les repercussions que té per a un nen la privació d'aferrament a la primera infància, com els afecta els nens l’estrès i viure en una institució, quines repercussions poden tenir aquestes experiències en el seu desenvolupament.";
$enunciat4="Reflexionar sobre les necessitats específiques que aquests nens poden tenir, perquè el docent reconegui el que el nen està expressant amb els seus símptomes o conductes i pugui donar una resposta des de la comprensió i l’empatia més que des de l’exigència o aplicar-hi receptes.";
$enunciat5="Comprendre què és la plasticitat cerebral.";
$enunciat6="Reflexionar sobre la capacitat reparadora d'aquests alumnes tenint en compte les dificultats específiques de cada infant i la seva individualitat.";

// Mòdul 2 
$enunciat7="Conèixer i reflexionar sobre les reaccions habituals dels infants, tant quan l'adopció és recent com en la postadopció, per poder entendre algunes d'aquestes conductes a l'escola, i poder respondre-hi adequadament.";
$enunciat8="Comprendre que la causa dels possibles retards i necessitats especials no és l'adopció sinó les circumstàncies que van portar a l’infant fins a ella.";
$enunciat9="Saber que cada infant reacciona davant d’aquest fet amb una    gran varietat de diferències individuals.";
$enunciat10="Analitzar  les diferents etapes de l’infant respecte a la consciència de \"saber-se adoptat\" i per tant dels seus orígens.";
$enunciat11="Reflexionar sobre quan i com és aconsellable parlar amb ell sobre el fet de la seva adopció.";
$enunciat12="Sensibilitzar sobre algunes circumstàncies que poden sorgir a l'escola amb alumnes que van ser adoptats.";
$enunciat13="Interpretar la complexitat de factors que influeixen en el desenvolupament de l’adolescent que va ser adoptat.";
$enunciat14="Entendre la recerca dels orígens en l’adolescència.";

// Mòdul 3 
$enunciat15="Comprendre quines són les carències i pèrdues dels infants adoptats i què podem fer per ajudar-los a superar-les.";
$enunciat16="Analitzar i entendre com podem promoure resiliència des del nostre rol professional per respondre a les necessitats dels alumnes que han viscut adversitat.";
$enunciat17="Reflexionar com esdevenir uns bons tutors de resiliència.";
$enunciat18="Analitzar els factors que promouen la resiliència.";
$enunciat19="Conèixer diferents programes per fomentar la resiliència i poder-los adaptar i aplicar a l’escola amb els nostres alumnes.";

// Mòdul 4
$enunciat20="Analitzar les dificultats i causes més comuns que es presenten en els infants que han passat per un procés d’adopció.";
$enunciat21="Reflexionar sobre la manera d’enfocar el fet adoptiu a l’escola.";
$enunciat22="Proporcionar indicadors de trastorn del Vincle.";
$enunciat23="Proposar eines d’intervenció i orientació als docents que atenen a infants que han arribat a les seves famílies per un procés d’adopció.";

// Valoració segons el grau de participació i interacció dels participants a les diferents   activitats per mòdul: 

// Mòdul 1
$enunciat24="Grau de participació.";
$enunciat25="Grau d’interacció entre els participants.";

// Mòdul 2
$enunciat26="Grau de participació.";
$enunciat27="Grau d’interacció entre els participants.";

// Mòdul 3
$enunciat28="Grau de participació.";
$enunciat29="Grau d’interacció entre els participants.";

// Mòdul 4
$enunciat30="Grau de participació.";
$enunciat31="Grau d’interacció entre els participants.";

// ANÀLISI DE LES ACTIVITATS COMPLEMENTÀRIES (DEBATS, RACÓ D’INTERCANVI I P@SSADÍS)
$enunciat32="Resposta dels participants al DEBAT 1.";
$enunciat33="Resposta dels participants al DEBAT 2.";
$enunciat34="Resposta dels participants al DEBAT 3.";
$enunciat35="Resposta dels participants al DEBAT 4.";
$enunciat36="Hi ha debats iniciats pels participants?";
$enunciat37="Hi ha recursos proposats pels participants en el RACÓ D'INTERCANVI?";
  
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
									<tr class="borde_inferior">
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
                                    <tr class="borde_inferior">
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
                                    <tr class="borde_inferior">
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
                                    <tr class="borde_inferior">
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
                                    <tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat17; ?></p>
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
										<td colspan="2">                        		
											<p><?php echo $enunciat18; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg18" value="0" <?php if ($cadena[18]=='0') {echo ('checked');} ?> disabled="disabled">
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
                                    <tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat19; ?></p>
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
											<p><?php echo $enunciat20; ?></p>
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
										<td colspan="2">                        		
											<p><?php echo $enunciat21; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg21" value="0" <?php if ($cadena[21]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg21" value="1" <?php if ($cadena[21]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg21" value="2" <?php if ($cadena[21]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg21" value="3" <?php if ($cadena[21]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg21" value="4" <?php if ($cadena[21]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg21" value="5" <?php if ($cadena[21]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
                                    <tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat22; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg22" value="0" <?php if ($cadena[22]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg22" value="1" <?php if ($cadena[22]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg22" value="2" <?php if ($cadena[22]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg22" value="3" <?php if ($cadena[22]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg22" value="4" <?php if ($cadena[22]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg22" value="5" <?php if ($cadena[22]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat23; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg23" value="0" <?php if ($cadena[23]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg23" value="1" <?php if ($cadena[23]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg23" value="2" <?php if ($cadena[23]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg23" value="3" <?php if ($cadena[23]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg23" value="4" <?php if ($cadena[23]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg23" value="5" <?php if ($cadena[23]=='5') {echo ('checked');} ?> disabled="disabled">
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
											<p><?php echo $enunciat24; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg24" value="0" <?php if ($cadena[24]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg24" value="1" <?php if ($cadena[24]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg24" value="2" <?php if ($cadena[24]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg24" value="3" <?php if ($cadena[24]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg24" value="4" <?php if ($cadena[24]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg24" value="5" <?php if ($cadena[24]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat25; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg25" value="0" <?php if ($cadena[25]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg25" value="1" <?php if ($cadena[25]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg25" value="2" <?php if ($cadena[25]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg25" value="3" <?php if ($cadena[25]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg25" value="4" <?php if ($cadena[25]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg25" value="5" <?php if ($cadena[25]=='5') {echo ('checked');} ?> disabled="disabled">
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
											<p><?php echo $enunciat26; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg26" value="0" <?php if ($cadena[26]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg26" value="1" <?php if ($cadena[26]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg26" value="2" <?php if ($cadena[26]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg26" value="3" <?php if ($cadena[26]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg26" value="4" <?php if ($cadena[26]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg26" value="5" <?php if ($cadena[26]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat27; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg27" value="0" <?php if ($cadena[27]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg27" value="1" <?php if ($cadena[27]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg27" value="2" <?php if ($cadena[27]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg27" value="3" <?php if ($cadena[27]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg27" value="4" <?php if ($cadena[27]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg27" value="5" <?php if ($cadena[27]=='5') {echo ('checked');} ?> disabled="disabled">
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
											<p><?php echo $enunciat28; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg28" value="0" <?php if ($cadena[28]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg28" value="1" <?php if ($cadena[28]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg28" value="2" <?php if ($cadena[28]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg28" value="3" <?php if ($cadena[28]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg28" value="4" <?php if ($cadena[28]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg28" value="5" <?php if ($cadena[28]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat29; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg29" value="0" <?php if ($cadena[29]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg29" value="1" <?php if ($cadena[29]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg29" value="2" <?php if ($cadena[29]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg29" value="3" <?php if ($cadena[29]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg29" value="4" <?php if ($cadena[29]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg29" value="5" <?php if ($cadena[29]=='5') {echo ('checked');} ?> disabled="disabled">
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
											<p><?php echo $enunciat30; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg30" value="0" <?php if ($cadena[30]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg30" value="1" <?php if ($cadena[30]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg30" value="2" <?php if ($cadena[30]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg30" value="3" <?php if ($cadena[30]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg30" value="4" <?php if ($cadena[30]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg30" value="5" <?php if ($cadena[30]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr>
										<td colspan="2">                        		
											<p><?php echo $enunciat31; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg31" value="0" <?php if ($cadena[31]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg31" value="1" <?php if ($cadena[31]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg31" value="2" <?php if ($cadena[31]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg31" value="3" <?php if ($cadena[31]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg31" value="4" <?php if ($cadena[31]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg31" value="5" <?php if ($cadena[31]=='5') {echo ('checked');} ?> disabled="disabled">
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
											<p><?php echo $enunciat32; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg32" value="C" <?php if ($cadena[32]=='C') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg32" value="0" <?php if ($cadena[32]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg32" value="1" <?php if ($cadena[32]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg32" value="2" <?php if ($cadena[32]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg32" value="3" <?php if ($cadena[32]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg32" value="4" <?php if ($cadena[32]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg32" value="5" <?php if ($cadena[32]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr class="borde_inferior">
										<td>                        		
											<p><?php echo $enunciat33; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg33" value="C" <?php if ($cadena[33]=='C') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg33" value="0" <?php if ($cadena[33]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg33" value="1" <?php if ($cadena[33]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg33" value="2" <?php if ($cadena[33]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg33" value="3" <?php if ($cadena[33]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg33" value="4" <?php if ($cadena[33]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg33" value="5" <?php if ($cadena[33]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr class="borde_inferior">
										<td>                        		
											<p><?php echo $enunciat34; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg34" value="C" <?php if ($cadena[34]=='C') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg34" value="0" <?php if ($cadena[34]=='0') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg34" value="1" <?php if ($cadena[34]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg34" value="2" <?php if ($cadena[34]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg34" value="3" <?php if ($cadena[34]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg34" value="4" <?php if ($cadena[34]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg34" value="5" <?php if ($cadena[34]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">                        		
											<p><?php echo $enunciat35; ?></p>
										</td>       
										<td align="center">                    		
											<input type="radio" name="preg35" value="C" <?php if ($cadena[35]=='C') {echo ('checked');} ?> disabled="disabled">
										</td>                     
										<td align="center">                    		
											<input type="radio" name="preg35" value="1" <?php if ($cadena[35]=='1') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg35" value="2" <?php if ($cadena[35]=='2') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg35" value="3" <?php if ($cadena[35]=='3') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg35" value="4" <?php if ($cadena[35]=='4') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											<input type="radio" name="preg35" value="5" <?php if ($cadena[35]=='5') {echo ('checked');} ?> disabled="disabled">
										</td>                                                     
									</tr>
									<tr class="borde_inferior">
										<td colspan="4">                        		
											<p><?php echo $enunciat36; ?></p>
										</td>               
										<td align="center">                    		
											SÍ
										</td>
										<td align="center">                    		
											<input type="radio" name="preg36" value="si" <?php if ($cadena[36]=='si') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											NO
										</td>
										<td align="center">                    		
											<input type="radio" name="preg36" value="no" <?php if ($cadena[36]=='no') {echo ('checked');} ?> disabled="disabled">
										</td>                                                                                
									</tr>
									<tr class="borde_inferior">
										<td colspan="4">              		
											<p><?php echo $enunciat37; ?></p>
										</td>       
										<td align="center">                    		
											SÍ
										</td>
										<td align="center">                    		
											<input type="radio" name="preg37" value="si" <?php if ($cadena[37]=='si') {echo ('checked');} ?> disabled="disabled">
										</td>
										<td align="center">                    		
											NO
										</td>
										<td align="center">                    		
											<input type="radio" name="preg37" value="no" <?php if ($cadena[37]=='no') {echo ('checked');} ?> disabled="disabled">
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