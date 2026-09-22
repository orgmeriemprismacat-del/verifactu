<?php
session_name("sessio_facturacio");
session_start();

if(isset($_SESSION['usuari']) && isset($_SESSION['contrasenya_encriptada']) && ($_SESSION['rol']=="facturacio") || ($_SESSION['rol']=="admin"))
{
	$pagina = "pagaments_pendents";

	include('../inc/funcions_servidor.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<!-- Required meta tags -->
    <meta charset="utf-8">
	<!-- Responsive meta tag -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<title>Intranet | Pagaments pendents</title>

	<!-- CSS Menu-->
	<link rel="stylesheet" href="../css/estilo_back.css"/>
	<!-- CSS General Intranet-->
	<link rel="stylesheet" href="../css/estil_general.css"/>
	<!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

	<!-- jQuery-->
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
	<!-- Bootstrap JS -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
	<!-- AJAX-->
	<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>

	<script language="javascript">
		function marcar_pagat(n)
		{
			document.gestions.gestiook.value = 'S';
			document.gestions.num.value = n;
		}
	</script>

	<style>
		.table>tbody>tr>td, .table>tbody>tr>th, .table>tfoot>tr>td, .table>tfoot>tr>th, .table>thead>tr>td, .table>thead>tr>th {
			padding: 4px !important;
			vertical-align: middle !important;
		}

		body {
			font-size: 13px;
			margin-bottom: 50px;
		}

		.prioritat_urgent {
			color: #FF0000;
		}

		.prioritat_alta {
			color: #077F07;
		}

		hr {
			margin-top: 0px !important;
			margin-bottom: 10px !important;
			border-top: 2px solid #ddd !important;
		}
	</style>
</head>

<body topmargin="0">
	<table style="min-width: 1100px;" align="center" id="main">
        <tr>
            <?php include('../inc/menu_intranet_facturacio.php'); ?>
        </tr>
        <tr>
            <td align="center">
                <div id="login">
                    <div id="llegenda_curs" class="ge">
                    	<form name="gestions" method="post" action="<?php echo $PHP_SELF ?>">
                        	<div style="text-align:left; border-top:solid 1px #CCCCCC"></div><br /><br />

                            <?php
							  	include('../inc/dades.php');

							  	$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);

								mysqli_set_charset ($connexio, "utf8");

								if (mysqli_connect_errno())
								{
									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
								}
								else if ($_POST['gestiook']=="S") // hem apretat el botó PAGAT
								{
									if ($_POST[pagat."$_POST[num]"]=="PAGAT")
									{
										$n=$_POST[num];
										$o="";
										$observ="";

										if ($_POST[obs."$n"] !="")
										{
											$o=", OBSERVACIONS='".$_POST[obs."$n"]."'";
											$observ = str_replace("\'","'",$_POST[obs."$n"]);
											$observ ="OBSERVACIONS: ".str_replace("\''","''",$_POST[obs."$n"]);
										}

										$result_up = mysqli_query($connexio, "UPDATE cobraments SET PAGAT=CURRENT_DATE ".$o." WHERE ID=".$_POST[ident."$n"]."") or die(mysqli_error());

										echo("<br />El pagament s'ha registrat correctament.<br /><br />");

										$to = $_POST[mailt."$n"];
										//$to = "suport.informatic@prisma.cat";
										echo $to;
										$headers  = "MIME-Version: 1.0\r\n";
										$headers .= "Content-type: text/html; charset=UTF-8\r\n";
										$headers .= "From: PrisMa Facturació <facturacio@prisma.cat>\nReply-To: facturacio@prisma.cat\nBcc: inscripcions@prisma.cat";

										$subject = "Confirmació de pagament curs ".$_POST[curs."$n"]." del ".$_POST[mes."$n"]."/".$_POST[any."$n"]."";

										if ($_POST[rol."$n"]=='Tutoria') {
											$rol_long="de la tutoria";
											$edicio = "de l'edici&oacute; del ".$_POST[mes."$n"]."/".$_POST[any."$n"]."";
										}
										else if ($_POST[rol."$n"]=='Autoria' || $_POST[rol."$n"]=='Coordinacio' || $_POST[rol."$n"]=='Autoria_Coordinacio') {
											if ($_POST[rol."$n"]=='Autoria')
												$rol_long="de l'autoria";
											else if ($_POST[rol."$n"]=='Coordinacio')
												$rol_long="de la coordinació";
											else if ($_POST[rol."$n"]=='Autoria_Coordinacio')
												$rol_long="de l'autoria i la coordinació";

											if ($_POST[mes."$n"]=='1T')
												$numero_tri="1er";
											else if ($_POST[mes."$n"]=='2T')
												$numero_tri="2nd";
											else if ($_POST[mes."$n"]=='3T')
												$numero_tri="3er";
											else if ($_POST[mes."$n"]=='4T')
												$numero_tri="4t";

											$edicio = "del ".$numero_tri." trimestre de l'any ".$_POST[any."$n"]."";
										}

										$message = "<p>Benvolgut/da ".$_POST[ntutor."$n"].",</p>
										<p align=justify>Et confirmem que hem fet efectiu el pagament de ".$_POST[importnet."$n"]." euros corresponent a la factura/rebut ".$rol_long." del curs ".$_POST[curs."$n"]." ".$edicio.".</p>

										".$observ."

										<p>Salutacions ben cordials,</p>

										<p>Adam Carmona<br>
										Departament de Facturaci&oacute;<br>
										Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa<br>
										972 21 75 65 - 678 12 36 87 - www.prisma.cat</p>
										<div style='padding-top: 6px; border-bottom: 1px solid #7a7a7b; padding-bottom: 6px; max-width: 352px'>
		<a title='Instagram' name='Instagram' href='https://www.instagram.com/prisma.educacio/' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/instagram.png'>
	  </a><a title='Twitter' name='Twitter' href='https://twitter.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/twitter.png'>
	  </a><a title='YouTube' name='YouTube' href='https://www.youtube.com/channel/UCy5M8DYXgHm5IjI4MIUCXqg' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/youtube.png'>
	  </a><a title='Facebook' name='Facebook' href='https://www.facebook.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/facebook.png'>
		 </a>
		 <a title='TikTok' name='TikTok' href='https://www.tiktok.com/@prisma.educacio' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/tiktok.png'>
		 </a>
	</div>
										<p style='font-size:10px' align=justify><br />Aquest missatge es dirigeix exclusivament al seu destinatari; si no &eacute;s aix&iacute;, et preguem que ens ho comuniquis i l’esborris. La informaci&oacute; tractada pot ser confidencial i no est&agrave; permesa la seva comunicaci&oacute;, reproducci&oacute; o distribuci&oacute;. De conformitat amb el que disposa la normativa vigent en protecció de dades (<em>RGPD</em> i <em>LOPD</em>), les teves dades personals estan incorporades als nostres fitxers amb la finalitat de dur a terme correctament les gestions acad&eacute;miques i administratives i mantenir el contacte amb tu per via correu electr&ograve;nic. En qualsevol moment pots exercir els teus drets d'acc&eacute;s, rectificaci&oacute;, cancel·laci&oacute; i oposici&oacute; escrivint a l'Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa a atencio.usuari@prisma.cat. Consulta l’<a title=\"Avís legal\" name=\"Avís legal\" href=\"https://www.prisma.cat/avis-legal\" target=\"_blank\">Av&iacute;s legal</a> per a més informaci&oacute;.</p>";

										mail($to, $subject, $message, $headers);
									}
								}

								if (!(isset($_POST[ordre])))
									$or = "Cap";
								else if (!(isset($_POST[ordrepagat])))
									$or = $_POST[ordre];
								else
									$or = $_POST[ordrepagat];


								if (($or == "Cap") || ($or == "Data"))
								{
									$result = mysqli_query($connexio, "SELECT DISTINCT cobraments.ID, cobraments.ANY, cobraments.CURS, cobraments.MES, cobraments.ALUMNES, cobraments.IMPORT, cobraments.IRPF, cobraments.APAGAR, cobraments.GESTIONAT, cobraments.PAGAT, cobraments.DNI_TUTOR, cobraments.ROL, cobraments.BESTRETA, cobraments.APAGAR_REAL, personal.NOM, personal.COGNOMS, personal.IBAN, MAIL_PRISMA FROM personal INNER JOIN cobraments ON personal.DNI = cobraments.DNI_TUTOR WHERE (((cobraments.GESTIONAT) IS NOT NULL) AND ((cobraments.PAGAT) IS NULL)) ORDER BY cobraments.GESTIONAT, cobraments.ANY, cobraments.MES, cobraments.CURS");

									//$result = mysqli_query ($connexio, "SELECT DISTINCT ID, cb.ANY, cb.CURS, cb.MES, ALUMNES, IMPORT, IRPF, APAGAR, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats, NOM, COGNOMS, MAIL_PRISMA, IBAN FROM cobraments AS cb, cursos AS cr, personal AS p WHERE cb.DNI_TUTOR=DNI AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>19 AND GESTIONAT IS NOT NULL AND cb.PAGAT IS NULL AND cb.ANY=cr.ANY AND cb.MES=cr.MES AND cb.CURS=cr.CURS ORDER BY cb.ANY, cb.MES, cb.CURS");
								}
								else if ($or == "Tutors")
								{
									$result = mysqli_query($connexio, "SELECT  DISTINCT cobraments.ID, cobraments.ANY, cobraments.CURS, cobraments.MES, cobraments.ALUMNES, cobraments.IMPORT, cobraments.IRPF, cobraments.APAGAR, cobraments.GESTIONAT, cobraments.PAGAT, cobraments.DNI_TUTOR, cobraments.ROL, cobraments.BESTRETA, cobraments.APAGAR_REAL, personal.NOM, personal.COGNOMS, personal.IBAN, MAIL_PRISMA FROM personal INNER JOIN cobraments ON personal.DNI = cobraments.DNI_TUTOR WHERE (((cobraments.GESTIONAT) IS NOT NULL) AND ((cobraments.PAGAT) IS NULL)) ORDER BY personal.NOM, personal.COGNOMS");

									//$result = mysqli_query ($connexio, "SELECT DISTINCT ID, cb.ANY, cb.CURS, cb.MES, ALUMNES, IMPORT, IRPF, APAGAR, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats, NOM, COGNOMS, MAIL_PRISMA, IBAN FROM cobraments AS cb, cursos AS cr, personal AS p WHERE cb.DNI_TUTOR=DNI AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>19 AND GESTIONAT IS NOT NULL AND cb.PAGAT IS NULL AND cb.ANY=cr.ANY AND cb.MES=cr.MES AND cb.CURS=cr.CURS ORDER BY COGNOMS, NOM, cb.ANY, cb.MES, cb.CURS");
								}
								else if ($or == "Dies")
								{
									$result = mysqli_query($connexio, "SELECT DISTINCT cobraments.ID, c.ANY, c.CURS, c.MES, c.ALUMNES, c.IMPORT, c.IRPF, c.APAGAR, c.GESTIONAT, c.PAGAT, c.DNI_TUTOR, c.ROL, c.BESTRETA, c.APAGAR_REAL, p.NOM, p.COGNOMS, p.IBAN, MAIL_PRISMA, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats FROM personal AS p INNER JOIN cobraments AS c ON p.DNI = c.DNI_TUTOR INNER JOIN cursos AS cu ON cu.ANY = c.ANY AND cu.CURS = c.CURS  AND cu.MES = c.MES WHERE (((c.GESTIONAT) IS NOT NULL) AND ((c.PAGAT) IS NULL)) ORDER BY DATEDIFF(CURRENT_DATE,`DATA FIN`)");
								}

								// mirem si hi ha duplicats per error
								$result_repes = mysqli_query($connexio, "SELECT ID, COUNT(*) FROM cobraments GROUP BY DNI_TUTOR, ROL, ANY, CURS, MES, DATES, ALUMNES, IMPORT, IRPF, APAGAR HAVING COUNT(*)>1");


								if(mysqli_num_rows($result)>0)
								{
									$repetits = 0;
									$repe = "";

									if(mysqli_num_rows($result_repes)>0) {
										$repetits = mysqli_num_rows($result_repes);
										$rowrepes = mysqli_fetch_array($result_repes);
										$repe = $rowrepes['ID'];
									}

									?>
									<table class="table table-hover" id="taula_cursos" style="margin-bottom: 0px;">
									<thead class="thead-light">
										<tr>
										  <th scope="col">PDF</th>
										  <th scope="col">ANY</th>
										  <th scope="col">CURS</th>
										  <th scope="col">MES</th>
										  <th scope="col">DATA FI</th>
										  <th scope="col">ROL</th>
										  <th scope="col">TUTOR/A</th>
										  <th scope="col">ALUMNES</th>
										  <th scope="col">DATA GESTIÓ</th>
										  <th scope="col">I. BRUT</th>
										  <th scope="col">IRPF</th>
										  <th scope="col">A PAGAR</th>
										  <th scope="col">IBAN</th>
										  <th scope="col" style="max-width: 250px;">OBSERVACIONS PER AL TUTOR</th>
										  <th scope="col" colspan="2">DIES LÍMIT</th>
										  <!--<th scope="col"></th>-->
										</tr>
									</thead>
									<tbody>

									<input type="hidden" name="gestiook" value="N" />
									<input type="hidden" name="num" value="0" />

									<?php

									$i=0;
									$diners=0;

									while ($row = mysqli_fetch_array($result))
									{
										$originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
    									$modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';

										$data_mostrar=date("d-m-Y", strtotime($row['GESTIONAT']));

										$any_gestio_cobrament =date("Y", strtotime($row['GESTIONAT']));
										$mes_gestio_cobrament =date("m", strtotime($row['GESTIONAT']));
										if ($row['DNI_TUTOR']=='B87456992')
											$nom_tutor = "Zazil";
										else if ($row['DNI_TUTOR']=='B25750407')
											$nom_tutor = "Daniel_Gabarro";
										else if ($row['DNI_TUTOR']=='G17843830')
											$nom_tutor = "Ads_escola";
										else if ($row['DNI_TUTOR']=='G67253443')
											$nom_tutor = "AESH";
										else {
											$nom_tutor = utf8_decode(str_replace(' ','_',$row['NOM']." ".$row['COGNOMS']));
											$nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
											$nom_tutor = utf8_encode($nom_tutor);
										}

										if ($row['ROL']=='T') {
											$rol_llarg='Tutoria';

											$result_data_final = mysqli_query($connexio, "SELECT DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats, `DATA FIN` AS datafi FROM cursos WHERE (((ANY)=".$row['ANY'].") AND ((MES)='".$row['MES']."') AND ((CURS)='".$row['CURS']."') AND ((AULA)='A'))");

											$row_data = mysqli_fetch_array($result_data_final);

											//$dies_passats = $row_data['dies_passats'];
										
											$data_actual = date("Y-m-d");
											$dias = (strtotime($data_actual)-strtotime($row['GESTIONAT']))/86400;						
											$dias = floor($dias);
											$dies_passats = $dias;
											
											//echo 'dies passats:'.$dies_passats.'<br />';
										}
										else if ($row['ROL']=='A') {
											$rol_llarg='Autoria';

											$result_hores = mysqli_query($connexio, "SELECT HORES FROM cursos WHERE (((ANY)=".$row['ANY'].") AND ((CURS)='".$row['CURS']."') AND ((AULA)='A'))");
											$row_hores = mysqli_fetch_array($result_hores);

											//echo "HORES:".$row_hores['HORES'].' '.$row['MES'].'<br />';

											$any_actual = $row['ANY'];
											$any_anterior = $row['ANY']-1;
											$any_seguent = $row['ANY']+1;

											if ($row['MES']=="1T" or $row['MES']=="2T")
												$curs_escolar_actual = $any_anterior."/".$any_actual;
											else
												$curs_escolar_actual = $any_actual."/".$any_seguent;

											$result_dates = mysqli_query ($connexio, "SELECT * FROM dates_cobraments WHERE HORES=".$row_hores['HORES']." AND curs_escolar='".$curs_escolar_actual."'");
											$row_dates = mysqli_fetch_array($result_dates);

											if ($row['MES']=='1T') {
												$data_finalitzacio = $row_dates['1T'];
											}
											else if ($row['MES']=='2T') {
												$data_finalitzacio = $row_dates['2T'];
											}
											else if ($row['MES']=='3T') {
												$data_finalitzacio = $row_dates['3T'];
											}
											else if ($row['MES']=='4T') {
												$data_finalitzacio = $row_dates['4T'];
											}

											//$data_actual = date("d-m-Y");
											$data_actual = date("Y-m-d");
											//$dias	= (strtotime($data_actual)-strtotime($data_finalitzacio))/86400;
											$dias = (strtotime($data_actual)-strtotime($row['GESTIONAT']))/86400;						
											$dias = floor($dias);
											$dies_passats = $dias;
										}
										else if ($row['ROL']=='C') {
											$rol_llarg='Coordinacio';

											$result_hores = mysqli_query($connexio, "SELECT HORES FROM cursos WHERE (((ANY)=".$row['ANY'].") AND ((CURS)='".$row['CURS']."') AND ((AULA)='A'))");
											$row_hores = mysqli_fetch_array($result_hores);

											//echo "HORES:".$row_hores['HORES'].' '.$row['MES'].'<br />';
											$any_actual = $row['ANY'];
											$any_anterior = $row['ANY']-1;
											$any_seguent = $row['ANY']+1;

											if ($row['MES']=="1T" or $row['MES']=="2T")
												$curs_escolar_actual = $any_anterior."/".$any_actual;
											else
												$curs_escolar_actual = $any_actual."/".$any_seguent;

											$result_dates = mysqli_query ($connexio, "SELECT * FROM dates_cobraments WHERE HORES=".$row_hores['HORES']." AND curs_escolar='".$curs_escolar_actual."'");
											$row_dates = mysqli_fetch_array($result_dates);

											if ($row['MES']=='1T') {
												$data_finalitzacio = $row_dates['1T'];
											}
											else if ($row['MES']=='2T') {
												$data_finalitzacio = $row_dates['2T'];
											}
											else if ($row['MES']=='3T') {
												$data_finalitzacio = $row_dates['3T'];
											}
											else if ($row['MES']=='4T') {
												$data_finalitzacio = $row_dates['4T'];
											}

											//$data_actual = date("d-m-Y");
											$data_actual = date("Y-m-d");
											//$dias	= (strtotime($data_actual)-strtotime($data_finalitzacio))/86400;
											$dias = (strtotime($data_actual)-strtotime($row['GESTIONAT']))/86400;	
											$dias = floor($dias);
											$dies_passats = $dias;
										}
										else if ($row['ROL']=='D') {
											$rol_llarg='Autoria_Coordinacio';

											$result_hores = mysqli_query($connexio, "SELECT HORES FROM cursos WHERE (((ANY)=".$row['ANY'].") AND ((CURS)='".$row['CURS']."') AND ((AULA)='A')) GROUP BY HORES");
											$row_hores = mysqli_fetch_array($result_hores);

											//echo "HORES:".$row_hores['HORES'].' '.$row['MES'].'<br />';
											$any_actual = $row['ANY'];
											$any_anterior = $row['ANY']-1;
											$any_seguent = $row['ANY']+1;

											if ($row['MES']=="1T" or $row['MES']=="2T")
												$curs_escolar_actual = $any_anterior."/".$any_actual;
											else
												$curs_escolar_actual = $any_actual."/".$any_seguent;

											$result_dates = mysqli_query ($connexio, "SELECT * FROM dates_cobraments WHERE HORES=".$row_hores['HORES']." AND curs_escolar='".$curs_escolar_actual."'");
											$row_dates = mysqli_fetch_array($result_dates);

											if ($row['MES']=='1T') {
												$data_finalitzacio = $row_dates['1T'];
											}
											else if ($row['MES']=='2T') {
												$data_finalitzacio = $row_dates['2T'];
											}
											else if ($row['MES']=='3T') {
												$data_finalitzacio = $row_dates['3T'];
											}
											else if ($row['MES']=='4T') {
												$data_finalitzacio = $row_dates['4T'];
											}

											//$data_actual = date("d-m-Y");
											$data_actual = date("Y-m-d");
											//$dias	= (strtotime($data_actual)-strtotime($data_finalitzacio))/86400;
											$dias = (strtotime($data_actual)-strtotime($row['GESTIONAT']))/86400;	
											$dias = floor($dias);
											$dies_passats = $dias;
										}

										//formato fecha americana
									?>
										<input type="hidden" name="<?php echo "ident".$i ?>" value="<?php echo($row['ID']); ?>"  />
										<input type="hidden" name="<?php echo "ntutor".$i ?>" value="<?php echo($row['NOM']); ?>"  />
										<input type="hidden" name="<?php echo "mailt".$i ?>" value="<?php echo($row['MAIL_PRISMA']); ?>"  />
                                        <input type="hidden" name="<?php echo "importnet".$i ?>" value="<?php echo($row['APAGAR']); ?>"  />
                                        <input type="hidden" name="<?php echo "bestreta".$i ?>" value="<?php echo($row['BESTRETA']); ?>"  />
                                        <input type="hidden" name="<?php echo "apagarreal".$i ?>" value="<?php echo($row['APAGAR_REAL']); ?>"  />
                                        <input type="hidden" name="<?php echo "any".$i ?>" value="<?php echo($row['ANY']); ?>"  />
										<input type="hidden" name="<?php echo "curs".$i ?>" value="<?php echo($row['CURS']); ?>"  />
										<input type="hidden" name="<?php echo "mes".$i ?>" value="<?php echo($row['MES']); ?>"  />
                                        <input type="hidden" name="<?php echo "rol".$i ?>" value="<?php echo($rol_llarg); ?>"  />


                                        <?php

											$pagar = $row['APAGAR'];

											if ($row['BESTRETA']=="1") $pagar = $row['APAGAR_REAL'];

											// saber quan va acabar un curs
											if ($row['ROL']=='T'){
												$df=date_create($row_data['datafi']);
												$data_fi = date_format($df,"d-m-Y");
											}
											else {
												$df=date_create($data_finalitzacio);
												$data_fi = date_format($df,"d-m-Y");
											}

										?>


										<?php

											$trobat = false;

											// si hi ha registres repetits el pintem de color
											if ($repetits > 0) {
												if ($repe == $row['ID']) {
													echo "<tr style='background-color: #a40909; color: #fff;'>";
													$rowrepes = mysqli_fetch_array($result_repes);
													$repe = $rowrepes['ID'];
													$repetits--;
													$trobat = true;
												}
												else {
													/*if ($row['ROL']=='T' && ($dies_passats>=25) && ($dies_passats<30)) //color verd
														echo "<tr class=\"prioritat_alta\">";
													else if ( $row['ROL']=='T' && $dies_passats>=30) //color vermell
														echo "<tr class=\"prioritat_urgent\">";
													else if (($dies_passats>=7) && ($dies_passats<30) && ($row['ROL']=='A' or $row['ROL']=='C' or $row['ROL']=='D'))
														echo "<tr class=\"prioritat_alta\">";
													else if ($dies_passats>=30 && ($row['ROL']=='A' or $row['ROL']=='C' or $row['ROL']=='D'))
														echo "<tr class=\"prioritat_urgent\">";
													else
														echo "<tr>";*/
													if (($dies_passats>=25) && ($dies_passats<30)) //color verd
														echo "<tr class=\"prioritat_alta\">";
													else if ($dies_passats>=30) //color vermell
														echo "<tr class=\"prioritat_urgent\">";
													else
														echo "<tr>";
												}
											}

											if ($trobat == false) {
												/*if ($row['ROL']=='T' && ($dies_passats>=25) && ($dies_passats<30)) //color verd
													echo "<tr class=\"prioritat_alta\">";
												else if ( $row['ROL']=='T' && $dies_passats>=30) //color vermell
													echo "<tr class=\"prioritat_urgent\">";
												else if (($dies_passats>=7) && ($dies_passats<30) && ($row['ROL']=='A' or $row['ROL']=='C' or $row['ROL']=='D'))
													echo "<tr class=\"prioritat_alta\">";
												else if ($dies_passats>=30 && ($row['ROL']=='A' or $row['ROL']=='C' or $row['ROL']=='D'))
													echo "<tr class=\"prioritat_urgent\">";
												else
													echo "<tr>";
												*/
												if (($dies_passats>=25) && ($dies_passats<30)) //color verd
														echo "<tr class=\"prioritat_alta\">";
													else if ($dies_passats>=30) //color vermell
														echo "<tr class=\"prioritat_urgent\">";
													else
														echo "<tr>";
											}
										?>

											<td scope="col">
												<?php
												/*if (($any_gestio_cobrament > 2023 || ($any_gestio_cobrament == 2023 && ( $mes_gestio_cobrament == '09' || $mes_gestio_cobrament == '10' || $mes_gestio_cobrament == '11' || $mes_gestio_cobrament == '12' )))	&& $nom_tutor != "Zazil" && $nom_tutor != "Daniel_Gabarro" && $nom_tutor != "Ads_escola" && $nom_tutor != "AESH")
												echo "<a href=\"https://campus.prisma.cat/intranet-collaboradors/cobraments/factures/".$any_gestio_cobrament.$mes_gestio_cobrament."_Curs_".$row['ANY'].$row['CURS'].$row['MES']."_".$rol_llarg."_".$nom_tutor.".pdf\"
												target=\"_blank\"><img src=\"https://old.prisma.cat/intranet/img/pdf.png\" alt=\"Imatge pdf\" /></a></td>" ;
												else
												echo "<a href=\"https://www.prisma.cat/campus/intranet/tutors/factures/".$any_gestio_cobrament.$mes_gestio_cobrament."_Curs_".$row['ANY'].$row['CURS'].$row['MES']."_".$rol_llarg."_".$nom_tutor.".pdf\"
												target=\"_blank\"><img src=\"https://old.prisma.cat/intranet/img/pdf.png\" alt=\"Imatge pdf\" /></a></td>" ;*/

												$file = "https://campus.prisma.cat/intranet-collaboradors/cobraments/factures/".$any_gestio_cobrament.$mes_gestio_cobrament."_Curs_".$row['ANY'].$row['CURS'].$row['MES']."_".$rol_llarg."_".$nom_tutor.".pdf";
												if (url_exists("https://campus.prisma.cat/intranet-collaboradors/cobraments/factures/".$any_gestio_cobrament.$mes_gestio_cobrament."_Curs_".$row['ANY'].$row['CURS'].$row['MES']."_".$rol_llarg."_".$nom_tutor.".pdf"))
													echo "<a href=\"".$file."\" target=\"_blank\"><img src=\"https://old.prisma.cat/img/pdf.png\" alt=\"Imatge pdf\" /></a>";
												else
													echo "<img src=\"https://old.prisma.cat/img/creu.png\" alt=\"Imatge creu\" />";
				

												?>
											</td>
											<td scope="col"><?php echo($row['ANY']);  ?></td>
											<td scope="col"><?php echo($row['CURS']); ?></td>
											<td scope="col"><?php echo($row['MES']); ?></td>
											<td scope="col"><?php echo($data_fi)?></td>
											<td scope="col"><?php echo($rol_llarg); ?></td>
											<td scope="col" style="text-align: left; max-width: 165px;"><?php if ($row['DNI_TUTOR']=="B25750407" or $row['DNI_TUTOR']=="B87456992") {echo($row['COGNOMS']);} else { echo($row['NOM']." ".$row['COGNOMS']); } ?></td>
											<td scope="col"><?php echo($row['ALUMNES']); ?></td>
											<td scope="col"><?php echo($data_mostrar)?></td>
											<!--number_format($row['IMPORT'], 2, ',', '.');
											number_format($row['IRPF'], 2, ',', '.');
											number_format($pagar, 2, ',', '.');-->
											<td scope="col"><?php echo(number_format($row['IMPORT'], 2, ',', '.')); ?> €</td>
											<td scope="col"><?php echo(number_format($row['IRPF'], 2, ',', '.')); ?> €</td>
											<td scope="col"><strong><?php echo(number_format($pagar, 2, ',', '.')); ?> €</strong></td>

											<td scope="col"><?php echo($row['IBAN']); ?></td>
											<td scope="col" style="max-width: 250px;"><textarea name="<?php echo "obs".$i ?>" style="width:180px; height:30px"></textarea></td>

											<?php
												if ($row['ROL']=='T')
													$diesvermell = 30-$dies_passats;
												else if ($row['ROL']=='A' or $row['ROL']=='C' or $row['ROL']=='D')
													$diesvermell = 30-$dies_passats;

												/*if ($diesvermell <=0)
													$diesvermell = abs($diesvermell);*/
											?>


											<td scope="col"><?php echo($diesvermell); ?></td>

											<td scope="col"><input type="submit" style="padding: 3px !important;" name="<?php echo "pagat".$i ?>" value="PAGAT" onclick="marcar_pagat(<?php echo $i; ?>);" /></td>
									</tr>

									<?
										$diners=$diners+$row['APAGAR'];

										if (is_null($row['PAGAT']))
											$i++;
									}

									?>
									</tbody>
									</table>
									<hr />
									<table class="table" id="taula_cursos_total" style="margin-bottom: 0px;">
										<thead>
											<td scope="col" style="width: 20%;"></td>
											<td scope="col"><strong>TOTAL PENDENT DE PAGAR</strong></td>
											<td scope="col"><strong><?php echo(number_format($diners, 2, ',', '.')); ?> €</strong></td>
											<td scope="col" style="width: 25%;"></td>
										</thead>
									</table>

									<?php

									if ($i == 0)
										echo "<p style=text-align:left;clear:both;><br>No hi ha cap curs pendent de pagament.</p>";

									?>

									<div align="left" style="padding:20px 0px; clear:both">

									Prioritat de pagament (segons data de gestió de factura): <img src="../img/grey.png" border="0" style="vertical-align: middle; padding-left:10px;" /> <strong>normal</strong> (fa menys de 25 dies que han gestionat la factura) <img src="../img/green.png" border="0" style="vertical-align: middle; padding-left:20px;" /> <strong>alta</strong> (s'acosta data límit pagament factura - entre 25 i 29 dies)<img src="../img/red.png" border="0" style="vertical-align: middle; padding-left:20px;" /> <strong>urgent</strong> (han passat 30 dies o més)
									<br /><br />Ordenar per:
									&nbsp;

									<?php
									//if (!(isset($_POST[ordrepagat])))
									if ($_POST[ordrepagat])
									{
									?>
									<select name="ordre" id="ordre" size="1" onchange="this.form.submit()">
										<option value="Cap" <?php if ($_POST[ordrepagat] == "Cap") { echo "selected=selected"; } ?>>--Triar--</option>
										<option value="Tutors"<?php if ($_POST[ordrepagat] == "Tutors") { echo "selected=selected"; } ?>>Tutors</option>
										<option value="Data"<?php if ($_POST[ordrepagat] == "Data") { echo "selected=selected"; } ?>>Data</option>
                                        <option value="Dies"<?php if ($_POST[ordrepagat] == "Dies") { echo "selected=selected"; } ?>>Dies</option>
									</select>

                                    <input type="hidden" name="ordrepagat" value="<?php echo $_POST[ordrepagat]; ?>" />

                                    <?php
									}
									else
									{
									?>
                                    <select name="ordre" id="ordre" size="1" onchange="this.form.submit()">
										<option value="Cap" <?php if (($_POST[ordre] == "Cap") || !(isset($_POST[ordre]))) { echo "selected=selected"; } ?>>--Triar--</option>
										<option value="Tutors"<?php if ($_POST[ordre] == "Tutors") { echo "selected=selected"; } ?>>Tutors</option>
										<option value="Data"<?php if ($_POST[ordre] == "Data") { echo "selected=selected"; } ?>>Data</option>
                                        <option value="Dies"<?php if ($_POST[ordre] == "Dies") { echo "selected=selected"; } ?>>Dies</option>
									</select>

                                    <?php

									}
									?>
									</div>

									<?php
								}
								else
									echo "No s'ha trobat cap resultat.";

							?>
						</form>
                    </div>
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
	header("Location: ../acces2.php");
	exit;
}
?>
