<?php
   require('../../config.php');
   include ('../ConnexioWeb.php'); //BD cursos
   include ('../ConnexioMoodle.php'); //BD cursos
   include ('../ConnexioMoodleAntic.php'); //BD cursos
   include ('../ConnexioIntranet.php'); //BD cursos
   include ('../Text.php');
   include ('inc/missatgesError.php');

   $conWeb = new ConnexioWeb();
   $conWeb->connectarBD();

   if ( $stmt = $conWeb->prepare( "SELECT ADRECA, CP, POBLE, FIX, MBL, EMAIL
   FROM contacte WHERE ESTAT=1" ) ) {
      $stmt->execute();
      $stmt->store_result();
      if ( $stmt->num_rows() > 0 ) {
         $stmt->bind_result($adreca, $cp, $poblacio, $numFix, $numMbl, $email);
         $stmt->fetch();
      }
   }
   else {
      throw new Exception('', 11301);
   }

   $conWeb->desconectarBD();

?>

<?php
 /***************************************************************/

 // Aquí es canvia el codi del curs que s'ha de passar l'informe. També canviar el títol del document a "informe_model.php"

 $curs = $_GET['shortname']; // canviar també el número de radios si canviem les preguntes!!!!
 $course = substr( $curs, 4, -3);

// Aquí van les preguntes dels radiobuttons. Els números de la variable enunciat han de ser correlatius

// Valoració segons els continguts de cada mòdul:
include('inc/enunciats/e-'.mb_strtolower($course, 'UTF-8').'.php');
 /***************************************************************/
// preparem el correu
$to = "secretaria@prisma.cat";
$subject = "Informe: ".$curs;
$message = "
S'ha enviat l'informe del curs ".$curs;

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=UTF-8\r\n";
$headers .= "From: ".$_POST['nom_tutor']."<".$_POST['mail_tutor'].">\nReply-To: secretaria@prisma.cat";

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
	<head>
    	<title>Informe</title>
  		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
      <meta content="MSHTML 6.00.2900.2180" name=GENERATOR>
      <meta name="google" value="notranslate" />
      <link rel='stylesheet' href='https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css'>
      <link rel="stylesheet" href="css/estil_informes.css"/>
      <!-- jQuery JS-->
      <script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js'></script>
      <!-- Activitat JS -->
      <script language="Javascript" src="js/validar_informes_qualificador.js"></script>
	</head>
    <body>
        <div id="tot">
            <div id="cap">
            </div>
            <div id="cos">
            	<?php

				if (isloggedin())  // comprovem si està identificat en el campus
			   	{
			   	  include('./inc/comprovarUser.php');
			   	  if($user_course) {

					// comprovem si hi ha dades guardades
					$conexion = mysqli_connect("localhost","suport","1324GiRoNa","gestio");
					if (mysqli_connect_errno())
					{
						echo "No es pot connectar: " . mysqli_connect_error();
					}
					mysqli_set_charset($conexion, "utf8");

						$result_g = mysqli_query ($conexion, "SELECT codic, guardat, finalitzat FROM informe_tutor INNER JOIN cursos ON codic=id_Curs WHERE codic='".$curs."'");

						// existeix l'informe
						if (mysqli_num_rows($result_g)>0)
						{
							$row_g = mysqli_fetch_array($result_g);

							// preparem els valors a actualitzar
							$valors_update = "radios='";

	/**************************************************/
	// ACTUALITZAR AMB EL NÚMERO DE RADIOS
	/**************************************************/
							for ($i=1; $i<=26; $i++)
							{
								$valors_update .= "#".$_POST[preg.$i];
							}

							$valors_update .= "',";
							$valors_update .= "expectatives='".str_replace("'","\'",$_POST[expectatives])."',";
							$valors_update .= "mes_debat='".str_replace("'","\'",$_POST[mes_debat])."',";
							$valors_update .= "menys_debat='".str_replace("'","\'",$_POST[menys_debat])."',";
							$valors_update .= "observacions='".str_replace("'","\'",$_POST[observacions])."',";
							$valors_update .= "dubtes='".str_replace("'","\'",$_POST[dubtes])."',";
							$valors_update .= "incidencies='".str_replace("'","\'",$_POST[incidencies])."',";
							$valors_update .= "enquesta='".str_replace("'","\'",$_POST[enquesta])."',";
							$valors_update .= "valoracions='".str_replace("'","\'",$_POST[valoracions])."',";
							$valors_update .= "millores='".str_replace("'","\'",$_POST[millores])."',";
							$valors_update .= "comentaris='".str_replace("'","\'",$_POST[comentaris])."'";

							// l'informe ja ha estat enviat
							if ($row_g['finalitzat']!='')
							{
								$data = date("d.m.Y",strtotime($row_g['finalitzat']));

								echo "<p class=enviat>L'informe està enviat a data ".$data.". <br><br>Per a qualsevol modificació, consulta amb secretaria@prisma.cat. <br><br>Gràcies<br><br></p>";
							}
							else if ($_POST['botPress'] == 'Envia i acaba')
							{
								// finalitzem l'informe i guardem o actualitzem a la base de dades com a finalitzat
								$result_actualitzar = mysqli_query ($conexion, "UPDATE informe_tutor SET codic='".$curs."',finalitzat=CURRENT_DATE,".$valors_update." WHERE codic='".$curs."'");

								if (mysqli_affected_rows($conexion))
								{
									if (mail($to, $subject, $message, $headers))
									{
										print "<p class=enviat>L'informe ha estat enviat correctament.<br><br></p>";
									}
									else
									{
										print "<p class=enviat>Hi ha hagut algun problema. Prova-ho més tard o avisa a suport@prisma.cat.<br><br></p>";
									}
								}

								// insereix o actualitza els usuaris suspesos, en el cas que n'hi hagi (nsuspesos - 1)
								for ($i=1;$i<$_POST[nsuspesos];$i++)
								{
									$seguiment = str_replace("'","''",$_POST[seguiment."$i"]);

									if ($_POST[nou."$i"] == "si")
									{
										$result_inserir_suspesos = mysqli_query ($conexion, "INSERT INTO suspesos (codicurs,usuari,seguiment) VALUES ('".$curs."','".$_POST[usuari."$i"]."','".$seguiment."')");
									}
									else
									{
										$result_update_suspesos = mysqli_query($conexion, "UPDATE suspesos SET seguiment='".$seguiment."' WHERE codicurs='".$curs."' AND usuari='".$_POST[usuari."$i"]."'");
									}
								}

								// actualitzem la taula cursos amb la data d'enviament de l'informe

								$result_cursos = mysqli_query ($conexion, "UPDATE cursos SET data_informe=CURRENT_DATE WHERE id_Curs='".$curs."'");

							}
							else if ($_POST['botPress'] == 'Desa sense enviar')
							{
								$result_actualitzar = mysqli_query ($conexion, "UPDATE informe_tutor SET codic='".$curs."',guardat=CURRENT_DATE,".$valors_update." WHERE codic='".$curs."'");

								if ($result_actualitzar)
								{
									echo ("<p class=enviat>Les dades de l'informe s'han desat correctament.</p>");
								}
								// insereix o actualitza els usuaris suspesos, en el cas que n'hi hagi (nsuspesos - 1)
								for ($i=1;$i<$_POST[nsuspesos];$i++)
								{
									$seguiment = str_replace("'","''",$_POST[seguiment."$i"]);

									if ($_POST[nou."$i"] == "si")
									{
										$result_inserir_suspesos = mysqli_query ($conexion, "INSERT INTO suspesos (codicurs,usuari,seguiment) VALUES ('".$curs."','".$_POST[usuari."$i"]."','".$seguiment."')");
									}
									else
									{
										$result_update_suspesos = mysqli_query($conexion, "UPDATE suspesos SET seguiment='".$seguiment."' WHERE codicurs='".$curs."' AND usuari='".$_POST[usuari."$i"]."'");
									}
								}
							}
							else
							{
								// mostrem el que hi ha guardat	fins ara
								$conexion_m = mysqli_connect("localhost","muser","8rTd.rp4H-m2pKbYBF!p","dbmood");
								if (mysqli_connect_errno())
								{
									echo "No es pot connectar: " . mysqli_connect_error();
								}
								mysqli_set_charset($conexion_m, "utf8");

								$result_dades = mysqli_query ($conexion,"SELECT id_Curs, c.MES, `NOM CURS` AS ncurs, AULA, `DATA FIN` AS dataf, DNI_TUTOR, p.NOM AS tnom, p.COGNOMS AS tcogs, MAIL_PRISMA, m.nom as nmes, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits, SUM(CASE WHEN (`INSC CURS` = 'M') THEN 1 ELSE 0 END) deutors, SUM(CASE WHEN ((`CERTIFICAT` NOT LIKE '%no aprovat%' OR `CERTIFICAT` IS NULL) AND `INSC CURS`='1') THEN 1 ELSE 0 END) aprovats, SUM(CASE WHEN ((`CERTIFICAT` LIKE '%no aprovat%') AND `INSC CURS`='1') THEN 1 ELSE 0 END) pendents, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats FROM cursos AS c, personal AS p, inscripcions AS i, mesos AS m WHERE id_Curs='".$curs."' AND DNI_TUTOR=p.DNI AND c.ANY=i.ANY AND c.MES =i.MES AND c.CURS =i.CURS AND c.AULA=i.Grup AND m.num=i.MES");

								include 'inc/aprovats_pendents_informe.php';

								$result_rec = mysqli_query ($conexion,"SELECT * FROM informe_tutor AS i WHERE codic='".$curs."'");

								if(mysqli_num_rows($result_dades)>0)
								{
									$row = mysqli_fetch_array($result_dades);
									$row_m = mysqli_fetch_array($result_m);
									$row_r = mysqli_fetch_array($result_rec);

									$cadena=explode("#", $row_r['radios']);

							 ?>

									<form id="informe" name="informe" action="<?php echo $PHP_SELF ?>" method="post">

                                    <input type="hidden" name="mail_tutor" value="<?php echo $row['MAIL_PRISMA']; ?>">
                                    <input type="hidden" name="nom_tutor" value="<?php echo $row['tnom']." ".$row['tcogs']; ?>">

									<table border="0" align="center" width="900" vspace="0" cellpadding="0" cellspacing="0" class="taula">
										<?php
											include 'inc/dades_informe_nou.php';

											if ($row['dies_passats']>0) // si ja ha acabat el curs podem mostrar els suspesos
											{
                                              	include 'inc/dades_informe_suspesos_nou.php';
											}
										?>
										<tr>
											<td colspan="2">&nbsp;                            </td>
										</tr>
										<tr class="apartat">
											<td colspan="2">
												ANÀLISI DE LES EXPECTATIVES (fòrum PRESENTACIÓ I EXPECTATIVES i ESPAI DE COMIAT)                            </td>
										</tr>
										<tr>
											<td class="celda" colspan="2">
												<textarea name="expectatives" style="width:900px; height:50px;"><?php if ($row_r['expectatives']!='') {echo ($row_r['expectatives']);} ?></textarea>                          	</td>
										</tr>

									</table>
									<br>
									<table border="0" align="center" width="900" vspace="0" style="margin-top:0px; padding-bottom:5px;" cellpadding="0" cellspacing="0" class="celda">
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
										<tr>
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
										<tr>
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
										<tr>
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
										<tr>
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
										<tr>
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
                                        <tr>
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
										<tr>
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
												<textarea name="mes_debat" style="width:900px; height:50px;"><?php if ($row_r['mes_debat']!='') {echo ($row_r['mes_debat']);} ?></textarea>
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
												<textarea name="menys_debat" style="width:900px; height:50px;"><?php if ($row_r['menys_debat']!='') {echo ($row_r['menys_debat']);} ?></textarea>
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
												<p><?php echo $enunciat21; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg21" value="C" <?php if ($cadena[21]=='C') {echo ('checked');} ?>>
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
											<td>
												<p><?php echo $enunciat22; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg22" value="C" <?php if ($cadena[22]=='C') {echo ('checked');} ?>>
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
											<td>
												<p><?php echo $enunciat23; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg23" value="C" <?php if ($cadena[23]=='C') {echo ('checked');} ?>>
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
											<td>
												<p><?php echo $enunciat24; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg24" value="C" <?php if ($cadena[24]=='C') {echo ('checked');} ?>>
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
											<td colspan="4">
												<p><?php echo $enunciat25; ?></p>
											</td>
											<td align="center">
												SÍ
											</td>
											<td align="center">
												<input type="radio" name="preg25" value="si" <?php if ($cadena[25]=='si') {echo ('checked');} ?>>
											</td>
											<td align="center">
												NO
											</td>
											<td align="center">
												<input type="radio" name="preg25" value="no" <?php if ($cadena[25]=='no') {echo ('checked');} ?>>
											</td>
										</tr>
										<tr class="borde_inferior">
											<td colspan="4">
												<p><?php echo $enunciat26; ?></p>
											</td>
											<td align="center">
												SÍ
											</td>
											<td align="center">
												<input type="radio" name="preg26" value="si" <?php if ($cadena[26]=='si') {echo ('checked');} ?>>
											</td>
											<td align="center">
												NO
											</td>
											<td align="center">
												<input type="radio" name="preg26" value="no" <?php if ($cadena[26]=='no') {echo ('checked');} ?>>
											</td>
										</tr>
										<tr>
											<td colspan="8">&nbsp;
											</td>
										</tr>
										<tr>
											<td colspan="8" class="apartat2">
												<strong class="fort">Altres observacions</strong>
											</td>
										</tr>
										<tr>
											<td class="celda" colspan="8">
												<textarea name="observacions" style="width:900px; height:50px;"><?php if ($row_r['observacions']!='') {echo ($row_r['observacions']);} ?></textarea>
											</td>
										</tr>
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
												<textarea name="dubtes" style="width:900px; height:50px;"><?php if ($row_r['dubtes']!='') {echo ($row_r['dubtes']);} ?></textarea>
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
												<textarea name="incidencies" style="width:900px; height:50px;"><?php if ($row_r['incidencies']!='') {echo ($row_r['incidencies']);} ?></textarea>
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
												<textarea name="enquesta" style="width:900px; height:50px;"><?php if ($row_r['enquesta']!='') {echo ($row_r['enquesta']);} ?></textarea>
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
												<textarea name="valoracions" style="width:900px; height:50px;"><?php if ($row_r['valoracions']!='') {echo ($row_r['valoracions']);} ?></textarea>
											</td>
										</tr>
										<tr>
											<td colspan="8">&nbsp;
											</td>
										</tr>
										<tr class="apartat">
											<td colspan="8">
												PROPOSTA DE MILLORES DEL TUTOR/A
											</td>
										</tr>
										<tr>
											<td class="celda" colspan="8">
												<textarea name="millores" style="width:900px; height:50px;"><?php if ($row_r['millores']!='') {echo ($row_r['millores']);} ?></textarea>
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
												<textarea name="comentaris" style="width:900px; height:50px;"><?php if ($row_r['comentaris']!='') {echo ($row_r['comentaris']);} ?></textarea>
											</td>
										</tr>
									</table>

									<div style="width:100%; text-align:center; margin-top:5px">
									<?php if ($trobat==1) echo $frase; ?>
									<input type="hidden" name="botPress">
									<input type="button" name="desar" value="Desa sense enviar" onClick="comprovar(this.form,'Desa sense enviar')">&nbsp;<?php if ((($row['dies_passats']>1 && $totsaprovats==1) || ($row['dies_passats']>7 && $totsaprovats==0)) && ($trobat==0)) { ?><input type="button" name="enviar" value="Envia i acaba" onClick="comprovar(this.form,'Envia i acaba',<?php echo $nsuspesos; ?>)"><?php } else { ?><input type="button" name="enviar" value="Envia i acaba" disabled><?php } ?></div>
									</form>
							<?php
								mysqli_close($conexion_m);
								}
								else
								{
									echo "<p>No s'ha trobat cap curs amb aquest codi.</p>";
								}
							}
						}
						else // no guardat
						{
							// entra como nuevo
							$valors_ = "'";

	/**************************************************/
	// ACTUALITZAR AMB EL NÚMERO DE RADIOS
	/**************************************************/
							for ($i=1; $i<=26; $i++)
							{
								$valors_ .= "#".$_POST[preg.$i];
							}

							$valors_ .= "',";
							$valors_ .= "'".str_replace("'","\'",$_POST[expectatives])."',";
							$valors_ .= "'".str_replace("'","\'",$_POST[mes_debat])."',";
							$valors_ .= "'".str_replace("'","\'",$_POST[menys_debat])."',";
							$valors_ .= "'".str_replace("'","\'",$_POST[observacions])."',";
							$valors_ .= "'".str_replace("'","\'",$_POST[dubtes])."',";
							$valors_ .= "'".str_replace("'","\'",$_POST[incidencies])."',";
							$valors_ .= "'".str_replace("'","\'",$_POST[enquesta])."',";
							$valors_ .= "'".str_replace("'","\'",$_POST[valoracions])."',";
							$valors_ .= "'".str_replace("'","\'",$_POST[millores])."',";
							$valors_ .= "'".str_replace("'","\'",$_POST[comentaris])."'";

							// ho comença i ho acaba d'un sol cop
							if ($_POST['botPress'] == 'Envia i acaba')
							{
								$result_inserir = mysqli_query ($conexion, "INSERT INTO informe_tutor (codic,finalitzat,radios,expectatives,mes_debat,menys_debat,observacions,dubtes,incidencies,enquesta,valoracions,millores,comentaris) VALUES ('".$curs."',CURRENT_DATE,".$valors_.")");

								// insereix o actualitza els usuaris suspesos, en el cas que n'hi hagi
								for ($i=1;$i<$_POST[nsuspesos];$i++)
								{
									$seguiment = str_replace("'","''",$_POST[seguiment."$i"]);

									if ($_POST[nou."$i"] == "si")
									{
										$result_inserir_suspesos = mysqli_query ($conexion, "INSERT INTO suspesos (codicurs,usuari,seguiment) VALUES ('".$curs."','".$_POST[usuari."$i"]."','".$seguiment."')");
									}
									else
									{
										$result_update_suspesos = mysqli_query($conexion, "UPDATE suspesos SET seguiment='".$seguiment."' WHERE codicurs='".$curs."' AND usuari='".$_POST[usuari."$i"]."'");
									}
								}

								if (mysqli_affected_rows($conexion))
								{
									if (mail($to, $subject, $message, $headers))
									{
										print "<p class=enviat>L'informe ha estat enviat correctament.<br><br></p>";
									}
									else
									{
										print "<p class=enviat>Hi ha hagut algun problema. Prova-ho més tard o avisa a suport@prisma.cat.<br><br></p>";
									}
								}

								// actualitzem la taula cursos amb la data d'enviament de l'informe

								$result_cursos = mysqli_query ($conexion, "UPDATE cursos SET data_informe=CURRENT_DATE WHERE id_Curs='".$curs."'");

							}
							else if ($_POST['botPress'] == 'Desa sense enviar')
							{
								$result_inserir = mysqli_query ($conexion, "INSERT INTO informe_tutor (codic,guardat,radios,expectatives,mes_debat,menys_debat,observacions,dubtes,incidencies,enquesta,valoracions,millores,comentaris) VALUES ('".$curs."',CURRENT_DATE,".$valors_.")");
								// insereix o actualitza els usuaris suspesos, en el cas que n'hi hagi (nsuspesos - 1)
								for ($i=1;$i<$_POST[nsuspesos];$i++)
								{
									$seguiment = str_replace("'","''",$_POST[seguiment."$i"]);

									if ($_POST[nou."$i"] == "si")
									{
										$result_inserir_suspesos = mysqli_query ($conexion, "INSERT INTO suspesos (codicurs,usuari,seguiment) VALUES ('".$curs."','".$_POST[usuari."$i"]."','".$seguiment."')");

									}
									else
									{
										$result_update_suspesos = mysqli_query($conexion, "UPDATE suspesos SET seguiment='".$seguiment."' WHERE codicurs='".$curs."' AND usuari='".$_POST[usuari."$i"]."'");
									}
								}

								if ($result_inserir)
								{
									echo ("<p class=enviat>Les dades de l'informe s'han desat correctament.</p>");
								}
							}
							else // mostrem el formulari buit
							{
								$conexion_m = mysqli_connect("localhost","muser","8rTd.rp4H-m2pKbYBF!p","dbmood");
								if (mysqli_connect_errno())
								{
									echo "No es pot connectar: " . mysqli_connect_error();
								}
								mysqli_set_charset($conexion_m, "utf8");


								$result_dades = mysqli_query ($conexion,"SELECT id_Curs, c.MES, `NOM CURS` AS ncurs, AULA, `DATA FIN` AS dataf, DNI_TUTOR, p.NOM AS tnom, p.COGNOMS AS tcogs, MAIL_PRISMA, m.nom as nmes, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits, SUM(CASE WHEN (`INSC CURS` = 'M') THEN 1 ELSE 0 END) deutors, SUM(CASE WHEN ((`CERTIFICAT` NOT LIKE '%no aprovat%' OR `CERTIFICAT` IS NULL) AND `INSC CURS`='1') THEN 1 ELSE 0 END) aprovats, SUM(CASE WHEN ((`CERTIFICAT` LIKE '%no aprovat%') AND `INSC CURS`='1') THEN 1 ELSE 0 END) pendents, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats FROM cursos AS c, personal AS p, inscripcions AS i, mesos AS m WHERE id_Curs='".$curs."' AND DNI_TUTOR LIKE '".$USER->username."%' AND DNI_TUTOR=p.DNI AND c.ANY=i.ANY AND c.MES =i.MES AND c.CURS =i.CURS AND c.AULA=i.Grup AND m.num=i.MES");

								include 'inc/aprovats_pendents_informe.php';

								//$result_s = mysqli_query ($conexion_m,"SELECT  username, firstname, lastname FROM (SELECT c.id, s.userid, s.grade, username, firstname, lastname FROM mdl_user AS u, mdl_assign_grades AS s, mdl_course AS c, mdl_assign AS a WHERE (((s.userid) In (SELECT userid FROM mdl_role_assignments WHERE roleid = 5 and contextid in (SELECT id FROM mdl_context WHERE contextlevel = 50 and instanceid in (SELECT c.id FROM mdl_course AS c, mdl_assign AS a, mdl_assign_grades AS s WHERE shortname='".$curs."' AND c.id=a.course AND a.id=s.assignment)))) AND ((u.id) In (SELECT userid FROM mdl_role_assignments WHERE roleid = 5 and contextid in (SELECT id FROM mdl_context WHERE contextlevel = 50 and instanceid in (SELECT c.id FROM mdl_course AS c, mdl_assign AS a, mdl_assign_grades AS s WHERE shortname='".$curs."' AND c.id=a.course AND a.id=s.assignment))) And (u.id)=s.userid) AND ((c.shortname)='".$curs."') AND ((s.assignment)=a.id) AND ((a.course)=c.id))) AS consulta WHERE ((consulta.grade <> '-1') AND (consulta.grade <> '6'))");

								if(mysqli_num_rows($result_dades)>0)
								{
									$row = mysqli_fetch_array($result_dades);
									$row_m = mysqli_fetch_array($result_m);

							 ?>

									<form id="informe" name="informe" action="<?php echo $PHP_SELF ?>" method="post">

                                    <input type="hidden" name="mail_tutor" value="<?php echo $row['MAIL_PRISMA']; ?>">
                                    <input type="hidden" name="nom_tutor" value="<?php echo $row['tnom']." ".$row['tcogs']; ?>">

									<table border="0" align="center" width="900" vspace="0" cellpadding="0" cellspacing="0" class="taula">
										<?php

											include 'inc/dades_informe_nou.php';

											if ($row['dies_passats']>0) // si ja ha acabat el curs podem mostrar els suspesos
											{
                                              	include 'inc/dades_informe_suspesos_nou.php';
											}
										?>
										<tr>
											<td colspan="2">&nbsp;                            </td>
										</tr>
										<tr class="apartat">
											<td colspan="2">
												ANÀLISI DE LES EXPECTATIVES (fòrum PRESENTACIÓ I EXPECTATIVES i ESPAI DE COMIAT)                            </td>
										</tr>
										<tr>
											<td class="celda" colspan="2">
												<textarea name="expectatives" style="width:900px; height:50px;"></textarea>                          	</td>
										</tr>
									</table>
									<br>
									<table border="0" align="center" width="900" vspace="0" style="margin-top:0px; padding-bottom:5px;" cellpadding="0" cellspacing="0" class="celda">
										<tr class="apartat">
											<td colspan="8">
												GRAU D'ASSOLIMENT DELS OBJECTIUS DE CADA MÒDUL
											</td>
										</tr>
										<tr>
											<td class="celda" colspan="8">&nbsp;

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
												<input type="radio" name="preg1" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg1" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg1" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg1" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg1" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg1" value="5">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td colspan="2">
												<p><?php echo $enunciat2; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg2" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg2" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg2" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg2" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg2" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg2" value="5">
											</td>
										</tr>
										<tr>
											<td colspan="2">
												<p><?php echo $enunciat3; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg3" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg3" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg3" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg3" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg3" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg3" value="5">
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
												<p><?php echo $enunciat4; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg4" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg4" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg4" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg4" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg4" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg4" value="5">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td colspan="2">
												<p><?php echo $enunciat5; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg5" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg5" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg5" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg5" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg5" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg5" value="5">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td colspan="2">
												<p><?php echo $enunciat6; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg6" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg6" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg6" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg6" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg6" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg6" value="5">
											</td>
										</tr>
                                        <tr>
											<td colspan="2">
												<p><?php echo $enunciat7; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg7" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg7" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg7" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg7" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg7" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg7" value="5">
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
												<p><?php echo $enunciat8; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg8" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg8" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg8" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg8" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg8" value="4">

											</td>
											<td align="center">
												<input type="radio" name="preg8" value="5">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td colspan="2">
												<p><?php echo $enunciat9; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg9" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg9" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg9" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg9" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg9" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg9" value="5">
											</td>
										</tr>
										<tr>
											<td colspan="2">
												<p><?php echo $enunciat10; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg10" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg10" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg10" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg10" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg10" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg10" value="5">
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
												<p><?php echo $enunciat11; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg11" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg11" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg11" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg11" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg11" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg11" value="5">
											</td>
										</tr>
										<tr>
											<td colspan="2">
												<p><?php echo $enunciat12; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg12" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg12" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg12" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg12" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg12" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg12" value="5">
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
												<p><?php echo $enunciat13; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg13" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg13" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg13" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg13" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg13" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg13" value="5">
											</td>
										</tr>
										<tr>
											<td colspan="2">
												<p><?php echo $enunciat14; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg14" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg14" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg14" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg14" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg14" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg14" value="5">
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
												<p><?php echo $enunciat15; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg15" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg15" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg15" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg15" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg15" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg15" value="5">
											</td>
										</tr>
										<tr>
											<td colspan="2">
												<p><?php echo $enunciat16; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg16" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg16" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg16" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg16" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg16" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg16" value="5">
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
												<p><?php echo $enunciat17; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg17" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg17" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg17" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg17" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg17" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg17" value="5">
											</td>
										</tr>
										<tr>
											<td colspan="2">
												<p><?php echo $enunciat18; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg18" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg18" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg18" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg18" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg18" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg18" value="5">
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
												<p><?php echo $enunciat19; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg19" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg19" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg19" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg19" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg19" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg19" value="5">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td colspan="2">
												<p><?php echo $enunciat20; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg20" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg20" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg20" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg20" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg20" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg20" value="5">
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
												<textarea name="mes_debat" style="width:900px; height:50px;"></textarea>
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
												<textarea name="menys_debat" style="width:900px; height:50px;"></textarea>
											</td>
										</tr>
										<tr>
											<td colspan="8">&nbsp;
											</td>
										</tr>
										<tr class="apartat">
											<td>
												ANÀLISI DE LES ACTIVITATS COMPLEMENTÀRIES <BR />(DEBATS i RACÓ D’INTERCANVI)
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
												<p><?php echo $enunciat21; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg21" value="C">
											</td>
                                            <td align="center">
												<input type="radio" name="preg21" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg21" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg21" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg21" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg21" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg21" value="5">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td>
												<p><?php echo $enunciat22; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg22" value="C">
											</td>
                                            <td align="center">
												<input type="radio" name="preg22" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg22" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg22" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg22" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg22" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg22" value="5">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td>
												<p><?php echo $enunciat23; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg23" value="C">
											</td>
                                            <td align="center">
												<input type="radio" name="preg23" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg23" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg23" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg23" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg23" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg23" value="5">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td>
												<p><?php echo $enunciat24; ?></p>
											</td>
											<td align="center">
												<input type="radio" name="preg24" value="C">
											</td>
                                            <td align="center">
												<input type="radio" name="preg24" value="0">
											</td>
											<td align="center">
												<input type="radio" name="preg24" value="1">
											</td>
											<td align="center">
												<input type="radio" name="preg24" value="2">
											</td>
											<td align="center">
												<input type="radio" name="preg24" value="3">
											</td>
											<td align="center">
												<input type="radio" name="preg24" value="4">
											</td>
											<td align="center">
												<input type="radio" name="preg24" value="5">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td colspan="4">
												<p><?php echo $enunciat25; ?></p>
											</td>
											<td align="center">
												SÍ
											</td>
											<td align="center">
												<input type="radio" name="preg25" value="si">
											</td>
											<td align="center">
												NO
											</td>
											<td align="center">
												<input type="radio" name="preg25" value="no">
											</td>
										</tr>
										<tr class="borde_inferior">
											<td colspan="4">
												<p><?php echo $enunciat26; ?></p>
											</td>
											<td align="center">
												SÍ
											</td>
											<td align="center">
												<input type="radio" name="preg26" value="si">
											</td>
											<td align="center">
												NO
											</td>
											<td align="center">
												<input type="radio" name="preg26" value="no">
											</td>
										</tr>
										<tr>
											<td colspan="8">&nbsp;
											</td>
										</tr>
										<tr>
											<td colspan="8" class="apartat2">
												<strong class="fort">Altres observacions</strong>
											</td>
										</tr>
										<tr>
											<td class="celda" colspan="8">
												<textarea name="observacions" style="width:900px; height:50px;"></textarea>
											</td>
										</tr>
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
												<textarea name="dubtes" style="width:900px; height:50px;"></textarea>
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
												<textarea name="incidencies" style="width:900px; height:50px;"></textarea>
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
												<textarea name="enquesta" style="width:900px; height:50px;"></textarea>
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
												<textarea name="valoracions" style="width:900px; height:50px;"></textarea>
											</td>
										</tr>
										<tr>
											<td colspan="8">&nbsp;
											</td>
										</tr>
										<tr class="apartat">
											<td colspan="8">
												PROPOSTA DE MILLORES DEL TUTOR/A
											</td>
										</tr>
										<tr>
											<td class="celda" colspan="8">
												<textarea name="millores" style="width:900px; height:50px;"></textarea>
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
												<textarea name="comentaris" style="width:900px; height:50px;"></textarea>
											</td>
										</tr>
									</table>

									<div style="width:100%; text-align:center; margin-top:5px">
									<?php if ($trobat==1) echo $frase; ?>
									<input type="hidden" name="botPress">
									<input type="button" name="desar" value="Desa sense enviar" onClick="comprovar(this.form,'Desa sense enviar')">&nbsp;<?php if ((($row['dies_passats']>1 && $totsaprovats==1) || ($row['dies_passats']>7 && $totsaprovats==0)) && ($trobat==0)) { ?><input type="button" name="enviar" value="Envia i acaba" onClick="comprovar(this.form,'Envia i acaba',<?php echo $nsuspesos; ?>)"><?php } else { ?><input type="button" name="enviar" value="Envia i acaba" disabled><?php } ?></div>
									</form>
							<?php
								mysqli_close($conexion_m);

								}
								else
								{
									echo "<p>No s'ha trobat cap curs amb aquest codi.</p>";
								}
							}
						}
					}
          else
              include('./inc/errorNoInscritCurs.php');
				}
				else
					include('./inc/errorNoIniciatSessio.php');
			?>
			</div>
<?php include('./inc/peuPagina.php') ?>
       	</div>
	<script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>
