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

 $curs = $_GET['shortname']; 
 $course = substr( $curs, 4, -3);

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
      <script language="Javascript" src="js/validar_informes_qualificador_suspesos.js"></script>
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

							// l'informe ja ha estat enviat
							if ($row_g['finalitzat']!='')
							{
								$data = date("d.m.Y",strtotime($row_g['finalitzat']));

								echo "<p class=enviat>L'informe està enviat a data ".$data.". <br><br>Per a qualsevol modificació, consulta amb secretaria@prisma.cat. <br><br>Gràcies<br><br></p>";
							}
							else if ($_POST['botPress'] == 'Envia i acaba')
							{
								// finalitzem l'informe i guardem o actualitzem a la base de dades com a finalitzat
								$result_actualitzar = mysqli_query ($conexion, "UPDATE informe_tutor SET codic='".$curs."',finalitzat=CURRENT_DATE WHERE codic='".$curs."'");

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
								$result_actualitzar = mysqli_query ($conexion, "UPDATE informe_tutor SET codic='".$curs."',guardat=CURRENT_DATE WHERE codic='".$curs."'");

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

								$result_dades = mysqli_query ($conexion,"SELECT id_Curs, c.MES, `NOM CURS` AS ncurs, AULA, `DATA FIN` AS dataf, DNI_TUTOR, p.NOM AS tnom, p.COGNOMS AS tcogs, MAIL_PRISMA, m.nom as nmes, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits, SUM(CASE WHEN ((`CERTIFICAT` NOT LIKE '%no aprovat%' OR `CERTIFICAT` IS NULL) AND `INSC CURS`='1') THEN 1 ELSE 0 END) aprovats, SUM(CASE WHEN ((`CERTIFICAT` LIKE '%no aprovat%') AND `INSC CURS`='1') THEN 1 ELSE 0 END) pendents, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats FROM cursos AS c, personal AS p, inscripcions AS i, mesos AS m WHERE id_Curs='".$curs."' AND DNI_TUTOR=p.DNI AND c.ANY=i.ANY AND c.MES =i.MES AND c.CURS =i.CURS AND c.AULA=i.Grup AND m.num=i.MES");

								include 'inc/aprovats_pendents_informe_50.php';

								$result_rec = mysqli_query ($conexion,"SELECT * FROM informe_tutor AS i WHERE codic='".$curs."'");

								if(mysqli_num_rows($result_dades)>0)
								{
									$row = mysqli_fetch_array($result_dades);
									$row_m = mysqli_fetch_array($result_m);
									$row_r = mysqli_fetch_array($result_rec);

							 ?>

									<form id="informe" name="informe" action="<?php echo $PHP_SELF ?>" method="post">

                                    <input type="hidden" name="mail_tutor" value="<?php echo $row['MAIL_PRISMA']; ?>">
                                    <input type="hidden" name="nom_tutor" value="<?php echo $row['tnom']." ".$row['tcogs']; ?>">

									<table border="0" align="center" width="900" vspace="0" cellpadding="0" cellspacing="0" class="taula">
										<?php

											include 'inc/dades_informe_nou.php';

											if ($row['dies_passats']>0) // si ja ha acabat el curs podem mostrar els suspesos
											{
                                              	include 'inc/dades_informe_suspesos_nou_subvencionats_50.php';
											}
										?>
									
									</table>

									<div style="width:100%; text-align:center; margin-top:5px">
									<?php if ($trobat==1) echo $frase; ?>
									<input type="hidden" name="botPress">
									<!--<input type="button" name="desar" value="Desa sense enviar" onClick="comprovar(this.form,'Desa sense enviar')">&nbsp;<?php if (($row['dies_passats']>1) && ($trobat==0)) { ?><input type="button" name="enviar" value="Envia i acaba" onClick="comprovar(this.form,'Envia i acaba',<?php echo $nsuspesos; ?>)"><?php } else { ?><input type="button" name="enviar" value="Envia i acaba" disabled><?php } ?></div>-->
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
								
							// ho comença i ho acaba d'un sol cop
							if ($_POST['botPress'] == 'Envia i acaba')
							{
								$result_inserir = mysqli_query ($conexion, "INSERT INTO informe_tutor (codic,finalitzat) VALUES ('".$curs."',CURRENT_DATE)");

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
								$result_inserir = mysqli_query ($conexion, "INSERT INTO informe_tutor (codic,guardat) VALUES ('".$curs."',CURRENT_DATE)");
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


								$result_dades = mysqli_query ($conexion,"SELECT id_Curs, c.MES, `NOM CURS` AS ncurs, AULA, `DATA FIN` AS dataf, p.NOM AS tnom, p.COGNOMS AS tcogs, MAIL_PRISMA, m.nom as nmes, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits, SUM(CASE WHEN ((`CERTIFICAT` NOT LIKE '%no aprovat%' OR `CERTIFICAT` IS NULL) AND `INSC CURS`=1) THEN 1 ELSE 0 END) aprovats, SUM(CASE WHEN ((`CERTIFICAT` LIKE '%no aprovat%') AND `INSC CURS`=1) THEN 1 ELSE 0 END) pendents, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats FROM cursos AS c, personal AS p, inscripcions AS i, mesos AS m WHERE id_Curs='".$curs."' AND DNI_TUTOR=p.DNI AND c.ANY=i.ANY AND c.MES =i.MES AND c.CURS =i.CURS AND c.AULA=i.Grup AND m.num=i.MES");

								//$result_dades = mysqli_query ($conexion,"SELECT id_Curs, c.MES, `NOM CURS` AS ncurs, AULA, `DATA FIN` AS dataf, DNI_TUTOR, p.NOM AS tnom, p.COGNOMS AS tcogs, MAIL_PRISMA, m.nom as nmes, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits, SUM(CASE WHEN ((`CERTIFICAT` NOT LIKE '%no aprovat%' OR `CERTIFICAT` IS NULL) AND `INSC CURS`='1') THEN 1 ELSE 0 END) aprovats, SUM(CASE WHEN ((`CERTIFICAT` LIKE '%no aprovat%') AND `INSC CURS`='1') THEN 1 ELSE 0 END) pendents, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats FROM cursos AS c, personal AS p, inscripcions AS i, mesos AS m WHERE id_Curs='".$curs."' AND DNI_TUTOR LIKE '".$USER->username."%' AND DNI_TUTOR=p.DNI AND c.ANY=i.ANY AND c.MES =i.MES AND c.CURS =i.CURS AND c.AULA=i.Grup AND m.num=i.MES");

								include 'inc/aprovats_pendents_informe_50.php';

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
                                              	include 'inc/dades_informe_suspesos_nou_subvencionats_50.php';
											}
										?>
										
									</table>

									<div style="width:100%; text-align:center; margin-top:5px">
									<?php if ($trobat==1) echo $frase; ?>
									<input type="hidden" name="botPress">
									<!--<input type="button" name="desar" value="Desa sense enviar" onClick="comprovar(this.form,'Desa sense enviar')">&nbsp;<?php if (($row['dies_passats']>1) && ($trobat==0)) { ?><input type="button" name="enviar" value="Envia i acaba" onClick="comprovar(this.form,'Envia i acaba',<?php echo $nsuspesos; ?>)"><?php } else { ?><input type="button" name="enviar" value="Envia i acaba" disabled><?php } ?></div>-->
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
