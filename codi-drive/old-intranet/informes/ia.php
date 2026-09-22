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
$enunciat1="Comprendre el concepte d’Intel·ligència Artificial (IA).";
$enunciat2="Conèixer els antecedents i les definicions de la IA.";
$enunciat3="Conèixer la classificació de les branques d’IA.";
$enunciat4="Saber què són els algoritmes i reflexionar sobre la seva relació amb la IA.";
$enunciat5="Explorar les diferents aplicacions de la IA en l’àmbit educatiu.";
$enunciat6="Diagnosticar avantatges i desavantatges de la IA en l’educació.";
$enunciat7="Adquirir informació sobre les implicacions socials de la IA.";
$enunciat8="Reflexionar sobre l’impacte de la IA en els professionals de l’educació.";

// Mòdul 2
$enunciat9="Comprendre el concepte d’ètica aplicada a la IA.";
$enunciat10="Conèixer els valors i principis ètics aplicats a la IA.";
$enunciat11="Conèixer accions de transparència i de males pràctiques amb IA.";
$enunciat12="Conèixer un marc ètic de la IA.";
$enunciat13="Conèixer estratègies i accions per implementar correctament la IA a l’aula.";
$enunciat14="Comprendre el potencial que poden oferir les eines d’IA.";
$enunciat15="Fomentar la capacitació de les competències necessàries per fer un bon ús de la IA.";
$enunciat16="Conèixer els principals recursos d’IA generativa i els cercadors específics.";
$enunciat17="Descobrir el funcionament de les eines d’IA generatives.";

// Mòdul 3
$enunciat18="Comprendre què és un prompt i quines són les seves característiques.";
$enunciat19="Conèixer diferents tècniques per crear prompts.";
$enunciat20="Identificar les estructures eficaces dels prompts.";
$enunciat21="Entendre com funciona un prompt.";
$enunciat22="Aprendre a utilitzar un generador de prompts per crear situacions d’aprenentatge.";
$enunciat23="Saber com avaluar amb la utilització d’eines d’IA.";
$enunciat24="Conèixer recomanacions i orientacions per prevenir les males pràctiques de la IA a l’aula.";
$enunciat25="Innovar en l’avaluació i l’aprenentatge amb IA.";
$enunciat26="Adquirir coneixements per afrontar i prevenir el plagi.";

// Mòdul 4
$enunciat27="Entendre el potencial de la IA com a recurs per a l’aprenentatge a l’aula.";
$enunciat28="Explorar les diverses maneres en què la IA pot ser implementada a l’aula per millorar el procés d’ensenyament-aprenentatge.";
$enunciat29="Obtenir una comprensió clara dels fonaments de la IA i de com aplicar-los de manera efectiva en activitats didàctiques.";
$enunciat30="Desenvolupar estratègies per crear i adaptar materials educatius que incorporin la IA.";
$enunciat31="Conèixer recursos i suports per planificar, implementar i avaluar activitats educatives que incorporin la IA.";

// Valoració segons el grau de participació i interacció dels participants a les diferents activitats per mòdul:

// Mòdul 1
$enunciat32="Grau de participació.";
$enunciat33="Grau d’interacció entre els participants.";

// Mòdul 2
$enunciat34="Grau de participació.";
$enunciat35="Grau d’interacció entre els participants.";

// Mòdul 3
$enunciat36="Grau de participació.";
$enunciat37="Grau d’interacció entre els participants.";

// Mòdul 4
$enunciat38="Grau de participació.";
$enunciat39="Grau d’interacció entre els participants.";

// ANÀLISI DE LES ACTIVITATS COMPLEMENTÀRIES (DEBATS i RACÓ D’INTERCANVI)
$enunciat40="Resposta dels participants al DEBAT 1.";
$enunciat41="Resposta dels participants al DEBAT 2.";
$enunciat42="Resposta dels participants al DEBAT 3.";
$enunciat43="Resposta dels participants al DEBAT 4.";
$enunciat44="Hi ha debats iniciats pels participants?";
$enunciat45="Hi ha recursos proposats pels participants en el RACÓ D'INTERCANVI?";

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
													ANÀLISI DE LES EXPECTATIVES (fòrum PRESENTACIÓ I EXPECTATIVES i ESPAI DE COMIAT i ESPAI DE COMIAT)
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
											<input type="radio" name="preg1" value="0" <?php if ($cadena[1]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg1" value="1" <?php if ($cadena[1]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg1" value="2" <?php if ($cadena[1]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg1" value="3" <?php if ($cadena[1]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg1" value="4" <?php if ($cadena[1]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg1" value="5" <?php if ($cadena[1]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat2; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg2" value="0" <?php if ($cadena[2]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg2" value="1" <?php if ($cadena[2]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg2" value="2" <?php if ($cadena[2]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg2" value="3" <?php if ($cadena[2]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg2" value="4" <?php if ($cadena[2]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg2" value="5" <?php if ($cadena[2]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat3; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg3" value="0" <?php if ($cadena[3]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg3" value="1" <?php if ($cadena[3]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg3" value="2" <?php if ($cadena[3]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg3" value="3" <?php if ($cadena[3]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg3" value="4" <?php if ($cadena[3]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg3" value="5" <?php if ($cadena[3]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat4; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg4" value="0" <?php if ($cadena[4]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg4" value="1" <?php if ($cadena[4]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg4" value="2" <?php if ($cadena[4]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg4" value="3" <?php if ($cadena[4]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg4" value="4" <?php if ($cadena[4]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg4" value="5" <?php if ($cadena[4]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat5; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg5" value="0" <?php if ($cadena[5]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg5" value="1" <?php if ($cadena[5]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg5" value="2" <?php if ($cadena[5]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg5" value="3" <?php if ($cadena[5]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg5" value="4" <?php if ($cadena[5]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg5" value="5" <?php if ($cadena[5]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                    <tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat6; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg6" value="0" <?php if ($cadena[6]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg6" value="1" <?php if ($cadena[6]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg6" value="2" <?php if ($cadena[6]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg6" value="3" <?php if ($cadena[6]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg6" value="4" <?php if ($cadena[6]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg6" value="5" <?php if ($cadena[6]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat7; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg7" value="0" <?php if ($cadena[7]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg7" value="1" <?php if ($cadena[7]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg7" value="2" <?php if ($cadena[7]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg7" value="3" <?php if ($cadena[7]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg7" value="4" <?php if ($cadena[7]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg7" value="5" <?php if ($cadena[7]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<p><?php echo $enunciat8; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg8" value="0" <?php if ($cadena[8]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg8" value="1" <?php if ($cadena[8]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg8" value="2" <?php if ($cadena[8]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg8" value="3" <?php if ($cadena[8]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg8" value="4" <?php if ($cadena[8]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg8" value="5" <?php if ($cadena[8]=='5') {echo ('checked');} ?>>
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
											<p><?php echo $enunciat9; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg9" value="0" <?php if ($cadena[9]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg9" value="1" <?php if ($cadena[9]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg9" value="2" <?php if ($cadena[9]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg9" value="3" <?php if ($cadena[9]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg9" value="4" <?php if ($cadena[9]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg9" value="5" <?php if ($cadena[9]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat10; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg10" value="0" <?php if ($cadena[10]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg10" value="1" <?php if ($cadena[10]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg10" value="2" <?php if ($cadena[10]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg10" value="3" <?php if ($cadena[10]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg10" value="4" <?php if ($cadena[10]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg10" value="5" <?php if ($cadena[10]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat11; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg11" value="0" <?php if ($cadena[11]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg11" value="1" <?php if ($cadena[11]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg11" value="2" <?php if ($cadena[11]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg11" value="3" <?php if ($cadena[11]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg11" value="4" <?php if ($cadena[11]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg11" value="5" <?php if ($cadena[11]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                    <tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat12; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg12" value="0" <?php if ($cadena[12]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg12" value="1" <?php if ($cadena[12]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg12" value="2" <?php if ($cadena[12]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg12" value="3" <?php if ($cadena[12]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg12" value="4" <?php if ($cadena[12]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg12" value="5" <?php if ($cadena[12]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat13; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg13" value="0" <?php if ($cadena[13]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg13" value="1" <?php if ($cadena[13]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg13" value="2" <?php if ($cadena[13]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg13" value="3" <?php if ($cadena[13]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg13" value="4" <?php if ($cadena[13]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg13" value="5" <?php if ($cadena[13]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat14; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg14" value="0" <?php if ($cadena[14]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg14" value="1" <?php if ($cadena[14]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg14" value="2" <?php if ($cadena[14]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg14" value="3" <?php if ($cadena[14]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg14" value="4" <?php if ($cadena[14]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg14" value="5" <?php if ($cadena[14]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat15; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg15" value="0" <?php if ($cadena[15]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg15" value="1" <?php if ($cadena[15]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg15" value="2" <?php if ($cadena[15]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg15" value="3" <?php if ($cadena[15]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg15" value="4" <?php if ($cadena[15]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg15" value="5" <?php if ($cadena[15]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat16; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg16" value="0" <?php if ($cadena[16]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg16" value="1" <?php if ($cadena[16]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg16" value="2" <?php if ($cadena[16]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg16" value="3" <?php if ($cadena[16]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg16" value="4" <?php if ($cadena[16]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg16" value="5" <?php if ($cadena[16]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<p><?php echo $enunciat17; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg17" value="0" <?php if ($cadena[17]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg17" value="1" <?php if ($cadena[17]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg17" value="2" <?php if ($cadena[17]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg17" value="3" <?php if ($cadena[17]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg17" value="4" <?php if ($cadena[17]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg17" value="5" <?php if ($cadena[17]=='5') {echo ('checked');} ?>>
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
											<p><?php echo $enunciat18; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg18" value="0" <?php if ($cadena[18]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg18" value="1" <?php if ($cadena[18]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg18" value="2" <?php if ($cadena[18]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg18" value="3" <?php if ($cadena[18]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg18" value="4" <?php if ($cadena[18]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg18" value="5" <?php if ($cadena[18]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat19; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg19" value="0" <?php if ($cadena[19]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg19" value="1" <?php if ($cadena[19]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg19" value="2" <?php if ($cadena[19]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg19" value="3" <?php if ($cadena[19]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg19" value="4" <?php if ($cadena[19]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg19" value="5" <?php if ($cadena[19]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat20; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg20" value="0" <?php if ($cadena[20]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg20" value="1" <?php if ($cadena[20]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg20" value="2" <?php if ($cadena[20]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg20" value="3" <?php if ($cadena[20]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg20" value="4" <?php if ($cadena[20]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg20" value="5" <?php if ($cadena[20]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                    <tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat21; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg21" value="0" <?php if ($cadena[21]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg21" value="1" <?php if ($cadena[21]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg21" value="2" <?php if ($cadena[21]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg21" value="3" <?php if ($cadena[21]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg21" value="4" <?php if ($cadena[21]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg21" value="5" <?php if ($cadena[21]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat22; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg22" value="0" <?php if ($cadena[22]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg22" value="1" <?php if ($cadena[22]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg22" value="2" <?php if ($cadena[22]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg22" value="3" <?php if ($cadena[22]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg22" value="4" <?php if ($cadena[22]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg22" value="5" <?php if ($cadena[22]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                     <tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat23; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg23" value="0" <?php if ($cadena[23]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg23" value="1" <?php if ($cadena[23]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg23" value="2" <?php if ($cadena[23]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg23" value="3" <?php if ($cadena[23]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg23" value="4" <?php if ($cadena[23]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg23" value="5" <?php if ($cadena[23]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat24; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg24" value="0" <?php if ($cadena[24]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg24" value="1" <?php if ($cadena[24]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg24" value="2" <?php if ($cadena[24]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg24" value="3" <?php if ($cadena[24]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg24" value="4" <?php if ($cadena[24]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg24" value="5" <?php if ($cadena[24]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                    <tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat25; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg25" value="0" <?php if ($cadena[25]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg25" value="1" <?php if ($cadena[25]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg25" value="2" <?php if ($cadena[25]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg25" value="3" <?php if ($cadena[25]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg25" value="4" <?php if ($cadena[25]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg25" value="5" <?php if ($cadena[25]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                    <tr>
										<td colspan="2">
											<p><?php echo $enunciat26; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg26" value="0" <?php if ($cadena[26]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg26" value="1" <?php if ($cadena[26]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg26" value="2" <?php if ($cadena[26]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg26" value="3" <?php if ($cadena[26]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg26" value="4" <?php if ($cadena[26]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg26" value="5" <?php if ($cadena[26]=='5') {echo ('checked');} ?>>
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
											<p><?php echo $enunciat27; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg27" value="0" <?php if ($cadena[27]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg27" value="1" <?php if ($cadena[27]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg27" value="2" <?php if ($cadena[27]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg27" value="3" <?php if ($cadena[27]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg27" value="4" <?php if ($cadena[27]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg27" value="5" <?php if ($cadena[27]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat28; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg28" value="0" <?php if ($cadena[28]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg28" value="1" <?php if ($cadena[28]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg28" value="2" <?php if ($cadena[28]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg28" value="3" <?php if ($cadena[28]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg28" value="4" <?php if ($cadena[28]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg28" value="5" <?php if ($cadena[28]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat29; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg29" value="0" <?php if ($cadena[29]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg29" value="1" <?php if ($cadena[29]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg29" value="2" <?php if ($cadena[29]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg29" value="3" <?php if ($cadena[29]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg29" value="4" <?php if ($cadena[29]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg29" value="5" <?php if ($cadena[29]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                    <tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat30; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg30" value="0" <?php if ($cadena[30]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg30" value="1" <?php if ($cadena[30]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg30" value="2" <?php if ($cadena[30]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg30" value="3" <?php if ($cadena[30]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg30" value="4" <?php if ($cadena[30]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg30" value="5" <?php if ($cadena[30]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                    <tr class="borde_inferior">
										<td colspan="2">
											<p><?php echo $enunciat31; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg31" value="0" <?php if ($cadena[31]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg31" value="1" <?php if ($cadena[31]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg31" value="2" <?php if ($cadena[31]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg31" value="3" <?php if ($cadena[31]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg31" value="4" <?php if ($cadena[31]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg31" value="5" <?php if ($cadena[31]=='5') {echo ('checked');} ?>>
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
											<p><?php echo $enunciat32; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg32" value="0" <?php if ($cadena[32]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg32" value="1" <?php if ($cadena[32]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg32" value="2" <?php if ($cadena[32]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg32" value="3" <?php if ($cadena[32]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg32" value="4" <?php if ($cadena[32]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg32" value="5" <?php if ($cadena[32]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<p><?php echo $enunciat33; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg33" value="0" <?php if ($cadena[33]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg33" value="1" <?php if ($cadena[33]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg33" value="2" <?php if ($cadena[33]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg33" value="3" <?php if ($cadena[33]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg33" value="4" <?php if ($cadena[33]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg33" value="5" <?php if ($cadena[33]=='5') {echo ('checked');} ?>>
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
											<p><?php echo $enunciat34; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg34" value="0" <?php if ($cadena[34]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg34" value="1" <?php if ($cadena[34]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg34" value="2" <?php if ($cadena[34]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg34" value="3" <?php if ($cadena[34]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg34" value="4" <?php if ($cadena[34]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg34" value="5" <?php if ($cadena[34]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<p><?php echo $enunciat35; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg35" value="0" <?php if ($cadena[35]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg35" value="1" <?php if ($cadena[35]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg35" value="2" <?php if ($cadena[35]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg35" value="3" <?php if ($cadena[35]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg35" value="4" <?php if ($cadena[35]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg35" value="5" <?php if ($cadena[35]=='5') {echo ('checked');} ?>>
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
											<p><?php echo $enunciat36; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg36" value="0" <?php if ($cadena[36]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg36" value="1" <?php if ($cadena[36]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg36" value="2" <?php if ($cadena[36]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg36" value="3" <?php if ($cadena[36]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg36" value="4" <?php if ($cadena[36]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg36" value="5" <?php if ($cadena[36]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<p><?php echo $enunciat37; ?></p>
										</td>
								<td align="center">
											<input type="radio" name="preg37" value="0" <?php if ($cadena[37]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg37" value="1" <?php if ($cadena[37]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg37" value="2" <?php if ($cadena[37]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg37" value="3" <?php if ($cadena[37]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg37" value="4" <?php if ($cadena[37]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg37" value="5" <?php if ($cadena[37]=='5') {echo ('checked');} ?>>
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
											<p><?php echo $enunciat38; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg38" value="0" <?php if ($cadena[38]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg38" value="1" <?php if ($cadena[38]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg38" value="2" <?php if ($cadena[38]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg38" value="3" <?php if ($cadena[38]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg38" value="4" <?php if ($cadena[38]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg38" value="5" <?php if ($cadena[38]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<p><?php echo $enunciat39; ?></p>
										</td>
								<td align="center">
											<input type="radio" name="preg39" value="0" <?php if ($cadena[39]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg39" value="1" <?php if ($cadena[39]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg39" value="2" <?php if ($cadena[39]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg39" value="3" <?php if ($cadena[39]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg39" value="4" <?php if ($cadena[39]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg39" value="5" <?php if ($cadena[39]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr>
										<td colspan="8" class="modul">

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
											ANÀLISI DE LES ACTIVITATS COMPLEMENTÀRIES <br />(DEBATS i RACÓ D’INTERCANVI)
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
											<p><?php echo $enunciat40; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg40" value="C" <?php if ($cadena[40]=='C') {echo ('checked');} ?>>
										</td>
													  <td align="center">
											<input type="radio" name="preg40" value="0" <?php if ($cadena[40]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg40" value="1" <?php if ($cadena[40]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg40" value="2" <?php if ($cadena[40]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg40" value="3" <?php if ($cadena[40]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg40" value="4" <?php if ($cadena[40]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg40" value="5" <?php if ($cadena[40]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                    <tr class="borde_inferior">
										<td>
											<p><?php echo $enunciat41; ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg41" value="C" <?php if ($cadena[41]=='C') {echo ('checked');} ?>>
										</td>
													  <td align="center">
											<input type="radio" name="preg41" value="0" <?php if ($cadena[41]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg41" value="1" <?php if ($cadena[41]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg41" value="2" <?php if ($cadena[41]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg41" value="3" <?php if ($cadena[41]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg41" value="4" <?php if ($cadena[41]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg41" value="5" <?php if ($cadena[41]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td>
											<p><?php echo $enunciat42 ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg42" value="C" <?php if ($cadena[42]=='C') {echo ('checked');} ?>>
										</td>
													  <td align="center">
											<input type="radio" name="preg42" value="0" <?php if ($cadena[42]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg42" value="1" <?php if ($cadena[42]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg42" value="2" <?php if ($cadena[42]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg42" value="3" <?php if ($cadena[42]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg42" value="4" <?php if ($cadena[42]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg42" value="5" <?php if ($cadena[42]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
                                    <tr class="borde_inferior">
										<td>
											<p><?php echo $enunciat43 ?></p>
										</td>
										<td align="center">
											<input type="radio" name="preg43" value="C" <?php if ($cadena[43]=='C') {echo ('checked');} ?>>
										</td>
													  <td align="center">
											<input type="radio" name="preg43" value="0" <?php if ($cadena[43]=='0') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg43" value="1" <?php if ($cadena[43]=='1') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg43" value="2" <?php if ($cadena[43]=='2') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg43" value="3" <?php if ($cadena[43]=='3') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg43" value="4" <?php if ($cadena[43]=='4') {echo ('checked');} ?>>
										</td>
										<td align="center">
											<input type="radio" name="preg43" value="5" <?php if ($cadena[43]=='5') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr class="borde_inferior">
										<td colspan="4">
											<p><?php echo $enunciat44; ?></p>
										</td>
										<td align="center">
											SÍ
										</td>
										<td align="center">
											<input type="radio" name="preg44" value="si" <?php if ($cadena[44]=='si') {echo ('checked');} ?>>
										</td>
										<td align="center">
											NO
										</td>
										<td align="center">
											<input type="radio" name="preg44" value="no" <?php if ($cadena[44]=='no') {echo ('checked');} ?>>
										</td>
									</tr>
									<tr>
										<td colspan="4">
											<p><?php echo $enunciat45; ?></p>
										</td>
										<td align="center">
											SÍ
										</td>
										<td align="center">
											<input type="radio" name="preg45" value="si" <?php if ($cadena[45]=='si') {echo ('checked');} ?>>
										</td>
										<td align="center">
											NO
										</td>
										<td align="center">
											<input type="radio" name="preg45" value="no" <?php if ($cadena[45]=='no') {echo ('checked');} ?>>
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
											ALTRES COMENTARIS DEL TUTOR/A DEL TUTOR/A
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
