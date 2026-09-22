<?php
session_name("sessio_facturacio");
session_start();

$pagina = "pagaments_efectuats";
$visualitzacio = "tots";

include('../inc/funcions_servidor.php');

if(isset($_SESSION['usuari']) && isset($_SESSION['contrasenya_encriptada']) && ($_SESSION['rol']=="facturacio") || ($_SESSION['rol']=="admin"))
{
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<!-- Required meta tags -->
    <meta charset="utf-8">
	<!-- Responsive meta tag -->
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

	<title>Intranet | Pagaments efectuats</title>

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
		function buscar_anys(dni)
		{
			document.gestions.dni_seleccionat.value=dni;
			document.gestions.submit();
		}
	</script>
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
                    	<?php include('../inc/menu_intranet_facturacio_pagaments_efectuats.php'); ?>

                    	<form name="gestions" method="post" action="<?php echo $PHP_SELF ?>">

                            <?php

								$mes_actual = date(m);
								$any_actual = date(Y);
								$any_anterior = $any_actual-1;

								switch ($mes_actual) {
									case '01':
										$mes = "((MES='10' OR MES='11' OR MES='12' OR MES='4T') AND ANY=".$any_anterior.")";
										break;
									case '02':
										$mes = "((MES='11' OR MES='12' OR MES='4T') AND ANY=".$any_anterior.") OR (MES='01' AND ANY=".$any_actual.")";
										break;
									case '03':
										$mes = "((MES='12' OR MES='4T') AND ANY=".$any_anterior.") OR ((MES='01' OR MES='02') AND ANY=".$any_actual.")";
										break;
									case '04':
										$mes = "((MES='01' OR MES='02' OR MES='03' OR MES='1T') AND ANY=".$any_actual.")";
										break;
									case '05':
										$mes = "((MES='02' OR MES='03' OR MES='04' OR MES='1T') AND ANY=".$any_actual.")";
										break;
									case '06':
										$mes = "((MES='03' OR MES='04' OR MES='05' OR MES='1T') AND ANY=".$any_actual.")";
										break;
									case '07':
										$mes = "((MES='04' OR MES='05' OR MES='06' OR MES='2T') AND ANY=".$any_actual.")";
										break;
									case '08':
										$mes = "((MES='05' OR MES='06' OR MES='07' OR MES='2T') AND ANY=".$any_actual.")";
										break;
									case '09':
										$mes = "((MES='06' OR MES='07' OR MES='08' OR MES='2T') AND ANY=".$any_actual.")";
										break;
									case '10':
										$mes = "((MES='07' OR MES='08' OR MES='09' OR MES='3T') AND ANY=".$any_actual.")";
										break;
									case '11':
										$mes = "((MES='08' OR MES='09' OR MES='10' OR MES='3T') AND ANY=".$any_actual.")";
										break;
									case '12':
										$mes = "((MES='09' OR MES='10' OR MES='11' OR MES='3T') AND ANY=".$any_actual.")";
										break;
								}

								include('../inc/dades.php');

							  	$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);

								mysqli_set_charset ($connexio, "utf8");

								if (mysqli_connect_errno())
								{
									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
								}
								else if (($_POST[ordre] == "Cap") || !(isset($_POST[ordre])))
								{
									$result = mysqli_query ($connexio, "SELECT DISTINCT ANY, CURS, MES, ROL, IMPORT, NOM, COGNOMS, DNI_TUTOR, PAGAT, IRPF, APAGAR, GESTIONAT, cobraments.OBSERVACIONS FROM cobraments INNER JOIN personal AS p ON cobraments.DNI_TUTOR=p.DNI WHERE (((cobraments.PAGAT) Is Not Null) AND ((cobraments.GESTIONAT) Is Not Null) AND ALUMNES>0) AND ".$mes." ORDER BY cobraments.PAGAT DESC, cobraments.ANY DESC, cobraments.MES DESC, cobraments.CURS, p.NOM, p.COGNOMS");
								}
								else if ($_POST[ordre] == "Tots")
								{
									$result = mysqli_query ($connexio, "SELECT DISTINCT ANY, CURS, MES, ROL, IMPORT, NOM, COGNOMS, DNI_TUTOR, PAGAT, IRPF, APAGAR, GESTIONAT, cobraments.OBSERVACIONS FROM cobraments, personal AS p WHERE DNI_TUTOR=DNI AND GESTIONAT IS NOT NULL AND PAGAT IS NOT NULL ORDER BY ANY, MES, CURS");
								}
								else if ($_POST[ordre] == "Tutors")
								{
									$result = mysqli_query ($connexio, "SELECT DISTINCT ANY, CURS, MES, ROL, IMPORT, NOM, COGNOMS, DNI_TUTOR, PAGAT, IRPF, APAGAR, GESTIONAT, cobraments.OBSERVACIONS FROM cobraments INNER JOIN personal AS p ON cobraments.DNI_TUTOR=p.DNI WHERE (((cobraments.PAGAT) Is Not Null) AND ((cobraments.GESTIONAT) Is Not Null) AND ALUMNES>0) ORDER BY p.NOM, p.COGNOMS, cobraments.ANY, cobraments.MES, cobraments.CURS");
								}
								else if ($_POST[ordre] == "Cursos")
								{
									$result = mysqli_query ($connexio, "SELECT DISTINCT ANY, CURS, MES, ROL, IMPORT, NOM, COGNOMS, DNI_TUTOR, PAGAT, IRPF, APAGAR, GESTIONAT, cobraments.OBSERVACIONS FROM cobraments INNER JOIN personal AS p ON cobraments.DNI_TUTOR=p.DNI WHERE (((cobraments.PAGAT) Is Not Null) AND ((cobraments.GESTIONAT) Is Not Null) AND ALUMNES>0) ORDER BY cobraments.CURS, cobraments.ANY, cobraments.MES, p.NOM, p.COGNOMS");

								}
								else if ($_POST[ordre] == "Data de pagament")
								{
									$result = mysqli_query ($connexio, "SELECT DISTINCT ANY, CURS, MES, ROL, IMPORT, NOM, COGNOMS, DNI_TUTOR, PAGAT, IRPF, APAGAR, GESTIONAT, cobraments.OBSERVACIONS FROM cobraments INNER JOIN personal AS p ON cobraments.DNI_TUTOR=p.DNI WHERE (((cobraments.PAGAT) Is Not Null) AND ((cobraments.GESTIONAT) Is Not Null) AND ALUMNES>0) ORDER BY cobraments.PAGAT DESC, cobraments.ANY DESC, cobraments.MES DESC, cobraments.CURS, p.NOM, p.COGNOMS");
								}
								else if ($_POST[ordre] == "Any i mes")
								{
									$result = mysqli_query ($connexio, "SELECT DISTINCT ANY, CURS, MES, ROL, IMPORT, NOM, COGNOMS, DNI_TUTOR, PAGAT, IRPF, APAGAR, GESTIONAT, cobraments.OBSERVACIONS FROM cobraments INNER JOIN personal AS p ON cobraments.DNI_TUTOR=p.DNI WHERE (((cobraments.PAGAT) Is Not Null) AND ((cobraments.GESTIONAT) Is Not Null) AND ALUMNES>0) ORDER BY cobraments.ANY, cobraments.MES, cobraments.CURS, p.NOM, p.COGNOMS");
								}

								if(mysqli_num_rows($result)>0)
								{
									?>

									<table class="table table-hover" id="taula_cursos">
									<thead class="thead-light">
										<tr>
										  <th scope="col">PDF</th>
										  <th scope="col">ANY</th>
										  <th scope="col">CURS</th>
										  <th scope="col">MES</th>
										  <th scope="col" style="text-align: left">TUTOR</th>
										  <th scope="col">I. BRUT</th>
										  <th scope="col">IRPF</th>
										  <th scope="col">PAGAT</th>
										  <th scope="col">DATA PAGAMENT</th>
										  <th scope="col" style="max-width: 250px;">OBSERVACIONS PER AL TUTOR</th>
										</tr>
									</thead>
									<tbody>

									<?php

									$i=0;
									$diners=0;

									while ($row = mysqli_fetch_array($result))
									{
										$fecha_separada=explode("-",$row['PAGAT']);
										$data_pagat=$fecha_separada[2]."/".$fecha_separada[1]."/".$fecha_separada[0];

										$any_gestio_cobrament =date("Y", strtotime($row['GESTIONAT']));
										$mes_gestio_cobrament =date("m", strtotime($row['GESTIONAT']));

										$originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
    									$modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';

										if ($row['DNI_TUTOR']=='B87456992')
											$nom_tutor = "Zazil";
										else if ($row['DNI_TUTOR']=='B25750407')
											$nom_tutor = "Daniel_Gabarro";
										else if ($row['DNI_TUTOR']=='G17843830')
											$nom_tutor = "Ads_escola";
										else if ($row['DNI_TUTOR']=='77897279M')
											$nom_tutor = "Carmen_Boix_Casas";
										else {
											$nom_tutor = utf8_decode(str_replace(' ','_',$row['NOM']." ".$row['COGNOMS']));
											$nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
											$nom_tutor = utf8_encode($nom_tutor);
										}

										if ($row['ROL']=='T')
											$rol_llarg='Tutoria';
										else if ($row['ROL']=='A')
											$rol_llarg='Autoria';
										else if ($row['ROL']=='C')
											$rol_llarg='Coordinacio';
										else if ($row['ROL']=='D')
											$rol_llarg='Autoria_Coordinacio';

									?>
										<tr>
											<?php
											$file = "https://campus.prisma.cat/intranet-collaboradors/cobraments/factures/".$any_gestio_cobrament.$mes_gestio_cobrament."_Curs_".$row['ANY'].$row['CURS'].$row['MES']."_".$rol_llarg."_".$nom_tutor.".pdf";
											if (url_exists("https://campus.prisma.cat/intranet-collaboradors/cobraments/factures/".$any_gestio_cobrament.$mes_gestio_cobrament."_Curs_".$row['ANY'].$row['CURS'].$row['MES']."_".$rol_llarg."_".$nom_tutor.".pdf"))
												echo "<td scope=\"col\"><a href=\"".$file."\" target=\"_blank\"><img src=\"https://old.prisma.cat/img/pdf.png\" alt=\"Imatge pdf\" /></a></td>";
											else
												echo "<td scope=\"col\"><img src=\"https://old.prisma.cat/img/creu.png\" alt=\"Imatge creu\" /></td>";

											?>
											<td scope="col"><?php echo($row['ANY']);  ?></td>
											<td scope="col"><?php echo($row['CURS']); ?></td>
											<td scope="col"><?php echo($row['MES']); ?></td>
											<td scope="col" style="text-align: left"><?php if ($row['DNI_TUTOR']=="B25750407" or $row['DNI_TUTOR']=="B87456992") {echo($row['COGNOMS']);} else { echo($row['NOM']." ".$row['COGNOMS']); } ?></td>
                      <td scope="col"><?php echo(	number_format($row['IMPORT'], 2, ',', '.')); ?> €</td>
                      <td scope="col"><?php echo(number_format($row['IRPF'], 2, ',', '.')); ?> €</td>
                      <td scope="col"><strong><?php echo(	number_format($row['APAGAR'], 2, ',', '.')); ?> €</strong></td>
											<td scope="col"><?php echo($data_pagat);  ?></td>
											<td style="max-width: 250px;"><?php echo ($row['OBSERVACIONS']); ?></td>
										</tr>
									<?
										$diners=$diners+$row['IMPORT'];

										if (is_null($row['PAGAT']))
											$i++;
									}
									?>
									</tbody>
									</table>

									<table class="table" id="taula_cursos_total">
										<thead>
											<td scope="col"><strong>TOTAL PAGAT</strong></td>
											<td scope="col"><strong><?php echo(number_format($diners, 2, ',', '.')); ?> €</strong></td>
											<td scope="col"></td>
										</thead>
									</table>

									<div align="left" style="padding-top:20px; clear:both">

									<?php

									$result_tutors = mysqli_query ($connexio, "SELECT DNI, NOM, COGNOMS FROM personal, cobraments WHERE PAGAT IS NOT NULL AND DNI=DNI_TUTOR GROUP BY DNI ORDER BY COGNOMS, NOM");

								?>

									<input type="hidden" name="any_seleccionat" />

                            		Ordena per:
                                    <select name="ordre" id="ordre" size="1" onchange="this.form.submit()" style="margin-left: 5px">
										<option value="Cap" selected="selected">--Triar--</option>
										<option value="Tutors">Tutors</option>
										<option value="Cursos">Cursos</option>
                                        <option value="Data de pagament">Data de pagament</option>
                                        <option value="Any i mes">Any i mes</option>
									</select>

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
